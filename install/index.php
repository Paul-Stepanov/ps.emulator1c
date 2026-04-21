<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;

class ps_emulator1c extends CModule
{
    public $MODULE_ID = "ps.emulator1c";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . "/../version.php");

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = "Эмулятор выгрузки 1С";
        $this->MODULE_DESCRIPTION = "Модуль для эмуляции выгрузки файлов из 1С";
    }

    public function DoInstall(): bool
    {
        global $APPLICATION;

        if (!IsModuleInstalled($this->MODULE_ID)) {
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();

            ModuleManager::registerModule($this->MODULE_ID);
        }

        $APPLICATION->IncludeAdminFile(
            "Установка модуля " . $this->MODULE_ID,
            __DIR__ . "/step.php"
        );

        return true;
    }

    public function DoUninstall(): bool
    {
        global $APPLICATION;

        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            "Деинсталляция модуля " . $this->MODULE_ID,
            __DIR__ . "/unstep.php"
        );

        return true;
    }

    public function InstallDB(): bool
    {
        global $DB;
        $DB->StartTransaction();

        $sql = file_get_contents(__DIR__ . '/db/mysql/install.sql');
        $result = $DB->Query($sql);

        if (!$result) {
            $DB->Rollback();
            return false;
        }

        $DB->Commit();
        return true;
    }

    public function UnInstallDB(): bool
    {
        global $DB;
        $DB->StartTransaction();

        $sql = file_get_contents(__DIR__ . '/db/mysql/uninstall.sql');
        $result = $DB->Query($sql);

        if (!$result) {
            $DB->Rollback();
            return false;
        }

        $DB->Commit();
        return true;
    }

    public function InstallEvents(): bool
    {
        return true;
    }

    public function UnInstallEvents(): bool
    {
        return true;
    }

    public function InstallFiles(): bool
    {
        // Копируем административные скрипты
        CopyDirFiles(
            __DIR__ . '/../admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );
        
        // Создаем директорию для ассетов
        $assetsJsDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID;
        $assetsCssDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID;

        if (!file_exists($assetsJsDir) && !mkdir($assetsJsDir, 0755, true) && !is_dir($assetsJsDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $assetsJsDir));
        }

        if (!file_exists($assetsCssDir) && !mkdir($assetsCssDir, 0755, true) && !is_dir($assetsCssDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $assetsCssDir));
        }
        
        // Копируем JavaScript файлы
        CopyDirFiles(
            __DIR__ . '/assets/js',
            $assetsJsDir,
            true,
            true
        );
        
        // Копируем CSS файлы
        CopyDirFiles(
            __DIR__ . '/assets/css',
            $assetsCssDir,
            true,
            true
        );
        
        return true;
    }

    public function UnInstallFiles(): bool
    {
        // Удаляем административные скрипты
        DeleteDirFiles(
            __DIR__ . '/../admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );
        
        // Удаляем директорию с ассетами
        $assetsJsDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID;
        $assetsCssDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID;

        if (file_exists($assetsJsDir)) {
            DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID);
        }

        if (file_exists($assetsCssDir)) {
            DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID);
        }
        
        return true;
    }
}