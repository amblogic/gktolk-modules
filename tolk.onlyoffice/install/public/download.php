<?php
// Отдаёт файл Document Server'у по подписанной ссылке (без сессии портала).
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Tolk\OnlyOffice\FileResolver;
use Tolk\OnlyOffice\Jwt;
use Tolk\OnlyOffice\Options;

define('NOT_CHECK_PERMISSIONS', true);
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('disk') || !Loader::includeModule('tolk.onlyoffice')) {
    http_response_code(500);
    die();
}

$request = Application::getInstance()->getContext()->getRequest();
$d = Jwt::decode((string)$request->getQuery('t'), Options::linkSecret());
if (!$d || ($d['p'] ?? '') !== 'dl') {
    http_response_code(403);
    die();
}

$res = FileResolver::resolve((int)$d['f'], (int)$d['a'], (int)$d['u']);
if (!$res) {
    http_response_code(404);
    die();
}

$bFile = CFile::GetFileArray($res['file']->getFileId());
if (!$bFile) {
    http_response_code(404);
    die();
}

CFile::ViewByUser($bFile, [
    'force_download' => true,
    'attachment_name' => $res['file']->getName(),
    'cache_time' => 0,
]);
