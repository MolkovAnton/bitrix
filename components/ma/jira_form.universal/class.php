<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
    Bitrix\Main\UserTable,
    Bitrix\Main\Config\Option;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;

class JiraFormUniversal extends \CBitrixComponent implements Controllerable {
    private $usersFields = [];
    private $alias = [];


    protected function init()
    {
        CJSCore::Init("date");
        if (empty($this->arParams['OPTIONS']['URL'])) {
            throw new Exception('Empty options');
        }
        
        $this->arResult = [
            'FIELDS' => $this->prepairFields($this->arParams["FIELDS"]),
            'OPTIONS' => $this->arParams["OPTIONS"],
            'REQUEST_OPTIONS' => $this->arParams["REQUEST_OPTIONS"],
            'FORM_NAME' => !empty($this->arParams["FORM_NAME"]) ? $this->arParams["FORM_NAME"] : substr(md5(rand()), 0, 7),
            'USERS_FIELDS' => $this->usersFields,
            'ALIAS' => $this->alias
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
            'sendFormToJiraAction' => [
                'prefilters' => [
                    new HttpMethod([HttpMethod::METHOD_POST]),
                ],
                'postfilters' => []
            ]
        ];
    }
    
    //Пример ajax метода создания лидов
    public function sendFormToJiraAction($post)
    {
        if(!empty($post['DATA']))
        {
            $encodedData = self::prepaireData($post['DATA']);

            //Логирование
            $dataLog = [
                'UF_DATE' => new \Bitrix\Main\Type\DateTime(),
                'UF_USER_ID' => $GLOBALS['USER']->GetID(),
                'UF_EVENT' => 'JiraFormSend',
                'UF_DESCRIPTION' => $encodedData,
                'UF_FUNCTIONAL_CODE' => 'JIRA_FORM'
            ];
            \HBE::Add('Logs', $dataLog);
            
            $curlOptions = self::prepaireCurlOpt($post['DATA']['OPTIONS']);
            
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedData);
            curl_setopt_array($curl, $curlOptions);
            $result = curl_exec($curl);
            curl_close($curl);
            return json_decode($result, true);
        }
    }
    
    private static function prepaireCurlOpt(array $options) {
        $curlOptions = [
            CURLOPT_HTTPHEADER => array('Content-type: application/json'),
            CURLOPT_RETURNTRANSFER => true
        ];
        foreach ($options as $name => $value) {
            switch ($name) {
                case 'URL':
                    $curlOptions[CURLOPT_URL] = $value;
                    break;
                case 'LOGIN':
                    $curlOptions[CURLOPT_USERPWD] = $value['LOGIN'] . ':' . $value['PASSWORD'];
                    break;
                default :
                    $curlOptions[$name] = $value;
                    break;
            }
        }
        
        return $curlOptions;
    }

    private static function prepaireData($data)
    {
        $result = [];
        $userLogins = self::getLogins($data);
        $fieldsName = $data['REQUEST_OPTIONS']['fieldsName'];
        unset($data['REQUEST_OPTIONS']['fieldsName']);
        
        foreach ($data['REQUEST_OPTIONS'] as $name => $val) {
            $result[$name] = $val;
        }
        foreach ($data['FIELDS'] as $name => $val) {
            if (in_array($name, $data['USERS_FIELDS'])) {
                $val = $userLogins[$val];
            }
            if (!empty($data['ALIAS'][$name])) {
                $result[$fieldsName][$name] = [$data['ALIAS'][$name] => $val]; 
            } else {
                $result[$fieldsName][$name] = $val;
            }
        }
        
        return json_encode($result);
    }
    
    private function prepairFields($fields) {
        foreach ($fields as $name => $val) {
            if ($val['TYPE'] === 'department') {
                $departments = $this->getDepartmentTree();
                $fields[$name]['TYPE'] = 'list';
                $fields[$name]['OPTIONS'] = $departments;
            } elseif ($val['TYPE'] === 'user') {
                $this->usersFields[] = $name;
            } elseif ($val['TYPE'] === 'self') {
                $fields[$name] = [
                    'TYPE' => 'hidden',
                    'VALUE' => $this->getSelfLogin()
                ];
            }
            if (!empty($val['ALIAS'])) {
                $this->alias[$name] = $val['ALIAS'];
            }
        }
        return $fields;
    }

    private function getDepartmentTree() {
        $depTree = [];
        $sections = CIBlockSection::GetTreeList(['IBLOCK_ID' => Option::get('intranet', 'iblock_structure', 0), 'ACTIVE' => 'Y']);
        while ($dep = $sections->fetch()) {
            $depTree[] = ['NAME' => str_repeat('. ', $dep['DEPTH_LEVEL']).$dep['NAME'], 'VALUE' => $dep['NAME']];
        }
        return $depTree;
    }
    
    private static function getLogins(array $data = []) {
        if(empty($data['USERS_FIELDS'])) return [];
        
        $result = $users = [];
        foreach ($data['USERS_FIELDS'] as $field) {
            if (is_array($data['FIELDS'][$field])) {
                $key = array_key_first($data['FIELDS'][$field]);
                $users[] = $data['FIELDS'][$field][$key];
            } else {
                $users[] = $data['FIELDS'][$field];
            }
        }
        $userRes = UserTable::getList([
            'filter' => ['ID' => $users],
            'select' => ['ID', 'LOGIN']
        ]);
        while ($user = $userRes->fetch()) {
            $result[$user['ID']] = strstr($user['LOGIN'], '@', true) ?: $user['LOGIN'];
        }
        
        return $result;
    }
    
    private function getSelfLogin() {
        $login = $GLOBALS['USER']->getLogin();
        return strstr($login, '@', true) ?: $login;
    }
}