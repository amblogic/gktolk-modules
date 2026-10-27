<?php

namespace Amb\Agreement;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Type;
use Bitrix\Main\Web\HttpClient;
use Amb\Agreement\Db\AgreementObject;
use Amb\Agreement\Db\AgreementHistory;
use Bitrix\Main\Loader;
use Bitrix\Crm\Service;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Bizproc\Workflow\Task\TaskUserTable;

Loader::includeModule("crm");
Loader::includeModule("bizproc");

//require_once '/home/bitrix/www/vendor/autoload.php';
class AgreementManager
{

    const MODULE_ID = 'amb.agreement';
    public const IBLOCK_ID = 20;
    public const MY_COMPANY_IBLOCK_ID = 20;
//    public const SMART_TYPE_ID = 1040;
    public const SMART_TYPE_ID = 1082;
    public const TEMPLATE_ID = 122;
    public const APPROVE_RESULT = 254;
    public const REJECT_RESULT = 255;

    public $dbug = false;
    public $dbug_path = null;
    public $domain = null;
    public $token = null;
    public $sms_token = null;
    public $chat_id = null;
    public $manager_id = null;
    public $company_phone = null;
    public $sms_enabled = false;

    public const BX_STATUS = [
        0 => "",
        1 => 254,
        2 => 255,
        9 => 645,
    ];
//    public const AGR_COMPLETE_STATUS = [
//        'DT1040_8:1' => 'Согласовано. Подписание',
//        'DT1040_8:SUCCESS' => 'Подписано. Сохранено в Реестр',
//    ];
//    public const AGR_ACTIVE_STATUS = [
//        'DT1040_8:NEW' => 'Не согласовано',
//        'DT1040_8:PREPARATION' => 'Согласование Юрист',
//        'DT1040_8:CLIENT' => 'Согласование службами',
//        'DT1040_8:1' => 'Согласовано. Подписание',
//    ];
    public const AGR_EDIT_STATUSES = [
        'DT1082_15:NEW' => 'Черновик',
        'DT1082_15:PREPARATION' => 'Доработка',
    ];
    public const AGR_COMPLETE_STATUS = [
        'DT1082_15:4' => 'Согласован',
        'DT1082_15:3' => 'Подготовка к подписанию',
        'DT1082_15:5' => 'Контроль возврата инициатором',
        'DT1082_15:SUCCESS' => 'Действующий',
    ];
    public const AGR_ACTIVE_STATUS = [
        'DT1082_15:NEW' => 'Черновик',
        'DT1082_15:PREPARATION' => 'Доработка',
        'DT1082_15:CLIENT' => 'Согласование Службами',
        'DT1082_15:1' => 'Согласование Юридическим отделом',
        'DT1082_15:2' => 'Согласование Генеральным Директором',
        'DT1082_15:4' => 'Согласован',
        'DT1082_15:3' => 'Подготовка к подписанию',
        'DT1082_15:5' => 'Контроль возврата инициатором',
    ];
    public const BX_STATUS_TEXT = [
        0 => "Новый",
        1 => "Утвержден",
        2 => "Отклонен",
        9 => "Прерван",
    ];
    public const BX_APPROVE_TEXT = [
        0 => "Новый",
        1 => "Утвердил",
        2 => "Отклонил",
    ];
    public const BX_BUTTON_STATUSES = [
        0 => "base",
        1 => "success",
        2 => "alert",
    ];
    public const SERVICE_COMMENTS_FIELD_ID = "UF_CRM_11_SERVICE_COMMENTS";
    public const SERVICE_END_FIELD_ID = "UF_CRM_11_SERVICE_APPROOVE_END";
    public const STATUS_POINTS_FIELD_ID = "UF_CRM_11_PROCESS_POINT";

    public function __construct()
    {
        $this->chat_id = Option::get(self::MODULE_ID, 'CHAT_ID', '11946');
        $this->manager_id = Option::get(self::MODULE_ID, 'MANAGER_ID', '44');
        $this->dbug = (Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $this->dbug = 1;
        $this->dbug_path = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_PATH', '/home/bitrix/agreement_module.log');

        date_default_timezone_set('Asia/Yekaterinburg');
    }

    public static function getDbugPath()
    {
        return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_PATH', '/home/bitrix/agreement_module.log');
    }

    public static function getSmartTypeId()
    {
        return (int) \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'SMART_TYPE_ID', self::SMART_TYPE_ID);
    }

    public static function getHistoryIblockId()
    {
        return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'HISTORY_IBLOCK_ID', false);
    }

    public static function getProjectIblockId()
    {
        return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'PROJECT_IBLOCK_ID', false);
    }

    public static function getCfoIblockId()
    {
        return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'CFO_IBLOCK_ID', false);
    }

    public static function getOrganizationIblockId()
    {
        return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ORGANIZATION_IBLOCK_ID', false);
    }

    public static function getWorkFlowIds()
    {
        $template_str = \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'TEMPLATE_IDS', "");
        return explode(",", $template_str);
    }

    public static function getTemplateProcessId()
    {
        return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'TEMPLATE_ID', self::TEMPLATE_ID);
    }

    public static function getCurrentWorkflow($smartId, $userId = false)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $templateIds = self::getWorkFlowIds();
        $ormParams = [
            'filter' => [
                'WORKFLOW_TEMPLATE_ID' => $templateIds,
                'DOCUMENT_ID' => "DYNAMIC_" . self::getSmartTypeId() . "_" . $smartId,
                'STATE' => "InProgress",
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ];
//        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflow filter: " . print_r($ormParams, true) . "\n", FILE_APPEND);
        $wf = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList($ormParams)->fetch();
        if ($wf && isset($wf["ID"])) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflow find Current wf: " . print_r($wf["ID"], true) . "\n", FILE_APPEND);
            return $wf["ID"];
        }
        $ormParams = [
            'filter' => [
                'WORKFLOW_TEMPLATE_ID' => $templateIds,
                'DOCUMENT_ID' => "DYNAMIC_" . self::getSmartTypeId() . "_" . $smartId,
                'STATE' => "Completed",
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ];
//        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflow filter: " . print_r($ormParams, true) . "\n", FILE_APPEND);
        $wf = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList($ormParams)->fetch();
        if ($wf && isset($wf["ID"])) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflow find Complete wf: " . print_r($wf["ID"], true) . "\n", FILE_APPEND);
            return $wf["ID"];
        }
        return "";
    }

    public static function getCurrentWorkflowInProgress($smartId, $current = false)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $templateIds = self::getWorkFlowIds();
        $ormParams = [
            'filter' => [
                'WORKFLOW_TEMPLATE_ID' => $templateIds,
                'DOCUMENT_ID' => "DYNAMIC_" . self::getSmartTypeId() . "_" . $smartId,
                'STATE' => "InProgress",
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ];
        if ($current) {
            $ormParams['filter']['!ID'] = $current;
        }
//        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflowInProgress filter: " . print_r($ormParams, true) . "\n", FILE_APPEND);
        $wf = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList($ormParams)->fetch();
        if ($wf && isset($wf["ID"])) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflowInProgress find Current wf: " . print_r($wf["ID"], true) . "\n", FILE_APPEND);
            return $wf["ID"];
        }
        return "";
    }

    /* не используется */

    public static function checkCurrentWorkflowInProgress()
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $templateIds = self::getWorkFlowIds();
        $ormParams = [
            'filter' => [
                'WORKFLOW_TEMPLATE_ID' => $templateIds,
                '%DOCUMENT_ID' => "DYNAMIC_" . self::getSmartTypeId() . "_" . $smartId,
                'STATE' => "InProgress",
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ];
        if ($current) {
            $ormParams['filter']['!ID'] = $current;
        }
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflowInProgress filter: " . print_r($ormParams, true) . "\n", FILE_APPEND);
        $wf = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList($ormParams)->fetch();
        if ($wf && isset($wf["ID"])) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentWorkflowInProgress find Current wf: " . print_r($wf, true) . "\n", FILE_APPEND);
            return $wf["ID"];
        }
        return "";
    }

    public static function getHistoryByUserActivity($dealId, $wfId, $userId, $actName = "")
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $filter = array(
            'agreementUser.value' => (int) $userId,
            'smartId.value' => $dealId,
            'workflowId.value' => $wfId,
            'status.value' => 0
        );
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getHistoryByUserActivity filter: " . print_r($filter, true) . "\n", FILE_APPEND);
        $Agreement = AgreementHistory::getByFilter($filter)[0] ?? false;
//        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getHistoryByUserActivity Agreement: " . print_r($Agreement, true) . "\n", FILE_APPEND);
        if (!$Agreement) {
            $filter = array(
                'agreementUser.value' => (int) $userId,
                'smartId.value' => $dealId,
                'workflowId.value' => $wfId,
                '>status.value' => 0
            );
            $Agreement = AgreementHistory::getByFilter($filter)[0] ?? false;
            if ($Agreement) {
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getHistoryByUserActivity Last Complete Agreement: " . print_r($Agreement->smartId, true) . "\n", FILE_APPEND);
            }
        }

        return $Agreement;
    }

    public static function getHistoryBySmartActivity($dealId, $wfId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $filter = array(
            'smartId.value' => $dealId,
            'workflowId.value' => $wfId,
            'status.value' => 0
        );
//        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getHistoryBySmartActivity filter: " . print_r($filter, true) . "\n", FILE_APPEND);
        $Agreement = AgreementHistory::getByFilter($filter)[0] ?? false;
//        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getHistoryBySmartActivity Agreement: " . print_r($Agreement, true) . "\n", FILE_APPEND);
        if (!$Agreement) {
            $filter = array(
                'smartId.value' => $dealId,
                'workflowId.value' => $wfId,
                '>status.value' => 0
            );
            $Agreement = AgreementHistory::getByFilter($filter)[0] ?? false;
//            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getHistoryBySmartActivity LAst Complete Agreement: " . print_r($Agreement, true) . "\n", FILE_APPEND);
        }

        return $Agreement;
    }

    public static function getCurrentUsersBySmartActivity($dealId, $wfId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $filter = array(
            'smartId.value' => $dealId,
            'workflowId.value' => $wfId,
            'status.value' => 0
        );
        $Agreements = [];
        $tasks = [];
        $CurrentAgreements = AgreementHistory::getByFilter($filter);
        if (!empty($CurrentAgreements)) {
            $curfilter = array(
                'smartId.value' => $dealId,
                'workflowId.value' => $wfId,
                'taskId.value' => $CurrentAgreements[0]->taskId
            );
//            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentUsersBySmartActivity CurrentAgreements: " . print_r($curfilter, 1) . "\n", FILE_APPEND);
            $Agreements = AgreementHistory::getByFilter($curfilter);
        }
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentUsersBySmartActivity count Agreements: " . count($Agreements) . "\n", FILE_APPEND);

        foreach ($Agreements as $Agreement) {
            $userProps = json_decode($Agreement->agreementUserProps, 1);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentUsersBySmartActivity userProps: " . print_r($userProps, 1) . "\n", FILE_APPEND);
            $title = $userProps && isset($userProps['title']) ? $userProps["title"] : $Agreement->taskName;
            if ($Agreement->status == 1) {
                $resultTime = strtotime($Agreement->resultTime);
                $resultTime = date("d.m.Y", $resultTime);
                $item = [
                    "title" => $title . " (от {$resultTime})",
                    "icon" => "/local/modules/amb.agreement/assets/img/checked_box_lt.png"
                ];
            } else if ($Agreement->status == 2) {
                $resultTime = strtotime($Agreement->resultTime);
                $resultTime = date("d.m.Y", $resultTime);
                $item = [
                    "title" => $title . " (от {$resultTime})",
                    "icon" => "/local/modules/amb.agreement/assets/img/reject.png"
                ];
            } else {
                $item = [
                    "title" => $title . " (от {$Agreement->createdAt->format("d.m.Y")})",
                    "icon" => "/local/modules/amb.agreement/assets/img/blue_arrow_lt.png"
                ];
            }
            $tasks[] = $item;
        }
        return $tasks;
    }

    public static function getSmartStatus($smartId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $status = false;
        $completed = false;
        $active = false;
        $edit = false;
        $itemFields = [];
        try {


            if ($smartId) {
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartStatus smartId: " . print_r($smartId, true) . "\n", FILE_APPEND);
                $factory = self::getFactory();
                $item = $factory->getItem($smartId);
                if ($item) {
                    $itemFields = $item->getData();
                    if (in_array($item->getStageId(), array_keys(self::AGR_ACTIVE_STATUS))) {  //активное согласование
                        $active = true;
                    }
                    if (in_array($item->getStageId(), array_keys(self::AGR_COMPLETE_STATUS))) {  //согласовано
                        $completed = true;
                    }
                    if (in_array($item->getStageId(), array_keys(self::AGR_EDIT_STATUSES))) {  //согласовано
                        $edit = true;
                    }
                }
            }
            $status = ["item" => $itemFields, "active" => $active, "completed" => $completed, "edit" => $edit];
//            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartStatus status: " . print_r($status, true) . "\n", FILE_APPEND);
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartStatus Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $status;
    }

    public static function getSmartStagesForOptions()
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {
            $dynamic_stages = [];
            $factory = self::getFactory();

            if ($factory && $factory->isStagesSupported()) {

                $stages = $factory->getStages()->getAll();

                foreach ($stages as $stage) {

                    $dynamic_stages['REFERENCE_ID'][] = $stage->getStatusId();
                    $dynamic_stages['REFERENCE'][] = $stage->getName();
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartStagesForOptions Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $dynamic_stages;
    }

    public static function getSmartItem($smartId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {
            if ($smartId) {
                $factory = self::getFactory();
                $item = $factory->getItem($smartId);
                if ($item) {
                    return $item;
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartStatus Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return false;
    }

    public static function changeCurrentApproverInSmart(AgreementObject $Agreement)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {
            if ($Agreement->smartId) {
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " changeCurrenApproverInSmart smartId: " . print_r($Agreement->smartId, 1) . "\n", FILE_APPEND);
                $factory = self::getFactory();
                $item = $factory->getItem($Agreement->smartId);
                if ($item) {
                    $entityTypeId = self::getSmartTypeId();
                    $currTaskUsers = self::getCurrentApproverOnTask($Agreement->taskId);
                    $itemFields = $item->getData();
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " changeCurrenApproverInSmart itemFields UF_CRM_11_CURRENT_USERS: " . print_r($itemFields['UF_CRM_11_CURRENT_USERS'], 1) . "\n", FILE_APPEND);
                    $item->set('UF_CRM_11_CURRENT_USERS', array_keys($currTaskUsers));

                    $item->save();
                    PullHandler::pull($Agreement->smartId);
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "changeCurrenApproverInSmart Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return false;
    }

    public static function getCurrentApproverOnTask($taskId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $taskUsers = [];
        try {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " getCurrenApproverOnTask taskId: " . print_r($taskId, 1) . "\n", FILE_APPEND);
            if (Loader::includeModule('bizproc')) {
                $rsUsers = \Bitrix\Bizproc\Workflow\Task\TaskUserTable::getList([
                            'select' => [
                                'USER_ID',
                                'STATUS',
                                'USER_NAME' => 'USER.NAME',
                                'USER_LAST_NAME' => 'USER.LAST_NAME'
                            ],
                            'filter' => [
                                '=TASK_ID' => $taskId,
                                '=STATUS' => 0,
                            ],
                            'runtime' => [
                                // Описываем связь b_bp_task_user.USER_ID = b_user.ID
                                new Reference(
                                        'USER',
                                        \Bitrix\Main\UserTable::getEntity(),
                                        Join::on('this.USER_ID', 'ref.ID')
                                )
                            ]
                        ])->fetchAll();

                foreach ($rsUsers as $user) {
                    $taskUsers[$user['USER_ID']] = $user['USER_NAME'] . " " . $user['USER_LAST_NAME'];
                }


//                $curfilter = array(
//                    'smartId.value' => $Agreement->smartId,
//                    'workflowId.value' => $Agreement->workflowId,
//                    'taskId.value' => $Agreement->taskId,
//                    'status.value' => 0
//                );
//                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " getCurrenApproverOnTask curfilter: " . print_r($curfilter, 1) . "\n", FILE_APPEND);
//                $Agreements = AgreementHistory::getByFilter($curfilter);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " getCurrenApproverOnTask taskUsers: " . print_r($taskUsers, true) . "\n", FILE_APPEND);
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrenApproverOnTask Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $taskUsers;
    }

    public static function getCurrentTaskInProcess(AgreementObject $Agreement)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {
            if ($Agreement->taskId) {
                if (Loader::includeModule('bizproc')) {
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " getCurrentTaskInProcess smartId: " . print_r($Agreement->smartId, 1) . "\n", FILE_APPEND);
                    $tasks = [];
                    // Запрашиваем информацию о задании по его ID
                    // 
                    $connection = \Bitrix\Main\Application::getConnection();

                    // Пишем прямой запрос к таблице b_bp_task_user
                    $sql = "SELECT ID, TASK_ID, USER_ID, STATUS, DATE_UPDATE, ORIGINAL_USER_ID 
            FROM b_bp_task_user 
            WHERE STATUS=0 AND TASK_ID = " . (int) $Agreement->taskId;

                    $recordset = $connection->query($sql);

                    while ($userTask = $recordset->fetch()) {
                        // Каждая запись содержит информацию о пользователе, привязанном к задаче
                        $tasks[] = $userTask;
                    }

                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " getCurrentTaskInProcess count tasks: " . print_r($tasks, 1) . "\n", FILE_APPEND);
                    return $tasks;
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getCurrentTaskInProcess Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return [];
    }

    public static function getSmartExecutor($smartId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $itemFields = [];
        try {


            if ($smartId) {
                $factory = (\Bitrix\Crm\Service\Container::getInstance())->getFactory(self::getSmartTypeId());
                $item = $factory->getItem($smartId);
                if ($item) {
                    return $item->getAssignedById();
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartStatus Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return 1;
    }

    public static function canUserChangeDoc($smartId, $userId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $dbug_path = '/home/bitrix/getUpdateOperation.log';
        $change_enable_users = \Bitrix\Main\Config\Option::get('amb.agreement', 'change_enable_users', []);
        $change_enable_users = unserialize($change_enable_users);
        $change_enable_stages = \Bitrix\Main\Config\Option::get('amb.agreement', 'change_enable_stages', []);
        $change_enable_stages = unserialize($change_enable_stages);
        if (!$change_enable_stages)
            $change_enable_stages = [];
        if (!$change_enable_users)
            $change_enable_users = [];
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . ' canUserChangeDoc change_enable_users: ' . print_r($change_enable_users, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . ' canUserChangeDoc change_enable_stages: ' . print_r($change_enable_stages, true) . "\n", FILE_APPEND);
        try {
            if ($smartId) {
                $factory = (\Bitrix\Crm\Service\Container::getInstance())->getFactory(self::getSmartTypeId());
                $item = $factory->getItem($smartId);
                $stageId = $item->getStageId();
                $assignedId = $item->getAssignedById();
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . ' canUserChangeDoc current stageId: ' . print_r($stageId, true) . "\n", FILE_APPEND);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . ' canUserChangeDoc current assignedId: ' . print_r($assignedId, true) . "\n", FILE_APPEND);
                if ($assignedId == $userId && in_array($stageId, $change_enable_stages)) {
                    return true;
                }
                if (in_array($userId, $change_enable_users)) {
                    return true;
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartStatus Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return false;
    }

    public static function getSmartDogType($smartId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $itemFields = [];
        try {


            if ($smartId) {
                $factory = (\Bitrix\Crm\Service\Container::getInstance())->getFactory(self::getSmartTypeId());
                $item = $factory->getItem($smartId);
                if ($item) {
                    return $item->get("UF_CRM_11_DOG_TYPE");
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getSmartDogType Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return false;
    }

    public static function getApproveResultText($smartId, $userId, $result, $comment)
    {
        return Botmessages::getApproveResultText($smartId, $userId, $result, $comment);
    }

    public static function setApproveStatusInField($item, $buttonTitle, $result)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " setApproveStstuaInField points: " . var_export($points, 1) . "\n", FILE_APPEND);
        $points = "";
        try {
            $itemFields = $item->getData();
            $fieldId = self::STATUS_POINTS_FIELD_ID;
            if (isset($itemFields[$fieldId])) {
                $points = json_decode($itemFields[$fieldId], JSON_UNESCAPED_UNICODE);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " setApproveStstuaInField points: " . var_export($points, 1) . "\n", FILE_APPEND);
                if (is_array($points)) {
                    $points[$buttonTitle] = self::BX_BUTTON_STATUSES[$result];
                }
                $newpoints = json_encode($points, JSON_UNESCAPED_UNICODE);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " setApproveStstuaInField newpoints: " . var_export($newpoints, 1) . "\n", FILE_APPEND);
                $item->set($fieldId, $newpoints);
                $item->save();
                return $newpoints;
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " setApproveStstuaInField Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return $points;
    }

    public static function getApproveStartText($smartFields)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        return Botmessages::getApproveStartText($smartFields);
    }

    public static function getBreakThroughText($smartFields)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        return Botmessages::getBreakThroughText($smartFields);
    }

    public static function sendInitMessage($smartId, $initiatorId, $arrayUsers)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "sendInitMessage smartId: " . print_r($smartId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "sendInitMessage initiatorId: " . print_r($initiatorId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "sendInitMessage arrayUsers: " . print_r($arrayUsers, true) . "\n", FILE_APPEND);
//            \Bitrix\Main\Loader::includeModule('bizproc');
            $item = self::getSmartItem($smartId);
            if ($item) {
                $smartFields = $item->getData();
                $userId = \CBPHelper::StripUserPrefix($initiatorId);
                $textMessage = Botmessages::getInitApproveText($smartFields, $arrayUsers);
                $timeLineMessage = Botmessages::getInitTimeLineText($smartFields, $arrayUsers, $userId);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "sendInitMessage textMessage: " . print_r($textMessage, true) . "\n", FILE_APPEND);
                $messageId = self::sendMessageToUser($userId, $smartFields, $textMessage);
                self::addComment($smartId, $timeLineMessage, $authorId = 1);
                return $messageId;
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "sendInitMessage Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return false;
    }

    /* Вызвается из компонента Согласование */

    public static function setApproveResult($workflowId, $smartId, $userId, $actName, $result, $comment)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult workflowId: " . print_r($workflowId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult smartId: " . print_r($smartId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult userId: " . print_r($userId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult actName: " . print_r($actName, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult result: " . print_r($result, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult comment: " . print_r($comment, true) . "\n", FILE_APPEND);
        try {

            $filter = array(
                'workflowId.value' => $workflowId,
                'agreementUser.value' => (int) $userId,
                'smartId.value' => (int) $smartId,
                'activityName.value' => $actName
            );
            $Agreement = AgreementHistory::getByFilter($filter)[0];
            $arEventParameters = [
                "USER_ID" => $userId,
                "REAL_USER_ID" => $userId,
                "USER_NAME" => "",
                "COMMENT" => $comment,
                "APPROVE" => $result,
                "STATUS" => $result
            ];
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult arEventParameters: " . print_r($arEventParameters, true) . "\n", FILE_APPEND);

            if ($Agreement) {
                \CBPDocument::SendExternalEvent($Agreement->workflowId, $Agreement->activityName, $arEventParameters, $arErrors);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult arErrors: " . print_r($arErrors, true) . "\n", FILE_APPEND);
                PullHandler::pull($Agreement->smartId);
            }
            return $Agreement->id;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "setApproveResult Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    /* Вызвается из компонента Согласование */

    public static function delegateUser($workflowId, $smartId, $userId, $actName, $fromUserId)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser workflowId: " . print_r($workflowId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser smartId: " . print_r($smartId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser userId: " . print_r($userId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser actName: " . print_r($actName, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser fromUserId: " . print_r($fromUserId, true) . "\n", FILE_APPEND);
        try {
            (Loader::includeModule('bizproc'));

            $filter = array(
                'workflowId.value' => $workflowId,
                'agreementUser.value' => (int) $fromUserId,
                'smartId.value' => (int) $smartId,
                'activityName.value' => $actName
            );
            $Agreement = AgreementHistory::getByFilter($filter)[0];
            // Формируем параметры для события делегирования
            if ($Agreement) {
                $arEventParameters = [
                    'USER_ID' => $userId, // Кому делегируем
                    'COMMENT' => 'Задание делегировано автоматически через API.', // Необязательный комментарий
                ];

                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser Agreement->taskId: " . print_r($Agreement->taskId, true) . "\n", FILE_APPEND);
                $errors = [];
                // Вызываем корневой метод ядра БП напрямую
                $result = \CBPDocument::delegateTasks(
                        $fromUserId,
                        $userId,
                        [$Agreement->taskId], // Метод принимает массив ID заданий
                        $errors
                );
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser errors: " . print_r($errors, true) . "\n", FILE_APPEND);

                if ($result) {
                    if (class_exists('\CUserCounter')) {
                        \CUserCounter::Clear($userId, 'bp_tasks', "s1");
                    }
                }

//                \CBPDocument::SendExternalEvent($Agreement->workflowId, $actName, $arEventParameters, $arErrors);
                PullHandler::pull($Agreement->smartId);
            }
            return $Agreement->id;
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "delegateUser Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function getFactory()
    {

        $entityTypeId = self::getSmartTypeId(); //задаем id нужного смарт-процесса
        $Factory = Container::getInstance()->getFactory($entityTypeId);
        return $Factory;
    }

    public static function taskAddHandler($taskId, $fields = [])
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        if ($dbug) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler taskId: " . print_r($taskId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler Name: " . print_r($fields["NAME"], true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler USERS: " . print_r($fields["USERS"], true) . "\n", FILE_APPEND);
        }

        try {
            $smartId = "";
            $params = unserialize($fields["PARAMETERS"]);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler type: " . print_r($params['DOCUMENT_ID'][0], true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler module: " . print_r($params['DOCUMENT_ID'][1], true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler DocID: " . print_r($params['DOCUMENT_ID'][2], true) . "\n", FILE_APPEND);
            $type = $params['DOCUMENT_ID'][1];
            if ($type !== "Bitrix\Crm\Integration\BizProc\Document\Dynamic") {
                return;
            }
            if (isset($params['DOCUMENT_ID'][2]) && !empty($params['DOCUMENT_ID'][2])) {
                $chat_approve_users = \Bitrix\Main\Config\Option::get('amb.agreement', 'chat_approve_users', []);
                $chat_approve_users = unserialize($chat_approve_users);
                if (!$chat_approve_users) {
                    $chat_approve_users = [];
                }
                $smartId = (int) preg_replace("/^\w+_\d+_(\d+)/", "$1", $params['DOCUMENT_ID'][2]);
                $typeId = (int) preg_replace("/^\w+_(\d+)_\d+/", "$1", $params['DOCUMENT_ID'][2]);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler smartId: " . print_r($smartId, true) . "\n", FILE_APPEND);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler typeId: " . print_r($typeId, true) . "\n", FILE_APPEND);
                $entityTypeId = self::getSmartTypeId(); //задаем id нужного смарт-процесса
                if ($typeId != $entityTypeId) {
                    return;
                }
                if ($dbug) {
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler smartId: " . print_r($smartId, true) . "\n", FILE_APPEND);
                }
                if ($dbug) {
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler params: " . print_r($params, true) . "\n", FILE_APPEND);
                }
                $item = self::getSmartItem($smartId);
                if ($item) {
                    $smartFields = $item->getData();
                    if (isset($params["isBreakThrough"]) && $params["isBreakThrough"]) {
                        $textMessage = self::getBreakThroughText($smartFields);
                    } else {
                        $textMessage = self::getApproveStartText($smartFields);
                    }
                }
                foreach ($fields["USERS"] as $user_id) {
                    $userProps = self::getUserProps($user_id);
                    $text = "Согласование: {$userProps['title']}";
                    $data = [
                        'smartId' => $smartId,
                        'entityTypeId' => $entityTypeId,
                        'taskName' => $text,
                        'taskId' => $taskId,
                        'deadline' => $fields["OVERDUE_DATE"],
                        'resultTime' => null,
                        'agreementResult' => null,
                        'agreementUser' => $user_id,
                        'agreementUserProps' => json_encode($userProps, 1),
                        'cfo' => "",
                        'agreementComment' => "",
                        'agreementResultText' => "",
                        'workflowId' => $fields["WORKFLOW_ID"],
                        'activityName' => $fields["ACTIVITY_NAME"],
                        'arEventParameters' => json_encode($params, 1),
                        'cfoText' => "",
                        'status' => 0,
                        'documentName' => $fields["DOCUMENT_NAME"],
                        'description' => $fields["DESCRIPTION"],
                        'isBreakThrough' => $params["isBreakThrough"] ?? 0,
                        'buttonTitle' => $params["buttonTitle"] ?? "",
                        'agreeButtonText' => $params["TaskButton1Message"],
                        'rejectButtonText' => $params["TaskButton2Message"],
                    ];
                    if (empty($data["deadline"])) {
                        $nowDateTime = new \Bitrix\Main\Type\DateTime();
                        $data["deadline"] = $nowDateTime->add("24 hour");
                    }
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler data: " . print_r($data, true) . "\n", FILE_APPEND);
                    $Agreement = AgreementHistory::createElement($data);

                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler Agreement: " . print_r($Agreement, true) . "\n", FILE_APPEND);
                    // Если пользователь имеет право согласовать в чате - отправим ему кнопки
                    if (in_array($user_id, $chat_approve_users)) {
                        $sendApproveButtons = true;
                    } else {
                        $sendApproveButtons = false;
                    }
                    self::sendMessageToUser($user_id, $smartFields, $textMessage, "", $sendApproveButtons);
                    PullHandler::pull($Agreement->smartId);
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskAddHandler Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function taskUpdateHandler($taskId, $fields = [])
    {
//        return;
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        if ($dbug) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskUpdateHandler taskId: " . print_r($taskId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskUpdateHandler fields: " . print_r($fields, true) . "\n", FILE_APPEND);
        }
        try {
            if ($taskId) {
                $taskParams = self::getTaskParams($taskId);
                $type = $taskParams['DOCUMENT_ID'][1] ?? false;
                if (!$type || $type !== "Bitrix\Crm\Integration\BizProc\Document\Dynamic") {
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskUpdateHandler not Smart Task: " . print_r($type, true) . "\n", FILE_APPEND);
                    return;
                }
                $Agreement = AgreementHistory::getByTaskId($taskId);
                if ($Agreement) {
                    if ($dbug) {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskUpdateHandler Agreement smartId: " . print_r($Agreement->smartId, true) . "\n", FILE_APPEND);
                    }
                    if ($fields["STATUS"] && is_numeric($fields["STATUS"])) {
                        if ($Agreement->status != intval($fields["STATUS"])) {
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskUpdateHandler Agreement STATUS NOT EQUAL. fields[STATUS]: " . print_r($fields["STATUS"], true) . "\n", FILE_APPEND);
                            $statusFields = [
                                "status" => $fields["STATUS"],
                                "agreementResult" => self::BX_STATUS[$fields["STATUS"]] ?? "",
                                "agreementResultText" => self::BX_STATUS_TEXT[$fields["STATUS"]] ?? "",
                                "resultTime" => (new \DateTime())->format("d.m.Y H:i:s"),
                            ];
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskUpdateHandler Agreement UPDATED. statusFields: " . print_r($statusFields, true) . "\n", FILE_APPEND);
                            \CIBlockElement::SetPropertyValuesEx($Agreement->id, self::getHistoryIblockId(), $statusFields);
                        }
                    }
                } else {

                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskUpdateHandler No Agreement. Continue " . "\n", FILE_APPEND);
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskUpdateHandler Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function taskDeleteHandler($taskId, $fields = [], $dop = [], $etc = [])
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        if ($dbug) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDeleteHandler taskId: " . print_r($taskId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDeleteHandler fields: " . print_r($fields, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDeleteHandler dop: " . print_r($dop, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDeleteHandler etc: " . print_r($etc, true) . "\n", FILE_APPEND);
        }

        try {
            
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDeleteHandler Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function taskDelegateHandler($taskId, $fromUser = [], $toUser = [], $etc = [])
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        if ($dbug) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDelegateHandler taskId: " . print_r($taskId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDelegateHandler fromUser: " . print_r($fromUser, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDelegateHandler toUser: " . print_r($toUser, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskDelegateHandler etc: " . print_r($etc, true) . "\n", FILE_APPEND);
        }

        try {

            if ($taskId) {
                $taskParams = self::getTaskParams($taskId);
                $type = $taskParams['DOCUMENT_ID'][1] ?? false;
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDelegateHandler taskParams: " . print_r($taskParams, true) . "\n", FILE_APPEND);
                if (!$type || $type !== "Bitrix\Crm\Integration\BizProc\Document\Dynamic") {
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDelegateHandler not Smart Task: " . print_r($type, true) . "\n", FILE_APPEND);
                    return;
                }
                $Agreement = AgreementHistory::getByTaskId($taskId);
                if ($Agreement) {
                    if ($dbug) {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDelegateHandler Agreement smartId: " . print_r($Agreement->smartId, true) . "\n", FILE_APPEND);
                    }
                    if ($Agreement->agreementUser != intval($toUser)) {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDelegateHandler Agreement agreementUser NOT EQUAL.: " . print_r($toUser, true) . "\n", FILE_APPEND);
                        $userProps = self::getUserProps($toUser);
                        $updateFields = [
                            "agreementUserProps" => json_encode($userProps, 1),
                            "agreementUser" => (int) $toUser,
                        ];
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDelegateHandler Agreement UPDATED. statusFields: " . print_r($statusFields, true) . "\n", FILE_APPEND);
                        \CIBlockElement::SetPropertyValuesEx($Agreement->id, self::getHistoryIblockId(), $updateFields);
                        self::changeCurrentApproverInSmart($Agreement);
                        $item = self::getSmartItem($Agreement->smartId);
                        if ($item) {
                            $smartFields = $item->getData();
                            $delegateText = Botmessages::getDelegateText($smartFields, $fromUser, $toUser);
                            self::sendMessageToUser($toUser, $smartFields, $delegateText);
                            self::addComment($Agreement->smartId, $delegateText, $fromUser);
                        }
                    }
                } else {

                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDelegateHandler No Agreement. Continue " . "\n", FILE_APPEND);
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskDelegateHandler Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function taskMarkCompletedHandler($taskId, $user = [], $status = [], $request = [])
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        if ($dbug) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskMarkCompletedHandler taskId: " . print_r($taskId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskMarkCompletedHandler user: " . print_r($user, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskMarkCompletedHandler status: " . print_r($status, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " taskMarkCompletedHandler request: " . print_r($request, true) . "\n", FILE_APPEND);
        }
        try {
            
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "taskMarkCompletedHandler Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function getSmartByFilter($filter, $limit = false)
    {
        $entityTypeId = self::SMART_TYPE_ID; //задаем id нужного смарт-процесса
        $container = Service\Container::getInstance();
        $factory = $container->getFactory($entityTypeId);
        $smarts = [];
        $parameters = array(
            'select' => array(), // имена полей, которые необходимо получить в результате
            'filter' => $filter, // описание фильтра для WHERE и HAVING
            'group' => array(), // явное указание полей, по которым нужно группировать результат
            'order' => array(), // параметры сортировки
            'limit' => $limit, // параметры сортировки
//            'runtime' => array(), // динамически определенные поля
        );
        $allItems = $factory->getItems($parameters);
        $rows = array();
        foreach ($allItems as $item) {
            $rows = $item->getCompatibleData(\Bitrix\Main\ORM\Objectify\Values::ALL); //getData - для вида полей "нового API"
            $smarts[$rows["ID"]] = $rows;
        }
        return $smarts;
    }

    /* Вызвается из активити ambapprooveactivity */

    public static function saveApproveResult($taskId, $userId, $status = false, $comment)
    {
        \Bitrix\Main\Loader::includeModule('bizproc');
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        if ($dbug) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult taskId: " . print_r($taskId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult userId: " . print_r($userId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult status: " . print_r($status, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult comment: " . print_r($comment, true) . "\n", FILE_APPEND);
        }
        try {

            if ($taskId) {
                $Agreement = AgreementHistory::getByTaskUserId((int) $taskId, (int) $userId);
                if ($dbug) {
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult Agreement: " . print_r($Agreement->id, true) . "\n", FILE_APPEND);
                }
                if ($Agreement) {

                    $item = self::getSmartItem($Agreement->smartId);
                    if ($item) {
                        $smartFields = $item->getData();
//                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "saveApproveResult smartFields: " . print_r($smartFields, true) . "\n", FILE_APPEND);
                        $executor = self::getSmartExecutor($Agreement->smartId);
                        if ($dbug) {
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult comment: " . print_r($comment, true) . "\n", FILE_APPEND);
                        }
                        $taskUsers = self::getCurrentTaskInProcess($Agreement);
                        // если на задаче еще есть согласанты то желтый. Иначе Красный или зеленый
                        if (empty($taskUsers)) {
                            $json_status = self::setApproveStatusInField($item, $Agreement->buttonTitle, $status);
                        } else {
                            $json_status = self::setApproveStatusInField($item, $Agreement->buttonTitle, 9);
                        }
                        // Сохраним комментарии сервисных служб в поле
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult SERVICE_END_FIELD_ID: " . print_r(self::SERVICE_END_FIELD_ID, true) . "\n", FILE_APPEND);
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult SERVICE_END_FIELD Value: " . print_r($smartFields[self::SERVICE_END_FIELD_ID] ?? "--", true) . "\n", FILE_APPEND);
                        if (empty($smartFields[self::SERVICE_END_FIELD_ID])) {
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult SERVICE_END_FIELD empty " . "\n", FILE_APPEND);
                            self::addCommentToField($smartFields, $comment, $userId);
                        }

                        $text = Botmessages::getApproveResultText($Agreement->smartId, $Agreement->agreementUser, $status, $comment);
                        self::sendMessageToUser($executor, $smartFields, $text, $json_status);
                        self::sendBalloonToUser($Agreement, $userId, $status, $executor);
                        self::addComment($Agreement->smartId, $text, $userId);
                        self::addHistoryLog($smartFields, $text, $userId);
                        if (is_numeric($status)) {
                            $statusFields = [
                                "status" => $status,
                                "agreementComment" => $comment,
                                "agreementResult" => self::BX_STATUS[$status] ?? "",
                                "agreementResultText" => self::BX_STATUS_TEXT[$status] ?? "",
                                "resultTime" => (new \DateTime())->format("d.m.Y H:i:s"),
                            ];
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult statusFields: " . print_r($statusFields, true) . "\n", FILE_APPEND);
                            $res = \CIBlockElement::SetPropertyValuesEx($Agreement->id, self::getHistoryIblockId(), $statusFields);
                            // прерывание если не нужно согласовывать дальше
//                            if ($status === 2) {
//                                self::interruptTask($taskId, $Agreement->id);
//                            }
                            self::changeCurrentApproverInSmart($Agreement);
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " saveApproveResult Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return "";
    }

    public static function interruptTask($taskId, $current_agreement_id)
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        if ($dbug) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " interruptTask taskId: " . print_r($taskId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " interruptTask current_agreement_id: " . print_r($current_agreement_id, true) . "\n", FILE_APPEND);
        }
        try {
            if ($taskId) {
                $Agreements = AgreementHistory::getAllByTaskId($taskId);

                foreach ($Agreements as $Agreement) {
                    if ($Agreement->id == $current_agreement_id)
                        continue;
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " interruptTask interrupt Agreement ID: " . print_r($Agreement->id, true) . "\n", FILE_APPEND);
                    $statusFields = [
                        "status" => 9,
                        "agreementResult" => self::BX_STATUS[9] ?? "",
                        "agreementResultText" => self::BX_STATUS_TEXT[9] ?? "",
                        "resultTime" => (new \DateTime())->format("d.m.Y H:i:s"),
                    ];
                    $res = \CIBlockElement::SetPropertyValuesEx($Agreement->id, self::getHistoryIblockId(), $statusFields);
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "interruptTask Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function botApproveResult($smartId, $status, $userId)
    {
        $dbug = true;
//        $dbug_path = self::getDbugPath();
        $dbug_path = '/home/bitrix/botMessageAddHander.log';
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " botApproveResult smartId: " . print_r($smartId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " botApproveResult status: " . print_r($status, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " botApproveResult userId: " . print_r($userId, true) . "\n", FILE_APPEND);
        try {
            // найдем текущее согласование по данному пользователю   
            $wfId = self::getCurrentWorkflowInProgress($smartId);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " botApproveResult wfId: " . print_r($wfId, true) . "\n", FILE_APPEND);
            if (!empty($wfId)) {
                $Agreement = self::getHistoryByUserActivity($smartId, $wfId, $userId);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " botApproveResult find Agr by user/ TaskId: " . print_r($Agreement->taskId, true) . "\n", FILE_APPEND);
                if ($Agreement && $Agreement->taskId) {
                    $arEventParameters = [
                        "USER_ID" => $userId,
                        "REAL_USER_ID" => $userId,
                        "USER_NAME" => "",
                        "COMMENT" => "",
                        "APPROVE" => $status,
                        "STATUS" => $status
                    ];
                    $arErrors = [];
                    \CBPDocument::SendExternalEvent($Agreement->workflowId, $Agreement->activityName, $arEventParameters, $arErrors);
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " SendExternalEvent arErrors: " . print_r($arErrors, true) . "\n", FILE_APPEND);
                    $result = $status ? 1 : 2;
                    $text = Botmessages::getApproveAnswerText($smartId, $result, $Agreement);
                    $params = Array(
                        'DIALOG_ID' => $userId,
                        'MESSAGE' => $text,
                        'ATTACH' => "",
                    );
                    \Amb\Agreement\Bot::sendMessage($params);
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " botApproveResult Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function getTaskById($taskId)
    {
        \Bitrix\Main\Loader::includeModule('bizproc');
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {

            // Запрашиваем информацию о задании по его ID
            $task = \CBPTaskService::GetList(
                            [],
                            ['ID' => $taskId],
                            false,
                            false,
                            ['ID', 'WORKFLOW_ID', 'PARAMETERS', 'STATUS']
                    )->Fetch();
            if ($task) {
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getTaskById task {$taskId} wf: " . print_r($task['WORKFLOW_ID'], true) . "\n", FILE_APPEND);
                return $task;
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getTaskById Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return null;
    }

    public static function getTaskParams($taskId)
    {
        \Bitrix\Main\Loader::includeModule('bizproc');
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {

            $task = self::getTaskById($taskId);
            if ($task) {
                $parameters = $params = $task["PARAMETERS"];
                return $parameters;
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getTaskParams Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return null;
    }

    public static function getApproveComment($taskId, $userId)
    {
        \Bitrix\Main\Loader::includeModule('bizproc');
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        try {

            // Запрашиваем информацию о задании по его ID
            $task = \CBPTaskService::GetList(
                            [],
                            ['ID' => $taskId],
                            false,
                            false,
                            ['ID', 'WORKFLOW_ID', 'PARAMETERS', 'STATUS']
                    )->Fetch();
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getApproveComment task: " . print_r($task, true) . "\n", FILE_APPEND);
            if ($task) {
                $parameters = $task['PARAMETERS'];
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getApproveComment task parameters: " . print_r($parameters, true) . "\n", FILE_APPEND);

                // Ищем комментарий, оставленный именно этим пользователем ($userId)
                if (isset($parameters['RESPONSES']['COMMENT'][$userId])) {
                    $userComment = $parameters['RESPONSES']['COMMENT'][$userId];
                    return $userComment ?? "";
                }
            }
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getApproveComment Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return "";
    }

    /**
     * Получить данные о пользователе
     *
     * @param int $userId пользователь
     * @return ["title" => $text, "position" => $position, "fio" => $fio]
     */
    public static function getUserProps($userId)
    {
        \Bitrix\Main\Loader::includeModule('bizproc');
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $fio = "Пользователь";
        $position = "";
        $text = "";
        try {
            $rsUser = \CUser::GetList(
                    ("id"),
                    ("asc"),
                    array("ID" => $userId),
                    array("FIELDS" => array("ID", "NAME", "LAST_NAME", "SECOND_NAME", "WORK_POSITION"))
            );

            if ($arUser = $rsUser->Fetch()) {
                $fio = array_filter([$arUser["LAST_NAME"], $arUser["NAME"], $arUser["SECOND_NAME"]]);
                $fio = implode(" ", $fio);
                $position = $arUser["WORK_POSITION"];
            }

            $text = "{$fio}";
            if (!empty($position)) {
                $text = "{$position}: " . $text;
            }
            return ["title" => $text, "position" => $position, "fio" => $fio];
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "getUserTitle Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return ["title" => $text, "position" => $position, "fio" => $fio];
    }

    /**
     * Добавить комментарий в сделке
     * @param string $message
     * @return void
     */
    public static function addComment($smartId = null, string $message = "", $authorId = 1)
    {
        $dbug_path = self::getDbugPath();
        $authorId = 1; // Ставим 
        $entityTypeId = self::getSmartTypeId();
        $entityId = $smartId;
        try {
            if ($smartId && !empty($message)) {
                $fields = array(
                    'TEXT' => $message,
                    'FILES' => [],
                    'SETTINGS' => [
                        'TITLE' => 'Системное уведомление',
                        'TEXT' => $message,
                        'CATEGORY_ID' => 12,
                        'ICON_CODE' => 'success' // Иконка (info, success, warning, danger)
                    ],
                    'AUTHOR_ID' => $authorId, //ID пользователя, от которого будет добавлен комментарий
                    'BINDINGS' => array(array('ENTITY_TYPE_ID' => self::getSmartTypeId(), 'ENTITY_ID' => $smartId)) // привязка к сущности CRM: ENTITY_TYPE_ID - тип сущности CRM (3 - Контакт).
                );
                $commentId = \Bitrix\Crm\Timeline\CommentEntry::create($fields);

                if ($commentId > 0) {
                    $pushFields = [
                        'ENTITY_TYPE_ID' => $entityTypeId,
                        'ENTITY_ID' => $entityId,
                        'TITLE' => 'Системное уведомление',
                        'TEXT' => $message,
                        'ICON_CODE' => 'success'
                    ];
                    // Отправляем событие для обновления интерфейса (push-уведомление в карточку)
                    \Bitrix\Crm\Timeline\CommentController::getInstance()->onCreate($commentId, $pushFields);
                }
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "addComment Exception: " . print_r($e->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    /**
     * Добавить комментарий в сделке
     * @param string $message
     * @return void
     */
    private static function addCommentToField($fields = null, string $message = "", $authorId = 1)
    {
        $dbug_path = self::getDbugPath();
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "addCommentToField message: " . print_r($message, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "addCommentToField authorId: " . print_r($authorId, true) . "\n", FILE_APPEND);
        try {
            if ($fields && !empty($message)) {
                $userProps = self::getUserProps($authorId);
                $time = date("(d.m.Y H:i) ");
                $item = self::getSmartItem($fields["ID"]);
                $item->set(self::SERVICE_COMMENTS_FIELD_ID, $fields[self::SERVICE_COMMENTS_FIELD_ID] . $time . $userProps["title"] . ": " . $message . PHP_EOL);
                $item->save();
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "addCommentToField Exception: " . print_r($e->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    /**
     * Добавить комментарий в сделке
     * @param string $message
     * @return void
     */
    private static function sendBalloonToUser(AgreementObject $Agreement, $userId = 1, $status, $executor = 1)
    {
        $dbug_path = self::getDbugPath();
        $send_balloon_to_user = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'send_balloon_to_user', '') == 'Y') ? true : false;
        if (!$send_balloon_to_user)
            return;

        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendBalloonToUser status: " . print_r($status, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendBalloonToUser userId: " . print_r($userId, true) . "\n", FILE_APPEND);
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . " sendBalloonToUser executor: " . print_r($executor, true) . "\n", FILE_APPEND);
        try {
            if ($Agreement) {
                $userProps = self::getUserProps($userId);
                $smart_type = self::getSmartTypeId();
                $time = date("(d.m.Y H:i) ");
                $link = "/crm/type/{$smart_type}/details/{$Agreement->smartId}/";
                PullHandler::sendNotifyBallon($executor, $userProps["fio"], $Agreement->documentName, $link, $status);
            }
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "sendBalloonToUser Exception: " . print_r($e->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    /**
     * Добавить комментарий в сделке
     * @param string $message
     * @return void
     */
    private static function addHistoryLog($itemFields = [], string $message = "", $authorId = 1)
    {
        $dbug_path = self::getDbugPath();
        try {
            if (!\Bitrix\Main\Loader::includeModule('crm')) {
                throw new \Exception("CRM module not included");
            }

            $CCrmEvent = new \CCrmEvent();
            $CCrmEvent->Add(
                    array(
                        'ENTITY_TYPE' => "DYNAMIC_" . self::getSmartTypeId(), //LEAD, CONTACT, DEAL,COMPANY
                        'ENTITY_ID' => $itemFields["ID"],
                        'EVENT_ID' => 'INFO',
                        'EVENT_TYPE' => 1,
                        'CREATED_BY' => $authorId,
                        'USER_ID' => $authorId,
                        'EVENT_TEXT_1' => $message, //какой-то текст      
                    //'FILES' => array(CFile::MakeFileArray('/bitrix/templates/bitrix24/images/template_sprite_21.png')),
                    //'DATE_CREATE' => ConvertTimeStamp(time() + CTimeZone::GetOffset(), 'FULL', SITE_ID), //по умолчанию текущее время      
                    )
            );
        } catch (\Exception $e) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "addHistoryLog Exception: " . print_r($e->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    /**
     * Добавить комментарий в сделке
     * @param string $message
     * @return void
     */
    private function autoStartBp($dealId = null, string $message = "")
    {
        try {
            if ($this->dbug) {
                file_put_contents($this->dbug_path, date("d.m.Y H:i:s#") . "autoStartBp deal_id: " . $dealId . "\n", FILE_APPEND);
            }

            $arErrorsTmp = array();
            \CBPDocument::AutoStartWorkflows(
                    ['crm', 'CCrmDocumentDeal', 'DEAL'],
                    \CBPDocumentEventType::Create,
                    ['crm', 'CCrmDocumentDeal', 'DEAL_' . $dealId],
                    array("TargetUser" => "user_" . intval($GLOBALS["USER"]->GetID())),
                    $arErrorsTmp
            );
            if (count($arErrorsTmp) > 0) {
                foreach ($arErrorsTmp as $e) {
                    $errorMessage .= "[" . $e["code"] . "] " . $e["message"] . "";
                    file_put_contents($this->dbug_path, date("d.m.Y H:i:s#") . "BD errors: " . print_r($errorMessage, true) . "\n", FILE_APPEND);
                }
            }
            $result[] = $wfId;
        } catch (\Exception $exc) {
            file_put_contents($this->dbug_path, date("d.m.Y H:i:s#") . "errors: " . print_r($exc->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    private function startDealBp($templateId, $entityId, $amount = 0)
    {

        $arErrorsTmp = [];
        $arWorkflowParameters = [];
        file_put_contents($this->dbug_path, date("d.m.Y H:i:s#") . "## DEAL: {$entityId}## startDealBp StartWorkflow templateId: " . $templateId . "\n", FILE_APPEND);
        $wfId = \CBPDocument::StartWorkflow(
                $templateId,
                ['crm', 'CCrmDocumentDeal', 'DEAL_' . $entityId],
                array_merge($arWorkflowParameters, array("TargetUser" => "user_" . intval(1), "summ" => $amount)),
                $arErrorsTmp
        );

        if (count($arErrorsTmp) > 0) {
            foreach ($arErrorsTmp as $e)
                $errorMessage .= "[" . $e["code"] . "] " . $e["message"] . "";
            if ($this->dbug) {
                file_put_contents($this->dbug_path, date("d.m.Y H:i:s#") . "## DEAL: {$entityId}## startDealBp StartWorkflow templateId: " . print_r($errorMessage, true) . "\n", FILE_APPEND);
            }
        }
        return $wfId;
    }

    public static function startSmartBp($templateId, $entityId, $arWorkflowParameters = [])
    {
        $dbug = (\Bitrix\Main\Config\Option::get(self::MODULE_ID, 'DBUG_ENABLED', '') == 'Y') ? 1 : 0;
        $dbug_path = self::getDbugPath();
        $arErrorsTmp = [];
        $wfId = \CBPDocument::StartWorkflow(
                $templateId, //ID робота, смотреть через таблицу b_bp_workflow_template
                array("crm", "Bitrix\Crm\Integration\BizProc\Document\Dynamic", "DYNAMIC_" . self::SMART_TYPE_ID . "_" . $entityId),
                array_merge($arWorkflowParameters, array("TargetUser" => "user_" . intval(1))),
                $arErrorsTmp
        );
        if (count($arErrorsTmp) > 0) {
            foreach ($arErrorsTmp as $e)
                $errorMessage .= "[" . $e["code"] . "] " . $e["message"] . "";
            if ($dbug) {
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "startSmartBp errors: " . print_r($errorMessage, true) . "\n", FILE_APPEND);
            }
        }
        return $wfId;
    }

    private function getTemplateName($templateId)
    {

        $ormParams = [
            'filter' => [
                'ID' => $templateId,
            ],
        ];
        $wf = \Bitrix\Bizproc\Workflow\Template\Entity\WorkflowTemplateTable::getList($ormParams)->fetch();
        if ($wf && isset($wf["NAME"]))
            return $wf["NAME"];
        return "";
    }

    public static function blockCustomFieldInlineEdit(\Bitrix\Main\Event $event)
    {
        $parameters = $event->getParameters();
        $dbug_path = self::getDbugPath();
        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "blockCustomFieldInlineEdit parameters: " . print_r($parameters, true) . "\n", FILE_APPEND);
        // Проверяем, что это ваш смарт-процесс (укажите ваш entityTypeId вместо 140)
        if ($parameters['ENTITY_TYPE_ID'] == self::getSmartTypeId()) {

            // Перебираем конфигурацию полей карточки
            foreach ($parameters['FIELDS'] as &$field) {
                // Ищем наше кастомное поле
                if ($field['name'] === 'UF_CRM_11_UF_APPROVE_FIELD') {
                    // Запрещаем инлайн-редактирование в карточке
                    $field['editable'] = false;
                    $field['hasInlineEdit'] = false;
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "blockCustomFieldInlineEdit field total: " . print_r($field, true) . "\n", FILE_APPEND);
                }
            }
        }
    }

    public static function sendMessageToUser($chatId, $itemFields, $text, $json_points = "", $sendApproveButtons = false)
    {
        $keyboard = Array();

        file_put_contents('/home/bitrix/sendChatMessage.log', date("d.m.Y H:i:s#") . "chatId: " . var_export($chatId, 1) . "\n", FILE_APPEND);
        file_put_contents('/home/bitrix/sendChatMessage.log', date("d.m.Y H:i:s#") . "smartId: " . var_export($itemFields["ID"], 1) . "\n", FILE_APPEND);
        if ($itemFields) {
            try {
                $project_block_id = self::getProjectIblockId();
                $smart_type = self::getSmartTypeId();
                if (empty($json_points)) {
                    if (isset($itemFields[self::STATUS_POINTS_FIELD_ID]) && !empty($itemFields[self::STATUS_POINTS_FIELD_ID])) {
                        file_put_contents('/home/bitrix/sendChatMessage.log', date("d.m.Y H:i:s#") . " json_points: " . var_export($itemFields[self::STATUS_POINTS_FIELD_ID], 1) . "\n", FILE_APPEND);
                        $json_points = $itemFields[self::STATUS_POINTS_FIELD_ID];
                    }
                } else {
                    file_put_contents('/home/bitrix/sendChatMessage.log', date("d.m.Y H:i:s#") . " json_points from arguments: " . var_export($json_points, 1) . "\n", FILE_APPEND);
                }
                $keyboard = self::createKeyboardFromPoints($json_points, $smart_type, $itemFields["ID"]);
                //добавим кнопки согласования
                if ($sendApproveButtons) {
                    $keyboard = self::addApproveButtons($keyboard, $itemFields["ID"]);
                }
                if (!\Bitrix\Main\Loader::includeModule('crm'))
                    return false;
                $summ = floatval(str_replace("|RUB", "", $itemFields['UF_CRM_11_DOG_SUMM']));

                if (!empty($itemFields['CONTACT_ID'])) {
                    $Contact = \CCrmContact::GetbyId($itemFields['CONTACT_ID'], false);
                    $Contragent['TITLE'] = $Contact["LAST_NAME"] . " " . $Contact["NAME"];
                } else {
                    if (!empty($itemFields['COMPANY_ID'])) {
                        $Contragent = \CCrmCompany::GetbyId($itemFields['COMPANY_ID'], false);
                    } else {
                        $Contragent['TITLE'] = "Контрагент";
                    }
                }
                if ($project_block_id && !empty($itemFields['UF_CRM_11_PROJECT'])) {
                    $Project = \CIBlockElement::GetList(array(), ['IBLOCK_ID' => $project_block_id, 'ID' => $itemFields['UF_CRM_11_PROJECT']], false, false, ["NAME", "ID"])->fetch();
                } else
                    $Project["NAME"] = "";
                if (!empty($itemFields['MYCOMPANY_ID'])) {
                    $Company = \CCrmCompany::GetbyId($itemFields['MYCOMPANY_ID'], false);
                } else {
                    $Company["TITLE"] = "";
                }
                $attach = Array(
                    array(
                        'NAME' => 'Договор',
                        'VALUE' => $itemFields['TITLE'],
                        'LINK' => "/crm/type/{$smart_type}/details/{$itemFields['ID']}/",
                        'DISPLAY' => 'COLUMN',
                        'WIDTH' => 150,
                    ), array(
                        'NAME' => 'Контрагент',
                        'VALUE' => $Contragent['TITLE'] ?? "",
                        'DISPLAY' => 'COLUMN',
                        'WIDTH' => 150,
                    ), array(
                        'NAME' => 'Организация',
                        'VALUE' => $Company['TITLE'] ?? "",
                        'DISPLAY' => 'COLUMN',
                        'COLOR' => "#000",
                        'WIDTH' => 150,
                    ), array(
                        'NAME' => 'Сумма договора',
                        'VALUE' => number_format($summ, 2, ",", " ") . " руб.",
                        'DISPLAY' => 'COLUMN',
                        'COLOR' => "#000",
                        'WIDTH' => 150,
                    ), array(
                        'NAME' => 'Проект',
                        'VALUE' => $Project["NAME"] ?? "",
                        'DISPLAY' => 'COLUMN',
                        'COLOR' => "#000",
                        'WIDTH' => 150,
                    ),
                );

                if ($chatId) {
                    $params = Array(
                        'DIALOG_ID' => $chatId,
                        'MESSAGE' => $text,
                        'ATTACH' => $attach ?? "",
                        'KEYBOARD' => $keyboard,
                    );
                    file_put_contents('/home/bitrix/sendChatMessage.log', date("[Y-m-d H:i:s]") . "params: " . print_r($params, 1) . "\n", FILE_APPEND);
                    if ($messageId = \Amb\Agreement\Bot::sendMessage($params)) {
                        file_put_contents('/home/bitrix/sendChatMessage.log', date("[Y-m-d H:i:s]") . "OK_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                        return $messageId;
                    } else {
                        file_put_contents('/home/bitrix/sendChatMessage.log', date("[Y-m-d H:i:s]") . "ERROR_NO_SEND: " . print_r($messageId, 1) . "\n", FILE_APPEND);
                        return null;
                    }
                }
            } catch (\Exception $e) {
                file_put_contents('/home/bitrix/sendChatMessage.log', date("[Y-m-d H:i:s]") . "Exception: " . print_r($e->getMessage(), 1) . "\n", FILE_APPEND);
            }
        }
        return null;
    }

    public static function getProduct($product_id)
    {
        $arElFilter = array(
            'IBLOCK_ID' => 24,
            'ACTIVE' => 'Y',
            'ID' => $product_id,
        );
        $arElSelect = array(
            "ID",
            'NAME',
            'PROPERTY_OWNER_COMPANY',
            'PROPERTY_PARENT',
        );

        $resData = \CIBlockElement::GetList(array(), $arElFilter, false, false, $arElSelect)->fetch();
        if ($resData) {
            return $resData;
        }
        return false;
    }

    public static function createKeyboardFromPoints($json_points = "", $typeId, $smartId, $sendApproveButtons = false)
    {
        $keyboard = [];

        $points = json_decode($json_points, JSON_UNESCAPED_UNICODE);
        file_put_contents('/home/bitrix/createKeyboardFromPoints.log', date("d.m.Y H:i:s#") . " smartId: " . print_r($smartId, 1) . "\n", FILE_APPEND);
        file_put_contents('/home/bitrix/createKeyboardFromPoints.log', date("d.m.Y H:i:s#") . " points: " . print_r($points, 1) . "\n", FILE_APPEND);
        if (is_array($points)) {

            foreach ($points as $etap => $value) {

                $keyboard[] = Array(
                    "TEXT" => $etap,
                    "DISPLAY" => "LINE",
                    "COMMAND" => "empty",
                    "WIDTH" => 120,
                    "BG_COLOR_TOKEN" => $value,
                    "BG_COLOR" => "#29619b",
                    "TEXT_COLOR" => "#fff",
                    "BLOCK" => "Y",
                    "DISABLED" => "Y",
                );
            }
        }
        $keyboard[] = array(
            "TEXT" => 'Открыть',
            "DISPLAY" => "LINE",
            "COMMAND" => "empty",
            "LINK" => "/crm/type/{$typeId}/details/{$smartId}/",
            "WIDTH" => 150,
            "BG_COLOR_TOKEN" => "primary",
            "BG_COLOR" => "#29619b",
//            "BG_COLOR" => "#ff0000",
            "TEXT_COLOR" => "#fff",
            "DISABLED" => "N",
        );
        if ($sendApproveButtons) {
            $keyboard[] = array(
                "TYPE" => "NEWLINE"
            );
            $keyboard[] = array(
                "TYPE" => "NEWLINE"
            );
            $keyboard[] = array(
                "DISPLAY" => "LINE",
                'TEXT' => 'Утвердить',
                'ACTION' => 'SEND', // Отправка команды от имени пользователя в чат
                'ACTION_VALUE' => "BP_APPROVE_{$smartId}",
                'BG_COLOR_TOKEN' => 'success', // Основной цвет кнопки
                'TEXT_COLOR_TOKEN' => 'white',
            );
            $keyboard[] = array(
                "DISPLAY" => "LINE",
                'TEXT' => 'Отклонить',
                'ACTION' => 'SEND', // Отправка команды от имени пользователя в чат
                'ACTION_VALUE' => "BP_REJECT_{$smartId}",
                'BG_COLOR_TOKEN' => 'alert', // Основной цвет кнопки
                'TEXT_COLOR_TOKEN' => 'white',
            );
        }

        return $keyboard;
    }

    public static function addApproveButtons($keyboard, $smartId)
    {

        $keyboard[] = array(
            "TYPE" => "NEWLINE"
        );
        $keyboard[] = array(
            "TYPE" => "NEWLINE"
        );
        $keyboard[] = array(
            "DISPLAY" => "LINE",
            'TEXT' => 'Утвердить',
            'ACTION' => 'SEND', // Отправка команды от имени пользователя в чат
            'ACTION_VALUE' => "BP_APPROVE_{$smartId}",
            'BG_COLOR_TOKEN' => 'success', // Основной цвет кнопки
            'TEXT_COLOR_TOKEN' => 'white',
        );
        $keyboard[] = array(
            "DISPLAY" => "LINE",
            'TEXT' => 'Отклонить',
            'ACTION' => 'SEND', // Отправка команды от имени пользователя в чат
            'ACTION_VALUE' => "BP_REJECT_{$smartId}",
            'BG_COLOR_TOKEN' => 'alert', // Основной цвет кнопки
            'TEXT_COLOR_TOKEN' => 'white',
        );

        return $keyboard;
    }
}
