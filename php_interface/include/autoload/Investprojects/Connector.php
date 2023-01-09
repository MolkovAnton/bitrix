<?
namespace Investprojects;

use \Bitrix\Main\Config\Option,
    \Bitrix\Main\Loader,
    \Bitrix\Lists\Entity\Element,
    \Bitrix\Crm\CompanyTable,
    \Bitrix\Crm\RequisiteTable,
    \Bitrix\Socialservices\Properties\Client,
    \Bitrix\Crm\FieldMultiTable,
    \Bitrix\Lists\Service\Param;

class Connector
{
    private $url;
    private $token;
    private $iblockId;
    private $listFields;
    private $memberRole = [
        'Инвестор' => 'project_member_investor',
        'Генеральный проектировщик' => 'project_member_main_developer',
        'Генеральный подрядчик' => 'project_member_contractor',
        'Проектировщик' => 'project_member_developer'
    ];

    const IBLOCK_CODE = 'INVESTPROJECTS';
    const INN_PROP_XML = 'CRM_COMPANY_INN';

    public function __construct(string $url = '')
    {
        if (empty($url)) {
            $this->url = Option::get('askaron.settings', 'UF_INVESTPROJECTS_URL');
        } else {
            $this->url = $url;
        }
        if (empty($this->url)) {
            throw new \Error('Investprojects url is empty');
        }
        
        $this->token = Option::get('askaron.settings', 'UF_INVESTPROJECTS_TOKEN');
        if (empty($this->token)) {
            throw new \Error('Investprojects token is empty');
        }
        
        Loader::includeModule('lists');
        $this->iblockId = \CIBlock::getList([], [
            "CHECK_PERMISSIONS" => "N",
            "=CODE" => self::IBLOCK_CODE
        ])->fetch()['ID'];
        if (!$this->iblockId > 0) {
            throw new \Error('No list for projects');
        }
        
        $this->listFields = (new \CList($this->iblockId))->GetFields();
    }
    
    private function sendRequest(array $query, string $action)
    {
        $curl = curl_init();
        curl_setopt_array($curl, 
            array(
                CURLOPT_URL => $this->url.$action.'?'.http_build_query($query),
                CURLOPT_RETURNTRANSFER => true,
            )
        );
        $requestResult = curl_exec($curl);
        $result = json_decode($requestResult, true);
        curl_close($curl);

        return $result;
    }
    
    public function addProjects(array $ids, $additional = false) {
        $listElements = [];
        foreach ($ids as $id) {
            $error = false;
            $projectRaw = $this->sendRequest(['api_key' => $this->token, 'id' => $id], 'project/info');
            switch ($projectRaw['status']) {
                case 404:
                    $listElements[$id] = ['ERROR' => 'Проект не найден'];
                    $error = true;
                    break;
                case 403:
                    $listElements[$id] = ['ERROR' => 'Проблема с лицензией'];
                    $error = true;
                    break;
                case 401:
                    $listElements[$id] = ['ERROR' => 'Неуспешная авторизация'];
                    $error = true;
                    break;
            }
            if ($error) continue;
            
            $project = $projectRaw['project'];
            $fields = $this->makeElementFields($project, $additional);
            $param = new Param([
                'IBLOCK_ID' => $this->iblockId,
                'IBLOCK_TYPE_ID' => 'lists',
                'ELEMENT_CODE' => 'investprojects_'.$project['project_id'],
                'FIELDS' => $fields
            ]);
            $listElement = new Element($param);

            if ($listElement->isExist()) {
                $listElement->update();
                $listElements[$id] = $listElement->hasErrors() ? ['ERROR' => implode(' | ', $listElement->getErrors())] : 'Update';
            } else {
                $listElement->add();
                $listElements[$id] = $listElement->hasErrors() ? ['ERROR' => implode(' | ', $listElement->getErrors())] : 'Add';
            }
        }
        
        return $listElements;
    }
    
    private function makeElementFields(array $project, $additional = false) {
        $fields = [
            'NAME' => $project['project_name']
        ];
        foreach ($project as $propName => $prop) {
            if ($propName === 'project_status') {
                $statuses = [];
                foreach ($prop as $innerProp) {
                    $statuses[] = $innerProp['name'];
                }
                $project['project_status_name'] = $statuses;
            } else if ($propName === 'project_products') {
                $products = [];
                foreach ($prop as $innerProp) {
                    $products[] = $innerProp['okpd_code'];
                }
                $project['project_products_okpd_code'] = $products;
            } else if ($propName === 'project_addtime' || $propName === 'project_last_update') {
                $project[$propName] = date('d.m.Y H:i:s', $prop);
            } else if ($propName === 'project_location') {
                foreach ($prop as $innerPropName => $innerProp) {
                    $project[$propName.'_'.$innerPropName] = $innerProp;
                }
            } else if ($propName === 'project_members') {
                $members = $this->addCompanys($project['project_members'], $additional);
                foreach ($members as $role => $companys) {
                    $project[$role] = $companys;
                }
            }
        }

        foreach ($this->listFields as $field) {
            if (isset($project[$field['CODE']])) {
                $fields[$field['FIELD_ID']] = $project[$field['CODE']];
            }
        }
        
        return $fields;
    }
    
    private function createSubProjects(array $companys) {
        foreach ($companys as $member => $company) {
            $projects = $this->sendRequest(['api_key' => $this->token, 'id' => $member], 'company/info')['company']['company_projects']['projects'];
            
            foreach ($projects as $project) {
                $fieldsRaw = [
                    'project_name' => $project['name'],
                    'project_id' => $project['id'],
                    'project_status_name' => $project['stage_name'],
                    'project_location_region' => $project['region_name'],
                    $this->memberRole[$project['role']] => $company,
                    'project_investments' => $project['investments'],
                ];
                $param = new Param([
                    'IBLOCK_ID' => $this->iblockId,
                    'IBLOCK_TYPE_ID' => 'lists',
                    'ELEMENT_CODE' => 'investprojects_'.$project['id'],
                    'FIELDS' => $this->makeElementFields($fieldsRaw)
                ]);
                $listElement = new Element($param);

                if ($listElement->isExist()) {
                    $listElement->update();
                } else {
                    $listElement->add();
                }
            }
        }
    }

    private function addCompanys(array $companys, $additional = false) {
        Loader::includeModule('crm');
        $inns = $found = $members = [];
        
        foreach ($companys as $id => $company) {
            $inns[$company['member_company_inn']] = [
                'ID' => $id,
                'ROLE' => isset($this->memberRole[$company['member_name']]) ? $this->memberRole[$company['member_name']] : 'project_companys'
            ];
        }
        
        //Поиск компаний по реквизитам
        $obRequisites = RequisiteTable::getList([
            'filter' => ['ENTITY_TYPE_ID' => \CCrmOwnerType::Company, 'RQ_INN' => array_keys($inns)],
            'select' => ['ID', 'RQ_INN', 'ENTITY_ID', 'ENTITY_TYPE_ID']
        ]);
        while($arRequisite = $obRequisites->Fetch()) {
            $found[$inns[$arRequisite['RQ_INN']]['ROLE']][] = $arRequisite['ENTITY_ID'];
            $memberKey = $inns[$arRequisite['RQ_INN']]['ID'];
            $members[$companys[$memberKey]['member_id']] = $arRequisite['ENTITY_ID'];
            unset($inns[$arRequisite['RQ_INN']]);
        }
        
        //Поиск компаний по пользовательскому свойству
        $entityInnPropCode = \IteesFunctions::getUserTypeEntityCodeByXml(self::INN_PROP_XML, 'CRM_COMPANY');
        $obentity = CompanyTable::getList([
            'filter' => [$entityInnPropCode => array_keys($inns)],
            'select' => ['ID', $entityInnPropCode]
        ]);
        while($arEntity = $obentity->Fetch()) {
            $found[$inns[$arEntity[$entityInnPropCode]]['ROLE']][] = $arEntity['ID'];
            $memberKey = $inns[$arEntity[$entityInnPropCode]]['ID'];
            $members[$companys[$memberKey]['member_id']] = $arEntity['ID'];
            unset($inns[$arEntity[$entityInnPropCode]]);
        }
        
        if (!empty($inns)) {
            foreach ($inns as $comp) {
                $crmCompanyId = $this->createCompany($companys[$comp['ID']]);
                $found[$comp['ROLE']][] = $crmCompanyId;
                $members[$companys[$comp['ID']]['member_id']] = $crmCompanyId;
            }
        }

        if ($additional) $this->createSubProjects($members);

        return $found;
    }
    
    private function createCompany(array $fields) {
        $companyFields = [
            'TITLE' => $fields['member_company_name'],
            'FM' => [
                'EMAIL' => [
                    'n1' => [
                        'VALUE_TYPE' => 'WORK',
                        'VALUE' => $fields['member_company_email']
                    ]
                ],
                'PHONE' => [
                    'n1' => [
                        'VALUE_TYPE' => 'WORK',
                        'VALUE' => $fields['member_company_phone']
                    ]
                ],
                'SITE' => [
                    'n1' => [
                        'VALUE_TYPE' => 'WORK',
                        'VALUE' => $fields['member_company_site']
                    ]
                ]
            ],
            /*'ADDRESS_CITY' => $fields['member_company_address']['city'],
            'ADDRESS' => $fields['member_company_address']['street']['name'],
            'ADDRESS_2' => $fields['member_company_address']['house'].' '.$fields['member_company_address']['flat'].' '.$fields['member_company_address']['corpus'],
            'ADDRESS_POSTAL_CODE' => $fields['member_company_address']['index'],
            'ADDRESS_REGION' => $fields['member_company_address']['region'],
            'ADDRESS_PROVINCE' => $fields['member_company_address']['fed_district'],
            'ADDRESS_COUNTRY' => $fields['member_company_address']['country'],*/
        ];
        $entity = new \CCrmCompany(false);
        $companyId = $entity->Add($companyFields);
        
        $this->createContacts(array_merge($fields['member_contacts'], $fields['member_contacts_group']), $companyId);
        
        $requisiteArr = (new Client())->getByInn($fields['member_company_inn']);
        \IteesFunctions::addRequisiteToCompany($requisiteArr, $companyId);
        return $companyId;
    }
    
    private function createContacts(array $contacts, int $companyId) {
        $searchEmails = $searchPhones = [];
        foreach ($contacts as $key => $contact) {
            $emailArr = $this->prepaireFM($contact['contact_email'], 'EMAIL');
            $phoneArr = $this->prepaireFM($contact['contact_phone'], 'PHONE');
            foreach ($emailArr as $email) {
                $searchEmails[$email['VALUE']] = $key;
            }
            foreach ($phoneArr as $phone) {
                $searchPhones[$phone['VALUE']] = $key;
            }
        }
        $contactsRes = FieldMultiTable::getList([
            'filter' => [
                'ENTITY_ID' => 'CONTACT',
                'VALUE_TYPE' => 'WORK',
                [
                    'LOGIC' => 'OR',
                    ['TYPE_ID' => 'EMAIL', 'VALUE' => array_keys($searchEmails)],
                    ['TYPE_ID' => 'PHONE', 'VALUE' => array_keys($searchPhones)],
                ]
            ],
            'select' => ['ID', 'ELEMENT_ID', 'TYPE_ID']
        ]);
        while ($cont = $contactsRes->fetch()) {
            if ($cont['TYPE_ID'] === 'EMAIL' && isset($searchEmails[$cont['VALUE']])) {
                unset($contacts[$searchEmails[$cont['VALUE']]]);
            } else if ($cont['TYPE_ID'] === 'PHONE' && isset ($searchPhones[$cont['VALUE']])) {
                unset($contacts[$searchPhones[$cont['VALUE']]]);
            }
        }
        
        $entity = new \CCrmContact(false);
        foreach ($contacts as $contact) {
            $fields = [
                'FM' => [
                    'EMAIL' => $this->prepaireFM($contact['contact_email'], 'EMAIL'),
                    'PHONE' => $this->prepaireFM($contact['contact_phone'], 'PHONE'),
                ],
                'NAME' => $contact['contact_fio'],
                'COMPANY_ID' => $companyId,
                'POST' => $contact['contact_position']
            ];
            $entity->Add($fields);
        }
    }
    
    private function prepaireFM($fm, string $type = 'PHONE') {
        if (!is_string($fm)) return;
        
        $fmArray = explode(',', $fm);
        $result = [];
        foreach ($fmArray as $key => $rawfm) {
            $result['n'.$key] = [
                'VALUE_TYPE' => 'WORK',
                'VALUE' => $type === 'PHONE' ? preg_replace("/[^0-9\#\*,;]/i", "", $rawfm) : $rawfm
            ];
        }
        
        return $result;
    }
}