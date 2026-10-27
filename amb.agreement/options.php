<?php

defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;

$moduleId = 'amb.agreement';
Bitrix\Main\Loader::includeModule($moduleId);

// Подключение дополнительных модулей
Loader::includeModule('iblock');

$factory = Container::getInstance()->getFactory(1082);

if ($factory && $factory->isStagesSupported()) {

    $stages = $factory->getStages()->getAll();

    foreach ($stages as $stage) {

        $dynamic_stages1082['REFERENCE_ID'][] = $stage->getStatusId();
        $dynamic_stages1082['REFERENCE'][] = $stage->getName();
    }
}

// Список инфоблоков
$arIBlocks = IblockTable::GetList()->FetchAll();
$iBlocks = array(
    'REFERENCE_ID' => array(),
    'REFERENCE' => array(),
);
foreach ($arIBlocks as $arIBlock) {
    $iBlocks['REFERENCE_ID'][] = $arIBlock['ID'];
    $iBlocks['REFERENCE'][] = "[{$arIBlock['ID']}] {$arIBlock['NAME']}";
}

// Основные настройки модуля (для всех сайтов одинаковые настройки)
// -----------------------------------------------------------------------------
// Вкладки
$arTabs = array(
    array(
        'DIV' => 'edit1',
        'TAB' => 'Настройки',
        'ICON' => '',
        'TITLE' => 'Настройки'
    ),
);

// Группы
$arGroups = array(
    'MAIN' => array('TITLE' => 'Основные настройки', 'TAB' => 0),
    'NOTIFY_OPTIONS' => array('TITLE' => 'Настройки Напоминаний', 'TAB' => 0),
    'STAGE_OPTIONS' => array('TITLE' => 'Настройки Этапов Согласования', 'TAB' => 0),
);

// Параметры внутри группы
$arOptions = array(
    'DBUG_ENABLED' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Включить отладку',
        'TYPE' => 'CHECKBOX',
        'DEFAULT' => 'N',
        'SORT' => '1',
        'NOTES' => ''
    ),
    'DBUG_PATH' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Полный Путь для Лога',
        'TYPE' => 'STRING',
        'DEFAULT' => '/home/bitrix/agreement_module.log',
        'SORT' => '2',
        'NOTES' => ''
    ),
    'SMART_TYPE_ID' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'ID Смарт процесса',
        'TYPE' => 'INTEGER',
        'DEFAULT' => '',
        'SORT' => '8',
    ),
    'HISTORY_IBLOCK_ID' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Инфоблок Истории',
        'TYPE' => 'SELECT',
        'VALUES' => $iBlocks,
        'DEFAULT' => '',
        'SORT' => '8',
    ),
    'PROJECT_IBLOCK_ID' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Инфоблок Проектов',
        'TYPE' => 'SELECT',
        'VALUES' => $iBlocks,
        'DEFAULT' => '',
        'SORT' => '8',
    ),
    'CFO_IBLOCK_ID' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Инфоблок ЦФО',
        'TYPE' => 'SELECT',
        'VALUES' => $iBlocks,
        'DEFAULT' => '',
        'SORT' => '8',
    ),
    'ORGANIZATION_IBLOCK_ID' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Инфоблок Моих Организаций',
        'TYPE' => 'SELECT',
        'VALUES' => $iBlocks,
        'DEFAULT' => '',
        'SORT' => '8',
    ),
    'TEMPLATE_IDS' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'ID шаблона согласования (через запятую)',
        'TYPE' => 'STRING',
        'DEFAULT' => '',
        'SORT' => '8',
    ),
    'CHAT_ID' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'ID чата для уведомления о согласованиях',
        'TYPE' => 'STRING',
        'DEFAULT' => '',
        'SORT' => '8',
    ),
    'topmanager_id' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Пользователи имеющие право пробить согласование',
        'MULTIPLE' => 'Y',
        'TYPE' => 'USER',
        'DEFAULT' => '',
        'SORT' => '9',
    ),
    'change_enable_users' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Пользователи имеющие право менять документ на согласовании',
        'MULTIPLE' => 'Y',
        'TYPE' => 'USER',
        'DEFAULT' => '',
        'SORT' => '10',
    ),
    'change_enable_stages' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Стадии на которых ответственный может менять документ',
        'MULTIPLE' => 'Y',
        'TYPE' => 'MSELECT',
        'VALUES' => $dynamic_stages1082,
        'DEFAULT' => '',
        'SORT' => '12',
    ),
    'agreement_bot_bot_id' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'ID Бота согласования',
        'TYPE' => 'INTEGER',
        'DEFAULT' => '',
        'SORT' => '11',
    ),
    'NOTIFY_PERIOD_DEADLINE' => array(
        'GROUP' => 'NOTIFY_OPTIONS',
        'TITLE' => 'Время в мин для напоминания о дедлайне',
        'TYPE' => 'INTEGER',
        'DEFAULT' => '60',
        'SORT' => '12',
        'NOTES' => 'Бот будет отправлять напоминалку о дедлайне за этот период'
    ),
    'STAGE_PERIOD_DEFAULT' => array(
        'GROUP' => 'STAGE_OPTIONS',
        'TITLE' => 'Срок согласования (по-умолчанию)',
        'TYPE' => 'INTEGER',
        'DEFAULT' => '24',
        'SORT' => '12',
        'NOTES' => 'Количество часов на каждый этап согласования'
    ),
    'agreement_period_work_hours' => array(
        'GROUP' => 'STAGE_OPTIONS',
        'TITLE' => 'Срок согласования (в рабочих часах)',
        'TYPE' => 'INTEGER',
        'DEFAULT' => '27',
        'SORT' => '12',
        'NOTES' => 'Количество часов для согласования на 1го сотрудника'
    ),
    'send_alert_to_user' => array(
        'GROUP' => 'NOTIFY_OPTIONS',
        'TITLE' => 'Показывать у пользователей баннеры с сообщениями о текущих согласованиях',
        'TYPE' => 'CHECKBOX',
        'DEFAULT' => 'N',
        'SORT' => '13',
    ),
    'alert_period' => array(
        'GROUP' => 'NOTIFY_OPTIONS',
        'TITLE' => 'Время через которое проверять текущие согласования',
        'TYPE' => 'CHECKBOX',
        'DEFAULT' => 'N',
        'SORT' => '14',
    ),
    'send_balloon_to_user' => array(
        'GROUP' => 'NOTIFY_OPTIONS',
        'TITLE' => 'Отправлять ответственным нотификации о согласовании',
        'TYPE' => 'CHECKBOX',
        'DEFAULT' => 'N',
        'SORT' => '15',
    ),
    'forgotten_statistic_users' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Пользователи получающие отчет по статистике согласований',
        'MULTIPLE' => 'Y',
        'TYPE' => 'USER',
        'DEFAULT' => '',
        'SORT' => '17',
    ),
    'chat_approve_users' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Пользователи согласующие в чате',
        'MULTIPLE' => 'Y',
        'TYPE' => 'USER',
        'DEFAULT' => '',
        'SORT' => '18',
    ),
);

$showRightsTab = true;

/*
  Конструктор класса ModuleOptions
  $moduleId - ID модуля
  $arTabs - массив вкладок с параметрами
  $arGroups - массив групп параметров
  $arOptions - собственно сам массив, содержащий параметры
  $showRightsTab - определяет надо ли показывать вкладку с настройками прав доступа к модулю ( true / false )
 */
$arButtons = [];
try {


// Вывод формы
    $Options = new \Amb\Agreement\ModuleOptions($moduleId, $arTabs, $arGroups, $arOptions, $showAccessTab, $arButtons);
    $Options->ShowHTML();
} catch (\Exception $exc) {
    echo $exc->getMessage();
}