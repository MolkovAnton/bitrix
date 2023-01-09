<?php
defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use MA\Favorites\Favorites;

if (class_exists('MA_favorites'))
    return;

/**
 * Class MA_favorites
 */
class MA_favorites extends \CModule
{
    /** @var string id модуля */
    public $MODULE_ID = 'ma.favorites';
    
    /** @var string автор */
    public $PARTNER_NAME = 'MA';
    
    /**
     * MA_favorites constructor.
     */
    function __construct()
    {
        $this->MODULE_NAME = "Избранное";
        
        include __DIR__ . '/version.php';
        
        if (isset($arModuleVersion, $arModuleVersion['VERSION'], $arModuleVersion['VERSION_DATE']))
        {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }
    }
    
    /**
     * Установка модуля
     */
    public function DoInstall()
    {
        $this->InstallDB();
        
        ModuleManager::registerModule($this->MODULE_ID);
        
        $this->InstallEvents();
    }
    
    /**
     * Удаление модуля
     */
    public function DoUninstall()
    {
        $this->UnInstallEvents();
        $this->UnInstallDB();
        
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
 
    /**
     * Создание таблиц в базе данных
     */
    public function InstallDB()
    {
        if (!Loader::includeModule('highloadblock')) {
            return;
        }
        
        require_once(__DIR__ . "/../lib/favorites.php");
        
        $arTable = array(
            'NAME' => MA\Favorites\Favorites::HL_BLOCK,
            'TABLE_NAME' => MA\Favorites\Favorites::TABLE_NAME,
        );

        $addResult = HighloadBlockTable::add($arTable);

        if ($addResult->isSuccess()) {
            HighloadBlockLangTable::add(array(
                'ID' => $addResult->getId(),
                'LID' => 'ru',
                'NAME' => 'Избранное'
            ));

            HighloadBlockLangTable::add(array(
                'ID' => $addResult->getId(),
                'LID' => 'en',
                'NAME' => 'Favorites'
            ));

            $obUserField = new \CUserTypeEntity;

            $arLangFields = array("EDIT_FORM_LABEL", "LIST_COLUMN_LABEL", "LIST_FILTER_LABEL");
            $arUserFields = array(
				array("NAME" => "UF_ITEM_ID", "TYPE" => "integer", "MULTIPLE" => "N", "LANG_RU" => "ID товара", "LANG_EN" => "Product ID"),
                array("NAME" => "UF_TYPE", "TYPE" => "string", "MULTIPLE" => "N", "LANG_RU" => "Тип элемента", "LANG_EN" => "Element type"),
				array("NAME" => "UF_USER_ID", "TYPE" => "integer", "MULTIPLE" => "N", "LANG_RU" => "ID пользователя", "LANG_EN" => "User ID"),
                array("NAME" => "UF_SITE", "TYPE" => "string", "MULTIPLE" => "N", "LANG_RU" => "Сайт", "LANG_EN" => "Site"),
            );

            foreach ($arUserFields as $arUserField) {
                $arFields = array(
                    'ENTITY_ID' => 'HLBLOCK_' . $addResult->getId(),
                    'FIELD_NAME' => $arUserField["NAME"],
                    'USER_TYPE_ID' => $arUserField["TYPE"],
                    'XML_ID' => $arUserField["NAME"],
                    'SORT' => '100',
                    'MULTIPLE' => $arUserField["MULTIPLE"],
                    'MANDATORY' => 'Y',
                    'SHOW_FILTER' => 'E',
                    'SHOW_IN_LIST' => 'Y',
                    'EDIT_IN_LIST' => 'Y',
                    'IS_SEARCHABLE' => 'Y',
                    'SETTINGS' => array(
                        'DEFAULT_VALUE' => '',
                        'SIZE' => '60',
                        'ROWS' => '1',
                        'MIN_LENGTH' => '0',
                        'MAX_LENGTH' => '0',
                        'REGEXP' => '',
                    ),
                );

                foreach ($arLangFields as $langField) {
                    $arFields[$langField]["ru"] = $arUserField["LANG_RU"];
                    $arFields[$langField]["en"] = $arUserField["LANG_EN"];
                }

                $obUserField->Add($arFields);
            }
        } else {
            throw new Exception('Не удалось создать hl-block');
        }
    }
    
    /**
     * Удаление таблиц из базы данных
     */
    public function UnInstallDB()
    {
        if (!Loader::includeModule('highloadblock')) {
            return;
        }
        
        require_once(__DIR__ . "/../lib/favorites.php");
        
        $handlersHLBlock = HighloadBlockTable::getList(array(
            'filter' => array('NAME' => MA\Favorites\Favorites::HL_BLOCK),
            'cache' => array("ttl" => 3600 * 24 * 7)
        ))->fetch()['ID'];
        if ($handlersHLBlock > 0) {
            HighloadBlockTable::delete($handlersHLBlock);
        }
    }
    
    /**
     * Регистрация событий
     */
    public function InstallEvents()
    {
        $oEventManager = EventManager::getInstance();
        
        $oEventManager->registerEventHandler('main', 'OnAfterUserAuthorize',
            $this->MODULE_ID, Favorites::class, 'OnAfterUserAuthorize');
		
		$oEventManager->registerEventHandler('main', 'OnAfterUserLogin',
            $this->MODULE_ID, Favorites::class, 'OnAfterUserAuthorize');
		
		$oEventManager->registerEventHandler('main', 'OnAfterUserLoginByHash',
            $this->MODULE_ID, Favorites::class, 'OnAfterUserAuthorize');
    }
    
    /**
     * Удаление событий
     */
    public function UnInstallEvents()
    {
        $oEventManager = EventManager::getInstance();
        
        $oEventManager->unRegisterEventHandler('main', 'OnAfterUserAuthorize',
            $this->MODULE_ID, Favorites::class, 'OnAfterUserAuthorize');
		
		$oEventManager->unRegisterEventHandler('main', 'OnAfterUserLogin',
            $this->MODULE_ID, Favorites::class, 'OnAfterUserAuthorize');
		
		$oEventManager->unRegisterEventHandler('main', 'OnAfterUserLoginByHash',
            $this->MODULE_ID, Favorites::class, 'OnAfterUserAuthorize');
    }
}