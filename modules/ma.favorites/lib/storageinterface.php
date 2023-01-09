<?php
namespace MA\Favorites;

use Bitrix\Main\Result;

/**
 * Interface StorageInterface
 * @package MA\Favorites\Storage
 */
interface StorageInterface extends \Countable
{ 
    /**
     * @return int
     */
    public function getUserId();
    
    /**
     * @return int
     */
    public function getType();
    
    /**
     * @param array $parameters
     * @return array
     */
    public function getUserElements(array $parameters = []);
    
    /**
     * @param int $id
     * @return bool
     */
    public function has($id);
    
    /**
     * @param int $id
     * @return Result
     */
    public function add($id);
    
    /**
     * @param int $id
     * @return Result
     */
    public function delete($id);
}