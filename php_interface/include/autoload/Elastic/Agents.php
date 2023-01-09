<?
namespace Elastic;

use Elastic\Search,
    \Bitrix\Crm\LeadTable,
    \Bitrix\Main\Loader,
    \Bitrix\Main\Type\DateTime,
    \Bitrix\Main\Type\Date;

class Agents
{
    private static $methods = [
        'LEAD' => 'getLeadsStatistics',
        'HIT' => 'getHitsStatistic'
    ];
    
    public static function addToElastic(string $method, string $settingsRaw)
    {
        $settings = (array)json_decode($settingsRaw);
        $elasticUrl = $settings['url'];
        $elasticPassword = [
            'name' => $settings['name'],
            'password' => $settings['password']
        ];
        if ($method === 'HIT') {
            $indexId = $settings['index'].'_'.date('m-Y');
        } else {
           $indexId = $settings['index']; 
        }
        
        if (empty($elasticUrl) || empty($elasticPassword['name']) || empty($elasticPassword['password']) || empty($indexId)) {
            return false;
        }
        
        $elastic = new Search($elasticUrl, $elasticPassword, $indexId);
        
        $methodName = self::$methods[$method];
        self::$methodName($elastic);
        
        return "Elastic\Agents::addToElastic('$method', '$settingsRaw');";
    }
    
    private static function getLeadsStatistics($elastic)
    {
        $statuses = [];
        $leadsRes = LeadTable::getList([
            'filter' => [],
            'select' => ['ID', 'STATUS_ID']
        ]);
        while ($lead = $leadsRes->fetch()) {
            $statuses[$lead['STATUS_ID']] ++;
        }
        $statuses['date'] = date('Y-m-d');
        
        $elastic->sendDataToElastic([$statuses]);
    }
    
    private function getHitsStatistic($elastic)
    {
        Loader::includeModule('statistic');
        $err_mess = "File: ".__FILE__."<br>Line: ";
        $DB = \CDatabase::GetModuleConnection('statistic');
        $newDate = null;
        $continue = true;
        $endDate = new Date();
        while ($continue) {
            $result = [];
            $date_1 = $newDate ? new DateTime($newDate) : (new DateTime((new Date())->add('-1d')))->toString();
            $strSql = "
            SELECT
                H.*,
                ".$DB->DateToCharFunction("H.DATE_HIT")." DATE_HIT
            FROM
                b_stat_hit H
                LEFT JOIN b_stat_city CITY ON (CITY.ID = H.CITY_ID)
            WHERE
                H.DATE_HIT > ".$DB->CharToDateFunction($date_1).
            "LIMIT 5000";
            $hitRes = $DB->Query($strSql, false, $err_mess.__LINE__);
            while ($hit = $hitRes->fetch()) {
                if ($hit['DATE_HIT'] >= $endDate) {
                    $continue = false;
                    break;
                }
                $newDate = $hit['DATE_HIT'];
                $date = new \DateTimeImmutable($hit['DATE_HIT']);
                $hit['DATE_HIT'] = $date->format('Y-m-d').'T'.$date->format('H:i:s');
                $result[] = $hit;
            }
            $elastic->sendDataToElastic($result);
        }
    }
}