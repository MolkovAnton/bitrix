<?php
namespace MA\Favorites;

use Bitrix\Main\Entity\DataManager;

/**
 * Interface DatabaseStorageInterface
 * @package MA\Favorites\Storage
 */
interface DatabaseStorageInterface extends StorageInterface
{
    /**
     * @return DataManager|string
     */
    public function getDataManager();
}