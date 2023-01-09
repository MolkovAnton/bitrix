<?php
namespace MA\Favorites;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Entity\DataManager;

/**
 * Class DatabaseStorage
 * @package MA\Favorites\Storage
 */
class DatabaseStorage extends AbstractStorage implements DatabaseStorageInterface
{
    /** @var DataManager|string */
    protected $dataManager;
    
    /**
     * DatabaseStorage constructor.
     * @param $userId
     */
    public function __construct($userId, $type)
    {
        $this->_setUserId($userId);
        $this->_setType($type);
        $this->dataManager = Favorites::getDataManager();
    }
    
    /**
     * @return DataManager|string
     */
    public function getDataManager()
    {
        return $this->dataManager;
    }
    
    /**
     * @param int $id
     * @return bool
     */
    public function has($id)
    {
        $id = (int)$id;
        
        if ($id < 1)
        {
            return false;
        }
        
        $table = $this->getDataManager();
        
        $arItem = $table::getList([
            'filter' => [
                '=UF_USER_ID' => $this->getUserId(),
                '=UF_TYPE' => $this->getType(),
                '=UF_ITEM_ID' => $id,
            ],
        ])->fetch();
        
        if ($arItem === false)
        {
            return false;
        }
        
        return true;
    }
    
    /**
     * @param int $id
     * @param bool $dupCheck
     * @return Result
     */
    public function add($id, $dupCheck = true)
    {
        $id = (int)$id;
        
        if ($id < 1)
        {
            return new Error("Argument 'id' is empty");
        }
        
        if ($dupCheck && $this->has($id))
        {
            return $result;
        }
        
        $table = $this->getDataManager();
        $fields = [
            'UF_ITEM_ID' => $id,
            'UF_TYPE' => $this->getType(),
            'UF_USER_ID' => $this->getUserId(),
            'UF_SITE' => SITE_ID,
        ];
        
        try
        {
            return $table::add($fields);
        }
        catch (\Exception $e)
        {
            return new Error($e->getMessage());
        }
    }
    
    /**
     * @param int $id
     * @return Result
     */
    public function delete($id)
    {
        $id = (int)$id;
        
        if ($id < 1)
        {
            return new Error("Argument 'id' is empty");
        }
        
        $table = $this->getDataManager();
        $elementId = $table::getList([
            'filter' => [
                '=UF_USER_ID' => $this->getUserId(),
                '=UF_TYPE' => $this->getType(),
                '=UF_ITEM_ID' => $id
            ],
            'select' => ['ID']
        ])->fetch()['ID'];
        
        try
        {
            return $table::delete($elementId);
        }
        catch (\Exception $e)
        {
            return new Error($e->getMessage());
        }
    }
    
    /**
     * @param array $parameters
     * @return int[]
     */
    public function getUserElements(array $parameters = [])
    {
        $parameters['filter']['=UF_USER_ID'] = $this->getUserId();
        $parameters['filter']['=UF_TYPE'] = $this->getType();
        $parameters['select'] = ['UF_ITEM_ID'];
        
        $table = $this->getDataManager();
        return array_column($table::getList($parameters)->fetchAll(), 'UF_ITEM_ID');
    }
    
    /**
     * @param array $parameters
     * @return string[]
     */
    public static function getList(array $parameters = []) {
        $table = Favorites::getDataManager();
        return $table::getList($parameters)->fetchAll();
    }

    /**
     * @return int
     */
    public function count()
    {
        $table = $this->getDataManager();

        return (int)$table::getCount([
            '=UF_USER_ID' => $this->getUserId(),
            '=UF_TYPE' => $this->getType(),
        ]);
    }
}