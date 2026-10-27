<?php

namespace Amb\Agreement\Db;

use Exception;
use CIBlockElement;
use Bitrix\Main\Config\Option;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\Web\HttpClient;
//use Amb\Agreement\Db\EO_Agreementhistory;
use Amb\Agreement\Db\AgreementHistory;
//use Amb\Agreement\Db\AgreementhistoryTable;
use CCrmContact;

class AgreementObject
{

    public $id;
    public $smartId;
    public $taskName;
    public $deadline;
    public $resultTime;
    public $agreementResult;
    public $agreementUser;
    public $agreementUserPost;
    public $cfo;
    public $cfoText;
    public $agreementComment;
    public $agreementResultText;
    public $workflowId;
    public $activityName;
    public $arEventParameters;
    public $createdAt;
    public $updateddAt;
    public $name;
    public $code;
    public $status;
    public $documentName;
    public $description;
    public $taskId;
    public $agreeButtonText;
    public $rejectButtonText;
    public $isBreakThrough;
    public $buttonTitle;
    public $deadlineNotifySended;

    public function __get($name)
    {
        if (method_exists($this, ($method = 'get' . $name))) {
            return $this->$method();
        } else
            return;
    }

    public function toArray()
    {
        return (array) $this;
    }

    public static function getEntity()
    {
        return self::class;
    }

    public static function fillEntityObject($object)
    {
        try {

            $Agreement = new \Amb\Agreement\Db\AgreementObject();
            $Agreement->name = $object["NAME"] ?? "";
            $Agreement->id = $object["ID"] ?? "";
            $Agreement->createdAt = $object["DATE_CREATE"] ?? "";
            $Agreement->code = $object["CODE"] ?? "";
            $fields = $object->collectValues();

            foreach ($fields as $key => $value) {
                if (in_array($key, ["ID", "NAME", "CODE", "DATE_CREATE"]))
                    continue;
                if (!$value)
                    continue;
//                if ($key == "deadline") {
//                    $Agreement->{$key} = $value->getValue();
//                    continue;
//                }
                if ($value instanceof \Bitrix\Main\Type\DateTime) {
                    $Agreement->{$key} = $value;
                } else {
                    $Agreement->{$key} = $value->getValue();
                }
            }
            return $Agreement;
        } catch (\Exception $ex) {
            file_put_contents("/home/bitrix/agreement_module.log", date("[Y-m-d H:i:s]") . "fillEntityObject Exception: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }
}
