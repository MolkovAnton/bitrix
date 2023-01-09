<?php
namespace MA\Favorites;

use Bitrix\Main\ArgumentNullException;

/**
 * Class AbstractStorage
 * @package MA\Favorites\Storage
 */
abstract class AbstractStorage implements StorageInterface
{
    /** @var int */
    private $userId = null;
    
    /** @var int */
    protected $type = null;
    
    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }
    
    /**
     * @param int $id
     * @return $this
     * @throws ArgumentNullException
     */
    protected function _setUserId($id)
    {
        $id = (int)$id;
        
        if ($id < 1)
        {
            throw new ArgumentNullException('id');
        }
        
        $this->userId = $id;
        return $this;
    }
    
    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }
    
    /**
     * @param int $type
     * @return $this
     * @throws ArgumentNullException
     */
    protected function _setType($type)
    {
        $type = trim($type);
        
        if (!$type)
        {
            throw new ArgumentNullException('type');
        }
        
        $this->type = $type;
        return $this;
    }
}