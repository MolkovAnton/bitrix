<?
namespace Elastic;

use Elastic\Query;

class Search
{
    private $elasticUrl;
    private $elasticPassword;
    private $tempPatch;
    private $indexId;

    public function __construct(string $elasticUrl, array $elasticPassword, string $indexId)
    {
        if (empty($elasticUrl) || empty($elasticPassword)) {
            throw new Error('Empty url or password');
        }
        if (empty($indexId)) {
            throw new Error('Empty index');
        }
        $this->tempPatch = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT')."/upload/elastic/";
        $this->indexId = $indexId;
        $this->elasticUrl = $elasticUrl."/".$indexId;
        $this->elasticPassword = base64_encode($elasticPassword['name'].":".$elasticPassword['password']);
    }
    
    public function getList(array $params)
    {
        $query = new Query();
        foreach ($params['filter'] as $key => $filter) {
            $query->addFilter($key, $filter);
        }
        
        if (!empty($params['sort'])) {
            foreach ($params['sort'] as $key => $sort) {
                $query->addSort($key, $sort);
            }
        }
        
        if ($params['offset'] > 0) {
            $query->setOffset($params['offset']);
        }
        
        if ($params['limit'] > 0) {
            $query->setLimit($params['limit']);
        }
        
        return $this->search($query);
    }

    private function sendRequest(string $encodedQuery, string $action, string $method = "POST")
    {
        $curl = curl_init();
        curl_setopt_array($curl, 
            array(
                CURLOPT_URL => $this->elasticUrl."/$action",
                CURLOPT_HTTPHEADER => array('Content-type: application/json', 'Authorization: Basic '.$this->elasticPassword),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $encodedQuery
            )
        );
        $result = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return $result;
    }
    
    private function search(Query $query)
    {
        $result = $this->sendRequest($query->prepareQuery(), "_search");
        $hits = [];
        foreach ($result['hits']['hits'] as $hit) {
            $hits[] = $hit['_source'];
        }
        return $hits;
    }
    
    public function addDocsToElastic(string $url = '')
    {
        $reload = false;
        if ($url) {
            $fileName = pathinfo($url, PATHINFO_BASENAME);
            file_put_contents($this->tempPatch.$fileName, fopen($url, 'rb'));
        }
        if (is_dir($this->tempPatch) && $dh = opendir($this->tempPatch)) {
            $reload = $this->checkTempDir($dh);
            closedir($dh);
        }
        if ($reload) {
            $this->addDocsToElastic();
        }
        return true;
    }
    
    private function checkTempDir($dir)
    {
        $reload = false;
        while (($file = readdir($dir)) !== false) {
            $filePatch = $this->tempPatch . $file;
            if (filetype($filePatch) === 'file' && !$this->handleFile($filePatch)) {
                $reload = true;
            }
        }
        return $reload;
    }
    
    private function handleFile(string $filePatch) {
        $fileType = pathinfo($filePatch, PATHINFO_EXTENSION);
        if ($fileType === 'xml') {
            $this->putFileToIndex($filePatch);
        } else if ($fileType === 'zip') {
            $zip = new \ZipArchive;
            $res = $zip->open($filePatch);
            if ($res === TRUE) {
                $zip->extractTo($this->tempPatch);
                $zip->close();
                unlink($filePatch);
                return false;
            }
        }
        unlink($filePatch);
        return true;
    }

    private function putFileToIndex(string $filePatch)
    {
        $xml = simplexml_load_file($filePatch);
        $fields = $this->getIndexFields();
        $result = [];
        foreach ($xml->Документ as $doc) {
            $innerResult = [];
            $this->getDocValues($doc, null, $innerResult, $fields);
            $result[] = $innerResult;
        }
        
        $queryResult = $this->sendDataToElastic($result);
        return $queryResult;
    }
    
    private function getDocValues($field, $key, &$result, $fields)
    {
        if (is_array($field) || (gettype($field) === 'object' && get_class($field) === 'SimpleXMLElement')) {
            $arrField = (array)$field;
            foreach ($arrField as $innerKey => $innerField) {
                $this->getDocValues($innerField, $innerKey, $result, $fields);
            }
        } else if (array_key_exists($key, $fields)){
            $result[$key] = $field;
        }
    }

    public function sendDataToElastic(array $data)
    {
        $bulkData = $this->prepareBulk($data);
        $response = $this->sendRequest($bulkData, '_bulk');
        return $response;
    }
    
    private function getIndexFields()
    {
        $mapping = $this->sendRequest('', '_mapping', 'GET');
        $fields = $mapping[$this->indexId]['mappings']['properties'];
        return $fields;
    }
    
    private function prepareBulk(array $data)
    {
        $resultString = '';
        foreach ($data as $fields) {
            $resultString .= json_encode(['index' => new \stdClass()])."\n";
            $resultString .= json_encode($fields)."\n";
        }
        return $resultString;
    }

    public function deleteDocsFromIndex()
    {
        $query = new Query();
        $this->sendRequest($query->selectAll(), '_delete_by_query?conflicts=proceed');
    }
}