<?php

namespace Amb\Agreement;

use Amb\Agreement\Db\AgreementHistory;
use DateTime;
use Exception;
use Bitrix\Main\Event;
use Bitrix\DocumentGenerator\Document;

class Template
{

    const MODULE_ID = 'amb.agreement';

    public static function getDbugPath()
    {
        return '/home/bitrix/agreement_template_module.log';
    }

    public static function handleTemplate($document)
    {
        $dbug_path = self::getDbugPath();
        try {
            /** @var Document $document */
//            $document = $event->getSender();

            if (!$document) {
                return;
            }

            // 1. Получаем провайдер сущности CRM (Сделка, Лид и т.д.)
            $provider = $document->getProvider();
            if (!$provider) {
                return;
            }

            // получить список полей шаблона
            $tempfields = $document->getTemplate()->getBody()->getPlaceholders();
            $template = $document->getTemplate();
            $ownerId = $provider->getSource();
            $ownerType = $provider->getCrmOwnerType();
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "template ID: " . print_r($template->ID, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "ownerId: " . print_r($ownerId, true) . "\n", FILE_APPEND);
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "ownerType: " . print_r($ownerType, true) . "\n", FILE_APPEND);
            if ($ownerType == AgreementManager::getSmartTypeId()) {
                $item = AgreementManager::getSmartItem($ownerId);
                if ($item) {
                    $smartFields = $item->getData();
                } else {
                    return;
                }
//                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "smartFields: " . print_r($smartFields, true) . "\n", FILE_APPEND);
                if (in_array($template->ID, [1, 21])) {
                    if ($smartFields["ASSIGNED_BY_ID"]) {
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "Initiator: " . print_r($smartFields["ASSIGNED_BY_ID"], true) . "\n", FILE_APPEND);
                        $initiatorCont = \CUser::getById($smartFields["ASSIGNED_BY_ID"], false)->fetch();
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "Initiator LAST_NAME: " . print_r($initiatorCont["LAST_NAME"], true) . "\n", FILE_APPEND);
                        file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "Initiator NAME: " . print_r($initiatorCont["NAME"], true) . "\n", FILE_APPEND);
                        $document->setValues(['initiatorCont' => $initiatorCont]);
                        $document->setFields([
                            'initiatorCont' => [
                                'TYPE' => 'NAME',
                                'FORMAT' => [// здесь можно передать формат поля по умолчанию
                                    'case' => -1, // код падежа
                                    'format' => '#LAST_NAME# #NAME# #SECOND_NAME#' // формат вывода
                                ]
                            ],
                        ]);
                    }
                    $wfId = AgreementManager::getCurrentWorkflow($smartFields["ID"]);

                    if (!empty($wfId)) {
                        $filter = array(
                            'smartId.value' => $smartFields["ID"],
                            'workflowId.value' => $wfId,
                        );
                        $Agreements = AgreementHistory::getByFilter($filter);
                        if ($Agreements) {
                            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "handleTemplate count Agreement: " . count($Agreements, true) . "\n", FILE_APPEND);
                            $CommentData = [];
                            $objectData = [];
                            if (!empty($Agreements)) {
                                foreach ($Agreements as $Agreement) {
                                    $userProps = json_decode($Agreement->agreementUserProps, 1);
//                                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "handleTemplate userProps: " . print_r($userProps, 1) . "\n", FILE_APPEND);
                                    $title = $userProps && isset($userProps['title']) ? $userProps["title"] : $Agreement->taskName;
                                    $resultTime = strtotime($Agreement->resultTime);
                                    $resultTime = date("d.m.Y H:i:s", $resultTime);
                                    $ApproveData[] = [
                                        'fio' => $userProps['fio'],
                                        'position' => $userProps['position'],
                                        'resultTime' => $resultTime,
                                        'resultText' => $Agreement->agreementResultText
                                    ];
                                    if (!empty($Agreement->agreementComment)) {
                                        $CommentData[] = [
                                            'fio' => $userProps['fio'],
                                            'text' => $Agreement->agreementComment,
                                        ];
                                    }
                                }
                                if (empty($CommentData)) {
                                    $CommentData[] = ['fio' => "",
                                        'text' => ""];
                                }
                                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "ApproveData: " . print_r($ApproveData, true) . "\n", FILE_APPEND);
                                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "CommentData: " . print_r($CommentData, true) . "\n", FILE_APPEND);
                                $document->setValues([
                                    'ApproveTable' => $ApproveData,
                                    'ApproverFio' => 'ApproveTable.Item.fio',
                                    'ApproverPosition' => 'ApproveTable.Item.position',
                                    'ApproverTime' => 'ApproveTable.Item.resultTime',
                                    'ApproverResult' => 'ApproveTable.Item.resultText',
                                    'ApproverIdx' => 'ApproveTable.INDEX',
                                ]);
                                $document->setValues([
                                    'CommentTable' => $CommentData,
                                    'CommentFio' => 'CommentTable.Item.fio',
                                    'CommentText' => 'CommentTable.Item.text',
                                    'CommentTableIndex' => 'CommentTable.INDEX',
                                ]);
                                $ObjectSettings = [
                                    'ITEM_NAME' => 'Item',
                                    'ITEM_PROVIDER' => \Bitrix\DocumentGenerator\DataProvider\HashDataProvider::class,
                                ];

                                if ($ApproveData) {

                                    $document->setFields([
                                        'ApproveTable' => [
                                            'PROVIDER' => 'Bitrix\\DocumentGenerator\\DataProvider\\ArrayDataProvider',
                                            'OPTIONS' => $ObjectSettings,
                                        ],
                                    ]);
                                }
                                if ($CommentData) {
                                    $document->setFields([
                                        'CommentTable' => [
                                            'PROVIDER' => 'Bitrix\\DocumentGenerator\\DataProvider\\ArrayDataProvider',
                                            'OPTIONS' => $ObjectSettings,
                                        ],
                                    ]);
                                }
                            }
                        }
                    }
                }

                $newCustomTitle = "Лист согласования Договора " . $smartFields["TITLE"];

                $document->setValues([
                    'DocumentTitle' => $newCustomTitle
                ]);
            }
            /* Заполним данные полученные с документа */
            $document->setFields([
                'my' => ['VALUE' => ""],
                'from_me' => ['VALUE' => ""],
                'podpisant' => ['VALUE' => ""],
            ]);
        } catch (\Exception $ex) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "ApproveData: " . print_r($ex->getMessage(), true) . "\n", FILE_APPEND);
        }
    }
}
