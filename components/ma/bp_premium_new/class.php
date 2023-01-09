<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main,
    \Bitrix\Main\Loader;

class BpPremiumNewComponent extends \CBitrixComponent {
    private $historyElementsPerPage; //Количество элементов истории на странице
    private $bpListCodeElements; //Код списка с элементами таблицы премий
    private $bpListCodeContainer; //Код списка блоков с премиями
    private $bpListCodeApprovers; //Код списка с картой утверждающих
    private $bpListCodeProjects; //Код списка проектов
    protected $request; //Параметры для ajax запроса
    private $userID = 0; //ID пользователя
    private $userDepartment; //Подразделения из которых будут выбираться сотрудники для назначения премий
    private $userSubordinates = []; //Подчиненные пользователя
    private $selfDepartments = []; //Подразделение пользователя
    private $iblockElementId = null; //ID инфоблока с элементами по пользователям
    private $iblockContainerId = null; //ID инфоблока с группировкой по подающему
    private $iblockProjectsId = null; //ID инфоблока проектов
    private $isApprover = false; //Проверка является ли пользователь утверждающим у кого либо
    private $fieldsCodes = []; //Коды свойств
    private $canAddPremium = false; //Может ли пользователь подавать премии
    private $statuses = null;

    private function init()
    {
        Loader::includeModule('iblock');
        
        $this->statuses = ['NEW'=>GetMessage("BP_PREMIUM_STATUS_NEW"), 'APPROVED'=>GetMessage("BP_PREMIUM_STATUS_APPROVED"), 'NO_APPROVER'=>GetMessage("BP_PREMIUM_STATUS_NO_APPROVE"), 'CANCELED'=>GetMessage("BP_PREMIUM_STATUS_NOT_APPROVED")];
        $this->userID = CUser::GetId();
        $this->bpListCodeElements = $this->arParams['BP_LIST_CODE_ELEMENTS'];
        $this->bpListCodeContainer = $this->arParams['BP_LIST_CODE_CONTAINER'];
        $this->bpListCodeApprovers = $this->arParams['BP_LIST_CODE_APPROVERS'];
        $this->bpListCodeProjects = $this->arParams['BP_LIST_CODE_PROJECTS'];
        $this->historyElementsPerPage = $this->arParams['HISTORY_ELEMENTS_PER_PAGE'];
        $this->getIblocksId();
        $this->setFieldsCodes();
        
        if ($this->ajaxCheck()) $this->makeChange();
        
        $this->isApprover = $this->checkApprover();
        $this->setDepartment();
        $this->setSubordinates();
        $this->setApprovers();
        $this->setResult();
    }

    public function executeComponent() 
    {
        try {
            $this->init();	
            $this->IncludeComponentTemplate();
        } catch(\Exception $e) {
            ShowError($e->getMessage());
            return false;
        }
    }
    
    private function getIblocksId()
    {  
        $iblockRes = CIBlock::getList([], ["CODE" => [$this->bpListCodeContainer, $this->bpListCodeElements, $this->bpListCodeProjects], "CHECK_PERMISSIONS" => "N"]);
        while ($iblock = $iblockRes->Fetch()) {
            if ($iblock['CODE'] === $this->bpListCodeContainer) {
                $this->iblockContainerId = $iblock['ID'];
            } else if ($iblock['CODE'] === $this->bpListCodeElements) {
                $this->iblockElementId = $iblock['ID'];
            } else if ($iblock['CODE'] === $this->bpListCodeProjects) {
                $this->iblockProjectsId = $iblock['ID'];
            }
        }
        unset($iblockRes);
    }

    private function setDepartment()
    {
        //Устанавливает подразделения в которых данный пользователь является руководителем, если таких нет - кидает ошибку
        $tmpDep = [];
        $iblockStructureId = Main\Config\Option::get('intranet', 'iblock_structure', 0);
        $entity = \Bitrix\Iblock\Model\Section::compileEntityByIblock($iblockStructureId);
        $ormDep = $entity::getList([
            'select' => ['ID', 'UF_HEAD'],
            'filter' => [
                'ACTIVE' => 'Y',
                'UF_HEAD' => $this->userID,
            ]
        ]);
        while ($department = $ormDep->fetch())
        {
            $tmpDep[] = $department['ID'];
        }

        if (!empty($tmpDep)) {
            $this->canAddPremium = true;
        }
        
        $this->selfDepartments = $tmpDep;
        
        $this->userDepartment = [];
        foreach ($tmpDep as $depId) {
            $this->userDepartment[] = $depId;
            $this->userDepartment = array_merge($this->userDepartment, CIntranetUtils::GetDeparmentsTree($depId, true));
        }
        $this->userDepartment = array_unique($this->userDepartment);
        unset($tmpDep);
    }
    
    private function setSubordinates()
    {
        //Устанавливает подчиненных пользователя
        $obUsers = Main\UserTable::getList([
            'filter' => [
                'LOGIC' => 'AND',
                'ACTIVE' => 'Y',
                [
                    'LOGIC' => 'OR',
                    'UF_DEPARTMENT' => $this->userDepartment,
                    'ID' => $this->userID
                ]
            ],
            'select' => ['ID', 'NAME', 'LAST_NAME', 'UF_DEPARTMENT'],
        ]);
        while ($user = $obUsers->fetch()) {
            $this->userSubordinates[$user['ID']] = $user;
        }
        unset($obUsers);
    }
    
    private function setApprovers()
    {
        $arSort = array('SORT' => 'ASC', 'ID' => 'DESC');
        $arFilter = array('ACTIVE' => 'Y', 'IBLOCK_CODE' => $this->bpListCodeApprovers, "=PROPERTY_USER" => $this->userID, "!PROPERTY_APPROVER" => false);
        $arSelect = array('ID', 'PROPERTY_APPROVER');

        $res = CIBlockElement::getList($arSort, $arFilter, false, false, $arSelect);
        if ($row = $res->fetch()) {
           $this->userApprovers = CUser::GetByID($row['PROPERTY_APPROVER_VALUE'])->fetch();
           $this->userApprovers['IMG'] = CFile::GetPath($this->userApprovers['PERSONAL_PHOTO']);
        }
    }
    
    private function checkApprover()
    {
        $arSort = array('SORT' => 'ASC', 'ID' => 'DESC');
        $arFilter = array('ACTIVE' => 'Y', 'IBLOCK_CODE' => $this->bpListCodeApprovers, "=PROPERTY_APPROVER" => $this->userID);
        $arSelect = array('ID');

        $res = CIBlockElement::getList($arSort, $arFilter, false, false, $arSelect);
        return boolval($res->fetch());
    }

    private function setResult()
    {
        //Заполняет arResult
        $this->arResult = [
            'USER_ID' => $this->userID,
            'CAN_ADD_PREMIUM' => $this->canAddPremium,
            'BP_LIST_CODE' => $this->bpListCode,
            'REQUEST' => $this->request,
            'USER_DEPARTMENT' => $this->userDepartment,
            'USER_SUBORDINATES' => $this->userSubordinates,
            'USER_APPROVERS' => $this->userApprovers,
            'IS_APPROVER' => $this->isApprover,
            'USER_PROJECTS' => $this->getUserProjects()
        ];
    }

    private function ajaxCheck()
    {
        //Устанавливает параметры запроса и если они есть возвращает true
        $this->request = Main\Application::getInstance()->getContext()->getRequest();
        if (!empty($this->request)) return true;
        return false;
    }

    private function makeChange()
    {
        //Вносит изменения если есть есть параметры запроса
        $action = '';
        if ($this->request->isPost()) $action = $this->request->getPost('ACTION');
        
        switch ($action) {
            case 'ADD_BP':
                $this->addProces();
                break;
            case 'CHANGE_STATUS':
                $this->changeStatus();
                break;
            case 'GET_HISTORY':
                $history = $this->getHistory($this->request->getPost("page"));
                $this->endResponse($history);
                break;
            case 'SET_FILE':
                $this->addFile($this->request->getPost("ID"));
                break;
            case 'GET_APPROVE_HISTORY':
                $history = $this->getApproveHistory(intval($this->request->getPost("page")));
                $this->endResponse($history);
                break;
            case 'GET_NEW':
                $new = $this->getApproveHistory(intval($this->request->getPost("page")), 'new');
                $this->endResponse($new);
                break;
        }
    }
    
    private function addProces()
    {
        Loader::includeModule('lists');
            
        $containerId = null;
        $containerList = new CList($this->iblockContainerId);
        $listContFields = $containerList->GetFields();

        $status = '';
        if ($this->request->getPost('TYPE') === 'INIT') {
            $status = GetMessage("BP_PREMIUM_STATUS_INIT");
        } else {
            $status = $this->request->getPost("data")['no_agreement_required'] === 'Y' ? GetMessage("BP_PREMIUM_STATUS_NO_APPROVE") : GetMessage("BP_PREMIUM_STATUS_NEW");
        }

        $userFields = [
            "NAME" => $GLOBALS['USER']->GetFullName()." от ".date('d.m.Y'),
            "NO_APPROVER" => $this->request->getPost("data")['no_agreement_required'],
            "STATUS" => $status,
            "PROJECT" => $this->request->getPost("data")['project']
        ];
        if ($this->request->getPost("data")['no_agreement_required'] !== 'Y') {
            $userFields["APPROVER"] = $this->request->getPost("data")['approver'];
        }

        $newFields = [];
        $elementsFieldId = null;
        foreach ($listContFields as $id => $field) {
            $newFields[$id] = $field['CODE'] ? $userFields[$field['CODE']] : $userFields[$id];
            if ($field['CODE'] == 'ELEMENTS')
                $elementsFieldId = $id;
        }
        $newFields["CREATED_BY"] = $this->userID;
        $newFields["MODIFIED_BY"] = $this->userID;
        $newFields["ACTIVE"] = "Y";
        unset($userFields);

        $nameField = "iblock_".$this->iblockContainerId."_".$this->userID."_".time();

        $fieldsContainer = [
            "IBLOCK_TYPE_ID" => "lists",
            "IBLOCK_ID" => $this->iblockContainerId,
            "ELEMENT_CODE" => $nameField,
            "CAN_FULL_EDIT" => "N",
            "CHECK_PERMISSIONS" => "N",
            "FIELDS" => $newFields,
        ];

        try {
            $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fieldsContainer));
            $containerId = $elem->add();
        } catch (\Exception $e) {
            $this->endResponse($e->getMessage());
        }


        $newElements = [];
        $list = new CList($this->iblockElementId);
        $listFields = $list->GetFields();
        
        $users = [];
        $obUsers = Main\UserTable::getList([
            'filter' => [
                'ACTIVE' => 'Y',
                'ID' => array_keys($this->request->getPost("data")["users"])
            ],
            'select' => ['ID', 'NAME', 'LAST_NAME'],
        ]);
        while ($user = $obUsers->fetch()) {
            $users[$user['ID']] = $user;
        }

        foreach ($this->request->getPost("data")["users"] as $userId => $user) {
            $userFields = [
                "NAME" => $users[$userId]['LAST_NAME']." ".$users[$userId]['NAME'],
                "SUMM" => $user['summ'],
                "COMMENT" => $user['comment'],
                "BLOCK" => $containerId,
                "STATUS" => $this->request->getPost("data")['no_agreement_required'] === 'Y' ? GetMessage("BP_PREMIUM_STATUS_NO_APPROVE") : GetMessage("BP_PREMIUM_STATUS_NEW"),
                "USER_ID" => $userId
            ];
            if ($this->request->getPost("data")['no_agreement_required'] !== 'Y') {
                $userFields["APPROVER"] = $this->request->getPost("data")['approver'];
            }

            $newFields = [];
            foreach ($listFields as $id => $field) {
                $newFields[$id] = $field['CODE'] ? $userFields[$field['CODE']] : $userFields[$id];
            }
            $newFields["CREATED_BY"] = $this->userID;
            $newFields["MODIFIED_BY"] = $this->userID;
            $newFields["ACTIVE"] = "Y";
            unset($userFields);

            $nameField = "iblock_".$this->iblockElementId."_".$this->userID."_".$userId."_".time();

            $fields = [
                "IBLOCK_TYPE_ID" => "lists",
                "IBLOCK_ID" => $this->iblockElementId,
                "ELEMENT_CODE" => $nameField,
                "CAN_FULL_EDIT" => "N",
                "CHECK_PERMISSIONS" => "N",
                "FIELDS" => $newFields,
            ];

            try {
                $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fields));
                $newElements[] = $elem->add();
            } catch (\Exception $e) {
                $this->endResponse($e->getMessage());
            }
        }

        $fieldsContainer['FIELDS'][$elementsFieldId] = $newElements;
        try {
            $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fieldsContainer));
            $elem->update();
        } catch (\Exception $e) {
            $this->endResponse($e->getMessage());
        }

        $this->endResponse($containerId);
    }
    
    private function changeStatus()
    {
        Loader::includeModule('lists');
            
        if (empty($this->request->getPost("data")['TYPE']) || empty($this->request->getPost("data")['ID'])) {
            return false;
        }

        $fieldsContainer = [
            "IBLOCK_TYPE_ID" => "lists",
            "ELEMENT_ID" => $this->request->getPost("data")['ID'],
            "IBLOCK_ID" => $this->iblockContainerId,
            "CAN_FULL_EDIT" => "N",
            "CHECK_PERMISSIONS" => "N",
        ];
        $changeArr = [
            'STATUS' => $this->statuses[strtoupper($this->request->getPost("data")['TYPE'])],
            'COMMENT' => $this->request->getPost("data")['COMMENT'],
            'CLOSED' => 'Y'
        ];

        $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fieldsContainer));
        $newFields = $elem->get()[0][$this->request->getPost("data")['ID']];
        foreach ($changeArr as $code => $value) {
            $newFields[$this->fieldsCodes['container'][$code]] = $value;
        }
        $fieldsContainer['FIELDS'] = $newFields;
        $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fieldsContainer));

        $elementsIds = $newFields[$this->fieldsCodes['container']['ELEMENTS']];

        try {
            $elem->update();
        } catch (\Exception $e) {
            $this->endResponse($e->getMessage());
        }

        //По пользователям
        foreach ($elementsIds as $id) {
            $fields = [
                "IBLOCK_TYPE_ID" => "lists",
                "IBLOCK_ID" => $this->iblockElementId,
                "ELEMENT_ID" => $id,
                "CAN_FULL_EDIT" => "N",
                "CHECK_PERMISSIONS" => "N",
            ];
            $changeArr = [
                'STATUS' => $this->statuses[strtoupper($this->request->getPost("data")['TYPE'])],
            ];
            $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fields));
            $newFields = $elem->get()[0][$id];
            foreach ($changeArr as $code => $value) {
                $newFields[$this->fieldsCodes['element'][$code]] = $value;
            }
            $fields['FIELDS'] = $newFields;
            $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fields));
            try {
                $elem->update();
            } catch (\Exception $e) {
                $this->endResponse($e->getMessage());
            }
        }
        $this->addSummToProject($this->request->getPost("data")['SUMM'], $this->request->getPost("data")['PROJECT']);
        
        $this->endResponse($this->request->getPost("data")['ID']);
    }
    
    private function addSummToProject($summ, $projectId)
    {
        if ($summ > 0 && $projectId > 0) {
            $db_props = CIBlockElement::GetProperty($this->iblockProjectsId, $projectId, "sort", "asc", ["CODE"=>"APPROVED_PREMIUM_SUMM"])->fetch();
            $valArr = explode('|', $db_props['VALUE']);
            $valArr[0] = $valArr[0] + $summ;
            $newVal = implode('|', $valArr);
            CIBlockElement::SetPropertyValues($projectId, $this->iblockProjectsId, $newVal, 'APPROVED_PREMIUM_SUMM');
        }
    }

    private function addFile(int $id)
    {
        $file = $_FILES['file'];
        $fileId = 0;
        try {
            if (Loader::includeModule('disk')) {
                $fileToUpload = [
                    'name' => $file['name'],
                    //'type' => 'csv',
                    'size' => $file['size'],
                    'tmp_name' => $file['tmp_name']
                ];
                $driver = Bitrix\Disk\Driver::getInstance();
                $storage = $driver->getStorageByUserId($this->userID);
                $folder = $storage->getFolderForUploadedFiles();
                $newFile = $folder->uploadFile($fileToUpload, array(
                    'NAME' => $fileToUpload['name'],
                    'CREATED_BY' => $this->userID,
                ), array(), true);

                $fileId = $newFile->getId();
            }
        } catch (\Exception $e) {
            $fileId = 0;
        }
        
        $fieldsContainer = [
            "IBLOCK_TYPE_ID" => "lists",
            "ELEMENT_ID" => $id,
            "IBLOCK_ID" => $this->iblockContainerId,
            "CAN_FULL_EDIT" => "N",
            "CHECK_PERMISSIONS" => "N",
        ];
        $changeArr = [
            'FILE' => ['n0'=>'n'.$fileId],
            "STATUS" => $this->request->getPost("STATUS") === 'NO_APPROVE' ? GetMessage("BP_PREMIUM_STATUS_NO_APPROVE") : GetMessage("BP_PREMIUM_STATUS_NEW")
        ];

        $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fieldsContainer));
        $newFields = $elem->get()[0][$id];
        foreach ($changeArr as $code => $value) {
            $newFields[$this->fieldsCodes['container'][$code]] = $value;
        }
        $fieldsContainer['FIELDS'] = $newFields;
        $elem = new Bitrix\Lists\Entity\Element(new Bitrix\Lists\Service\Param($fieldsContainer));
        try {
            $elem->update();
        } catch (\Exception $e) {
            $this->endResponse($e->getMessage());
        }
        
        $this->endResponse($fileId);
    }
    
    private function setFieldsCodes()
    {
        //По контейнеру
        $containerList = new CList($this->iblockContainerId);
        $listContFields = $containerList->GetFields();

        foreach ($listContFields as $id => $field) {
            if (isset($field['CODE']) && !empty($field['CODE'])) {
                $this->fieldsCodes['container'][$field['CODE']] = $id;
            }
        }
        unset($listContFields);
        unset($containerList);
        
        //По элементам
        $list = new CList($this->iblockElementId);
        $listFields = $list->GetFields();

        foreach ($listFields as $id => $field) {
            $this->fieldsCodes['element'][$field['CODE']] = $id;
        }
        unset($listFields);
        unset($list);
    }

    private function getHistory (int $page)
    {
        $statusesFlip = array_flip($this->statuses);
        $approvers = [];
        $history = [];
        $arFilter = [
            'IBLOCK_CODE' => $this->bpListCodeContainer,
            'CREATED_BY' => $this->userID,
        ];
        $projects = [];
        $arSelect = ['ID', 'PROPERTY_APPROVER', 'PROPERTY_NO_APPROVER', 'PROPERTY_STATUS', 'DATE_CREATE', 'PROPERTY_FILE', 'PROPERTY_COMMENT', 'PROPERTY_PROJECT'];
        $histCont = CIBlockElement::GetList(['ID'=>'DESC'], $arFilter, false, Array("nPageSize"=>$this->historyElementsPerPage, "iNumPage"=>intval($page)), $arSelect);
        while ($histEl = $histCont->fetch()) {
            $approvers[$histEl['PROPERTY_APPROVER_VALUE']] = $histEl['PROPERTY_APPROVER_VALUE'];
            $elAr = [
                'STATUS' => $histEl['PROPERTY_STATUS_VALUE'],
                'STATUS_ID' => $statusesFlip[$histEl['PROPERTY_STATUS_VALUE']],
                'APPROVER' => ['ID' => $histEl['PROPERTY_APPROVER_VALUE']],
                'NO_APPROVER' => $histEl['PROPERTY_NO_APPROVER_VALUE'],
                'DATE_CREATE' => $histEl['DATE_CREATE'],
                'COMMENT' => $histEl['PROPERTY_COMMENT_VALUE'],
            ];
            if (!empty($histEl['PROPERTY_FILE_VALUE'][0])) {
                $elAr['FILE'] = $histEl['PROPERTY_FILE_VALUE'][0];
            }
            if (!empty($histEl['PROPERTY_PROJECT_VALUE'])) {
                $elAr['PROJECT'] = $histEl['PROPERTY_PROJECT_VALUE'];
                $projects[$histEl['PROPERTY_PROJECT_VALUE']] = null;
            }
            
            $history['CONTAINERS'][$histEl['ID']] = $elAr;
        }
        unset($histCont);

        if (empty($history['CONTAINERS'])) {
            return null;
        }
        
        $this->getProjectsNames($projects, $history['CONTAINERS']);
        
        $rsUsers = CUser::GetList(($by = "ID"), ($order = "desc"), ["ID" => $approvers], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO']]);
        while ($arUser = $rsUsers->Fetch()) {
            $approvers[$arUser['ID']] = $arUser;
        }
        
        foreach ($history['CONTAINERS'] as &$histCont) {
            $histCont['APPROVER'] = $approvers[$histCont['APPROVER']['ID']];
        }
        unset($approvers);
        
        $histElement = CIBlockElement::GetList([], ['IBLOCK_CODE' => $this->bpListCodeElements, 'PROPERTY_BLOCK' => array_keys($history['CONTAINERS'])], false, false, ['ID', 'PROPERTY_BLOCK', 'PROPERTY_SUMM', 'PROPERTY_COMMENT', 'NAME', 'PROPERTY_USER_ID']);
        while ($histEl = $histElement->fetch()) {
            $elAr = [];
            foreach ($histEl as $elId => $elValue) {
                if (strpos($elId, 'PROPERTY_') !== false && strpos($elId, '_VALUE_ID') === false) {
                    $elAr[substr(substr($elId, 8, $elId.length - 6), 1 , 9)] = $elValue !== false ? $elValue : '';
                } else if (strpos($elId, 'PROPERTY_') === false) {
                    $elAr[$elId] = $elValue !== false ? $elValue : '';
                }
            }
            $history['CONTAINERS'][$histEl['PROPERTY_BLOCK_VALUE']]['ELEMENTS'][] = $elAr;
        }
        unset($histElement);
        
        $history['PAGES'] = $this->getPageCount();
        
        return $history;
    }
    
    private function getApproveHistory (int $page, string $type = null)
    {
        $statusesFlip = array_flip($this->statuses);
        $creators = [];
        $history = [];
        $arFilter = [
            'IBLOCK_CODE' => $this->bpListCodeContainer,
            'PROPERTY_APPROVER' => $this->userID,
            '!PROPERTY_NO_APPROVER' => 'Y'
        ];
        
        if ($type === 'new') {
            $arFilter["!".$this->fieldsCodes['container']['CLOSED']] = 'Y';
        } else {
            $arFilter[$this->fieldsCodes['container']['CLOSED']] = 'Y';
        }
        
        $projects = [];
        $arSelect = ['ID', 'CREATED_BY', 'PROPERTY_APPROVER', 'PROPERTY_NO_APPROVER', 'PROPERTY_STATUS', 'DATE_CREATE', 'PROPERTY_COMMENT', 'PROPERTY_FILE', 'PROPERTY_PROJECT'];
        $histCont = CIBlockElement::GetList([['ID'=>'DESC']], $arFilter, false, ["nPageSize"=>$this->historyElementsPerPage, "iNumPage"=>intval($page)], $arSelect);
        while ($histEl = $histCont->fetch()) {
            $creators[$histEl['CREATED_BY']] = $histEl['CREATED_BY'];
            $elAr = [
                'STATUS' => $histEl['PROPERTY_STATUS_VALUE'],
                'STATUS_ID' => $statusesFlip[$histEl['PROPERTY_STATUS_VALUE']],
                'APPROVER' => ['ID' => $histEl['PROPERTY_APPROVER_VALUE']],
                'NO_APPROVER' => $histEl['PROPERTY_NO_APPROVER_VALUE'],
                'DATE_CREATE' => $histEl['DATE_CREATE'],
                'COMMENT' => $histEl['PROPERTY_COMMENT_VALUE'],
                'CREATED_BY' => $histEl['CREATED_BY'],
            ];
            if (!empty($histEl['PROPERTY_FILE_VALUE'][0])) {
                $elAr['FILE'] = $histEl['PROPERTY_FILE_VALUE'][0];
            }
            if (!empty($histEl['PROPERTY_PROJECT_VALUE'])) {
                $elAr['PROJECT'] = $histEl['PROPERTY_PROJECT_VALUE'];
                $elAr['SUMM'] = 0;
                $projects[$histEl['PROPERTY_PROJECT_VALUE']] = null;
            }
            $history['CONTAINERS'][$histEl['ID']] = $elAr;
        }
        unset($histCont);
        
        $this->getProjectsNames($projects, $history['CONTAINERS']);
        
        if (empty($history['CONTAINERS'])) {
            return null;
        }
        
        $rsUsers = CUser::GetList(($by = "ID"), ($order = "desc"), ["ID" => $creators], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO']]);
        while ($arUser = $rsUsers->Fetch()) {
            $creators[$arUser['ID']] = $arUser;
        }
        
        foreach ($history['CONTAINERS'] as &$histCont) {
            $histCont['CREATOR'] = $creators[$histCont['CREATED_BY']];
            $histCont['CREATOR']['IMG'] = CFile::GetPath($creators[$histCont['CREATED_BY']]['PERSONAL_PHOTO']);
        }
        unset($creators);
        
        $histElement = CIBlockElement::GetList([], ['IBLOCK_CODE' => $this->bpListCodeElements, 'PROPERTY_BLOCK' => array_keys($history['CONTAINERS'])], false, false, ['ID', 'PROPERTY_BLOCK', 'PROPERTY_SUMM', 'PROPERTY_COMMENT', 'NAME', 'PROPERTY_USER_ID']);
        while ($histEl = $histElement->fetch()) {
            $elAr = [];
            foreach ($histEl as $elId => $elValue) {
                if (strpos($elId, 'PROPERTY_') !== false && strpos($elId, '_VALUE_ID') === false) {
                    $elAr[substr(substr($elId, 8, $elId.length - 6), 1 , 9)] = $elValue !== false ? $elValue : '';
                } else if (strpos($elId, 'PROPERTY_') === false) {
                    $elAr[$elId] = $elValue !== false ? $elValue : '';
                }
            }
            $history['CONTAINERS'][$histEl['PROPERTY_BLOCK_VALUE']]['ELEMENTS'][] = $elAr;
            if (isset($history['CONTAINERS'][$histEl['PROPERTY_BLOCK_VALUE']]['SUMM'])) {
                $history['CONTAINERS'][$histEl['PROPERTY_BLOCK_VALUE']]['SUMM'] += $elAr['SUMM'];
            }
        }
        unset($histElement);
        
        $history['PAGES'] = $this->getApprovePageCount();

        return $history;
    }
    
    private function getProjectsNames($projects, &$history)
    {
        if (empty($projects)) return;
        $arFilter = [
            'IBLOCK_ID' => $this->iblockProjectsId,
            'ID' => array_keys($projects),
        ];
        $arSelect = ['ID', 'NAME'];
        $projectsRes = Bitrix\Iblock\ElementTable::GetList([
            'filter' => $arFilter,
            'select' => $arSelect
        ]);
        while ($project = $projectsRes->fetch()) {
            $projects[$project['ID']] = [
                'ID' => $project['ID'],
                'NAME' => $project['NAME'],
                'URL' => '/services/lists/'.$this->iblockProjectsId.'/element/0/'.$project['ID'].'/'
            ];
        }
        
        foreach ($history as &$hist) {
            if (isset($hist['PROJECT'])) {
                $hist['PROJECT'] = $projects[$hist['PROJECT']];
            }
        }
    }

    private function getPageCount()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->iblockContainerId,
            'CREATED_BY' => $this->userID,
        ];
        $arSelect = ['ID'];
        return ceil(Bitrix\Iblock\ElementTable::GetList([
            'filter' => $arFilter,
            'select' => $arSelect
        ])->getSelectedRowsCount() / $this->historyElementsPerPage);
    }
    
    private function getApprovePageCount()
    {
        $iHst = $iNew = 0;
        $arFilter = [
            'IBLOCK_CODE' => $this->bpListCodeContainer,
            'PROPERTY_APPROVER' => $this->userID,
            '!PROPERTY_NO_APPROVER' => 'Y',
        ];
        $arSelect = ['ID', 'PROPERTY_CLOSED'];
        $histCont = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
        while ($histEl = $histCont->fetch()) {
            if ($histEl['PROPERTY_CLOSED_VALUE'] === 'Y') {
                $iHst ++;
            } else {
               $iNew ++; 
            }
        }

        return ['NEW' => ceil($iNew / $this->historyElementsPerPage), 'HISTORY' => ceil($iHst / $this->historyElementsPerPage), 'TOTAL_NEW' => $iNew];
    }
    
    private function getUserProjects()
    {
        $projects = $users = [];
        $arFilter = [
            'IBLOCK_CODE' => $this->bpListCodeProjects,
            'PROPERTY_PROJECT_HEAD' => $this->userID,
            'PROPERTY_PREMIUM_PROJECT_VALUE' => 'Да',
            [
                'LOGIC' => 'OR',
                ['>DATE_ACTIVE_TO' => ConvertTimeStamp(time(),"FULL")],
                ['DATE_ACTIVE_TO' => false]
            ]
        ];
        $arSelect = ['ID', 'NAME', 'PROPERTY_PROJECT_HEAD', 'PROPERTY_PREMIUM_PROJECT', 'PROPERTY_PROJECT_MEMBERS', 'PROPERTY_PREMIUM_APPROVER', 'PROPERTY_PROJECT_BUDGET', 'PROPERTY_APPROVED_PREMIUM_SUMM'];
        $projectRes = CIBlockElement::GetList(['DATE_ACTIVE_FROM'=>'DESC'], $arFilter, false, false, $arSelect);
        while ($project = $projectRes->fetch()) {
            if (isset($projects[$project['ID']])) {
                $projects[$project['ID']]['MEMBERS'][$project['PROPERTY_PROJECT_MEMBERS_VALUE']] = [];
            } else {
                $budget = explode('|', $project['PROPERTY_PROJECT_BUDGET_VALUE']);
                $approved = explode('|', $project['PROPERTY_APPROVED_PREMIUM_SUMM_VALUE']);
                $projects[$project['ID']] = [
                    'ID' => $project['ID'],
                    'NAME' => $project['NAME'],
                    'BUDGET' => [
                        'CURRENCY' => $budget[1],
                        'SUMM' => $budget[0]
                    ],
                    'APPROVED_SUMM' => [
                        'CURRENCY' => $approved[1],
                        'SUMM' => $approved[0]
                    ],
                    'APPROVER' => $project['PROPERTY_PREMIUM_APPROVER_VALUE'],
                    'BUDGET_LEFT' => [
                        'CURRENCY' => $budget[1],
                        'SUMM' => $budget[0] - $approved[0]
                    ],
                    'MEMBERS' => [
                        $project['PROPERTY_PROJECT_MEMBERS_VALUE'] => []
                    ],
                ];
            }
            $users[$project['PROPERTY_PROJECT_MEMBERS_VALUE']] = [];
        }
        //$this->getProjectBudgetLeft($projects);
        
        $rsUsers = CUser::GetList(($by = "ID"), ($order = "desc"), ["ID" => array_keys($users)], ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO']]);
        while ($arUser = $rsUsers->Fetch()) {
            $users[$arUser['ID']] = [
                'ID' => $arUser['ID'],
                'NAME' => $arUser['NAME'].' '.$arUser['LAST_NAME'],
                'IMG' => CFile::GetPath($arUser['PERSONAL_PHOTO'])
            ];
        }
        
        foreach ($projects as &$project) {
            $project['APPROVER'] = $users[$project['APPROVER']];
            $project['MEMBERS'][$this->userID] = [];
            foreach ($project['MEMBERS'] as $id => $member) {
                $project['MEMBERS'][$id] = $users[$id];
            }
        }
        
        return $projects;
    }
    
    private function getProjectBudgetLeft(array &$projects)
    {
        $arFilter = [
            'IBLOCK_CODE' => $this->bpListCodeContainer,
            'PROPERTY_PROJECT' => array_keys($projects),
            '!PROPERTY_CLOSED' => 'Y'
        ];
        $cont = $toApprove = $contSumm = [];
        $arSelect = ['ID', 'PROPERTY_PROJECT'];
        $histCont = CIBlockElement::GetList(['ID'=>'DESC'], $arFilter, false, false, $arSelect);
        while ($histEl = $histCont->fetch()) {
            $cont[$histEl['ID']] = $histEl['PROPERTY_PROJECT_VALUE'];
        }
        
        $histElement = CIBlockElement::GetList([], ['IBLOCK_CODE' => $this->bpListCodeElements, 'PROPERTY_BLOCK' => array_keys($cont)], false, false, ['ID', 'PROPERTY_BLOCK', 'PROPERTY_SUMM']);
        while ($histEl = $histElement->fetch()) {
            $contSumm[$histEl['PROPERTY_BLOCK_VALUE']] += $histEl['PROPERTY_SUMM_VALUE'];
        }
        
        foreach ($cont as $contId => $project) {
            $toApprove[$project] += $contSumm[$contId];
        }
        
        foreach ($projects as &$project) {
            $budget = explode('|', $project['BUDGET']);
            $project['BUDGET_LEFT'] = [
                'CURRENCY' => $budget[1],
                'SUMM' => $budget[0] - explode('|', $project['APPROVED_SUMM'])[0] - $toApprove[$project['ID']]
            ];
        }
    }

    private function endResponse($result)
    {
        //Формирует ответ при ajax запросе
        $GLOBALS['APPLICATION']->RestartBuffer();
        Header('Content-Type: application/x-javascript; charset='.LANG_CHARSET);
        if(!empty($result))
        {
            echo CUtil::PhpToJSObject($result);
        }
        
        die();
    }
}

//Полифил функции из php 7.3
if (!function_exists('array_key_first')) {
    function array_key_first(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }
}