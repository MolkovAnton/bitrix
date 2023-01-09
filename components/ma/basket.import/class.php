<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

/***
 * Параметры компонента
 * 'REDIRECT' - url страницы для перехода после завершения работы компонента
 * 'ELASTIC_LOG_INDEX' - индекс в elastic куда будут логироватся данные переданные для импорта в корзину
 */

use Bitrix\Main\Engine\Contract\Controllerable,
    MA\Classes\B2B\Basket,
    Bitrix\Main\Application,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\Config\Option,
    Bitrix\Sale,
    Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter\{HttpMethod};

class BasketImport extends \CBitrixComponent implements Controllerable {

    protected function init()
    {
        $requestOb = Application::getInstance()->getContext()->getRequest();
        if($requestOb->isPost())
        {
            $post = $requestOb->getPostList()->toArray();
            $action = $post['action'];
            $count = $post['count'];
            $request = $post['products'];
        }
        else
        {
            //Ожидаем GET запрос с параметрами action=basketImport&count={количество товаров}&{guid товара}={количество}
            $request = $requestOb->getQueryList()->toArray();
            $action = $request['action'];
            $count = $request['count'];
            unset($request['action']);
            unset($request['count']);
        }

        if (($action !== 'basketImport' && $action !== 'basketImportExcel') || empty($request)) {
            LocalRedirect($this->arParams['REDIRECT']);
        }
        
        $this->arResult = [
            'GUIDS' => $request,
            'COUNT' => $count,
            'REDIRECT' => $this->arParams['REDIRECT'],
            'CURRENT_BASKET' => $this->getCurrentBasket()->count() > 0 ? true : false,
            'ELASTIC_LOG_INDEX' => $this->arParams['ELASTIC_LOG_INDEX'],
            'SOURCE' => $action
        ];
    }

    public function executeComponent() 
    {
        try {
            $this->init();	
            $this->includeComponentTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
    }
    
    public function updateBasketAction($param) {
        $this->request = $param['GUIDS'];
        $prices = $this->getPrices();
        $products = $this->getPropducts($prices);//не распознаны
        $this->source = $param['SOURCE'];

        $oldBasket = $this->getCurrentBasket();
        $oldBasketItems = $oldBasket->getBasketItems();
        if (!($param['MERGE'])) {
            foreach ($oldBasketItems as $item) {
                $item->delete();
            }
            $oldBasket->save();
        } else {
            foreach ($oldBasketItems as $item) {
                $oldQuantity = $item->getField('QUANTITY');
                $id = $item->getField('PRODUCT_ID');
                if (isset($products[$id])) {
                    $products[$id]['DIFFERENCE'] = $products[$id]['QUANTITY'];
                    $products[$id]['QUANTITY'] += $oldQuantity;
                }
            }
        }

        $result = $this->updateBasket($products);
        
        try {
            $this->sendDataToElastic($param['ELASTIC_LOG_INDEX']);
        } catch (\Error $e) {

        }
        
        $result['UNADDED'] = $this->unaddedArticleList;
        $result['UNRECOGNIZED'] = $this->unrecognizedList;
        
        return $result;
    }
    
    private function getPrices() {
        $prices = [];
        global $PARTNER_REPRESENTATIVE; 
        if(!empty($GLOBALS['ELASTIC_DATA']))
        {
            $searchResult = $GLOBALS['ELASTIC_DATA'];
        }
        else
        {
            $searchResult = ElasticSearchInit()->getList([
                'filter' => [
                    'productId' => array_keys($this->request),
                    'agreementId' => $PARTNER_REPRESENTATIVE->partner['UF_AGREEMENT_GUID']
                ]
            ]);
        }
        foreach ($searchResult as $res) {
            $prices[$res['productId']] = $res['price'];
        }
        
        return $prices;
    }

    private function getPropducts($prices) {
        $products = [];
        $arFilter = ['XML_ID' => array_keys($this->request), 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y', 'CHECK_PERMISSIONS' => 'Y', 'MIN_PERMISSION' => 'R'];
        $arSelect = ['ID', 'XML_ID', 'PROPERTY_CML2_ARTICLE'];
        
        $softwareDirsIdList = Option::get('askaron.settings', 'UF_SOFTWARE_DIRS');
        $statusProjectValue = Option::get('askaron.settings', 'UF_STATUS');

        //фильтр по свойству "Статус" отбираем не "проектный товар"
        if(!empty($statusProjectValue)) {
            $arFilter['!PROPERTY_STATUS'] = $statusProjectValue;
        }

        //фильрт разделов с ПО
        if(!empty($softwareDirsIdList)) {
            $arFilter['!SECTION_ID'] = $softwareDirsIdList;
        }

        $this->unaddedList = $this->unaddedArticleList = [];
        $this->unrecognizedList = $this->request;

        $elements = \CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
        while($element = $elements->fetch()) {
            if(!empty($this->request[$element['XML_ID']]) && empty($prices[$element['XML_ID']])) {
                $this->unaddedList[$element['XML_ID']] = $this->request[$element['XML_ID']];
                $this->unaddedArticleList[$element['PROPERTY_CML2_ARTICLE_VALUE']] = $this->request[$element['XML_ID']];
            }
            if (!empty($this->request[$element['XML_ID']]) && !empty($prices[$element['XML_ID']])) {
                $products[$element['ID']] = [
                    'PRODUCT_ID' => $element['ID'],
                    'QUANTITY' => $this->request[$element['XML_ID']],
                    'PRICE' => $prices[$element['XML_ID']]
                ];
            }
            unset($this->unrecognizedList[$element['XML_ID']]);
        }

        return $products;
    }

    private function updateBasket($products) {
        if (empty($products)) {
            return [
                'RESULT' => 0
            ];
        }
        
        $basket = new Basket();
        $basket->updateList($products);
        $result = $basket->result;
        
        if ($result['save']) {
            return [
                'RESULT' => count($products)
            ];
        } else {
            return [
                'ERROR' => Loc::getMessage('MA_BASKET_IMPORT.ERROR')
            ];
        }
    }

    public function configureActions()
    {
        return [
            'updateBasket' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST])
                ],
                'postfilters' => []
            ]
        ];
    }
    
    private function getCurrentBasket() {
        $basket = Sale\Basket::loadItemsForFUser(
            Sale\Fuser::getId(),
            Context::getCurrent()->getSite()
        );

        return $basket;
    }
    
    private function sendDataToElastic($index) {
        $search = ElasticSearchInit($index, '');

        $fields = [
            'GUIDS' => $this->request,
            'UNADDED_GUIDS' => $this->unaddedList, //не добавленные
            'UNRECOGNIZED_GUIDS' => $this->unrecognizedList, //не распознанные
            'SOURCE' => $this->source,
            'DATE' => (new \Bitrix\Main\Type\DateTime())->toString(),
            'USER' => $GLOBALS['USER']->getId()
        ];
        $search->sendDataToElastic([$fields]);
    }
}