<?php

CModule::IncludeModule('amb.agreement');
IncludeModuleLangFile(__FILE__);


\Bitrix\Main\Loader::registerAutoLoadClasses(
        "amb.agreement",
        [
            "\Amb\Agreement\PullHandler" => "lib/pullhandler.php", // путь относительно папки модуля
            '\\Amb\\Agreement\\StatisticManager' => 'lib/statisticmanager.php',
            '\\Amb\\Agreement\\Controller\\AlertController' => 'lib/controller/alertcontroller.php',
        ]
);
