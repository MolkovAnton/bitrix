<?
namespace Functions;

use \Bitrix\Main\Config\Option,
    \Bitrix\Iblock\Model\Section,
    \Bitrix\Main\UserTable;

class CompanyStructure
{
    private static $userFilter = [
        'ACTIVE' => 'Y',
    ];
    private $depArr, $depTree, $csvHeaders, $users;
    private $csv = '';
    private $maxDepth = 0;
    
    private function __construct() {
        $this->skipDep = $this->getSkipDepartments(['CHAT_BOTS']);
    }
    /*
     * Метод представления структуры подразделений в виде xml набора
     * $url - урл файла в который будет сохранен результат, указывается без типа относительно корневой папки сайта. Если не передан то выведет результат вместо записи в файл
     */
    public static function departmentStructureToXml(string $url = null)
    {
        $finalStructure = self::getDepartmentsTree();
        $xml_data = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><DEPARTMENTS></DEPARTMENTS>');
        self::arrayToXml($finalStructure, $xml_data, ['DEPARTMENT', 'EMPLOYEE']);

        if ($url !== null) {
            $result = $xml_data->asXML($_SERVER["DOCUMENT_ROOT"]."/$url.xml");
            return $result;
        }
        
        return $xml_data->asXML();
    }
    
    /*
     * Формирует дерево подразделений с пользователями
     */
    public static function getDepartmentsTree()
    {
        $depStructure = \CIntranetUtils::GetStructure();
        $users = [];
        
        $filter = self::$userFilter;
        $filter['!UF_DEPARTMENT'] = self::getSkipDepartments(['CHAT_BOTS']);
        $userRes = UserTable::getList(['filter'=>$filter, 'select'=>['ID', 'UF_DEPARTMENT', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'EMAIL', 'LOGIN', 'UF_MAIN_DEPARTMENT', 'UF_USER_TEAM', 'UF_USER_ROLE', 'UF_EMPLOYMENT_DATE', 'DATE_REGISTER', 'WORK_POSITION']]);
        while($user = $userRes->fetch()) {
			if(!empty($user['UF_EMPLOYMENT_DATE'])) {
				$user['UF_EMPLOYMENT_DATE'] = $user['UF_EMPLOYMENT_DATE']->toString();
			} else {
				$user['UF_EMPLOYMENT_DATE'] = '';
			}
			if(!empty($user['DATE_REGISTER'])) {
				$user['DATE_REGISTER'] = $user['DATE_REGISTER']->toString();
			} else {
				$user['DATE_REGISTER'] = '';
			}
            $users[$user['ID']] = $user;
        }

        return self::addDepartmenToStructure($depStructure['TREE'][0][0], $depStructure, $users);
    }
    
    public static function exportUsersCsv()
    {
        $self = new self;
        $csv = $self->prepareUsersExportCsv();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream; charset=windows-1251');
        header('Content-Disposition: attachment; filename=users.csv');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo mb_convert_encoding($csv, 'windows-1251');
        exit();
    }
    
    public function prepareUsersExportCsv()
    {
        $this->maxDepth = self::getDepartmentsMaxDept();
        $this->prepareCsvHeaders();
        $this->prepareCsvUsers();
        return $this->csv;
    }
    
    private function prepareCsvHeaders()
    {
        $this->csvHeaders = [
            'LAST_NAME' => 'Фамилия',
            'NAME' => 'Имя',
            'EMAIL' => 'Email',
            'PERSONAL_MOBILE' => 'Мобильный телефон',
            'WORK_PHONE' => 'Рабочий телефон',
            'UF_PHONE_INNER' => 'Внутренний телефон',
            'UF_REGION_OFFICE' => 'Город',
            'WORK_POSITION' => 'Должность',
            'CHIEF' => 'Руководитель',
            'UF_USER_ROLE' => 'Роль сотрудника',
            'UF_MAILING_ROLE' => 'Роль для рассылки'
        ];
        foreach ($this->csvHeaders as $header) {
            $this->csv .= self::sanitazeCsvString($header).';';
        }
        for ($i = 1; $i <= $this->maxDepth; $i++) {
            $this->csv .= "О$i;";
        }
        $this->csv .= PHP_EOL;
    }
    
    private function prepareCsvUsers() {
        $this->depTree = \CIntranetUtils::GetStructure();
        $offices = $roles = [];
        $officesRes = \CIBlockElement::GetList([], ['IBLOCK_CODE'=>['BRANCHES', 'LIST_ROLES']], false, false, ['ID','NAME','IBLOCK_CODE']);
        while ($office = $officesRes->fetch()) {
            if ($office['IBLOCK_CODE'] === 'BRANCHES') {
                $offices[$office['ID']] = $office['NAME'];
            } else {
                $roles[$office['ID']] = $office['NAME'];
            }
        }
        
        $mroles = [];
        $fieldRes = \CUserFieldEnum::GetList([], ['USER_FIELD_NAME' => 'UF_USR_1659004866790']);
        while ($role = $fieldRes->fetch()) {
            $mroles[$role['ID']] = $role['VALUE'];
        }
        
        $users = [];
        $filter = $this->userFilter;
        $filter['!UF_DEPARTMENT'] = $this->skipDep;
        $select = ['ID', 'UF_DEPARTMENT', 'NAME', 'LAST_NAME', 'EMAIL', 'PERSONAL_MOBILE', 'WORK_PHONE', 'UF_PHONE_INNER', 'UF_REGION_OFFICE', 'WORK_POSITION', 'UF_USER_ROLE', 'UF_MAILING_ROLE' => 'UF_USR_1659004866790'];
        $userRes = UserTable::getList(['filter'=>$filter, 'select'=>$select]);
        while($user = $userRes->fetch()) {
            $user['UF_REGION_OFFICE'] = $offices[$user['UF_REGION_OFFICE']];
            $user['UF_USER_ROLE'] = $roles[$user['UF_USER_ROLE']];
            $user['UF_MAILING_ROLE'] = $mroles[$user['UF_MAILING_ROLE']];
            $users[$user['ID']] = $user;
        }
        $this->users = $users;
        $this->addDepartmentToCsv($this->depTree['TREE'][0][0]);
    }
    
    private function addDepartmentToCsv(int $depId)
    {
        if (in_array($depId, $this->skipDep)) return;
        
        $department = $this->depTree['DATA'][$depId];
        $this->depArr[$department['DEPTH_LEVEL']] = $depId;
        for ($i = $department['DEPTH_LEVEL'] + 1; $i <= $this->maxDepth; $i++) {
            unset($this->depArr[$i]);
        }
        foreach ($department['EMPLOYEES'] as $user) {
            $this->addCsvUserString($user, $this->getUserChief($user, $depId));
        }
        foreach ($this->depTree['TREE'][$depId] as $subDep) {
            $this->addDepartmentToCsv($subDep);
        }
    }
    
    private function getUserChief(int $userId, int $depId) {
        $userChief = '';
        $department = $this->depTree['DATA'][$depId];
        $depLevel = intval($department['DEPTH_LEVEL']);
        
        while ($depLevel > 0) {
            $headDep = $this->depTree['DATA'][$this->depArr[$depLevel]];
            $headUserId = $headDep['UF_HEAD'];
            if ($userId == $headUserId) {
                $depLevel--;
                continue;
            }
            
            if (!empty($this->users[$headUserId]['EMAIL'])) {
                $userChief = $this->users[$headUserId]['EMAIL'];
                break;
            }
            $depLevel --;
        }
        
        return $userChief;
    }

    private function addCsvUserString(int $userId, string $userChief = '')
    {
        $user = $this->users[$userId];
        foreach ($this->csvHeaders as $headerId => $header) {
            if ($headerId === 'CHIEF') {
                $this->csv .= self::sanitazeCsvString($userChief).';';
            } else {
               $this->csv .= self::sanitazeCsvString($user[$headerId]).';'; 
            }
        }
        for ($i = 1; $i <= $this->maxDepth; $i++) {
            $this->csv .= $this->depTree['DATA'][$this->depArr[$i]]['NAME'].';';
        }
        $this->csv .= PHP_EOL;
    }

    private static function sanitazeCsvString($string)
    {
        return strtr($string, ';', ' ');
    }
    
    private static function getDepartmentsMaxDept() {
        $iblockStructureId = Option::get('intranet', 'iblock_structure', 0);
        $entity = Section::compileEntityByIblock($iblockStructureId);
        $maxDepth = $entity::getList([
            'select' => ['ID', 'DEPTH_LEVEL'],
            'filter' => [
                'IBLOCK_ID' => $iblockStructureId
            ],
            'limit' => 1,
            'order' => ['DEPTH_LEVEL' => 'DESC']
        ])->fetch()['DEPTH_LEVEL'];
        return $maxDepth;
    }

    /*
     * Метод для формирования структуры подразделений в виде многомерного массива, добавляет информацию о сотрудниках подразделения
     * $id - id подразделения, в первой итерации необходимо передать id конкретного подразделения (самого верхнего для полной структуры). Далее рекурсивно подставляются id подчиненных отделов
     * $depStructure - массив полученный путем вызова функции \CIntranetUtils::GetStructure()
     * $users - массив с данными пользователей
     * $first - флаг отмечающий первую итерацию, необходим для корректного формирования имен ключей результируещего массива для дальнейшего преобразования в xml
     */
    private static function addDepartmenToStructure(int $id, array $depStructure, array $users, bool $first = true)
    {
        $department = $depStructure['DATA'][$id];
        $department['UF_HEAD'] = $users[$department['UF_HEAD']];
        unset($department['SECTION_PAGE_URL']);
        unset($department['IBLOCK_SECTION_ID']);
        unset($department['DEPTH_LEVEL']);
        $depUsers = [];
        foreach ($department['EMPLOYEES'] as $user) {
            if (isset($users[$user]))
                $depUsers[] = $users[$user];
        }
        $department['EMPLOYEES'] = $depUsers;
        foreach ($depStructure['TREE'][$id] as $subDep) {
            $department['CHILD_DEPARTMENTS'][] = self::addDepartmenToStructure($subDep, $depStructure, $users, false);
        }
        return $first ? [$department] : $department;
    }
    
    /*
     * Возвращает массив $id подразделений по коду
     * $codes - массив с кодами подразделений ид которых нужнов вернуть
     */
    public static function getSkipDepartments(array $codes)
    {
        if (count($codes) === 0) return [];
        $skipDeps = [];
        
        $iblockStructureId = Option::get('intranet', 'iblock_structure', 0);
        $entity = Section::compileEntityByIblock($iblockStructureId);
        $sectionRes = $entity::getList([
            'select' => ['ID', 'CODE'],
            'filter' => [
                'IBLOCK_ID' => $iblockStructureId,
                'CODE' => $codes
            ],
        ]);
        while ($section = $sectionRes->fetch()) {
            $skipDeps[] = $section['ID'];
        }
        
        return $skipDeps;
    }
    
    /*
     * Возвращает строку csv с пользователями отформатированную особым образом
     */
    public static function getUsersCsvString()
    {
        $by = $order = '';
        $depStructure = \CIntranetUtils::GetStructure();
        $roles = [71156=>'Менеджер по продажам (хантер)',71206=>'РОП по Федеральному округу',71205=>'Ассистент отдела продаж',71204=>'Тимлид группы отдела продаж',71157=>'Менеджер по продажам (фермер)'];
        $comands = [];
        $res = CIBlockElement::GetList(Array(), ['IBLOCK_ID'=>176], false, false, ['ID','NAME']);
        while ($com = $res->fetch()) {
            $comands[$com['ID']] = $com['NAME'];
        }
        unset($res);
        $csv = 'Фамилия;Имя;Отчество;ИД сотрудника;основное под-е;ИД основного подразделения;должность;ИД роли;Название роли;ИД команды;Название команды;Дата создания;Дата принятия на работу'.PHP_EOL;
        $res = CUser::GetList($by, $order, ['ACTIVE' => 'Y', '!UF_MAIN_DEPARTMENT' => '', '!UF_MAIN_DEPARTMENT' => 2784], ['SELECT'=>['ID', 'UF_MAIN_DEPARTMENT', 'UF_USER_ROLE', 'UF_USER_TEAM', 'WORK_POSITION', 'DATE_REGISTER', 'UF_EMPLOYMENT_DATE']]);
        while ($fields = $res->fetch()) {
            $csv .= $fields['LAST_NAME'].';'.$fields['NAME'].';'.$fields['SECOND_NAME'].';'.$fields['ID'].';'.$depStructure['DATA'][$fields['UF_MAIN_DEPARTMENT']]['NAME'].';'.$fields['UF_MAIN_DEPARTMENT'].';'.$fields['WORK_POSITION'].';'.$fields['UF_USER_ROLE'].';'.$roles[$fields['UF_USER_ROLE']].';'.$fields['UF_USER_TEAM'].';'.$comands[$fields['UF_USER_TEAM']].';'.$fields['DATE_REGISTER'].';'.$fields['UF_EMPLOYMENT_DATE'].PHP_EOL;
        }
        return $csv;
    }
}