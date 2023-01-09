<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main,
    \Bitrix\Main\Loader,
    \Bitrix\Crm\FieldMultiTable,
    \Bitrix\Main\DB\SqlExpression,
    \Bitrix\Crm\CompanyTable,
    \Bitrix\Crm\LeadTable,
    \Bitrix\Crm\DealTable,
    \Bitrix\Crm\ContactTable,
    \Bitrix\Main\Entity\ReferenceField;

include_once $_SERVER["DOCUMENT_ROOT"].'/local/lib/idna_convert/idna_convert.php';

class InnCheckerComponent extends \CBitrixComponent {
    protected $propNames = ['COMPANY' => '', 'CONTACT' => '', 'LEAD' => '', 'DEAL' => ''];
    protected $propsNamesInFile = [];
    protected $searchData = null;
    protected $error = [];
    protected $arrDif = [];

    protected function init()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) throw new Exception(GetMessage("INN_CHECKER.NO_CRM_INSTALED"));

        $this->propsNamesInFile = $this->arParams['PROP_NAME_IN_FILE'];

        foreach ($this->propNames as $prop => $val) {
            $propConst = 'PROP_'.$prop.'_INN';
            if ($this->arParams['PROP_NAME'][$prop]) {
                $this->propNames[$prop] = $this->arParams['PROP_NAME'][$prop];
            } else if (defined($propConst) && constant($propConst)) {
                $allFieldsCont = array_column($GLOBALS['USER_FIELD_MANAGER']->GetUserFields('CRM_'.$prop), 'XML_ID', 'FIELD_NAME'); //Получаем имена и XML_ID всех пользовательских свойств
                $this->propNames[$prop] = array_search(constant($propConst), $allFieldsCont); //Находим имя свойства по XML_ID - ИНН
                unset($allFieldsCont);
            } else {
                $this->error[] = GetMessage("INN_CHECKER.NO_PROP_NAME_".$prop);
            }
        }

        $innLeadPropertyCode = $arLeadInnProperty['FIELD_NAME'];

        
        if (!empty($this->error)) {
            $this->arResult['ERROR'] = $this->error; 
            throw new Exception(implode('<br>', $this->error));
        } 
        $this->arResult['PROP_NAMES'] = $this->propNames;
        $this->arResult['PROP_NAME_IN_FILE'] = $this->propsNamesInFile;
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

    public function setRequest($request)
    {
        //Устанавливает параметры запроса
        $this->request = $request;
    }
    
    public function ajaxSearch()
    {
        //Поиск сущностей и возвращение полученного результата
        $action = '';
        $result = [];
        if ($this->request->isPost()) $action = $this->request->getPost('ACTION');
        
        if ($action === "SEARCH") {
            $propNames = $this->request->getPost('PROP_NAMES');
            $this->searchData = $this->request->getPost('DATA'); 
            $this->propsNamesInFile = $this->request->getPost('PROP_NAME_IN_FILE');
            
            foreach ($this->propNames as $prop => $val) {
                if (!empty($propNames[$prop])) {
                    $this->propNames[$prop] = $propNames[$prop];
                } else {
                    $this->error[] = GetMessage("INN_CHECKER.NO_PROP_NAME_".$prop);
                }
            }

            //if (!empty($this->error)) $this->endResponse(['ERROR' => $this->error]);
            $this->arrDif = $this->setDiffArray();

            $result["LEAD"] = $this->searchLeads();
            $result["CONTACT"] = $this->searchContacts();
            $result["COMPANY"] = $this->searchCompanyes();
            $result["DEAL"] = $this->searchDeals();
            $this->searchEmailTel($result);
            $this->searchRequisites($result);
            $this->searchSite($result);

            $result["DIFFERENCE"] = [];
            foreach ($this->arrDif as $key => $val) {
                $result["DIFFERENCE"][$key] = array_values($val);
            }
            $this->endResponse($result); 
        }
    }
    
    protected function setDiffArray()
    {
        $res = [];
        $res['INN'] = array_unique($this->searchData[$this->propsNamesInFile['INN']]);
        $res['PHONE'] = $this->trimPhone(array_unique($this->searchData[$this->propsNamesInFile['PHONE']]));
        $res['EMAIL'] = array_unique($this->searchData[$this->propsNamesInFile['EMAIL']]);
        $res['SITE'] = array_unique($this->searchData[$this->propsNamesInFile['SITE']]);
        return $res;
    }

    protected function searchLeads()
    {
        //Лиды
        if (!isset($this->propNames['LEAD']) || empty($this->searchData[$this->propsNamesInFile['INN']])) return false;
        $entityPath = \Bitrix\Main\Config\Option::get('crm', 'path_to_lead_details');
        $arrLeads = [];
        
        $filter = [];
        $select = ['ID', 'TITLE'];
        if ( !empty($this->searchData[$this->propsNamesInFile['INN']]) ) {
            $filter[$this->propNames['LEAD']] = $this->searchData[$this->propsNamesInFile['INN']];
            $fieldName = $this->propNames['LEAD'];
            $select[] = $fieldName;
        }
        
        $obLead = Bitrix\Crm\LeadTable::getList([
            'filter' => $filter,
            'select' => $select,
        ]);

        while ($lead = $obLead->fetch()) {
            $arrLeads[$lead['ID']] = $lead;
            $arrLeads[$lead['ID']]['url'] = \CComponentEngine::MakePathFromTemplate("https://".$_SERVER['SERVER_NAME'].$entityPath, ['lead_id' => $lead['ID']]);
            $arrLeads[$lead['ID']]['TYPE'] = 'INN';
            $arrLeads[$lead['ID']]['FIND'] = $lead[$fieldName];
            unset($this->arrDif['INN'][array_search($lead[$fieldName], $this->arrDif['INN'])]);
        }
        unset($obLead);
        return $arrLeads;
    }

    protected function searchContacts()
    {
        //Контакты
        if (!isset($this->propNames['CONTACT']) || empty($this->searchData[$this->propsNamesInFile['INN']])) return false;
        
        $arrConts = [];
        $filter = [];
        $entityPath = \Bitrix\Main\Config\Option::get('crm', 'path_to_contact_details');
        $select = ['ID', 'NAME'];
        
        if ( !empty($this->searchData[$this->propsNamesInFile['INN']]) ) {
            $filter[$this->propNames['CONTACT']] = $this->searchData[$this->propsNamesInFile['INN']];
            $fieldName = $this->propNames['CONTACT'];
            $select[] = $fieldName;
        } 
        $obCont = Bitrix\Crm\ContactTable::getList([
            'filter' => $filter,
            'select' => $select,
        ]);

        while ($comp = $obCont->fetch()) {
            $arrConts[$comp['ID']] = $comp;
            $arrConts[$comp['ID']]['url'] = \CComponentEngine::MakePathFromTemplate("https://".$_SERVER['SERVER_NAME'].$entityPath, ['contact_id' => $comp['ID']]);
            $arrConts[$comp['ID']]['TYPE'] = 'INN';
            $arrConts[$comp['ID']]['FIND'] = $comp[$fieldName];
            unset($this->arrDif['INN'][array_search($comp[$fieldName], $this->arrDif['INN'])]);
        }
        unset($obCont);
        return $arrConts;
    }
    
    protected function searchCompanyes()
    {
        //Компании
        if (!isset($this->propNames['COMPANY']) || empty($this->searchData[$this->propsNamesInFile['INN']])) return false;
        $arrComps = [];
        $filter = ['LOGIC' => 'OR'];
        $entityPath = \Bitrix\Main\Config\Option::get('crm', 'path_to_company_details');
        $select = ['ID', 'TITLE'];

        if ( !empty($this->searchData[$this->propsNamesInFile['INN']]) ) {
            $filter[] = [$this->propNames['COMPANY'] => $this->searchData[$this->propsNamesInFile['INN']]];
            $fieldName = $this->propNames['COMPANY'];
            $select[] = $fieldName;
        } 
        $obComp = Bitrix\Crm\CompanyTable::getList([
            'filter' => $filter,
            'select' => $select,
        ]);

        while ($comp = $obComp->fetch()) {
            $arrComps[$comp['ID']] = $comp;
            $arrComps[$comp['ID']]['url'] = \CComponentEngine::MakePathFromTemplate("https://".$_SERVER['SERVER_NAME'].$entityPath, ['company_id' => $comp['ID']]);
            $arrComps[$comp['ID']]['TYPE'] = 'INN';
            $arrComps[$comp['ID']]['FIND'] = $comp[$fieldName];
            unset($this->arrDif['INN'][array_search($comp[$fieldName], $this->arrDif['INN'])]);
        }
        unset($obComp);
        return $arrComps;
    }
    
    protected function searchDeals()
    {
        //Сделки
        if (!isset($this->propNames['DEAL']) || empty($this->searchData[$this->propsNamesInFile['INN']])) return false;
        $arrDeals = [];
        $filter = [];
        $entityPath = \Bitrix\Main\Config\Option::get('crm', 'path_to_deal_details');
        $select = ['ID', 'TITLE'];
        
        if ( !empty($this->searchData[$this->propsNamesInFile['INN']]) ) {
            $filter[$this->propNames['DEAL']] = $this->searchData[$this->propsNamesInFile['INN']];
            $fieldName = $this->propNames['DEAL'];
            $select[] = $fieldName;
        } 
        $obDeal = Bitrix\Crm\DealTable::getList([
            'filter' => $filter,
            'select' => $select,
        ]);

        while ($comp = $obDeal->fetch()) {
            $arrDeals[$comp['ID']] = $comp;
            $arrDeals[$comp['ID']]['url'] = \CComponentEngine::MakePathFromTemplate("https://".$_SERVER['SERVER_NAME'].$entityPath, ['deal_id' => $comp['ID']]);
            $arrDeals[$comp['ID']]['TYPE'] = 'INN';
            $arrDeals[$comp['ID']]['FIND'] = $comp[$fieldName];
            unset($this->arrDif['INN'][array_search($comp[$fieldName], $this->arrDif['INN'])]);
        }
        unset($obDeal);
        return $arrDeals;
    }
    
    protected function searchRequisites(&$result)
    {
        //Реквизиты
        $typeMap = [
            3 => 'CONTACT',
            4 => 'COMPANY'
        ];
        if (empty($this->searchData[$this->propsNamesInFile['INN']])) return;
        $obReq = Bitrix\Crm\RequisiteTable::getList([
            'filter' => ["RQ_INN" => $this->searchData[$this->propsNamesInFile['INN']]],
            'select' => ['ID', 'ENTITY_TYPE_ID', 'ENTITY_ID', 'COMPANY.TITLE', 'CONTACT.NAME', 'RQ_INN'],
            'runtime' => [
                new Main\Entity\ReferenceField('COMPANY', Bitrix\Crm\CompanyTable::getEntity(), array(
                    '=this.ENTITY_ID' => 'ref.ID'
                )),
                new Main\Entity\ReferenceField('CONTACT', Bitrix\Crm\ContactTable::getEntity(), array(
                    '=this.ENTITY_ID' => 'ref.ID'
                ))
            ],
        ]);

        while ($req = $obReq->fetch()) {
            $entity = [
                'TYPE' => 'INN_REQ', 
                'ID' => $req['ENTITY_ID'], 
                'ENTITY_ID' => $typeMap[$req['ENTITY_TYPE_ID']], 
                'TITLE' => $req['CRM_REQUISITE_'.$typeMap[$req['ENTITY_TYPE_ID']].'_TITLE'] ? $req['CRM_REQUISITE_'.$typeMap[$req['ENTITY_TYPE_ID']].'_TITLE'] : $req['CRM_REQUISITE_'.$typeMap[$req['ENTITY_TYPE_ID']].'_NAME'],
                'url' => "https://".$_SERVER['SERVER_NAME']."/crm/".strtolower($typeMap[$req['ENTITY_TYPE_ID']])."/details/".$req['ENTITY_ID']."/",
                'FIND' => $req['RQ_INN']
            ];
            $result[$typeMap[$req['ENTITY_TYPE_ID']]][] = $entity;
            unset($this->arrDif['INN'][array_search($req['RQ_INN'], $this->arrDif[$typeMap[$req['ENTITY_TYPE_ID']]])]);
        }
    }
    
    protected function searchEmailTel(&$result)
    {
        if((!empty($this->searchData[$this->propsNamesInFile['PHONE']]) || !empty($this->searchData[$this->propsNamesInFile['EMAIL']])))
        {
            if(!empty($this->searchData[$this->propsNamesInFile['PHONE']])) {
                $arFilter[] = ['TYPE' => 'PHONE', 'VALUE' => $this->trimPhone($this->searchData[$this->propsNamesInFile['PHONE']])];
            }
            
            if(!empty($this->searchData[$this->propsNamesInFile['EMAIL']])) {
                $arFilter[] = ['TYPE' => 'EMAIL', 'VALUE' => $this->searchData[$this->propsNamesInFile['EMAIL']]];
            }
            
            if(count($arFilter) > 1) {
                $arFilter['LOGIC'] = 'OR';
            }

            $obFieldMulti = Bitrix\Crm\Integrity\DuplicateCommunicationMatchCodeTable::getList([
                'filter' => $arFilter,
                'select' => ['ID', 'TYPE', 'VALUE', 'ENTITY_TYPE_ID', 'ENTITY_ID', 'COMPANY.TITLE', 'LEAD.TITLE', 'DEAL.TITLE', 'CONTACT.NAME'],
                'runtime' => [
                    new Main\Entity\ReferenceField('COMPANY', Bitrix\Crm\CompanyTable::getEntity(), array(
                        '=this.ENTITY_ID' => 'ref.ID',
                        CCrmOwnerType::ResolveID('COMPANY') => 'this.ENTITY_TYPE_ID'
                    )),
                    new Main\Entity\ReferenceField('LEAD', Bitrix\Crm\LeadTable::getEntity(), array(
                        '=this.ENTITY_ID' => 'ref.ID',
                        CCrmOwnerType::ResolveID('LEAD') => 'this.ENTITY_TYPE_ID'  
                    )),
                    new Main\Entity\ReferenceField('DEAL', Bitrix\Crm\DealTable::getEntity(), array(
                        '=this.ENTITY_ID' => 'ref.ID',
                        CCrmOwnerType::ResolveID('DEAL') => 'this.ENTITY_TYPE_ID'  
                    )),
                    new Main\Entity\ReferenceField('CONTACT', Bitrix\Crm\ContactTable::getEntity(), array(
                        '=this.ENTITY_ID' => 'ref.ID',
                        CCrmOwnerType::ResolveID('CONTACT') => 'this.ENTITY_TYPE_ID'  
                    ))
                ],
            ]);
            //найдем crm сущности по email и телефону
            while($arFieldMulti = $obFieldMulti->Fetch()) {
                $entity = ['test' => $this->arrDif[$arFieldMulti['TYPE']],
                    'TYPE' => $arFieldMulti['TYPE'], 
                    'ID' => $arFieldMulti['ENTITY_ID'], 
                    'ENTITY_ID' => $arFieldMulti['ENTITY_TYPE_ID'], 
                    'TITLE' => $arFieldMulti['CRM_INTEGRITY_DUPLICATE_COMMUNICATION_MATCH_CODE_'.CCrmOwnerType::ResolveName($arFieldMulti['ENTITY_TYPE_ID']).'_TITLE'] 
                                ? $arFieldMulti['CRM_INTEGRITY_DUPLICATE_COMMUNICATION_MATCH_CODE_'.CCrmOwnerType::ResolveName($arFieldMulti['ENTITY_TYPE_ID']).'_TITLE'] 
                                : $arFieldMulti['CRM_INTEGRITY_DUPLICATE_COMMUNICATION_MATCH_CODE_'.CCrmOwnerType::ResolveName($arFieldMulti['ENTITY_TYPE_ID']).'_NAME'],
                    'url' => "https://".$_SERVER['SERVER_NAME']."/crm/".strtolower(CCrmOwnerType::ResolveName($arFieldMulti['ENTITY_TYPE_ID']))."/details/".$arFieldMulti['ENTITY_ID']."/",
                    'FIND' => $arFieldMulti['VALUE'],
                ];
                $result[CCrmOwnerType::ResolveName($arFieldMulti['ENTITY_TYPE_ID'])][] = $entity;
                unset($this->arrDif[$arFieldMulti['TYPE']][array_search($arFieldMulti['VALUE'], $this->arrDif[$arFieldMulti['TYPE']])]);
            }
        }
    }
    
    protected function searchSite(&$result) {

        if(!empty($this->searchData[$this->propsNamesInFile['SITE']]))
        {
            $sites = $this->searchData[$this->propsNamesInFile['SITE']];
            
            $hosts = $filter = [];
            
            $idn = new idna_convert(['idn_version' => 2008]);

            foreach($sites as $key => $site) {
                $hostTmp = '';
                $host = $this->getHost($site);
                if(!empty($host)) {
                    $hosts[$key] = $host;

                    $filter['%VALUE'][] = $host;

                    if(stripos($host, 'xn--') !== false) {
                        $filter['%VALUE'][] = $idn->decode($host);
                    } else {
                        $hostTmp = $idn->encode($host);
                        if($hostTmp != $host) {
                            $filter['%VALUE'][] = $hostTmp;
                        }
                    }
                }
            }

            if(!empty($filter)) {
                
                $filter['%VALUE'] = array_unique($filter['%VALUE']);
                $filter['TYPE_ID'] = 'WEB';
                $filter['ENTITY_ID'] = ['COMPANY', 'CONTACT', 'LEAD', 'DEAL'];

                $sites = FieldMultiTable::getList([
                    'filter' => $filter,
                    'select' => [
                        'ENTITY_ID',
                        'ELEMENT_ID',
                        'VALUE',
                        'COMPANY_TITLE' => 'COMPANY.TITLE',
                        'LEAD_TITLE' => 'LEAD.TITLE',
                        'DEAL_TITLE' => 'DEAL.TITLE',
                        'CONTACT_NAME' => 'CONTACT.NAME'
                    ],
                    'runtime' => [
                        new ReferenceField('COMPANY', CompanyTable::getEntity(), array(
                            '=this.ELEMENT_ID' => 'ref.ID',
                            'this.ENTITY_ID' => new SqlExpression('?s', 'COMPANY')
                        )),
                        new ReferenceField('LEAD', LeadTable::getEntity(), array(
                            '=this.ELEMENT_ID' => 'ref.ID',
                            'this.ENTITY_ID' => new SqlExpression('?s', 'LEAD')
                        )),
                        new ReferenceField('DEAL', DealTable::getEntity(), array(
                            '=this.ELEMENT_ID' => 'ref.ID',
                            'this.ENTITY_ID' => new SqlExpression('?s', 'DEAL')
                        )),
                        new ReferenceField('CONTACT', ContactTable::getEntity(), array(
                            '=this.ELEMENT_ID' => 'ref.ID',
                            'this.ENTITY_ID' => new SqlExpression('?s', 'CONTACT')
                        ))
                    ],
                ]);

                while ($site = $sites->fetch()) {

                    $entityCode = $site['ENTITY_ID'];
                    $entityId = \CCrmOwnerType::ResolveName($entityCode);
                    
                    $entity = [
                        'ID' => $site['ELEMENT_ID'],
                        'url' => "https://".$_SERVER['SERVER_NAME']."/crm/".strtolower($entityCode)."/details/".$site['ELEMENT_ID']."/",
                        'FIND' => $site['VALUE'],
                        'TYPE' => 'SITE'
                    ];

                    switch ($site['ENTITY_ID']) {
                        case 'CONTACT':
                            $entity['NAME'] = $site['CONTACT_NAME'];
                            break;
                        default:
                            $entity['TITLE'] = $site[$site['ENTITY_ID'] . '_TITLE'];
                            break;
                    }
                    
                    $entity['ENTITY_ID'] = $entityId;
                    $result[$entityCode][] = $entity;

                    if(!empty($this->arrDif['SITE'])) {
                        foreach($hosts as $host) {
                            if(
                                strpos($site['VALUE'], $host) !== false ||
                                strpos($site['VALUE'], $idn->decode($host)) !== false ||
                                strpos($site['VALUE'], $idn->encode($host)) !== false
                            ) {
                                $findHosts[] = $host;
                                break;
                            }
                        }
                    }
                }
                
                if(!empty($this->arrDif['SITE']) && !empty($findHosts)) {                    
                    foreach($this->arrDif['SITE'] as $key => $site) {
                        foreach($findHosts as $host) {
                            if(
                                strpos($site, $host) !== false ||
                                strpos($site, $idn->decode($host)) !== false ||
                                strpos($site, $idn->encode($host)) !== false
                            ) {
                                unset($this->arrDif['SITE'][$key]);
                            }
                        }
                    }                    
                }
            }
        }
    }
    
    protected function getHost($url)
    {
        if(!empty($url)) {
            $host = parse_url($url, PHP_URL_HOST);

            if(empty($host)) {
                if(strpos($url, '/') !== false) {
                    $host = explode('/', $url)[0];
                } else {
                    $host = $url;
                }
            }

            $host = substr($host, 0, strripos($host, '.') + 1);
            
            return str_replace('www.', '', $host);
        }
        return false;
    }
    
    protected function trimPhone($arPhones)
    {
        $search = ['+', ' ', '(', ')', '-'];
        if (is_array($arPhones)) {
            $trimed = [];
            foreach ($arPhones as $phone) {
                $trimed[] = str_replace($search, '', $phone);
            }
        } else {
           $trimed = str_replace($search, '', $arPhones); 
        }
        
        return $trimed;
    }

    public function endResponse($result)
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