<?php
namespace MA\Favorites;

use Bitrix\Main\Result;

/**
 * Interface DatabaseStorageInterface
 * @package MA\Favorites\Storage
 */
interface LocalStorageInterface extends StorageInterface
{
    /**
     * @return string[]
     */
    public function getAllTypes();
    
    /**
     * @return array
     */
    public function getAllItems();
	
	/**
     * @param int $id
     */
	public function markDelete($id);
	
	/**
     * @param int $id
     */
	public function unMarkDelete($id);
	
	public function flushDelete();
}