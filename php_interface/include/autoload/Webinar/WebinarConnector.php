<?
namespace Webinar;

use \Bitrix\Main\Config\Option,
    \Bitrix\Main\UserTable,
    \Bitrix\Main\Loader,
    \Bitrix\Main\UserFieldTable,
    \Bitrix\Crm\StatusTable,
    \Bitrix\Crm\FieldMultiTable;

class WebinarConnector
{
    private $url;
    private $token;
    private $fieldsMap = [
        'name' => 'NAME',
        'access' => 'PROPERTY_ACCESS_LEVEL',
        'description' => 'PROPERTY_OPISANIE',
        'password ' => '',
        'isEventRegAllowed' => '',
        'startsAt' => 'PROPERTY_DATA_I_VREMYA_NACH',
        'endsAt' => 'PROPERTY_DATA_I_VREMYA_ZAVERSHENIYA',
        'timezone' => '',
        'type' => '',
        'lang' => '',
        'urlAlias' => '',
        'lectorIds' => 'PROPERTY_VEDUSHCHIY',
        'tags' => '',
        'duration' => '',
        'ownerId' => '',
        'defaultRemindersEnabled' => ''
        
    ];
    private $contactFields;

    public function __construct(string $webinarUrl = '')
    {
        if (empty($webinarUrl)) {
            $this->url = Option::get('askaron.settings', 'UF_WEBINAR_URL');
        } else {
            $this->url = $webinarUrl;
        }
        
        if (empty($this->url)) {
            \IteesFunctions::sendMessageToChat($this->getDevChatId(), 'Ошибка создания вебинара. Не задан url webinator.ru');
            throw new Error('Webinar url is empty');
        }
        $this->token = Option::get('askaron.settings', 'UF_WEBINAR_TOKEN');
        if (empty($this->token)) {
            \IteesFunctions::sendMessageToChat($this->getDevChatId(), 'Ошибка создания вебинара. Не задан токен авторизации webinator.ru');
            throw new Error('Webinar token is empty');
        }
    }
    
    private function sendRequest(array $query, string $action, string $method = "POST")
    {
        $curl = curl_init();
        curl_setopt_array($curl, 
            array(
                CURLOPT_URL => $this->url.$action.($method === 'GET' ? '?'.http_build_query($query) : ''),
                CURLOPT_HTTPHEADER => array('Content-type: application/x-www-form-urlencoded', 'x-auth-token: '.$this->token),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => http_build_query($query)
            )
        );
        $requestResult = curl_exec($curl);
        $result = json_decode($requestResult, true);
        curl_close($curl);

        return $result;
    }
    
    public function addEventFromBizproc(array $documentId)
    {
        $webinarFields = $this->getFieldsFromBizprocDocument($documentId);
        $result = $this->addEvent($webinarFields);
        return $result;
    }

    public function addEvent(array $eventFields)
    {
        $event = $this->sendRequest($eventFields, 'events');
        if (!empty($event['error'])) {
            \IteesFunctions::sendMessageToChat($this->getDevChatId(), 'Ошибка создания вебинара. '.$event['error']['message']);
            throw new \Error('Ошибка создания вебинара. '.$event['error']['message']);
        }
        $session = $this->sendRequest($eventFields, 'events/'.$event['eventId'].'/sessions');
        if (!empty($session['error'])) {
            \IteesFunctions::sendMessageToChat($this->getDevChatId(), 'Ошибка создания вебинара. '.$session['error']['message']);
            throw new \Error('Ошибка создания вебинара. '.$session['error']['message']);
        }
        
        return $session;
    }
    
    public function getMembers(array $params = [])
    {
        $members = $this->sendRequest($params, 'organization/members', 'GET');
        return $members;
    }


    private function getDevChatId()
    {
        return (int)Option::get('askaron.settings', 'UF_DEV_CHAT_ID');
    }
    
    private function getFieldsFromBizprocDocument(array $documentId)
    {
        $webinarFields = [];
        Loader::includeModule($documentId[0]);

		$document = $documentId[1]::getDocument($documentId[2]);

        if(!empty($document)) {
            foreach ($this->fieldsMap as $webinarField => $documentField) {
                $this->addFieldToWebinar($webinarField, $document[$documentField], $webinarFields);
            }
        }
        return $webinarFields;
    }
    
    private function addFieldToWebinar(string $webinarField, $documentField, &$webinarFields)
    {
        switch ($webinarField) {
            case 'access':
                $value = trim(substr(array_values($documentField)[0], 0, 2));
                break;
            case 'startsAt':
            case 'endsAt':
                $date = new \DateTimeImmutable($documentField);
                $value = [
                    'date' => [
                        'year' => (int)$date->format('Y'),
                        'month' => (int)$date->format('m'),
                        'day' => (int)$date->format('d')
                    ],
                    'time' => [
                        'hour' => (int)$date->format('H'),
                        'minute' => (int)$date->format('i')
                    ]
                ];
                break;
            case 'type':
                $value = 'webinar';
                break;
            case 'lectorIds':
                $value = $this->getLectorsIdByEmail($documentField);
                break;
            default :
                $value = !empty($documentField) ? $documentField : null;
        }
        if ($value !== null) {
            $webinarFields[$webinarField] = $value;
        }
    }
    
    private function getLectorsIdByEmail(array $employees = null)
    {
        if (!is_array($employees))
            return null;
        
        $employeesId = $employeeEmails = $webinarId = [];
        foreach ($employees as $employee) {
            $employeesId[] = substr($employee, 5);
        }
        $emlRes = UserTable::getList([
            'filter' => ['ID' => $employeesId],
            'select' => ['EMAIL']
        ]);
        while ($eml = $emlRes->fetch()) {
            $employeeEmails[] = $eml['EMAIL'];
        }
        
        $webinarUsers = $this->getMembers();
        foreach ($webinarUsers as $webinarUser) {
            if (in_array($webinarUser['email'], $employeeEmails)) {
                $webinarId[] = $webinarUser['id'];
            }
        }
        
        return $webinarId;
    }
    
    public function getParticipations(array $documentId)
    {
        $document = $this->getWebinarIdFromDocument($documentId);
        $webinarId = $document['PROPERTY_WEBINAR_ID'];
        if (empty($webinarId)) {
            throw new \Error('Не указан id мероприятия');
        }
        $total = '';
        $pageNum = 1;
        $pagination = [
            'perPage' => 250,
            'page' => $pageNum
        ];
        
        $this->contactFields = $this->prepareContactFieldsNames();
        
        $result = $this->sendRequest($pagination, "eventsessions/$webinarId/participations", 'GET');
        while (!empty($result)) {
            if (isset($result['error'])) {
                throw new \Error($result['error']['message']);
            } else {
               $pageNum++;
                $pagination['page'] = $pageNum;
                foreach ($result as $user) {
                    if ($user['agreementStatus'] === 'AGREED') {
                        $res = $this->addContact($user, $document['ID']);
                        $total .= $user['email'].' - '.$res.' | ';
                    }
                }
                $result = $this->sendRequest($pagination, "eventsessions/$webinarId/participations", 'GET'); 
            }
        }

        return $total;
    }
    
    private function getWebinarIdFromDocument(array $documentId)
    {
        Loader::includeModule($documentId[0]);
		return $documentId[1]::getDocument($documentId[2]);
    }
    
    private function addContact(array $user, int $eventId)
    {
        Loader::includeModule('crm');
        $entity = new \CCrmContact(false);
        $result = false;
        
        $contactId = FieldMultiTable::getList([
            'filter' => ['ENTITY_ID' => 'CONTACT', 'TYPE_ID' => 'EMAIL', 'VALUE_TYPE' => 'WORK', 'VALUE' => $user['email']],
            'limit' => 1
        ])->fetch()['ELEMENT_ID'];
        
        $newFields = [
            $this->contactFields['EVENT_REGISTERED'] => [$eventId],
            $this->contactFields['EVENT_VISITED'] => $user['visited'] ? [$eventId] : []
        ];
        if ($contactId > 0) {
            $contact = \CCrmContact::GetList([], ['ID' => $contactId, 'CHECK_PERMISSIONS' => 'N'], array_keys($newFields))->fetch();
            $contact['ID'] = $contactId;
            $eventRegistered = !empty($contact[$this->contactFields['EVENT_REGISTERED']]) ? array_unique(array_merge($contact[$this->contactFields['EVENT_REGISTERED']], $newFields[$this->contactFields['EVENT_REGISTERED']])) : $newFields[$this->contactFields['EVENT_REGISTERED']];
            $eventVisiteed = !empty($contact[$this->contactFields['EVENT_VISITED']]) ? array_unique(array_merge($contact[$this->contactFields['EVENT_VISITED']], $newFields[$this->contactFields['EVENT_VISITED']])) : $newFields[$this->contactFields['EVENT_VISITED']];
            $contact[$this->contactFields['EVENT_REGISTERED']] = $eventRegistered;
            $contact[$this->contactFields['EVENT_VISITED']] = $eventVisiteed;
            $result = $entity->Update($contactId, $contact, false, true, ['IS_SYSTEM_ACTION' => true]) ? $contactId : 'error';
        } else {
            $mainFields = [
                'NAME' => $user['name'] ?: $user['email'],
                'LAST_NAME' => $user['secondName'],
                'FM' => [
                    'EMAIL' => [
                        'n1' => [
                            'VALUE_TYPE' => 'WORK',
                            'VALUE' => $user['email']
                        ]
                    ]
                ]
            ];
            $result = $entity->Add(array_merge($mainFields, $newFields));
        }
        
        return $result;
    }
    
    private function prepareContactFieldsNames()
    {
        $result = [];
        $arUserTypeEntity = UserFieldTable::getList([
            'filter' => ['ENTITY_ID' => 'CRM_CONTACT']
        ]);
        while ($field = $arUserTypeEntity->fetch()) {
            $result[$field['XML_ID']] = $field['FIELD_NAME'];
        }
        
        return $result;
    }
    
    public static function createLeadsFromContacts(int $webinarId, array $categorys, $userId) {
        Loader::includeModule('crm');
        
        $userIdInt = (int)$userId > 0 ? (int)$userId : substr($userId, 5);
        $contacts = self::getContacts($webinarId, $categorys, (int)$userIdInt);
        $leads = self::createLeads($contacts);
        return $leads;
    }
    
    private function getContacts(int $webinarId, array $categorys, int $userId = 0) {
        $fieldNames = self::prepareContactFieldsNames();
        
        $webinarSourceId = StatusTable::getList(['filter'=>['ENTITY_ID'=>'SOURCE', 'NAME'=>'Вебинар']])->fetch()['STATUS_ID'];
        Loader::includeModule('iblock');
        $webinarElement = \CIBlockElement::getList([], ['ID' => $webinarId, 'IBLOCK_CODE' => 'SEMINARS'], false, false, ['NAME', 'PROPERTY_DATA_I_VREMYA_NACH', 'PROPERTY_WEBINAR_URL'])->fetch();
        $user = $userId > 0 ? $userId : $GLOBALS['USER']->getId();
        
        $catFilter = ['LOGIC' => 'OR'];
        foreach ($categorys as $cat) {
            if (isset($fieldNames[$cat])) {
                $subFilter = [$fieldNames[$cat] => $webinarId];
                $catFilter[] = $subFilter; 
            }
        }
        $contactFilter = [
            'CHECK_PERMISSIONS' => 'N',
            $catFilter
        ];
        $contactsRes = \CCrmContact::GetList([], $contactFilter, array_merge(['ID', 'NAME', 'LAST_NAME'], $fieldNames));
        $contacts = [];
        /*$statusNames = [
            'EVENT_INVITED' => 'Приглашен',
            'EVENT_REGISTERED' => 'Зарегистрирован',
            'EVENT_VISITED' => 'Посетил вебинар'
        ];*/
        while ($contact = $contactsRes->fetch()) {
            /*$forTitle = [];
            foreach ($statusNames as $statusId => $status) {
                if (in_array($webinarId, $contacts[$fieldNames[$statusId]])) {
                    $forTitle[] = $status;
                }
            }*/
            $comment = '';
            if (in_array($webinarId, $contact[$fieldNames['EVENT_VISITED']])) {
                $comment = 'Посетил вебинар "'.$webinarElement['NAME'].'" от '.$webinarElement['PROPERTY_DATA_I_VREMYA_NACH_VALUE'];
            } else if (in_array($webinarId, $contact[$fieldNames['EVENT_REGISTERED']])) {
                $comment = 'Не пришел на вебинар "'.$webinarElement['NAME'].'" от '.$webinarElement['PROPERTY_DATA_I_VREMYA_NACH_VALUE'];
            }
            $lead = [
                'ASSIGNED_BY_ID' => $user,
                'TITLE' => 'Лид из мероприятия: '.$contact['LAST_NAME'].' '.$contact['NAME'],
                'COMMENTS' => $comment,
                'SOURCE_ID' => $webinarSourceId,
                'SOURCE_DESCRIPTION' => 'Вебинар '.$webinarElement['NAME'].' от '.$webinarElement['PROPERTY_DATA_I_VREMYA_NACH_VALUE'].' ('.$webinarElement['PROPERTY_WEBINAR_URL_VALUE'].')',
                'CONTACT_ID' => $contact['ID']
            ];
            $contacts[$contact['ID']] = $lead;
        }
        return $contacts;
    }
    
    private function createLeads(array $contacts) {
        $leads = [];
        $entity = new \CCrmLead(false);
        foreach ($contacts as $contact) {
            $leadId = $entity->Add($contact);
            $leads[] = $leadId;
        }
        return count($leads);
    }
}