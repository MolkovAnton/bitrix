<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader,
    Bitrix\Main\Localization\Loc,
    Bitrix\Main\Engine\Contract\Controllerable,
    Bitrix\Main\Config\Option,
    Bitrix\Crm\RequisiteTable,
    Bitrix\Crm\FieldMultiTable;
use Bitrix\Main\Engine\ActionFilter\{Authentication, HttpMethod, Csrf};

class ParserSearch extends \CBitrixComponent implements Controllerable {
    protected $parserUrl = null;
    protected $responsibles = [];
    const EDITOR_GROUP_CODE = 'PARSER_EDITOR';
    

    protected function init()
    {
        \Bitrix\Main\Loader::includeModule("itees.car");
        $checkAccessRights = new \Itees\CAR\CheckAccessRights();
        if (!$checkAccessRights->check('PARSER')) throw new Exception(GetMessage("PARSER_SEARCH.NO_RIGTHS"));;
        
        GLOBAL $USER;
        $this->parserUrl = $this->arParams['PARSER_URL'];
        $this->responsibles = $this->getResponsibles();
        $this->InitComponentTemplate();
        $template = & $this->GetTemplate();
        
        $this->arResult = [
            'PARSER_URL' => $this->parserUrl,
            'REQUIRED_WORDS_COUNT' => Option::get("main", "REQUIRED_WORDS_COUNT", false),
            'WORDS' => Option::get("main", "WORDS", false),
            'LEAD_FIELDS_NAMES' => $this->getFieldsNames(),
            'EDITOR' => $this->canEdit(),
            'CUR_USER' => $USER->GetFullName(),
            'LOG_PATCH' => $template->GetFolder()."/log.txt",
            'TAG_FIELD_NAME' => \IteesFunctions::getUserTypeEntityCodeByXml($this->arParams['TAG_FIELD_NAME'], 'CRM_LEAD'), 
        ];
        $this->arResult['FM_FIELDS_NAMES'] = $this->getFmFieldsNames($this->arResult['LEAD_FIELDS_NAMES']['STRING']);
        
        if (isset($this->responsibles['FOUND'])) {
            $this->arResult['RESPONSIBLE_FOR_FOUND_WORDS_LEAD'] = $this->responsibles['FOUND'];
        } else {
            $this->arResult['ERROR'][] = Loc::getMessage("PARSER_SEARCH.NO_FOUND_RESPONSIBLE");
        }
        
        if (isset($this->responsibles['NOT_FOUND'])) {
            $this->arResult['RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD'] = $this->responsibles['NOT_FOUND'];
        } else {
            $this->arResult['ERROR'][] = Loc::getMessage("PARSER_SEARCH.NO_NOT_FOUND_RESPONSIBLE");
        }
    }

    public function executeComponent() 
    {
        try {
            $this->init();	
            $this->ShowComponentTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
    }
    
    private function getResponsibles()
    {
        $result = [];
        $foundId = Option::get("main", "RESPONSIBLE_FOR_FOUND_WORDS_LEAD", false);
        $notFoundId = Option::get("main", "RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD", false);
        $filter = [ 'ID' => [$foundId, $notFoundId] ];
        $resUser = Bitrix\Main\UserTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'NAME', 'LAST_NAME']
        ]);
        while ($user = $resUser->fetch()) {
            if ($user['ID'] === $foundId) {
                $result['FOUND'] = ['ID' => $user['ID'], 'NAME' => $user['LAST_NAME']." ".$user['NAME']];
            }
            if ($user['ID'] === $notFoundId) {
                $result['NOT_FOUND'] = ['ID' => $user['ID'], 'NAME' => $user['LAST_NAME']." ".$user['NAME']];
            }
        }
        return $result;
    }
    
    private function canEdit()
    {
        GLOBAL $USER;
        $group = Bitrix\Main\UserGroupTable::getList([
            'select' => ['USER_ID'],
            'filter' => ['USER_ID' => $USER->getId(), 'GROUP.STRING_ID' => self::EDITOR_GROUP_CODE]
        ])->fetch();
        if (!empty($group)) {
            return "Y";
        }
        return "N";
    }

    public function getFieldsNames()
    {
        $enumFields = [];
        $arFields = [ 
            'STRING' => [
                'Название лида' => 'TITLE',
                'Фамилия' => 'LAST_NAME',
                'Имя' => 'NAME',
                'Отчество' => 'SECOND_NAME',
                'Адрес' => 'ADDRESS',
                'Рабочий телефон' => ['FM' => ['PHONE' => ['n0' => ['VALUE_TYPE' => 'WORK']]]],
                'Мобильный телефон' => ['FM' => ['PHONE' => ['n1' => ['VALUE_TYPE' => 'MOBILE']]]],
                'Домашний телефон' => ['FM' => ['PHONE' => ['n2' => ['VALUE_TYPE' => 'HOME']]]],
                'Телефон для рассылок' => ['FM' => ['PHONE' => ['n3' => ['VALUE_TYPE' => 'MAILING']]]],
                'Другой телефон' => 'PHONE',
                'Корпоративный сайт' => ['FM' => ['WEB' => ['n0' => ['VALUE_TYPE' => 'WORK']]]],
                'Страница ВКонтакте' => 'IM_VK',
                'Микроблог Twitter' => 'IM_OTHER',
                'Страница Facebook' => 'IM_FACEBOOK',
                'Рабочий e-mail' => ['FM' => ['EMAIL' => ['n0' => ['VALUE_TYPE' => 'WORK']]]],
                'Частный e-mail' => ['FM' => ['EMAIL' => ['n1' => ['VALUE_TYPE' => 'HOME']]]],
                'Другой e-mail' => ['FM' => ['EMAIL' => ['n2' => ['VALUE_TYPE' => 'OTHER']]]],
                'Название компании' => 'COMPANY_TITLE',
                'Должность' => 'POST',
                'Комментарий' => 'COMMENTS',
                'Дополнительно об источнике' => 'SOURCE_DESCRIPTION',
                'Город' => 'ADDRESS_CITY',
            ]
        ];
        
        $arFilter = ['ENTITY_ID' => 'CRM_LEAD', 'LANG' => 'RU'];
        $arUserTypeEntity = CUserTypeEntity::GetList([], $arFilter);
        while ($field = $arUserTypeEntity->fetch()) {
            if ($field['USER_TYPE_ID'] === 'enumeration') {
               $enumFields[] = $field['ID']; 
               $arFields['ENUM'][$field['ID']]['NAME'] = $field['FIELD_NAME'];
               $arFields['ENUM'][$field['ID']]['NAME_RUS'] = $field['LIST_COLUMN_LABEL'];
               if ($field['MULTIPLE'] !== 'Y') $arFields['ENUM'][$field['ID']]['NOT_ARRAY'] = true;
            } else {
                $arFields['STRING'][$field['LIST_COLUMN_LABEL']] = $field['FIELD_NAME'];
            }
        }
        
        $obEnum = new CUserFieldEnum;
        $enumFieldsRes = $obEnum->GetList(["USER_FIELD_ID" => "ASC", "SORT" => "ASC", "ID" => "ASC"], ["USER_FIELD_ID" => $enumFields]);
        while ($enumField = $enumFieldsRes->Fetch()) {
            $arFields['ENUM'][$enumField['USER_FIELD_ID']]['VALUES'][$enumField['VALUE']] = $enumField;
        }
        
        foreach ($arFields['ENUM'] as $key => $val) {
            $arFields['ENUM'][$val['NAME_RUS']] = ['NAME'=>$val['NAME'], 'VALUES'=>$val['VALUES']];
            if ($val['NOT_ARRAY']) $arFields['ENUM'][$val['NAME_RUS']]['NOT_ARRAY'] = true;
            unset($arFields['ENUM'][$key]);
        }
        
        $arFields['ENUM']['Статус'] = ['NAME'=>'STATUS_ID', 'VALUES'=>[], 'NOT_ARRAY'=>true];
        $arFields['ENUM']['Источник'] = ['NAME'=>'SOURCE_ID', 'VALUES'=>[], 'NOT_ARRAY'=>true];
        $stetusRes = Bitrix\Crm\StatusTable::getList([
            'select' => ['ENTITY_ID', 'STATUS_ID', 'NAME'],
            'filter' => ['ENTITY_ID' => ['SOURCE', 'STATUS']]
        ]);
        while ($status = $stetusRes->fetch()) {
            if ($status['ENTITY_ID'] === 'SOURCE') {
                $arFields['ENUM']['Источник']['VALUES'][$status['NAME']]['ID'] = $status['STATUS_ID'];
            } else {
                $arFields['ENUM']['Статус']['VALUES'][$status['NAME']]['ID'] = $status['STATUS_ID'];
            }
        }

        return $arFields;
    }
    
    private function getFmFieldsNames($data)
    {
        //Массив с именами свойств в которых хранятся телефоны и имейлы
        $fmFields = [];
        foreach ($data as $rusName => $field) {
            if (isset($field['FM']['PHONE'])) {
                $fmFields['PHONE'][] = $rusName;
            } else if (isset($field['FM']['EMAIL'])) {
                $fmFields['EMAIL'][] = $rusName;
            }
        }
        
        return $fmFields;
    }

    public function makeArrayAction($post)
    {
        return ['response' => 'success', 'data' => $post];
    }
    
    //AJAX ACTIONS
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

    //функция получает на вход список ИНН, Телефоны и Email
    //ищит ИНН, Телефоны и Email в crm
    //возвращает не найденные ИНН
    public function getAbsentAction($post)
    {
        if(!empty($post))
        {
            Loader::includeModule('crm');
            
            $this->postData = $post['DATA'];
            $this->logPath = $post['LOG_PATH'];
            
            $this->mess = $this->arKeys = [];
            
            $this->getAbsentPhoneEmail();
            $this->getAbsentInn();
            $this->getPrepareKeys($post['DATA']);
            $this->saveLog();

            //возвращаем найденные ключи
            return $this->arKeys;
        }

        return false;
    }
    
    //подготовим все ключи
    private function getPrepareKeys($arPostData)
    {
        $arPhoneKeys = $arEmailKeys = $arInnKeys = $arWebKeys = [];
        
        //найденные телефоны
        if(!empty($this->arFilteredPhone)) {
            $arPhoneKeys = array_keys($this->arFilteredPhone);
        }

        //найденные email
        if(!empty($this->arFilteredEmail)) {
            $arEmailKeys = array_keys($this->arFilteredEmail);
        }
        
        //найденные сайты
        if(!empty($this->arFilteredWeb)) {
            $arWebKeys = array_keys($this->arFilteredWeb);
        }

        //найденные inn
        if(!empty($this->postData['INN'])) {
            $arInnKeys = array_keys($this->postData['INN']);
        }

        $this->arKeys = array_unique(array_merge($arPhoneKeys, $arEmailKeys, $arInnKeys, $arWebKeys));
    }
    
    private function saveLog()
    {
        if(!empty($this->mess) && !empty($this->logPath))
        {
            file_put_contents($_SERVER["DOCUMENT_ROOT"] . $this->logPath, $this->mess, FILE_APPEND);
        }
    }

    //проверка наличия сущностей crm по телефону и email
    private function getAbsentPhoneEmail()
    {
        if((!empty($this->postData['PHONE']) || !empty($this->postData['EMAIL'])))
        {
            $arFilter = $this->arPhones = $this->arEmails = $this->arFilteredPhone = $this->arFilteredEmail = $this->arWeb = $this->arFilteredWeb = [];

            if(!empty($this->postData['PHONE'])) {
                $arFilter[] = ['TYPE_ID' => 'PHONE', 'VALUE' => array_merge(... $this->postData['PHONE'])];
            }
            
            if(!empty($this->postData['EMAIL'])) {
                $arFilter[] = ['TYPE_ID' => 'EMAIL', 'VALUE' => array_merge(... $this->postData['EMAIL'])];
            }
            
            if(!empty($this->postData['WEB'])) {
                $arFilter[] = ['TYPE_ID' => 'WEB', 'VALUE' => $this->postData['WEB']];
            }
            
            if(count($arFilter) > 1) {
                $arFilter['LOGIC'] = 'OR';
            }

            $obFieldMulti = FieldMultiTable::getList([
                'filter' => [
                    $arFilter,
                    'ENTITY_ID' => ['COMPANY', 'LEAD', 'CONTACT', 'DEAL'],
                    'LOGIC' => 'AND'
                ],
                'select' => ['ID', 'TYPE_ID', 'VALUE', 'ENTITY_ID', 'ELEMENT_ID']
            ]);
            //найдем crm сущности по email и телефону
            while($arFieldMulti = $obFieldMulti->Fetch()) {
                switch ($arFieldMulti['TYPE_ID']) {
                    case 'PHONE':
                        $this->arPhones[] = $arFieldMulti['VALUE'];
                        break;
                    case 'EMAIL':
                        $this->arEmails[] = $arFieldMulti['VALUE'];
                        break;
                    case 'WEB':
                        $this->arWeb[] = $arFieldMulti['VALUE'];
                }
                
                $this->mess[] = date('d.m.Y - G:i:s')." - отклонено создание лида, " . Loc::getMessage($arFieldMulti['TYPE_ID']) . " существует : " . Loc::getMessage($arFieldMulti['ENTITY_ID']) . ' ' . $arFieldMulti['ELEMENT_ID']."\r\n";
            }

            if(!empty($this->arPhones)) {
                $this->arPhones = array_unique($this->arPhones);
                $this->arFilteredPhone = array_filter($this->postData['PHONE'], [$this, 'filterPhone']);
            }
            
            if(!empty($this->arEmails)) {
                $this->arEmails = array_unique($this->arEmails);
                $this->arFilteredEmail = array_filter($this->postData['EMAIL'], [$this, 'filterEmail']);
            }
            
            if(!empty($this->arWeb)) {
                $this->arWeb = array_unique($this->arWeb);
                $this->arFilteredWeb = array_filter($this->postData['WEB'], [$this, 'filterWeb']);
            }

            //убираем из поиска по ИНН сущности найденные по Телефону и Email
            $this->postData['INN'] = array_diff_key($this->postData['INN'], $this->arFilteredPhone, $this->arFilteredEmail, $this->arFilteredWeb);
        }
    }
    
    //фильтрация найденных телефонов
    private function filterPhone($value) {
        if(!empty(array_intersect($this->arPhones, $value))) {
            return true;
        }
        return false;
    }
    
    //фильтрация найденных email
    private function filterEmail($value) {
        if(!empty(array_intersect($this->arEmails, $value))) {
            return true;
        }
        return false;
    }
    
    //фильтрация найденных web
    private function filterWeb($value) {
        if(in_array($value, $this->arWeb)) {
            return true;
        }
        return false;
    }
    
    private function getAbsentInn()
    {
        if(!empty($this->postData['INN']))
        {
            $this->arFoundInn = [];
            
            //найдем инн указанный в пользовательском свойстве сущности
            $arEntities = [
                'CRM_CONTACT' => ['PROPERTY_XML' => 'CONTACT_INN', 'CLASS' => 'Bitrix\Crm\ContactTable'],
                'CRM_COMPANY' => ['PROPERTY_XML' => 'CRM_COMPANY_INN', 'CLASS' => 'Bitrix\Crm\CompanyTable'],
                'CRM_DEAL' => ['PROPERTY_XML' => 'UF_CRM_DEAL_INN', 'CLASS' => 'Bitrix\Crm\DealTable'],
                'CRM_LEAD' => ['PROPERTY_XML' => 'UF_CRM_LEAD_INN', 'CLASS' => 'Bitrix\Crm\LeadTable']
            ];

            foreach($arEntities as $entity => $arEntity) {
                $this->getFindCrmInn($entity, $arEntity);
            }

            //найдем инн указанный в реквизитах сущности
            $obRequisites = RequisiteTable::getList([
                'filter' => ['ENTITY_TYPE_ID' => [1, 2, 3, 4], 'RQ_INN' => $this->postData['INN']],
                'select' => ['ID', 'RQ_INN', 'ENTITY_ID', 'ENTITY_TYPE_ID']
            ]);
            
            while($arRequisite = $obRequisites->Fetch()) {
                $this->arFoundInn[] = $arRequisite['RQ_INN'];
                $this->mess[] = date('d.m.Y - G:i:s')." - отклонено создание лида, ИНН существует : " . Loc::getMessage(CCrmOwnerType::ResolveName($arRequisite['ENTITY_TYPE_ID'])) . ' ' . $arRequisite['ENTITY_ID']."\r\n";
            }

            $this->postData['INN'] = array_intersect($this->postData['INN'], $this->arFoundInn);
        }
    }
    
    //ищит ИНН в сущности crm
    public function getFindCrmInn($entity, $arEntity)
    {
        $entityInnPropCode = \IteesFunctions::getUserTypeEntityCodeByXml($arEntity['PROPERTY_XML'], $entity);

        $obentity = $arEntity['CLASS']::getList([
            'filter' => [$entityInnPropCode => $this->postData['INN']],
            'select' => ['ID', $entityInnPropCode]
        ]);

        while($arEntity = $obentity->Fetch()) {
            $this->arFoundInn[] = $arEntity[$entityInnPropCode];
            $this->mess[] = date('d.m.Y - G:i:s')." - отклонено создание лида, ИНН существует : " . Loc::getMessage(str_replace('CRM_', '', $entity)) . ' ' . $arEntity['ID']."\r\n";
        }
    }
    
    //создание лидов
    public function createLeadsAction($post)
    {
        if(!empty($post['ITEMS']))
        {
            Loader::includeModule('crm');
        
            $arLeadsId = ['CREATED' => [], 'NOT_CREATED' => 0];
            $entity = new \CCrmLead(false);
            $company = new \CCrmCompany(false);
            
            foreach ($post['ITEMS'] as $arItem) { 
                try {
                    $companyId = $company->add($arItem);
                    $arItem['COMPANY_ID'] = $companyId;
                    $leadId = $entity->add($arItem);
                    if ($leadId) {
                        $arLeadsId['CREATED'][] = $leadId;
                    } else {
                        $arLeadsId['NOT_CREATED'] ++;
                    }
                } catch (\Exception $e) {
                    $arLeadsId['ERRORS'][] = $e->getMessage();
                }
            }
            return $arLeadsId;
        }
    }
    
    //устанавливаем ответственных за лиды
    public function setParametersAction($post)
    {
        //ответственный за лиды для которых найдено нужное количество слов
        if(!empty($post['RESPONSIBLE_FOR_FOUND_WORDS_LEAD'])) {
            Option::set("main", "RESPONSIBLE_FOR_FOUND_WORDS_LEAD", $post['RESPONSIBLE_FOR_FOUND_WORDS_LEAD']);
        }

        //ответственный за лиды для которых не найдено нужное количество слов
        if(!empty($post['RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD'])) {
            Option::set("main", "RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD", $post['RESPONSIBLE_FOR_NOT_FOUND_WORDS_LEAD']);
        }
        
        //слова для поиска
        if(!empty($post['WORDS'])) {
            Option::set("main", "WORDS", $post['WORDS']);
        }
        
        //количество слов учитываемое при распределении лидов
        if(!empty($post['REQUIRED_WORDS_COUNT'])) {
            Option::set("main", "REQUIRED_WORDS_COUNT", $post['REQUIRED_WORDS_COUNT']);
        }
        
        return $post;
    }
    
    //Запись в лог файл
    public static function addToLogAction($post)
    {
        if (!empty($post['MESSAGE']) && !empty($post['LOG_PATH'])) {
            file_put_contents($_SERVER["DOCUMENT_ROOT"].$post['LOG_PATH'], $post['MESSAGE'], FILE_APPEND);
            return true;
        }
        
        return false;
    }
}