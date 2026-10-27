<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
require_once '/home/bitrix/www/local/modules/amb.agreement/lib/bot.php';

class amb_agreement extends CModule
{

    var $MODULE_ID = 'amb.agreement';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;

    function __construct()
    {
        $arModuleVersion = array();

        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen('/index.php'));
        include($path . '/version.php');
        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_NAME = 'AMB Agreement Module'; // Название модуля
        $this->PARTNER_NAME = 'AmbLogic';
        $this->MODULE_DESCRIPTION = 'AMB модуль согласований Договоров'; // Описание модуля
    }

    function DoInstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        RegisterModule($this->MODULE_ID);
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler(
                "bizproc",
                "OnTaskAdd",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskAddHandler'
        );
        $eventManager->registerEventHandler(
                "bizproc",
                "OnTaskUpdate",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskUpdateHandler'
        );
        $eventManager->registerEventHandler(
                "bizproc",
                "OnTaskMarkCompleted",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskMarkCompletedHandler'
        );
        $eventManager->registerEventHandler(
                "bizproc",
                "OnTaskDelegate",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskDelegateHandler'
        );
        $eventManager->registerEventHandler(
                "bizproc",
                "OnTaskDelete",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskDeleteHandler'
        );

//        $botId = \Amb\Agreement\Bot::register();
        $APPLICATION->IncludeAdminFile('Установка модуля', $DOCUMENT_ROOT . '/local/modules/' . $this->MODULE_ID . '/install/step.php');

        return true;
    }

    function DoUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
                "bizproc",
                "OnTaskAdd",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskAddHandler'
        );
        $eventManager->unRegisterEventHandler(
                "bizproc",
                "OnTaskUpdate",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskUpdateHandler'
        );
        $eventManager->unRegisterEventHandler(
                "bizproc",
                "OnTaskMarkCompleted",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskMarkCompletedHandler'
        );
        $eventManager->unRegisterEventHandler(
                "bizproc",
                "OnTaskDelegate",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskDelegateHandler'
        );
        $eventManager->unRegisterEventHandler(
                "bizproc",
                "OnTaskDelete",
                $this->MODULE_ID,
                '\Amb\Agreement\AgreementManager',
                'taskDeleteHandler'
        );
//        $res = \Amb\Agreement\Bot::unRegister();
        UnRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile('Удаление модуля', $DOCUMENT_ROOT . '/local/modules/' . $this->MODULE_ID . '/install/unstep.php');
        return true;
    }

}
