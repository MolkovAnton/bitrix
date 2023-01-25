<?
namespace Agents;

class WebService1C
{
    //Агент для получения статистики по продажам отделов от 1С и занесения этих данных в HL-блок
    public function updateSalesRaiting() {
        $raiting = self::getSalesRating()['departments'];
        if (empty($raiting)) return "\Agents\WebService1C::updateSalesRaiting();";
        
        $newDepartments = [];
        foreach ($raiting as $dep) {
            $newDepartments[$dep['id']] = $dep;
        }
        $departments = [];
        $entity = \HBE::GetEntityDataClass('SalesRating');
        $elementsRes = $entity::getList();
        while ($element = $elementsRes->fetch()) {
            $departments[$element['UF_DEPARTMENT']] = $element['ID'];
        }
        
        foreach ($departments as $depId => $elementId) {
            if (!isset($newDepartments[$depId])) {
                $entity::delete(['ID' => $elementId]);
            }
        }
        
        foreach ($newDepartments as $department) {
            $fields = [
                'UF_DEPARTMENT' => $department['id'],
                'UF_INCOME' => $department['income'],
                'UF_INCREASE' => $department['increase'],
                'UF_MARGINALITY' => $department['marginality'],
                'UF_PERCLAST' => $department['pribperclast'],
                'UF_PERCCURRENT' => $department['pribperccurrent'],
                'UF_PERCINCREASE' => $department['pribpercincrease'],
            ];
            if (!empty($departments[$department['id']])) {
                $entity::update($departments[$department['id']], $fields);
            } else {
                $entity::add($fields);
            }
        }
        
        return "\Agents\WebService1C::updateSalesRaiting();";
    }
    
    /*
     * Метод для получения статистики по продажам отделов от 1С
     * возвращает массив вида ['departments' => [ ['id' => ?, 'income' => ?, 'increase' => ?, 'marginality' => ?], ... ] ] 
     */
    public function getSalesRating() {
        return self::getWebService('change');
    }
    
    public function getWebService($method, $data = []) {
        $url = "https://web1c.MA.ru/$method";
        $login = "somelogin";
        $password = "somepassword";
        
        $obCurl = curl_init();
        curl_setopt_array(
            $obCurl,
            [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_UNRESTRICTED_AUTH => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $login . ":" . $password,
                CURLOPT_HTTPHEADER => ['Content-Type:application/json'],
                CURLOPT_POSTFIELDS => $data
            ]
        );

        $json = curl_exec($obCurl);
        $result = json_decode($json, true);
        
        return $result;
    }
    
    public function updateChallengeCupAgent() {
        $raiting = self::getWebService('rockets')['groups'];
        if (empty($raiting)) return "\Agents\WebService1C::updateChallengeCupAgent();";
        
        $oldData = [];
        $entity = \HBE::GetEntityDataClass('ChallengeCup');
        $elementsRes = $entity::getList();
        while ($element = $elementsRes->fetch()) {
            $oldData[] = $element['ID'];
        }
        foreach ($oldData as $id) {
            $entity::delete(['ID' => $id]);
        }
        
        foreach ($raiting as $group) {
            foreach ($group['departments'] as $dep) {
                $fields = [
                    'UF_CUP_DEPARTMENT' => $dep['id'],
                    'UF_CUP_RATING' => $dep['rating'],
                    'UF_CUP_GROUP' => $group['group_name']
                ];
                $entity::add($fields);
            }
        }
        
        return "\Agents\WebService1C::updateChallengeCupAgent();";
    }
}