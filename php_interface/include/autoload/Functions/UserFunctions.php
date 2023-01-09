<?
namespace Functions;

use \Bitrix\Main\UserTable,
    \Bitrix\Main\UserProfileHistoryTable,
    \Functions\CompanyStructure,
    \Bitrix\Main\UserGroupTable,
    \Bitrix\Rpa\Driver,
    \Bitrix\Main\Loader,
    \Bitrix\Main\UserProfileRecordTable;

class UserFunctions
{
    /*
     * Метод получения истории изменения свойств пользователя
     * $params - массив для фильтра
     *      $params['DATE_INSERT'] - с какой даты выбирать данные
     */
    public function getUserHistory(array $params) {
        $result = [];
        $eventTypes = [
            UserProfileHistoryTable::TYPE_ADD => 'ADD',
            UserProfileHistoryTable::TYPE_UPDATE => 'UPDATE',
            UserProfileHistoryTable::TYPE_DELETE => 'DELETE'
        ];
        
        $skipDepCodes = [
            'CHAT_BOTS'
        ];
        $skipDeps = CompanyStructure::getSkipDepartments($skipDepCodes);
        
        $nonUsers = [];
        $nonUsersRes = \Bitrix\Intranet\Util::getDepartmentEmployees([
            'DEPARTMENTS' => $skipDeps,
            'RECURSIVE' => 'Y',
            'ACTIVE' => 'Y',
            'SELECT' => ['ID']
        ]);
        while ($nonUser = $nonUsersRes->fetch()) {
            $nonUsers[] = $nonUser['ID'];
        }
        
        //Массив полей которые будут отдаваться
        $fieldsToSend = [
            //'UF_DEPARTMENT',
            'ACTIVE',
            'WORK_POSITION',
            'UF_USER_ROLE',
            'UF_USER_TEAM',
            'UF_MAIN_DEPARTMENT'
        ];
        $skipUsers = [
            'Test',
            'test',
            'testuser'
        ];
        $userGroups = [
            'EMPLOYEES',
            'DIRECTION',
            'ADMINS',
            'PERSONNEL_DEPARTMENT',
            'MARKETING_AND_SALES',
            'PORTAL_ADMINISTRATION'
        ];
        $filter = [
            '>DATE_INSERT' => $params['DATE_INSERT'],
            [
                'LOGIC' => 'OR',
                ['EVENT_TYPE' => UserProfileHistoryTable::TYPE_DELETE],
                [
                    'EVENT_TYPE' => [UserProfileHistoryTable::TYPE_ADD, UserProfileHistoryTable::TYPE_UPDATE],
                    '!USER_ID' => $nonUsers,
                    '!USER.NAME' => $skipUsers,
                    '!USER.LAST_NAME' => $skipUsers,
                    '!USER.LOGIN' => $skipUsers,
                    'GROUPS.GROUP.STRING_ID' => $userGroups,
                    [
                        'LOGIC' => 'OR',
                        ['RECORD.FIELD' => $fieldsToSend],
                        ['RECORD.FIELD' => '']
                    ]
                ]
            ]
        ];
        $historyRes = UserProfileHistoryTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'USER_ID', 'EVENT_TYPE', 'DATE_INSERT', 'UPDATED_BY_ID', 'RECORD', 'USER.LAST_NAME', 'USER.SECOND_NAME', 'USER.NAME', 'GROUPS.GROUP'],
            'order' => ['DATE_INSERT' => 'ASC'],
            'runtime' => [
                "RECORD" => [
                    'data_type' => UserProfileRecordTable::class,
                    'reference' => [
                        '=this.ID' => 'ref.HISTORY_ID'
                    ]
                ],
                "USER" => [
                    'data_type' => UserTable::class,
                    'reference' => [
                        '=this.USER_ID' => 'ref.ID'
                    ]
                ],
                "GROUPS" => [
                    'data_type' => UserGroupTable::class,
                    'reference' => [
                        '=this.USER_ID' => 'ref.USER_ID',
                    ]
                ]
            ]
        ]);

        while ($hist = $historyRes->fetch()) {
            $id = $hist['MAIN_USER_PROFILE_HISTORY_RECORD_ID'] > 0 ? $hist['MAIN_USER_PROFILE_HISTORY_RECORD_ID'] : 'H_'.$hist['ID'];
            $result[$id] = [
                'USER_ID' => $hist['USER_ID'],
                'USER_NAME' => $hist['MAIN_USER_PROFILE_HISTORY_USER_LAST_NAME'].' '.$hist['MAIN_USER_PROFILE_HISTORY_USER_NAME'].' '.$hist['MAIN_USER_PROFILE_HISTORY_USER_SECOND_NAME'],
                'EVENT_TYPE' => $hist['MAIN_USER_PROFILE_HISTORY_RECORD_FIELD'] === 'UF_DEPARTMENT*CORRECT' ? 'CORRECT' : $eventTypes[$hist['EVENT_TYPE']],
                'DATE_INSERT' => $hist['DATE_INSERT']->toString(),
                'UPDATED_BY_ID' => $hist['UPDATED_BY_ID'],
                'FIELD' => $hist['MAIN_USER_PROFILE_HISTORY_RECORD_FIELD'],
                'DATA' => $hist['MAIN_USER_PROFILE_HISTORY_RECORD_DATA']
            ];
        }

        usort($result, 'self::dateSort');
        return $result;
    }
    
    private function dateSort($a, $b) {
        if ($a['DATE_INSERT'] == $b['DATE_INSERT']) {
            return 0;
        }
        return ($a['DATE_INSERT'] < $b['DATE_INSERT']) ? -1 : 1;
    }
    
    public function userAdd(array $param) {
        Loader::includeModule('rpa');
        global $USER;
        $doc = explode(':', $param['document_id'][2]);
        if (empty($doc[0]) || empty($doc[1]) || empty($param['USER_CREATED_FIELD'])) {
            $data = [
                'UF_DATE' => new \Bitrix\Main\Type\DateTime(),
                'UF_USER_ID' => $USER->GetID(),
                'UF_EVENT' => 'UserAdd',
                'UF_DESCRIPTION' => 'Ошибка параметров запроса: '.print_r($param, true),
                'UF_FUNCTIONAL_CODE' => 'USER_ADD'
            ];
            \HBE::Add('Logs', $data);
            return;
        } else {
            $item = Driver::getInstance()->getType($doc[0])->getItem($doc[1]);
        }
        
        $userId = UserTable::getlist(['filter'=>['EMAIL'=>$param['EMAIL']]])->fetch()['ID'];
        if ($userId > 0) {
            $item->set($param['USER_CREATED_FIELD'], 1);
            $item->save();
            return;
        }
        
        if (!empty($param['PERSONAL_PHOTO'])) {
            $param['PERSONAL_PHOTO'] = \CFile::MakeFileArray($param['PERSONAL_PHOTO']);
        }
        if (!empty($param['PERSONAL_GENDER'])) {
            $param['PERSONAL_GENDER'] = $param['PERSONAL_GENDER'] === 'F' || $param['PERSONAL_GENDER'] === 'Женский' ? 'F' : 'M';
        }
        
        $user = new \CUser();
        $result = $user->Add($param);
        if ($result > 0) {
            $item->set($param['USER_CREATED_FIELD'], 1);
            $item->save();
        } else {
            $data = [
                'UF_DATE' => new \Bitrix\Main\Type\DateTime(),
                'UF_USER_ID' => $USER->GetID(),
                'UF_EVENT' => 'UserAdd',
                'UF_DESCRIPTION' => 'Ошибка создания пользователя с параметрами: '.print_r($param, true),
                'UF_FUNCTIONAL_CODE' => 'USER_ADD'
            ];
            \HBE::Add('Logs', $data);
        }
    }
}