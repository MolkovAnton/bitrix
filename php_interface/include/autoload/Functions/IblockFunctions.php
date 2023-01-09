<?
namespace Functions;

use \Bitrix\Main\Config\Option,
    \Handlers\IblockHandlers;

class IblockFunctions
{
    /*
     * Проверяет является ли инфоблок хранилищем структуры компании
     * $id - id инфоблока
     */
    public static function isDepartmentsIblock(int $id)
    {
        $iblockStructureId = Option::get('intranet', 'iblock_structure', 0);
        if ($id === (int)$iblockStructureId) {
           return true; 
        } else {
            return false;
        }           
    }

    /*
     * Метод трансформации массива в xml
     * $data - массив данных, может включать любую вложенность
     * $xml_data - ссылка на объект класса SimpleXMLElement
     * $names - массив псевдонимов, используется для корректного формирования именований элементов в xml. Участвует в проверке: если ключ массив данных начинается с одного из псевдонимов + _, то в конечном xml будет использоваться псевдоним в качестве имени элемента
     */
    private static function arrayToXml(array $data, \SimpleXMLElement &$xml_data, array $names = [])
    {
        foreach( $data as $key => $value ) {
            if( is_numeric($key) ){
                $key = 'item'.$key; //dealing with <0/>..<n/> issues
            } else {
                foreach ($names as $name) {
                    if (strpos($key, $name.'_') === 0) {
                        $key = $name;
                        break;
                    }
                }
            }
            if( is_array($value) ) {
                $subnode = $xml_data->addChild($key);
                self::arrayToXml($value, $subnode, $names);
            } else {
                $xml_data->addChild("$key",htmlspecialchars("$value"));
            }
         }
    }
    
    /*
     * Возвращает историю изменений в структуре подразделений
     * $params - массив для фильтра
     *      $params['DATE_INSERT'] - с какой даты выбирать данные
     */
    public function getDepartmentsHistory(array $params = []) {
        $result = [];
        $filter = [
            'UF_FUNCTIONAL_CODE' => [
                IblockHandlers::DEPARTMENT_ADD_EVENT,
                IblockHandlers::DEPARTMENT_CHANGE_EVENT,
                IblockHandlers::DEPARTMENT_DELETE_EVENT
            ]
        ];
        if ($params['DATE_INSERT'] > 0) {
            $filter['>UF_DATE'] = $params['DATE_INSERT'];
        }
        $logs = \HBE::Get('Logs', [], $filter);
        while ($log = $logs->fetch()) {
            $result[$log['ID']] = [
                'USER_ID' => $log['UF_USER_ID'],
                'EVENT_TYPE' => $log['UF_FUNCTIONAL_CODE'],
                'DATE_INSERT' => $log['UF_DATE']->toString(),
                'DATA' => json_decode($log['UF_DESCRIPTION'])
            ];
        }
        
        return $result;
    }
}