<?php

namespace Amb\Agreement;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Bitrix\Pull\Event;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\UI\Extension;
use Amb\Agreement\Db\AgreementHistory;

class StatisticManager extends Controller
{

    const MODULE_ID = 'amb.agreement';

    public static function getDbugPath()
    {
        return '/home/bitrix/agreement_statistic_module.log';
    }

    /* Получить текущие согласования у которых превышен дедлайн */

    public static function getForgottenHistory($userId = false)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $send_alert_to_user = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'send_alert_to_user', '') == 'Y') ? true : false;
        $dbug_path = self::getDbugPath();
        $agreement_period_work_hours = (int) \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'agreement_period_work_hours', 27);
        try {
            $Agreements = [];
            $templateIds = AgreementManager::getWorkFlowIds();
            $ormParams = [
                'filter' => [
                    'WORKFLOW_TEMPLATE_ID' => $templateIds,
                    '%DOCUMENT_ID' => "DYNAMIC_" . AgreementManager::getSmartTypeId() . "_",
                    'STATE' => "InProgress",
                ],
                'order' => ['ID' => 'DESC'],
                'limit' => 100,
            ];

            $wflows = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList($ormParams)->fetchAll();
//            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory wflows: " . print_r($wflows, true) . "\n", FILE_APPEND);
            $nowDate = new \Bitrix\Main\Type\DateTime(); // текущая дата
            $checkTime = $nowDate->add('-1 day');
            // Получаем рабочее время N часов назад
//            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory agreement_period_work_hours: " . print_r($agreement_period_work_hours, true) . "\n", FILE_APPEND);
//            $checkTime = self::getWorkingHoursAgo($agreement_period_work_hours, $nowDate, 8, 18);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory checkTime: " . print_r($checkTime, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory send_alert_to_user: " . var_export($send_alert_to_user, true) . "\n", FILE_APPEND);

            $histFilter = array(
                'status.value' => 0,
                '<DATE_CREATE' => $checkTime->format("d.m.Y H:i:0")
            );
            if ($userId) {
                $histFilter['agreementUser.value'] = (int) $userId;
            }
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory histFilter: " . print_r($histFilter, true) . "\n", FILE_APPEND);
            if ($wflows && is_array($wflows)) {
                foreach ($wflows as $wflow) {

//                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory find Current wf: " . print_r($wflow["ID"], true) . "\n", FILE_APPEND);
                    $histFilter['workflowId.value'] = $wflow["ID"];
                    $history = AgreementHistory::getByFilter($histFilter);
                    foreach ($history as $AgreementHistory) {
                        $Agreements[$AgreementHistory->agreementUser][] = $AgreementHistory;
                    }
                }
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory forgotten Agreements count: " . count($Agreements) . "\n", FILE_APPEND);
                return $Agreements;
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getForgottenHistory Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return [];
    }

    /* Получить текущие согласования у которых не загружен подписанный документ */

    public static function getNotSignedHistory()
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $forgottenSmarts = [];
        try {

            $nowDate = new \Bitrix\Main\Type\DateTime(); // текущая дата
//            $checkTime = $nowDate->add('-1 day');
            $checkTime = $nowDate->add('-1 day');

            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getNotSignedHistory checkTime: " . print_r($checkTime, true) . "\n", FILE_APPEND);

            $smartFilter = array(
                '=UF_CRM_7_SIGNED_DOGOVOR' => false,
                '<UF_CRM_7_APPROVE_END_TIME' => $checkTime->format("d.m.Y H:i:0")
            );

            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getNotSignedHistory histFilter: " . print_r($smartFilter, true) . "\n", FILE_APPEND);
            $smarts = AgreementManager::getSmartByFilter($smartFilter);
            if (!empty($smarts)) {
                foreach ($smarts as $smart) {

                    $forgottenSmarts[$smart["ASSIGNED_BY_ID"]][] = $smart;
                }
            }
            return $forgottenSmarts;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getNotSignedHistory Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return [];
    }

    /* Получить текущие согласования у которых согласования больше 3х часов */

    public static function getCurrentHistory($userId = false)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $send_alert_to_user = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'send_alert_to_user', '') == 'Y') ? true : false;
        $dbug_path = self::getDbugPath();
        try {

            $Agreements = [];
            $templateIds = AgreementManager::getWorkFlowIds();
            $ormParams = [
                'filter' => [
                    'WORKFLOW_TEMPLATE_ID' => $templateIds,
                    '%DOCUMENT_ID' => "DYNAMIC_" . AgreementManager::getSmartTypeId() . "_",
                    'STATE' => "InProgress",
                ],
                'order' => ['ID' => 'DESC'],
                'limit' => 100,
            ];

            $wflows = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList($ormParams)->fetchAll();
            $nowDate = new \Bitrix\Main\Type\DateTime(); // текущая дата
            $checkTime = $nowDate->add('-3 hour');

            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentHistory checkTime: " . print_r($checkTime, true) . "\n", FILE_APPEND);

            $histFilter = array(
                'status.value' => 0,
                '<DATE_CREATE' => $checkTime->format("d.m.Y H:i:0")
            );
            if ($userId) {
                $histFilter['agreementUser.value'] = (int) $userId;
            }
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentHistory histFilter: " . print_r($histFilter, true) . "\n", FILE_APPEND);
            if ($wflows && is_array($wflows)) {
                foreach ($wflows as $wflow) {

                    $histFilter['workflowId.value'] = $wflow["ID"];
                    $history = AgreementHistory::getByFilter($histFilter);
                    foreach ($history as $AgreementHistory) {
                        $Agreements[$AgreementHistory->agreementUser][] = $AgreementHistory;
                        if ($send_alert_to_user) {
                            self::saveAlertMessageToUser($AgreementHistory->agreementUser);
                        }
                    }
                }
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentHistory forgotten Agreements count: " . count($Agreements) . "\n", FILE_APPEND);
                return $Agreements;
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentHistory Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return [];
    }

    /**
     * Получить DateTime на N рабочих часов назад (расчет в часах)
     *
     * @param int $hoursBack Количество рабочих часов для вычитания
     * @param DateTime|null $startFrom Начальная дата (null для текущего времени)
     * @param int $startHour Час начала рабочего дня (например, 9)
     * @param int $endHour Час окончания рабочего дня (например, 18)
     * @return DateTime
     */
    public static function getWorkingHoursAgo(int $hoursBack, ?\DateTime $startFrom = null, int $startHour = 9, int $endHour = 18): \DateTime
    {
        $current = $startFrom ? clone $startFrom : new \DateTime();
        $dbug_path = self::getDbugPath();
        try {
            $current->setTime((int) $current->format('H'), 0, 0);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " startFrom: " . print_r($current, 1) . "\n", FILE_APPEND);
            // Длина рабочего дня в часах
            $workingHoursPerDay = $endHour - $startHour;
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " workingHoursPerDay: " . print_r($workingHoursPerDay, 1) . "\n", FILE_APPEND);
            $k = 0;
            while ($hoursBack > 0 && $k < 1000) {
                $k++;
                // 1. Проверка на выходные (суббота = 6, воскресенье = 7)
                $dayOfWeek = (int) $current->format('N');
                if ($dayOfWeek > 5) {
                    $current->modify('last friday');
                    $current->setTime($endHour, 0, 0);
                    continue;
                }

                // Текущий час
                $currentHour = (int) $current->format('H');

                // 2. Корректировка, если текущее время вне рабочего диапазона
                if ($currentHour > $endHour) {
                    $current->setTime($endHour, 0, 0);
                    $currentHour = $endHour;
                }

                if ($currentHour <= $startHour) {
                    $current->modify('-1 day');
                    $current->setTime($endHour, 0, 0);
                    continue;
                }

                // 3. Сколько рабочих часов доступно в текущих сутках (от startHour до текущего часа)
                $availableHoursInDay = $currentHour - $startHour;

                if ($hoursBack >= $availableHoursInDay) {
                    // Вычитаем все доступные часы этого дня и прыгаем на конец предыдущего дня
                    $hoursBack -= $availableHoursInDay;
                    $current->modify('-1 day');
                    $current->setTime($endHour, 0, 0);
                } else {
                    // Оставшихся часов в дне достаточно для завершения расчета
                    $current->modify("-{$hoursBack} hours");
                    $hoursBack = 0;
                }
            }

            // Финальная проверка на выходной после завершения цикла
            $dayOfWeek = (int) $current->format('N');
            if ($dayOfWeek > 5) {
                $current->modify('last friday');
                $current->setTime($endHour, 0, 0);
            }

            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " get working time: " . print_r($current, 1) . "\n", FILE_APPEND);
            return $current;
        } catch (\Exception $exc) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " Exception: " . print_r($exc->getMessage, 1) . "\n", FILE_APPEND);
            echo $exc->getMessage();
        }
        return $current;
    }

    public static function sendNotSignMessageToUsers($arSmarts)
    {
        $dbug_path = '/home/bitrix/sendNotSignMessageToUsers.log';
        $dbug_path = self::getDbugPath();
        try {
            if (!empty($arSmarts) && is_array($arSmarts)) {
                foreach ($arSmarts as $userId => $smarts) {

                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendNotSignMessageToUsers userId: " . var_export($userId, 1) . "\n", FILE_APPEND);
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendNotSignMessageToUsers count: " . count($smarts) . "\n", FILE_APPEND);
                    $text = Botmessages::getNotSignText($smarts);
                    $params = Array(
                        'DIALOG_ID' => $userId,
//                        'DIALOG_ID' => 116,
                        'MESSAGE' => $text,
                    );
//                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "params: " . print_r($params, 1) . "\n", FILE_APPEND);
                    if ($messageId = \Amb\Agreement\Bot::sendMessage($params)) {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendNotSignMessageToUsers OK_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                    } else {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendNotSignMessageToUsers ERROR_NO_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                    }
                }
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendNotSignMessageToUsers Exception: " . print_r($e->getMessage(), 1) . "\n", FILE_APPEND);
        }
        return null;
    }

    public static function sendForgottenMessageToUsers($UserAgreements)
    {
        $dbug_path = '/home/bitrix/sendForgottenMessageToUser.log';
        $dbug_path = self::getDbugPath();
        try {
            if (!empty($UserAgreements) && is_array($UserAgreements)) {
                foreach ($UserAgreements as $userId => $agreements) {

                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser userId: " . var_export($userId, 1) . "\n", FILE_APPEND);
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser count: " . count($agreements) . "\n", FILE_APPEND);
                    $text = Botmessages::getForgottenApproveText($agreements);
                    $params = Array(
                        'DIALOG_ID' => $userId,
//                        'DIALOG_ID' => 116,
                        'MESSAGE' => $text,
                    );
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser params: " . print_r($params, 1) . "\n", FILE_APPEND);
                    if ($messageId = \Amb\Agreement\Bot::sendMessage($params)) {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser OK_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                    } else {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser ERROR_NO_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                    }
                }
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser Exception: " . print_r($e->getMessage(), 1) . "\n", FILE_APPEND);
        }
        return null;
    }

    public static function sendForgottenStatistic($UserAgreements)
    {
        $forgotten_statistic_users = \Bitrix\Main\Config\Option::get('amb.agreement', 'forgotten_statistic_users', []);
        $forgotten_statistic_users = unserialize($forgotten_statistic_users);
        $dbug_path = self::getDbugPath();
        try {
            if (!empty($UserAgreements) && is_array($UserAgreements)) {
                $statText = Botmessages::getForgottenStatisticText($UserAgreements);
                if (!empty($forgotten_statistic_users)) {
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser forgotten_statistic_users: " . var_export($forgotten_statistic_users, 1) . "\n", FILE_APPEND);
                    foreach ($forgotten_statistic_users as $stat_user_id) {

                        $statParams = Array(
                            'DIALOG_ID' => $stat_user_id,
                            'MESSAGE' => $statText,
                        );
                        if ($messageId = \Amb\Agreement\Bot::sendMessage($statParams)) {
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser OK_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                        } else {
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser ERROR_NO_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser Exception: " . print_r($e->getMessage(), 1) . "\n", FILE_APPEND);
        }
        return null;
    }

    public static function saveAlertMessageToUser($targetUserId)
    {
        $dbug_path = self::getDbugPath();
        $alertOptionName = 'forgotten_approve_alert_active';
        try {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveAlertMessageToUser targetUserId: " . print_r($targetUserId, 1) . "\n", FILE_APPEND);
            $alert = \CUserOptions::GetOption(
                    self::MODULE_ID,
                    $alertOptionName,
                    false, // Значение по умолчанию, если записи в базе ещё нет
                    $targetUserId  // ID сотрудника (если не указать, проверит для текущего)
            );
            if ($alert) {
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveAlertMessageToUser user already have alert: " . print_r($alert, 1) . "\n", FILE_APPEND);
            } else {
                \CUserOptions::SetOption(
                        self::MODULE_ID, // Категория (произвольный идентификатор, например 'mymodule')
                        $alertOptionName, // Название настройки (ключ)
                        'Y', // Значение (может быть строкой, числом или даже массивом)
                        false, // false — для конкретного пользователя, true — для ВСЕХ пользователей
                        $targetUserId                             // ID сотрудника (если не указать, применится к текущему авторизованному)
                );
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveAlertMessageToUser Exception: " . print_r($e->getMessage(), 1) . "\n", FILE_APPEND);
        }
        return null;
    }

    public static function checkAlertMessageToUser()
    {
        $dbug_path = self::getDbugPath();
        try {
            if (
                    (defined('BX_WITH_SERVER_SERVICE') && BX_WITH_SERVER_SERVICE === true) ||
                    (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] === 'y') ||
                    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            ) {
                return;
            }

            if (!in_array($_SERVER['REQUEST_URI'], ['/online/', '/stream/'])) {
                return;
            }


            // Запрещаем вывод в административной панели
            if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
                return;
            }
            // Уникальный идентификатор  алерта 
            $alertOptionName = 'forgotten_approve_alert_active';
            $targetUserId = \Bitrix\Main\Engine\CurrentUser::get();
            if ($targetUserId) {
                $targetUserId = $targetUserId->getId();
//                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " checkAlertMessageToUser targetUserId: " . print_r($targetUserId, 1) . "\n", FILE_APPEND);
                Loader::includeModule('amb.agreement');
                $alert = \CUserOptions::GetOption(
                        self::MODULE_ID,
                        $alertOptionName,
                        'N', // Значение по умолчанию, если записи в базе ещё нет
                        $targetUserId  // ID сотрудника (если не указать, проверит для текущего)
                );

                if ($alert && $alert === 'Y') {
                    // Подключаем библиотеку ui.alerts и ядро для AJAX
                    Extension::load(["ui.alerts", "core"]);

                    // Выводим скрипт прямо в поток через echo
                    echo "
                            <script>
                                BX.ready(function() {
                                    // Создаем алерт
                                    var myAlert = new BX.UI.Alert({
                                        text: '<strong>Внимание!</strong> У вас есть просроченные согласования.',
                                        color: BX.UI.Alert.Color.WARNING,
                                        icon: BX.UI.Alert.Icon.WARNING,
                                        closeBtn: true
                                    });
                                    var alertNode = myAlert.render(); 
                                    var closeBtn = alertNode.querySelector('.ui-alert-close-btn');
                                    if (closeBtn) {
                                        BX.bind(closeBtn, 'click', function() {
                                            console.log('deleteAlertOnUser');

                                            BX.ajax.runAction('amb:agreement.api.alert.deleteAlertOnUser', {
                                                method: 'POST',
                                                data: {
                                                    userId: " . $targetUserId . "
                                                }
                                            }).then(function(response) {
                                                // Сюда попадаем, если сервер вернул статус 'success'
                                                console.log('success:', response.data);
                                            }, function(response) {
                                                // Сюда попадаем при ошибках (неверный sessid, падение PHP, addError в контроллере)
                                                console.error('error:', response.errors);
                                            });
                                        });
                                    }
                             
                                    // Поиск контейнера на странице
                                    var container = 
                                        document.querySelector('.crm-entity-wrap') || 
                                        document.getElementById('workarea-content') || 
                                        document.querySelector('.workarea-content-paddings');

                                    if (container) {
                                        container.insertBefore(alertNode, container.firstChild);
                                    } else {
                                        // Если стандартная верстка страницы еще не прогрузилась или отсутствует,
                                        // выводим алерт поверх интерфейса (фиксированная плашка)
                                        alertNode.style.position = 'fixed';
                                        alertNode.style.top = '75px';
                                        alertNode.style.left = '5%';
                                        alertNode.style.width = '90%';
                                        alertNode.style.zIndex = '9999';
                                        document.body.insertBefore(alertNode, document.body.firstChild);
                                    }
                                });
                            </script>
                            ";
                }
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendForgottenMessageToUser Exception: " . print_r($e->getMessage(), 1) . "\n", FILE_APPEND);
        }
        return null;
    }

    public static function deleteAlertOnUser()
    {
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $dbug_path = self::getDbugPath();
        try {
            $userIdToDelete = (int) $request->getPost('userId');
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " handleAlertDeleteAjaxOnStart serIdToDelete: " . print_r($userIdToDelete, 1) . "\n", FILE_APPEND);
            $targetUserId = \Bitrix\Main\Engine\CurrentUser::get();
            if ($targetUserId) {
                $targetUserId = $targetUserId->getId();
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " handleAlertDeleteAjaxOnStart targetUserId: " . print_r($targetUserId, 1) . "\n", FILE_APPEND);

                if ($userIdToDelete === $targetUserId) {
                    \CUserOptions::DeleteOption(self::MODULE_ID, 'forgotten_approve_alert_active', $userIdToDelete);

                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    die();
                }
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " handleAlertDeleteAjaxOnStart Exception: " . print_r($e->getMessage(), 1) . "\n", FILE_APPEND);
        }
        return null;
    }
}
