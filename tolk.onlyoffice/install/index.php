<?php
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

class tolk_onlyoffice extends CModule
{
    public $MODULE_ID = 'tolk.onlyoffice';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'ONLYOFFICE для Диска';
    public $MODULE_DESCRIPTION = 'Редактирование файлов Диска через собственный ONLYOFFICE Document Server';
    public $PARTNER_NAME = 'ТОЛК';
    public $PARTNER_URI = '';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall()
    {
        global $APPLICATION;
        if (!ModuleManager::isModuleInstalled('disk')) {
            $APPLICATION->ThrowException('Требуется установленный модуль «Диск» (disk)');
            return false;
        }
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
        if (Option::get($this->MODULE_ID, 'link_secret', '') === '') {
            Option::set($this->MODULE_ID, 'link_secret', bin2hex(random_bytes(32)));
        }
        return true;
    }

    public function DoUninstall()
    {
        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB();
        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

    public function InstallDB()
    {
        // Хранит ключ сессии редактирования: он не должен меняться при forcesave,
        // иначе новые участники попадут в отдельную сессию.
        // Данные в таблице временные (живые сессии), поэтому при переустановке пересоздаём.
        $c = Application::getConnection();
        $c->queryExecute('DROP TABLE IF EXISTS tolk_onlyoffice_key');
        $c->queryExecute(
            'CREATE TABLE tolk_onlyoffice_key (
                FILE_ID INT NOT NULL PRIMARY KEY,
                B_FILE_ID INT NOT NULL,
                DOC_KEY VARCHAR(128) NOT NULL,
                LAST_SEEN INT NOT NULL DEFAULT 0
            )'
        );
        return true;
    }

    public function UnInstallDB()
    {
        Application::getConnection()->queryExecute('DROP TABLE IF EXISTS tolk_onlyoffice_key');
        return true;
    }

    public function InstallEvents()
    {
        EventManager::getInstance()->registerEventHandler(
            'main', 'OnProlog', $this->MODULE_ID,
            '\\Tolk\\OnlyOffice\\EventHandler', 'onProlog'
        );
        // Снимает «зависшие» блокировки, если Document Server не прислал закрытие сессии.
        \CAgent::AddAgent('\\Tolk\\OnlyOffice\\Agent::cleanup();', $this->MODULE_ID, 'N', 600);
        return true;
    }

    public function UnInstallEvents()
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main', 'OnProlog', $this->MODULE_ID,
            '\\Tolk\\OnlyOffice\\EventHandler', 'onProlog'
        );
        \CAgent::RemoveModuleAgents($this->MODULE_ID);
        return true;
    }

    public function InstallFiles()
    {
        $root = Application::getDocumentRoot();
        CopyDirFiles(__DIR__ . '/public', $root . '/onlyoffice', true, true);
        CopyDirFiles(__DIR__ . '/js', $root . '/bitrix/js', true, true);
        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFilesEx('/onlyoffice');
        DeleteDirFilesEx('/bitrix/js/tolk.onlyoffice');
        return true;
    }
}
