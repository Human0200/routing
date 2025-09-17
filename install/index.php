<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class bg_routing extends CModule
{
    public $MODULE_ID = "bg.routing";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = GetMessage("BG_MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("BG_MODULE_DESC");
        $this->PARTNER_NAME = GetMessage("BG_PARTNER_NAME");
        $this->PARTNER_URI = GetMessage("BG_PARTNER_URI");
    }

    public function InstallDB()
    {
        return true;
    }

    public function DoInstall()
    {
        global $APPLICATION;

        BXClearCache(true, '/bitrix/menu/');
        $GLOBALS['CACHE_MANAGER']->CleanDir('menu');

        if (!$this->InstallDB()) {
            $APPLICATION->ThrowException("Ошибка при установке базы данных модуля");
            return false;
        }

        if (!IsModuleInstalled($this->MODULE_ID)) {
            $this->InstallFiles();
            $this->InstallEvents();
            $this->InstallCron();
            ModuleManager::registerModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(
                Loc::getMessage("BG_INSTALL_TITLE"),
                __DIR__ . '/step1.php'
            );
        }
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallCron();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage("BG_UNINSTALL_TITLE"),
            __DIR__ . '/unstep1.php'
        );
    }

    public function InstallFiles()
    {

        return true;
    }

    public function UnInstallFiles()
    {

        return true;
    }

    public function InstallEvents()
    {
        return true;
    }

    public function UnInstallEvents()
    {
        return true;
    }

    public function InstallCron()
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);
        // Создаем нового агента с правильными параметрами
        $agentId = CAgent::AddAgent(
            "\\Background\\AgentFunctions\\Agent::execute();", // ОБРАТИТЕ ВНИМАНИЕ на двойной обратный слэш в начале!
            "bg.routing",                                      // явно указываем ID модуля
            "N",                                               // не повторять при ошибке
            300,                                               // интервал 300 секунд (5 минут)
            "",                                                // дата первой проверки
            "Y",                                               // активность
            "",                                                // дата первой проверки (устаревший параметр)
            100                                                // приоритет (рекомендую 100)
        );

        if ($agentId) {
            // Дополнительно: записываем ID агента в настройки модуля
            Option::set($this->MODULE_ID, "main_agent_id", $agentId);
        }

        return true;
    }

    public function UnInstallCron()
    {
        // Удаляем все агенты модуля
        CAgent::RemoveModuleAgents($this->MODULE_ID);
        return true;
    }
}
