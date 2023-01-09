<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class ma_eventhandlers extends CModule
{
    public function __construct()
    {

        if (file_exists(__DIR__ . "/version.php")) {

            $arModuleVersion = array();

            include(__DIR__ . "/version.php");
            include_once(__DIR__ . "/handlersinstall.php");

            $this->MODULE_ID = str_replace("_", ".", get_class($this));
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
            $this->MODULE_NAME = Loc::getMessage("MA_EVENTHANDLERS_NAME");
            $this->MODULE_DESCRIPTION = Loc::getMessage("MA_EVENTHANDLERS_DESCRIPTION");
            $this->PARTNER_NAME = Loc::getMessage("MA_EVENTHANDLERS_PARTNER_NAME");
            $this->PARTNER_URI = Loc::getMessage("MA_EVENTHANDLERS_PARTNER_URI");
        }

        return false;
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (CheckVersion(ModuleManager::getVersion("main"), "14.00.00")) {

            $this->InstallFiles();
            $this->InstallDB();
            $this->InstallEvents();
        } else {

            $APPLICATION->ThrowException(
                Loc::getMessage("MA_EVENTHANDLERS_INSTALL_VERSION_ERROR")
            );
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage("MA_EVENTHANDLERS_INSTALL_TITLE") . " \"" . Loc::getMessage("MA_EVENTHANDLERS_NAME") . "\"",
            __DIR__ . "/step.php"
        );

        return false;
    }

    public function InstallFiles()
    {
        return false;
    }

    public function InstallDB()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        HandlersInstall::createHandlersHighloadblock(true);

        return false;
    }

    public function InstallEvents()
    {
        EventManager::getInstance()->registerEventHandler(
            "",
            "HandlersOnBeforeUpdate",
            $this->MODULE_ID,
            "MA\Eventhandlers\Handlers",
            "clearCache"
        );

        EventManager::getInstance()->registerEventHandler(
            "",
            "HandlersOnBeforeDelete",
            $this->MODULE_ID,
            "MA\Eventhandlers\Handlers",
            "clearCache"
        );

        EventManager::getInstance()->registerEventHandler(
            "",
            "HandlersOnBeforeAdd",
            $this->MODULE_ID,
            "MA\Eventhandlers\Handlers",
            "clearCache"
        );
        
        RegisterModuleDependences("main", "OnBeforeSiteAdd", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "setSiteListToUpdate");
        RegisterModuleDependences("main", "OnBeforeSiteUpdate", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "setSiteListToUpdate");
        RegisterModuleDependences("main", "OnSiteDelete", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "deleteSiteFromList");
        RegisterModuleDependences("main", "OnAdminListDisplay", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "updateSiteList");
        
        return false;
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB();

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage("MA_EVENTHANDLERS_UNINSTALL_TITLE") . " \"" . Loc::getMessage("MA_EVENTHANDLERS_NAME") . "\"",
            __DIR__ . "/unstep.php"
        );

        return false;
    }

    public function UnInstallFiles()
    {
        return false;
    }

    public function UnInstallDB()
    {
        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);
        HandlersInstall::deleteHandlersHighloadblock();

        return false;
    }

    public function UnInstallEvents()
    {
        EventManager::getInstance()->unRegisterEventHandler(
            "",
            "HandlersOnBeforeUpdate",
            $this->MODULE_ID,
            "MA\Eventhandlers\Handlers",
            "clearCache"
        );

        EventManager::getInstance()->unRegisterEventHandler(
            "",
            "HandlersOnBeforeDelete",
            $this->MODULE_ID,
            "MA\Eventhandlers\Handlers",
            "clearCache"
        );

        EventManager::getInstance()->unRegisterEventHandler(
            "",
            "HandlersOnBeforeAdd",
            $this->MODULE_ID,
            "MA\Eventhandlers\Handlers",
            "clearCache"
        );
        
        UnRegisterModuleDependences("main", "OnBeforeSiteAdd", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "setSiteListToUpdate");
        UnRegisterModuleDependences("main", "OnBeforeSiteUpdate", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "setSiteListToUpdate");
        UnRegisterModuleDependences("main", "OnSiteDelete", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "deleteSiteFromList");
        UnRegisterModuleDependences("main", "OnAdminListDisplay", $this->MODULE_ID, "\MA\Eventhandlers\Handlers", "updateSiteList");

        \Bitrix\Main\Config\Option::delete($this->MODULE_ID, ['name'=>'site_list_update']);
        return false;
    }
}