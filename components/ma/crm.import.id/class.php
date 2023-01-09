<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\{Authentication, HttpMethod, Csrf};

class CrmImportId extends \CBitrixComponent implements Controllerable {

    protected function init()
    {
        if (!$GLOBALS['USER']->isAdmin()) {
            throw new \Exception('Только для админов');
        }
        $this->arResult = [
            //'SOME_DATA' => 'SOME_DATA',
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
    
    //AJAX ACTIONS
    //Необходимый для работы метод
    public function configureActions()
    {
        return [
            'test' => [
                'prefilters' => [
                    new Authentication(),
                    new HttpMethod([HttpMethod::METHOD_POST]),
                    new Csrf(),
                ],
                'postfilters' => []
            ]
        ];
    }
    
    public function updateEntitysAction($post)
    {
        $result = [];
        Loader::includeModule('crm');
        $entity = '';
        switch ($post['ENTITY']) {
            case 'lead':
                $entity = new CCrmLead;
                break;
            case 'deal':
                $entity = new CCrmDeal;
                break;
            case 'contact':
                $entity = new CCrmContact;
                break;
            case 'company':
                $entity = new CCrmCompany;
                break;
            case 'user':
                $entity = new CUser;
                break;
        }

        $result['ITEMS'] = [];
        if ($entity !== '') {
            foreach ($post['ITEMS'] as $item) {
                if (!isset($item['ID'])) {
                    continue;
                }
                $bResult = $entity->update($item['ID'], $item, false);
                if ($bResult) {
                    $result['GOOD'] ++;
                    $result['ITEMS'][$item['ID']] = 'Y';
                } else {
                    $result['BAD'] ++;
                    $result['ITEMS'][$item['ID']] = 'N';
                }
            }
        }
        
        return $result;
    }
    
    public function getMultiFieldsAction()
    {
        $result = [];
        $userTypeRes = Bitrix\Main\UserFieldTable::GetList([
            'filter' => ['MULTIPLE' => 'Y', 'ENTITY_ID' => ['CRM_LEAD', 'CRM_DEAL', 'CRM_CONTACT', 'CRM_COMPANY']],
            'select' => ['ID', 'ENTITY_ID', 'FIELD_NAME']
        ]);
        while ($prop = $userTypeRes->fetch()) {
            $result[strtolower(substr($prop['ENTITY_ID'], 4))][] = $prop['FIELD_NAME'];
        }

        return $result;
    }
}