<?php

namespace Amb\Agreement\Db;

use Exception;
use CIBlockElement;
//use Bitrix\Iblock\Elements\AgreementhistoryTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\Web\HttpClient;
use Amb\Agreement\Db\EO_Agreementhistory;
use CCrmContact;

class AgreementHistory
{

    private $smartId;
    private $deadline;
    private $resultTime;
    private $agreementResult;
    private $agreementUser;
    private $agreementUserPost;
    private $cfo;
    private $cfoText;
    private $agreementComment;
    private $agreementResultText;
    private $workflowId;
    private $activityName;
    private $isBreakThrough;
    private $buttonTitle;
    private $arEventParameters;
    private $deadlineNotifySended;

    public function toArray()
    {
        return (array) $this;
    }

    public static function getEntity()
    {
//        return OrderBitrix::getEntityDataClass();
//        return OrderBitrix::class;
        $Entity = \Bitrix\Iblock\Iblock::wakeUp(\Amb\Agreement\AgreementManager::IBLOCK_ID)->getEntityDataClass();
//        echo $Entity;
        return $Entity;
    }

    public static function getDefaultSelect()
    {
        return ['ID', 'NAME', 'DATE_CREATE',
            'smartId',
            'deadline',
            'resultTime',
            'taskName',
            'agreementResult',
            'agreementUser',
            'agreementUserPost',
            'cfo',
            'agreementComment',
            'agreementResultText',
            'workflowId',
            'activityName',
            'arEventParameters',
            'cfoText',
            'status',
            'documentName',
            'description',
            'taskId',
            'agreeButtonText',
            'rejectButtonText',
            'isBreakThrough',
            'buttonTitle',
            'deadlineNotifySended',
        ];
    }

    public static function getByTaskId(int $taskId, $select = array('*')): ?AgreementObject
    {

        $Entity = self::getEntity();
        $obj = $Entity::getList(
                        array(
                            'filter' => array('taskId.value' => $taskId),
                            'select' => self::getDefaultSelect(),
                        )
                )->fetchObject();
        if ($obj) {
            $Agreement = AgreementObject::fillEntityObject($obj);
            return $Agreement;
        }
        return null;
    }

    public static function getAllByTaskId(int $taskId, $select = array('*'))
    {

        $filter = array('taskId.value' => $taskId);
        return self::getByFilter($filter, self::getDefaultSelect());
    }

    public static function getAllByWfId($wfId, $select = array('*'))
    {

        $filter = array('workflowId.value' => $wfId);
        return self::getByFilter($filter, self::getDefaultSelect());
    }

    public static function getByTaskUserId(int $taskId, int $userId, $select = array('*')): ?AgreementObject
    {

        $Entity = self::getEntity();
        $obj = $Entity::getList(
                        array(
                            'filter' => array('taskId.value' => $taskId, 'agreementUser.value' => $userId),
                            'select' => self::getDefaultSelect(),
                        )
                )->fetchObject();
        if ($obj) {
            $Agreement = AgreementObject::fillEntityObject($obj);
            return $Agreement;
        }
        return null;
    }

    public static function getByFilter($filter, $select = [])
    {
        // Подключаем класс для вывода SQL запроса
//        \Bitrix\Main\Application::getConnection()->startTracker();

        $Entity = self::getEntity();
        $values = [];
        $list = $Entity::getList(
                        array(
                            'order' => ['ID' => 'DESC'],
                            'filter' => $filter,
                            'select' => self::getDefaultSelect(),
                        )
                )->fetchCollection();
        foreach ($list as $obj) {
            $Agreement = AgreementObject::fillEntityObject($obj);
            $values[] = $Agreement;
        }
        return $values;
    }

    public static function getById(int $id, $select = array('*')): ?AgreementObject
    {
        $Entity = self::getEntity();
        $obj = $Entity::getList(
                        array(
                            'filter' => array('ID' => $id),
                            'select' => self::getDefaultSelect(),
                        )
                )->fetchObject();
        if ($obj) {
            $Agreement = AgreementObject::fillEntityObject($obj);
            return $Agreement;
        }
        return null;
    }

    public static function getArrayById(int $id, $select = array('PROPERTY_*'))
    {
        $Entity = self::getEntity();
        $obj = $Entity::getList(
                        array(
                            'filter' => array('ID' => $id),
                            'select' => self::getDefaultSelect(),
                        )
                )->fetchAll();
        if ($obj) {
            return $obj;
        }
        return null;
    }

    /**
     * Создать заявку в инфоблоке
     * @param mixed $data
     * @return null | AgreementObject
     * @throws Exception
     */
    public static function createElement($data): ?AgreementObject
    {
        $arFields = array(
            'IBLOCK_ID' => \Amb\Agreement\AgreementManager::IBLOCK_ID,
            'NAME' => "Согласование № {$data['taskId']}" . $data['cfoText'],
            'CODE' => $data['workflowId'] . "_" . $data['activityName'] . "_" . $data['agreementUser'],
            'PROPERTY_VALUES' => $data,
        );

        $el = new CIBlockElement();
        if (!$resultId = $el->Add($arFields)) {
            throw new Exception('Ошибка сохранения согласования: ' . $el->LAST_ERROR);
        }

        $Entity = self::getEntity();
        $obj = $Entity::getByPrimary($resultId, [
                    'select' => self::getDefaultSelect(),
                ])->fetchObject();
        if ($obj) {
            $Agreement = AgreementObject::fillEntityObject($obj);
            return $Agreement;
        }
        return null;
    }
}
