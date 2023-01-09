<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\{Authentication, HttpMethod, Csrf};

class CrmMultiFieldImport extends \CBitrixComponent implements Controllerable {
    private $fieldNames = [
        "EVENT_INVAITED",
        "EVENT_REGISTERED",
        "EVENT_VISITED"
    ];
    private $users = [880, 335, 1098];

    protected function init()
    {
        if (!$GLOBALS['USER']->isAdmin() && !in_array($GLOBALS['USER']->getId(), $this->users)) {
            throw new \Exception('Нет доступа');
        }
        $this->arResult = [
            'FIELD_NAMES' => $this->getFieldsNames($this->fieldNames),
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

        $result['ITEMS'] = [];
        $entity = new CCrmContact;
        $fieldsNames = $this->getFieldsNames($this->fieldNames);
        foreach ($post['ITEMS'] as $item) {
            if (!isset($item['EMAIL'])) {
                $result['BAD'] ++;
                continue;
            }
            $contactId = CCrmFieldMulti::GetList([], ['ENTITY_ID' => 'CONTACT', 'TYPE_ID' => 'EMAIL', 'VALUE' => $item['EMAIL']])->fetch()['ELEMENT_ID'];
            $contact = CCrmContact::GetList([], ['ID' => $contactId], array_merge(['ID'], array_values($fieldsNames)))->fetch();
            $newFieldNames = $this->makeNewFieldNames($item, $fieldsNames);
            if ($contact) {
                $newValues = $this->makeNewValues($contact, $newFieldNames);
                $bResult = $entity->update($contact['ID'], $newValues, false);
            } else {
                $bResult = $entity->Add(array_merge(['NAME'=>$item['EMAIL']], $newFieldNames));
            }
            if ($bResult) {
                $result['GOOD'] ++;
                $result['ITEMS'][$item['EMAIL']] = [
                    'STATUS' => 'Y',
                    'ID' => isset($contact['ID']) ? $contact['ID'] : $bResult
                ];
            } else {
                $result['BAD'] ++;
                $result['ITEMS'][$item['EMAIL']] = [
                    'STATUS' => 'N',
                    'ID' => isset($contact['ID']) ? $contact['ID'] : $bResult
                ];
            }
        }
        
        return $result;
    }
    
    private function makeNewFieldNames($item, $fieldsNames)
    {
        $result = [];
        foreach ($fieldsNames as $name => $id) {
            $result[$id] = $item[$name];
        }
        return $result;
    }
    
    private function makeNewValues($contact, $newFields)
    {
        $result = [];
        foreach ($newFields as $id => $val) {
            $result[$id] = array_unique(array_merge($val, is_array($contact[$id]) ? $contact[$id] : []));
        }
        return $result;
    }

    public function getFieldsNames(array $names)
    {
        $result = [];
        $arUserTypeEntity = Bitrix\Main\UserFieldTable::getList([
            'filter' => ['XML_ID' => $names, 'ENTITY_ID' => 'CRM_CONTACT']
        ]);
        while ($field = $arUserTypeEntity->fetch()) {
            $result[$field['XML_ID']] = $field['FIELD_NAME'];
        }
        
        return $result;
    }
}