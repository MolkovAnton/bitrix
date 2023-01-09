<?
namespace Infinity;

class InfinityConnector
{
    private $url;
    private $provider;

    public function __construct(string $url, string $provider)
    {
        $this->url = $url;
        $this->provider = $provider;
    }
    
    public function addData(array $data)
    {
        return $this->sendQuery("/data/insertarr/", $data);
    }

    private function sendQuery(string $action, array $data)
    {
        $dataToSend = [
            'result' => [
                'data' => [$data]
            ]
        ];
        $encodedQuery = json_encode($dataToSend);
        $curl = curl_init();
        curl_setopt_array($curl, 
            array(
                CURLOPT_URL => $this->url."$action?ProviderName=".$this->provider,
                CURLOPT_HTTPHEADER => array('Content-type: application/json'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $encodedQuery
            )
        );
        
        $result = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return $result;
    }
}