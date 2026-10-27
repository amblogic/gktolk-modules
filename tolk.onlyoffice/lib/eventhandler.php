<?php
namespace Tolk\OnlyOffice;

use Bitrix\Main\Application;
use Bitrix\Main\Page\Asset;

final class EventHandler
{
    /** Подключает JS, перехватывающий клики по офисным файлам. */
    public static function onProlog(): void
    {
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }
        global $USER;
        if (!is_object($USER) || !$USER->IsAuthorized()) {
            return;
        }
        $request = Application::getInstance()->getContext()->getRequest();
        if ($request->isAjaxRequest() || strpos((string)$request->getRequestedPage(), '/onlyoffice/') === 0) {
            return;
        }

        $cfg = [
            'exts' => Formats::intercepted(),
            'editUrl' => '/onlyoffice/edit.php',
            'tokenUrl' => '/onlyoffice/token.php',
        ];
        Asset::getInstance()->addString('<script>window.TolkOO=' . json_encode($cfg) . ';</script>');
        Asset::getInstance()->addString('<script src="/bitrix/js/tolk.onlyoffice/disk.js?' . @filemtime($_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/tolk.onlyoffice/disk.js') . '"></script>');
    }
}
