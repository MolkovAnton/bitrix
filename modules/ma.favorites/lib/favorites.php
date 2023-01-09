<?php
namespace MA\Favorites;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Highloadblock\HighloadBlockTable;
/**
 * Class Favorites
 * @package MA\Favorites
 */
class Favorites
{
    const HL_BLOCK = 'MAFavorites';
    const TABLE_NAME = 'ma_favorites';
   
    /**
     * Событие после авторизации пользователя
     * Проверяем есть ли в сессии избранные элементы и переносим их в базу данных
     * @param $arFields
     */
    public static function OnAfterUserAuthorize(& $arFields)
    {
        try
        {
            $userId = (int)$arFields['user_fields']['ID'];

            if ($userId > 0)
            {
                static::insertFromLocalStorage($userId);
            }
        }
        catch (\Exception $e) {}
    }
    
    /**
     * @param int $userId
     * @return bool
     * @throws ArgumentNullException
     */
    protected static function insertFromLocalStorage($userId)
    {
        $userId = (int)$userId;
        
        if ($userId < 1)
        {
            throw new ArgumentNullException('userId');
        }
        
        $localStorage = new LocalStorage(1);
        $arFavorites = $localStorage->getAllItems();
        
        if (!empty($arFavorites))
        {
            self::addArrayToDatabase($arFavorites, $userId); 
        }
        
        return true;
    }
    
    /**
     * @param array $arFavorites
     * @param int $userId
     */
    private function addArrayToDatabase($arFavorites, $userId) {
        
        $databaseStorageArr = DatabaseStorage::getList(['filter' => ['=UF_USER_ID' => $userId]]);
        $formatedStorage = [];

        foreach ($databaseStorageArr as $item) {
            $formatedStorage[$item['UF_TYPE']][] = $item['UF_ITEM_ID'];
        }
        foreach ($arFavorites as $type => $arr) {
            $databaseStorage = new DatabaseStorage($userId, $type);
            foreach ($arr['ADD'] as $el) {
                if (!in_array($el, $formatedStorage[$type])) {
                    $databaseStorage->add($el, false);
                }
            }
            foreach ($arr['DELETE'] as $el) {
                if (in_array($el, $formatedStorage[$type])) {
                    $databaseStorage->delete($el, false);
                    unset($formatedStorage[$type][array_search($el, $formatedStorage[$type], true)]);
                }
            }
        }
        foreach ($formatedStorage as $type => $arr) {
            $localStorage = new LocalStorage($type);
            foreach ($arr as $el) {
                $localStorage->add($el);
            }
            $localStorage->flushDelete();
        }
    }

    /**
     * Возвращает класс таблицы hl-блока
     * @return string
     */
    public function getDataManager() {
        $hlblock = HighloadBlockTable::getList(['filter' => ['NAME' => self::HL_BLOCK], 'cache' => array("ttl" => 3600 * 24 * 7)])->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock)->getDataClass();
        return $entity;
    }
}