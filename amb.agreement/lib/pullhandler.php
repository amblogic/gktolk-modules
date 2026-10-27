<?php

namespace Amb\Agreement;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Bitrix\Pull\Event;
use Bitrix\Main\UI\Extension;

class PullHandler
{

    public static function getDbugPath()
    {
        return '/home/bitrix/agreement_pullhandler.log';
    }

    public static function injectPullSubscriber()
    {
        // Не выполняем JS в административной панели
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }
        \Bitrix\Main\Loader::includeModule('pull');

        $dbug_path = '/home/bitrix/pullHandler.log';
        try {
            global $USER;
            $userId = $USER->GetID();
            $request = Context::getCurrent()->getRequest();
            $requestedPage = $request->getRequestedPage();
            if (strpos($requestedPage, '/crm/type/1032/details') === false) {
                return;
            }
            if (!Loader::includeModule('pull')) {
                return;
            }

            \Bitrix\Main\UI\Extension::load('pull');
            \Bitrix\Main\UI\Extension::load('pull.client');
            \Bitrix\Main\Loader::includeModule('amb.agreement');

            \CPullWatch::Add($userId, 'amb_agreement_process_watch');
            $res = Asset::getInstance()->addString(self::getJsCode());
        } catch (\Exception $exc) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "injectPullSubscriber Exception: " . print_r($exc->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    public static function injectGlobalUserSubscriber()
    {
        // Не выполняем JS в административной панели
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }

        $dbug_path = self::getDbugPath();
        try {
            global $USER;
            $userId = $USER->GetID();
            $request = Context::getCurrent()->getRequest();

            if (!Loader::includeModule('pull')) {
                return;
            }

            \Bitrix\Main\UI\Extension::load('pull');
            \Bitrix\Main\UI\Extension::load('pull.client');
            \Bitrix\Main\Loader::includeModule('amb.agreement');
            if ($userId == 116) {
                Extension::load(['pull.client', 'ui.notification', "sidepanel"]);
                Asset::getInstance()->addJs('/local/js/notification-handler.js');
                Asset::getInstance()->addJs('/local/js/notification-function.js');
            } else {
                
            }
        } catch (\Exception $exc) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "injectGlobalUserSubscriber Exception: " . print_r($exc->getMessage(), true) . "\n", FILE_APPEND);
        }
    }

    private static function getJsCode(): string
    {
        return "<script type=\"text/javascript\">
            BX.ready(function() {
                const channelName = 'amb_agreement_process_watch';
                // Проверяем, что объект PULL инициализировался на фронтенде
                if (typeof BX.PULL === 'undefined') {
                    return;
                }

                console.log('PullHandler: Инициализация подписки на канал:', channelName);

                BX.PULL.extendWatch(channelName);

                // Подписываемся на события PULL
                BX.addCustomEvent('onPullEvent-amb.agreement', function(command, params){
                     console.log('Push command',command);
                     if (command === 'refresh_smart_process') {
                        // Проверяем, открыт ли сейчас какой-либо слайдер SidePanel
                        if (typeof BX.SidePanel !== 'undefined' && BX.SidePanel.Instance) {
                            const topSlider = BX.SidePanel.Instance.getTopSlider();
                            
                            if (topSlider) {
                                const sliderUrl = topSlider.getUrl();
                                var sliderWindow = topSlider.getWindow();
                                // содержит ли URL параметры вашего смарт-процесса
                                if (sliderUrl.includes('/crm/type/1032/details/' + params.itemId)) {
                                    if (sliderWindow && sliderWindow.BX) {
                                        // Ищем редактор внутри контекста iframe, проверяя старый и новый D7-пути
                                        var editor = (sliderWindow.BX.UI && sliderWindow.BX.UI.EntityEditor) 
                                            ? sliderWindow.BX.UI.EntityEditor.getDefault() 
                                            : null;

                                        // Если через UI не нашелся, ищем в D7 CRM-реестре iframe
                                        if (!editor && sliderWindow.BX.Crm && sliderWindow.BX.Crm.EntityEditor) {
                                            editor = sliderWindow.BX.Crm.EntityEditor.getDefault();
                                        }

                                        if (editor && typeof editor.reload === 'function') {
                                           var fieldControl = editor.getControlById('UF_CRM_7_UF_APPROVE_FIELD');
                
                                            if (fieldControl) {
                                                 var fieldDataId = fieldControl.getDataKey(); 
                    
                                                if (editor._model && typeof editor._model.isSchemeFieldAvailable === 'function') {
                                                    editor._model.deleteField(fieldDataId);
                                                } else if (editor._model && editor._model._data) {
                                                    delete editor._model._data['UF_CRM_7_UF_APPROVE_FIELD'];
                                                }

                                                fieldControl._isChanged = true;
                                                if (typeof fieldControl.markAsChanged === 'function') {
                                                    fieldControl.markAsChanged();
                                                }
                                            }

                                            // 2. Включаем скрытые флаги Битрикса для игнорирования кэша данных компонента
                                            editor._isForceReload = true; 

                                            // 3. Перезапускаем редактор полей по AJAX
                                            editor.reload();
                                        } else {
                                            // Если редактор всё ещё не инициализирован (например, фрейм только открывается)
                                            // Вешаем обработчик события инициализации ВНУТРИ фрейма
                                            sliderWindow.BX.addCustomEvent('BX.UI.EntityEditor:onInit', function(editorInstance) {
                                                editorInstance.reload();
                                            });
                                            console.warn('Редактор внутри фрейма не найден, установлен триггер на init.');
                                        }
                                    }
                                }
                            }
                        }
                    }
                });
            });
        </script>";
    }

    public static function pull($itemId)
    {
        $entityTypeId = AgreementManager::getSmartTypeId();
        $dbug_path = AgreementManager::getDbugPath();
        try {

            if (Loader::includeModule('pull')) {
                $channelName = 'amb_agreement_process_watch';
                $res = \CPullWatch::AddToStack($channelName, [
                    'module_id' => 'amb.agreement', // Укажите ID вашего модуля
                    'command' => 'refresh_smart_process',
                    'params' => [
                        'entityTypeId' => $entityTypeId, // Подставьте ваши переменные
                        'itemId' => $itemId,
                    ],
                ]);
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "pull res: " . print_r($res, true) . "\n", FILE_APPEND);

                $res = \Bitrix\Pull\Event::send();
                file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "pull send res: " . print_r($res, true) . "\n", FILE_APPEND);
            }
        } catch (\Exception $exc) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "pull Exception: " . print_r($exc->getMessage(), true) . "\n", FILE_APPEND);
            return $exc->getMessage();
        }
        return $res;
    }

    public static function sendNotifyBallon($userId, $userTitle = "", $docTitle = "", $docLink = "", $status = false)
    {
        $entityTypeId = AgreementManager::getSmartTypeId();
        $dbug_path = self::getDbugPath();
        try {
            \Bitrix\Main\UI\Extension::load('amb.agreement');
            if (Loader::includeModule('pull')) {
                if ($status) {
                    // Превращаем в массив, если передан один ID
                    $userId = (int) $userId;
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "sendNotifyBallon userId: " . print_r($userId, true) . "\n", FILE_APPEND);
                    $text = "{$userTitle} одобрил документ {$docTitle}";
                    if ($status == 1)
                        $command = "confirm_approve_balloon";
                    if ($status == 2)
                        $command = "reject_approve_balloon";
                    $result = \Bitrix\Pull\Event::add($userId, [
                        'module_id' => 'amb.agreement', // Уникальный идентификатор вашего модуля/решения
                        'command' => $command, // Имя команды, которую поймает JS
                        'params' => [
                            'text' => $text,
                            'userTitle' => $userTitle,
                            'docTitle' => $docTitle,
                            'docLink' => $docLink,
                            'type' => 'success', // success, error, warning
                            'position' => "top-right", // Расположение: bottom-right, bottom-left, top-right, top-center и др.
                            'delay' => 6000                  // Время показа в мс
                        ]
                    ]);
                    file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "pull result: " . print_r($result, true) . "\n", FILE_APPEND);
                }
            }
        } catch (\Exception $exc) {
            file_put_contents($dbug_path, date("d.m.Y H:i:s#") . "pull Exception: " . print_r($exc->getMessage(), true) . "\n", FILE_APPEND);
            return $exc->getMessage();
        }
        return $result;
    }
}
