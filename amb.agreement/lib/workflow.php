<?php

namespace Amb\Agreement;

use Amb\Agreement\Db\AgreementHistory;
use DateTime;
use Exception;

class Workflow
{

    const MODULE_ID = 'amb.agreement';
    const STAGE_PERIOD_DEFAULT = 24; // Hours

    public static function getDbugPath()
    {
        return '/home/bitrix/agreement_agent_module.log';
    }

    public static function getTasksInProgress()
    {
        $dbug_path = self::getDbugPath();
        $templateIds = AgreementManager::getWorkFlowIds();
        $ormParams = [
            'filter' => [
                'WORKFLOW_TEMPLATE_ID' => $templateIds,
                '%DOCUMENT_ID' => "DYNAMIC_" . AgreementManager::getSmartTypeId(),
                'STATE' => "InProgress",
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 100,
        ];

//        file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getWorkflowInProgress filter: " . print_r($ormParams, true) . "\n", FILE_APPEND);
        $wfs = \Bitrix\Bizproc\Workflow\Entity\WorkflowStateTable::getList($ormParams)->fetchAll();
        foreach ($wfs as $workflow) {
            $TaskAgreements[$workflow['ID']] = self::findTaskByWf($workflow['ID']);
        }
//        file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . "getWorkflowInProgress filter: " . print_r($wfs, true) . "\n", FILE_APPEND);

        return $TaskAgreements;
    }

    public static function findTaskByWf($wfId)
    {
        $dbug_path = self::getDbugPath();
        try {
            if ($wfId) {
                $Agreements = AgreementHistory::getAllByWfId($wfId);
                return $Agreements;
            }
        } catch (Exception $ex) {
            file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . " findTaskByWf Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
        return [];
    }

    public static function checkTasksWithShortDeadLine()
    {
        $dbug_path = self::getDbugPath();
        $stage_period_default = (int) \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'STAGE_PERIOD_DEFAULT', self::STAGE_PERIOD_DEFAULT);
        $notify_period_deadline = (int) \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'NOTIFY_PERIOD_DEADLINE', self::STAGE_PERIOD_DEFAULT);
        $wfs = self::getTasksInProgress();
        foreach ($wfs as $wfId => $tasks) {
            foreach ($tasks as $task) {
                if (!empty($task->deadline)) {
                    $deadline = $task->deadline;
                } else {
                    $deadline = $task->createdAt->add("{$stage_period_default} hours");
                    $task->deadline = $deadline;
                }
                // Проверим попадает ли дедланй в промежуток напоминания
                $delta_time = $deadline->getTimestamp() - time();
                if ($delta_time > 0 && $delta_time < $notify_period_deadline * 60) {
                    // попадает в интервал
                    if ($task->deadlineNotifySended) {
                        // пропускаем, уже отправлено
                    } else {
                        $notify_dedline[] = $task;
                    }
                } else if ($delta_time < 0) {
                    $notify_overtime[] = $task;
                    // просрочено
                }
            }
        }
        echo '<pre>';
        print_r($notify_dedline);
        echo '</pre>';
        file_put_contents($dbug_path, date("[Y-m-d H:i:s]") . " checkTasksWithShortDeadLine notify_dedline: " . print_r($notify_dedline, true) . "\n", FILE_APPEND);
        foreach ($notify_dedline as $task) {
            $text = Botmessages::getNotifyDeadlineText($task);
            $item = AgreementManager::getSmartItem($task->smartId);
            $itemFields = $item->getData();
            AgreementManager::sendMessageToUser($task->agreementUser, $itemFields, $text);
        }
        return "";
    }
}
