<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

/***
 * Параметры компонента
 * 'SEGMENTS_BLOCK' => [
        'CODE' => 'Segments',
        'PROPS' => [
            'NAME' => 'UF_SEGMENT_NAME',
            'DATE' => 'UF_SEGMENT_DATE_CREATE',
            'AUTHOR' => 'UF_SEGMENT_AUTHOR'
        ]
    ],
    'SEGMENTS_USERS_BLOCK' => [
        'CODE' => 'SegmentsUsers',
        'SEGMENT_FIELD_CODE' => 'UF_SEGMENT',
        'PROPS' => [
            'USER' => 'UF_SEGMENTS_USERS_USER',
            'DATE' => 'UF_SEGMENTS_USERS_DATE',
            'GUID' => 'UF_SEGMENTS_USERS_GUID'
        ]
    ],
    'PARTNERS_IBLOCK_CODE' => 'PARTNERS'
 */

use Bitrix\Main\Engine\Contract\Controllerable,
    Bitrix\Main\UserTable,
    Bitrix\Main\UserFieldLangTable,
    Bitrix\Main\UI\PageNavigation,
    Bitrix\Main\Grid\Options,
    Bitrix\Iblock\SectionTable,
    Bitrix\Highloadblock\HighloadBlockTable,
    Bitrix\Main\Application,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\Grid\Panel\Snippet,
    Bitrix\Main\Type\DateTime;
use Bitrix\Main\Engine\ActionFilter\{HttpMethod};

class Segments extends \CBitrixComponent implements Controllerable {

    protected function init()
    {
        $this->request = Application::getInstance()->getContext()->getRequest();
        $this->segment = $this->request->get('segment');
        if ($this->segment > 0) {
            $this->arResult = $this->getSegment();
        } else {
            $this->arResult = $this->getSegments();
        }
        $this->arResult['CUR_URL'] = $this->request->getrequestedPageDirectory().'/';
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
    
    private function getSegments() {
        $arResult = [
            'GRID_ID' => $this->arParams['SEGMENTS_BLOCK']['CODE'],
            'ROWS' => $this->getSegmentsRows($this->arParams['SEGMENTS_BLOCK']),
            'COLUMNS' => $this->getColumns($this->arParams['SEGMENTS_BLOCK'])
        ];
        $arResult['COLUMNS'][] = [
            'id' => 'TOTAL',
            'name' => Loc::getMessage('MA_SEGMENTS.TOTAL_COLUMN'),
            'default' => true,
            'sort' => 'TOTAL'
        ];
        return $arResult;
    }
    
    private function getSegment() {
        $snippets = new Snippet();
        $removeButton = $snippets->getRemoveButton();
        $arResult = [
            'GRID_ID' => $this->arParams['SEGMENTS_USERS_BLOCK']['CODE'],
            'ROWS' => $this->getSegmentRows($this->arParams['SEGMENTS_USERS_BLOCK']),
            'COLUMNS' => $this->getColumns($this->arParams['SEGMENTS_USERS_BLOCK']),
            'SEGMENT' => $this->getSegmentInfo(),
            'PANEL' => [
                'GROUPS' => [ 
                    'TYPE' => [ 
                        'ITEMS' => [
                            $removeButton
                        ], 
                    ] 
                ],
            ]
        ];
        return $arResult;
    }

    private function getSegmentsRows($param) {
        [$entityClass, $pageSize, $nav, $sort] = $this->prepareSelection($param['CODE']);
        
        $partnersEntityClass = $this->getEntityClass($this->arParams['SEGMENTS_USERS_BLOCK']['CODE']);
        $propName = toUpper($param['CODE']);
        $elements = [];
        $elementsRes = $entityClass::getList([
            'runtime' => [
                'PERSON' => [
                    'data_type' => UserTable::class,
                    'reference' => [
                        '=this.'.$param['PROPS']['AUTHOR'] => 'ref.ID',
                    ]
                ],
                'CNT' => [
                    'data_type' => $partnersEntityClass,
                    'reference' => [
                        '=this.ID' => 'ref.'.$this->arParams['SEGMENTS_USERS_BLOCK']['SEGMENT_FIELD_CODE'],
                    ]
                ]
            ],
            'select' => array_merge($param['PROPS'], ['ID', 'PERSON.NAME', 'PERSON.LAST_NAME', 'PERSON.EMAIL', new Bitrix\Main\ORM\Fields\ExpressionField('TOTAL', "COUNT(".$this->arParams['SEGMENTS_USERS_BLOCK']['SEGMENT_FIELD_CODE'].")")]),
            'limit' => $pageSize,
            'offset' => $nav->getOffset(),
            'order' => $sort,
            'group' => ['ID']
        ]);
        while ($element = $elementsRes->fetch()) {
            $id = $element['ID'];
            $link = $this->request->getRequestedPageDirectory()."/?segment=$id";
            $element['DATE'] = $element['DATE']->toString();
            $element['AUTHOR'] = $element[$propName.'_PERSON_NAME'].' '.$element[$propName.'_PERSON_LAST_NAME'].' ('.$element[$propName.'_PERSON_EMAIL'].')';
            $element['NAME'] = "<a href='$link'>".$element['NAME']."</a>";
            unset($element[$propName.'_USER_NAME']);
            unset($element[$propName.'_USER_LAST_NAME']);
            unset($element[$propName.'_USER_EMAIL']);
            $elements[] = [
                'data' => $element,
                'actions' => [
                    [
                        'text'    => Loc::getMessage('MA_SEGMENTS.OPEN'),
                        'onclick' => "document.location.href='$link'"
                    ],
                    [
                        'text'    => Loc::getMessage('MA_SEGMENTS.DELETE'),
                        'onclick' => "BX.segmentsController.deleteSegment($id)"
                    ]
                ]
            ];
        }
        return ['DATA' => $elements, 'NAVIGATE' => $nav];
    }
    
    private function getSegmentRows($param) {
        [$entityClass, $pageSize, $nav, $sort] = $this->prepareSelection($param['CODE'], [$param['SEGMENT_FIELD_CODE'] => $this->segment]);
        
        $propName = toUpper(Bitrix\Main\Entity\Base::camel2snake($param['CODE']));
        $elements = [];
        $elementsRes = $entityClass::getList([
            'runtime' => [
                'PARTNER' => [
                    'data_type' => SectionTable::class,
                    'reference' => [
                        '=this.'.$param['PROPS']['USER'] => 'ref.ID',
                    ]
                ]
            ],
            'select' => array_merge($param['PROPS'], ['PARTNER.NAME', 'ID']),
            'limit' => $pageSize,
            'offset' => $nav->getOffset(),
            'order' => $sort,
            'filter' => [$param['SEGMENT_FIELD_CODE'] => $this->segment]
        ]);
        while ($element = $elementsRes->fetch()) {
            $id = $element['ID'];
            $element['USER'] = $element[$propName.'_PARTNER_NAME'];
            unset($element[$propName.'_PARTNER_NAME']);
            $elements[] = [
                'data' => $element,
                'actions' => [
                    [
                        'text'    => Loc::getMessage('MA_SEGMENTS.DELETE'),
                        'onclick' => "BX.segmentsController.deleteSegment($id, 'deleteSegmentElement')"
                    ]
                ]
            ];
        }
        return ['DATA' => $elements, 'NAVIGATE' => $nav];
    }

    private function getSegmentInfo() {
        $entityClass = $this->getEntityClass($this->arParams['SEGMENTS_BLOCK']['CODE']);
        $segment = $entityClass::getList([
            'filter' => ['ID' => $this->segment]
        ])->fetch();
        return [
            'ID' => $segment['ID'],
            'NAME' => $segment[$this->arParams['SEGMENTS_BLOCK']['PROPS']['NAME']],
            'DATE' => $segment[$this->arParams['SEGMENTS_BLOCK']['PROPS']['DATE']]
        ];
    }

    private function prepareSelection($code, $filter = []) {
        $entityClass = $this->getEntityClass($code);
        $recordCount = $entityClass::getList(['filter'=>$filter])->getSelectedRowsCount();
        $gridOptions = new Options($code);
        $navParams = $gridOptions->GetNavParams();
        $sort = $gridOptions->getSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']])['sort'];
        $pageSize = $navParams['nPageSize'];
        $nav = new PageNavigation($code);
        $nav->allowAllRecords(false)
            ->setRecordCount($recordCount)
            ->setPageSize($pageSize)
            ->initFromUri();
        return [$entityClass, $pageSize, $nav, $sort];
    }
    
    private function getEntityClass($code) {
        $hlblock = HighloadBlockTable::getList(['filter' => ['NAME' => $code]])->fetch();
        $entityClass = HighloadBlockTable::compileEntity($hlblock)->getDataClass();
        return $entityClass;
    }

    private function getColumns($param)
    {
        $codes = array_flip($param['PROPS']);
        $columns = [];
        $fieldsRes = UserFieldLangTable::getList([
            'filter' => ['USER_FIELD.FIELD_NAME' => $param['PROPS'], 'LANGUAGE_ID' => 'ru'],
            'select' => ['NAME' => 'EDIT_FORM_LABEL', 'CODE' => 'USER_FIELD.FIELD_NAME']
        ]);
        while ($field = $fieldsRes->fetch()) {
            $columns[$codes[$field['CODE']]] = [
                'id' => $codes[$field['CODE']],
                'name' => $field['NAME'],
                'default' => true,
                'sort' => $field['CODE']
            ];
        }
        return $columns;
    }

    //AJAX ACTIONS
    //Необходимый для работы метод
    public function configureActions()
    {
        return [
            'deleteSegment' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST])
                ],
                'postfilters' => []
            ],
            'getView' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST])
                ],
                'postfilters' => []
            ]
        ];
    }
    
    public function deleteSegmentAction($data)
    {
        if (!$data['id'] || !$data['params']) return false;
       
        $entitySegmentsClass = $this->getEntityClass($data['params']['SEGMENTS_BLOCK']['CODE']);
        $entityPartnersClass = $this->getEntityClass($data['params']['SEGMENTS_USERS_BLOCK']['CODE']);
        $partners = [];
        
        try {
            $partnersRes = $entityPartnersClass::getList([
                'filter' => [$data['params']['SEGMENTS_USERS_BLOCK']['SEGMENT_FIELD_CODE'] => $data['id']],
                'select' => ['ID']
            ]);
            while ($id = $partnersRes->fetch()['ID']) {
                $partners[] = $id;
            }
            foreach ($partners as $id) {
                $entityPartnersClass::delete($id);
            }
            $entitySegmentsClass::delete($data['id']);
            
            return true;
        } catch (\Error $e) {
            return $e->getMessage();
        }
    }
    
    public function getViewAction($template) {
        return $this->getViewContent($_SERVER['DOCUMENT_ROOT'].$template.'.php');
    }
    
    public function partnersSearchAction($data) {
        if (!$data['blockCode'] || !$data['guids']) return;
        
        array_walk($data['guids'], function(&$item){
            $item = str_replace([',', ' ', ';'], '', $item);
        });
        
        $this->partners = [];
        $partnersRes = SectionTable::getList([
            'filter' => ['XML_ID' => array_unique($data['guids']), 'IBLOCK.CODE' => $data['blockCode']],
            'select' => ['ID', 'XML_ID', 'NAME']
        ]);
        while ($partner = $partnersRes->fetch()) {
            if (isset($this->partners[$partner['XML_ID']])) {
                if (!isset($this->partners[$partner['XML_ID']]['SUB_RESULT'])) {
                    $this->partners[$partner['XML_ID']] = ['SUB_RESULT' => [$this->partners[$partner['XML_ID']]]];
                }
                $this->partners[$partner['XML_ID']]['SUB_RESULT'][] = $partner;
            } else {
                $this->partners[$partner['XML_ID']] = $partner;
            }
        }
        $this->notFound = array_diff(array_unique($data['guids']), array_keys($this->partners));
        
        return ['html' => $this->getViewContent($_SERVER['DOCUMENT_ROOT'].$data['template'].'searchResult.php'), 'result' => $this->partners];
    }
    
    public function addSegmentAction($params) {
        $entitySegmentsClass = $this->getEntityClass($params['params']['SEGMENTS_BLOCK']['CODE']);
        $entityPartnersClass = $this->getEntityClass($params['params']['SEGMENTS_USERS_BLOCK']['CODE']);
        $segmentName = $params['name'] ?: Loc::getMessage('MA_SEGMENTS.SEGMENT_NAME').(new DateTime())->toString();
        $partners = $params['elements'];
        $segmentProps = $params['params']['SEGMENTS_BLOCK']['PROPS'];
        $partnerProps = $params['params']['SEGMENTS_USERS_BLOCK']['PROPS'];
        $this->curUrl = $params['curUrl'];
        
        try {
            $this->segmentId = $entitySegmentsClass::add([
                $segmentProps['NAME'] => $segmentName,
                $segmentProps['AUTHOR'] => $GLOBALS['USER']->getId()
            ])->getId();
        
            foreach ($partners as $partner) {
                if ($partner['ID']) {
                    $entityPartnersClass::add([
                        $params['params']['SEGMENTS_USERS_BLOCK']['SEGMENT_FIELD_CODE'] => $this->segmentId,
                        $partnerProps['USER'] => $partner['ID'],
                        $partnerProps['GUID'] => $partner['XML_ID'],
                    ]);
                }
            }
        
            if ($this->segmentId > 0) {
                $entitySegmentsClass::update($this->segmentId, ['UF_XML_ID' => $this->segmentId]);
                return $this->getViewContent($_SERVER['DOCUMENT_ROOT'].$params['template'].'addResultSuccess.php');
            } else {
                return $this->getViewContent($_SERVER['DOCUMENT_ROOT'].$params['template'].'addResultFail.php');
            }
        } catch (Error $e) {
            $this->error = $e->getMessage();
            return $this->getViewContent($_SERVER['DOCUMENT_ROOT'].$params['template'].'addResultFail.php');
        }
    }
    
    public function deleteSegmentElementAction($data) {
        if (!$data['id'] || !$data['params']) return false;
       
        $entityPartnersClass = $this->getEntityClass($data['params']['SEGMENTS_USERS_BLOCK']['CODE']);  
        try {
            if (is_array($data['id'])) {
                foreach ($data['id'] as $id) {
                    $entityPartnersClass::delete($id);
                }
            } else {
                $entityPartnersClass::delete($data['id']);
            }
            return true;
        } catch (\Error $e) {
            return $e->getMessage();
        }
    }

    private function getViewContent($path) {
        if (file_exists($path)) {
            ob_start();
            include_once $path;
            $result = ob_get_contents();
            ob_end_clean();
            return $result;
        }
    }
}