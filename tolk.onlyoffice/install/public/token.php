<?php
// Выдаёт короткоживущую ссылку на редактор: нужна клиенту Битрикс24,
// который может открыть вкладку во внешнем браузере без сессии портала.
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Tolk\OnlyOffice\FileResolver;
use Tolk\OnlyOffice\Jwt;
use Tolk\OnlyOffice\Options;

define('NO_KEEP_STATISTIC', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');
global $USER;

if (!$USER->IsAuthorized() || !check_bitrix_sessid()) {
    http_response_code(403);
    die(json_encode(['error' => 'Нет авторизации']));
}
if (!Loader::includeModule('disk') || !Loader::includeModule('tolk.onlyoffice')) {
    http_response_code(500);
    die(json_encode(['error' => 'Модуль не установлен']));
}

$request = Application::getInstance()->getContext()->getRequest();
$objectId = (int)$request->getPost('fileId');
$attachedId = (int)$request->getPost('attachedId');
$userId = (int)$USER->GetID();

if (!FileResolver::resolve($objectId, $attachedId, $userId)) {
    http_response_code(404);
    die(json_encode(['error' => 'Файл не найден или нет доступа']));
}

$t = Jwt::encode(
    ['p' => 'open', 'f' => $objectId, 'a' => $attachedId, 'u' => $userId, 'exp' => time() + 120],
    Options::linkSecret()
);
echo json_encode([
    'url' => Options::currentHostUrl() . '/onlyoffice/edit.php?' . http_build_query([
        'fileId' => $objectId, 'attachedId' => $attachedId, 't' => $t,
    ]),
]);
