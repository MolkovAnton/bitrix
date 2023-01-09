<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Config\Option;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\{Authentication, HttpMethod, Csrf};

class ParserSettings extends \CBitrixComponent implements Controllerable {

    protected function init()
    {
        $companyTypeList = self::getPropList('COMPANY_SPECIALIZATION', 'CRM_COMPANY');
        $parserSettings = self::getSettings(array_keys($companyTypeList));
        
        $this->arResult = [
            'COMPANY_TYPE_LIST' => $companyTypeList,
            'SETTINGS' => $parserSettings,
            'USER_ROLE' => self::getUserRole()
        ];
    }
    
    public static function getPropList($propsXMLId, $entityType)
    {
        $propId = Bitrix\Main\UserFieldTable::GetList([
            'filter' => ['XML_ID'=>$propsXMLId, 'ENTITY_ID' => $entityType]
        ])->fetch()['ID'];
        
        $result = [];
        if ($propId > 0) {
            $propRes = CUserFieldEnum::GetList([], ['USER_FIELD_ID'=>$propId]);
            while ($prop = $propRes->fetch()) {
                $result[$prop['ID']] = ['ID' => $prop['ID'], 'VALUE' => $prop['VALUE'], 'XML_ID' => $prop['XML_ID']];
            }
        }
        return $result;
    }
    
    public static function getSettings(array $ids)
    {
        $result = [];
        
        foreach ($ids as $id) {
            $result[$id] = Option::get('main', 'parser_settings_'.$id);
        }
        return $result;
    }
    
    private static function getUserRole()
    {
        GLOBAL $USER;
        $role = '';
        
        if (IteesHandler::IsUserGroupByCode('PARSER_EDITOR')) {
            $role = 'EDITOR';
        } else if (IteesHandler::IsUserGroupByCode('PARSER_VIEW')) {
            $role = 'VIEW';
        }
        return $role;
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
    
    public function setSettingsAction($post)
    {
        Option::set("main", "parser_settings_".$post['id'], $post['req_words']);
        return $post;
    }
}