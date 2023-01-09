<?
namespace Jira;

use \Bitrix\Main\Loader,
    \Bitrix\Rpa\Driver,
    \Bitrix\Main\UserTable,
    \Bitrix\Rpa\Controller\Comment;

class Connector
{
    private $curlOptions, $formData, $bpOtions;
    
    public function __construct(array $param)
    {
        Loader::includeModule('rpa');
        $this->curlOptions = $this->prepaireCurlOpt($param['connection']);
        $this->formData = $this->prepareFields($param['data']);
        if (isset($param['document_id'])) {
           $doc = explode(':', $param['document_id'][2]);
            $this->bpOtions = [
                'typeId' => $doc[0],
                'itemId' => $doc[1],
                'responseField' => 'UF_RPA_'.$doc[0].'_'.$param['bpoptions']['responseField']
            ];
        }
    }
    
    public static function sendFormRest(array $param)
    {
        $jira = new self($param);
        $response = $jira->sendFormToJira();
        if (empty($response['issueId'])) {
            global $USER;
            $data = [
                'UF_DATE' => new \Bitrix\Main\Type\DateTime(),
                'UF_USER_ID' => $USER->GetID(),
                'UF_EVENT' => 'JiraFormSend',
                'UF_DESCRIPTION' => 'Ошибка создания создания задачи в Jira: '.print_r($response, true),
                'UF_FUNCTIONAL_CODE' => 'JIRA_SEND_FORM_ERROR'
            ];
            \HBE::Add('Logs', $data);
        }
        if ($jira->isBizproc()) {
            $jira->addJiraLinkToBp($response);
        }
        return $response;
    }
    
    private function addJiraLinkToBp(array $response) {
        $link = $response['_links']['web'];
        $type = Driver::getInstance()->getType($this->bpOtions['typeId']);
        if (empty($link)) {
            $commentFields = [
                'title' => 'Ошибка создания заявки в Jira',
                'description' => json_encode($response, JSON_UNESCAPED_UNICODE)
            ];
            $comment = new Comment();
            $comment->addAction($type, $this->bpOtions['itemId'], $commentFields);
        } else {
            $item = $type->getItem($this->bpOtions['itemId']);
            $item->set($this->bpOtions['responseField'], $link);
            $item->save();
        }
    }

    private function prepareFields(array $fields) {
        $this->prepareFieldrecursive($fields);
        return json_encode($fields);
    }

    private function prepareFieldrecursive(&$fields) {
        foreach ($fields as &$field) {
            if (isset($field['array'])) {
                $itemTmp = explode(', ', $field['array']);
                foreach ($itemTmp as &$item) {
                    $item = ['value' => $item];
                }
                $field = $itemTmp;
            } else if (isset($field['date'])) {
                $field = $this->prepaireDate($field['date']);
            } else if (isset($field['user'])) {
                $field = $this->getUserLogin($field['user']);
            } else if (isset($field['int'])) {
                $field = $this->getNumberValue($field['int']);
            } else if (is_array($field)) {
                $this->prepareFieldrecursive($field);
            }
        }
    }
    
    private function getUserLogin($user) {
        $userId = $this->getNumberValue($user);
        $login = UserTable::getList([
            'filter' => ['ID'=>$userId],
            'limit' => 1,
            'select' => ['ID', 'LOGIN']
        ])->fetch()['LOGIN'];
        return strstr($login, '@', true) ?: $login;
    }
    
    private function getNumberValue($value) {
        return preg_replace('/[^0-9]/', '', $value);
    }

    private function prepaireDate($date) {
        $newDate = \DateTime::createFromFormat('d.m.Y H:i:s', $date);
        return $newDate ? $newDate->format('Y-m-d') : '';
    }

    public function isBizproc() {
        return !empty($this->bpOtions);
    }

    public function sendFormToJira()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->formData);
        curl_setopt_array($curl, $this->curlOptions);
        $result = curl_exec($curl);
        curl_close($curl);
        return json_decode($result, true);
    }
    
    private function prepaireCurlOpt(array $options) {
        $curlOptions = [
            CURLOPT_HTTPHEADER => array('Content-type: application/json'),
            CURLOPT_RETURNTRANSFER => true
        ];
        foreach ($options as $name => $value) {
            switch ($name) {
                case 'URL':
                    $curlOptions[CURLOPT_URL] = $value;
                    break;
                case 'LOGIN':
                    $curlOptions[CURLOPT_USERPWD] = $value['LOGIN'] . ':' . $value['PASSWORD'];
                    break;
                default :
                    $curlOptions[$name] = $value;
                    break;
            }
        }
        
        return $curlOptions;
    }
}
