<?
namespace Elastic;

class Query
{
    private $filter = [];
    private $sort = [];
    private $offset = 0;
    private $limit = 0;
    private $filterTypes = ["=", ">", "<", "!"];

    public function prepareQuery()
    {
        $innerQuery = $this->parseFilter();
        $query = [
            "query" => $innerQuery
        ];
        
        if (!empty($this->sort)) {
            $query["sort"] = $this->sort;
        }
        
        if ($this->offset > 0) {
            $query["from"] = $this->offset;
        }
        
        if ($this->limit > 0) {
            $query["size"] = $this->limit;
        }
        
        return json_encode($query);
    }
    
    private function parseFilter()
    {
        $filter = [];
        foreach ($this->filter as $key => $val) {
            $firstSymbol = $key[0];
            switch ($firstSymbol) {
                case "=":
                    $filter['bool']['filter'][] = [is_array($val) ? 'terms' : 'term' => [substr($key, 1) => $val]];
                    break;
                case ">":
                    $eq = $key[1] === "=";
                    $filter['bool']['filter'][] = ['range' => [$eq ? substr($key, 2) : substr($key, 1) => ['gt'.($eq ? 'e' : '') => $val]]];
                    break;
                case "<":
                    $eq = $key[1] === "=";
                    $filter['bool']['filter'][] = ['range' => [$eq ? substr($key, 2) : substr($key, 1) => ['lt'.($eq ? 'e' : '') => $val]]];
                    break;
                case "!":
                    $filter['bool']['must_not'][] = [is_array($val) ? 'terms' : 'term' => [substr($key, 1) => $val]];
                    break;
                default :
                    $filter['bool']['filter'][] = $this->makeMatchQuery($key, $val);
            }
        }
        
        if (empty($this->filter)) {
            $filter = ["match_all" => new \stdClass()];
        }
        
        return $filter;
    }
    
    private function makeMatchQuery($key, $val)
    {
        $filter = [];
        if (is_array($val)) {
            $innerFilter = [];
            foreach ($val as $innerVal) {
                $innerFilter[] = ['match'=>[$key => ['query' => $innerVal, 'operator' => 'and']]];
            }
            $filter['bool']['should'] = $innerFilter;
        } else {
            $filter['match'][$key] = ['query' => $val, 'operator' => 'and'];
        }
        return $filter;
    }

    public function addFilter($key, $val)
    {
        $this->filter[$key] = $val;
    }
    
    public function addSort($param, $sort)
    {
        $this->sort[$param] = ["order" => $sort];
    }
    
    public function setOffset($offset)
    {
        $this->offset = $offset;
    }
    
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }
    
    public function selectAll()
    {
        $query = [
            "query" => [
                "match_all" => new \stdClass()
            ]
        ];
        return json_encode($query);
    }
}