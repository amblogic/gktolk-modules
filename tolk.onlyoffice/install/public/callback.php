<?php
// Принимает статусы от Document Server и сохраняет новые версии файла на Диск.
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use Tolk\OnlyOffice\FileResolver;
use Tolk\OnlyOffice\Jwt;
use Tolk\OnlyOffice\KeyStorage;
use Tolk\OnlyOffice\Locker;
use Tolk\OnlyOffice\Options;

define('NOT_CHECK_PERMISSIONS', true);
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

function tolkOoReply(int $error, string $log = ''): void
{
    if ($log !== '') {
        AddMessage2Log($log, 'tolk.onlyoffice');
    }
    echo json_encode(['error' => $error]);
    die();
}

if (!Loader::includeModule('disk') || !Loader::includeModule('tolk.onlyoffice')) {
    tolkOoReply(1, 'modules not loaded');
}

$request = Application::getInstance()->getContext()->getRequest();

// 1. Наша подпись ссылки.
$link = Jwt::decode((string)$request->getQuery('t'), Options::linkSecret());
if (!$link || ($link['p'] ?? '') !== 'cb') {
    tolkOoReply(1, 'bad link token');
}

// 2. JWT Document Server: в теле (JWT_IN_BODY=true) или в заголовке Authorization.
$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    tolkOoReply(1, 'bad body');
}
$token = $body['token'] ?? '';
if ($token === '') {
    $h = (string)($request->getHeader('Authorization') ?: ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($h, 'Bearer ') === 0) {
        $token = substr($h, 7);
    }
}
$data = $token !== '' ? Jwt::decode($token, Options::jwtSecret()) : null;
if (!$data) {
    tolkOoReply(1, 'bad docserver jwt');
}
$body = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : $data;

$fileId = (int)$link['f'];
$status = (int)($body['status'] ?? 0);
$docKey = (string)($body['key'] ?? '');

// 1 — редактируется, 4 — закрыт без изменений, 2 — готов к сохранению,
// 6 — принудительное сохранение (forcesave/autoAssembly), 3/7 — ошибки сохранения.
if ($status === 1) {
    KeyStorage::touch($fileId); // кто-то подключился/отключился — сессия жива
    tolkOoReply(0);
}
if ($status === 4) {
    $f = \Bitrix\Disk\File::loadById($fileId);
    if ($f) {
        Locker::unlock($f, $docKey);
    }
    KeyStorage::release($fileId);
    tolkOoReply(0);
}
if ($status === 3 || $status === 7) {
    // Блокировку не снимаем: несохранённые правки остались на Document Server.
    tolkOoReply(0, 'docserver save error, status ' . $status . ', file ' . $fileId);
}
if ($status !== 2 && $status !== 6) {
    tolkOoReply(0);
}

// Последний редактировавший — первый в users (для статусов 2 и 6).
$userId = (int)($body['users'][0] ?? 0) ?: (int)$link['u'];
$res = FileResolver::resolve($fileId, (int)$link['a'], $userId);
if (!$res || !$res['canUpdate']) {
    tolkOoReply(1, 'no rights, user ' . $userId . ', file ' . $fileId);
}
$file = $res['file'];

// Скачиваем результат только с нашего Document Server.
$url = (string)($body['url'] ?? '');
$public = Options::docServerUrl();
$internal = Options::docServerInternalUrl();
if (strpos($url, $public . '/') === 0) {
    if ($internal !== '') {
        $url = $internal . substr($url, strlen($public));
    }
} elseif ($internal === '' || strpos($url, $internal . '/') !== 0) {
    tolkOoReply(1, 'foreign url: ' . $url);
}

$tmp = CTempFile::GetFileName('oo_' . $file->getName());
CheckDirPath($tmp);
$http = new HttpClient([
    'socketTimeout' => 60,
    'streamTimeout' => 300,
    'disableSslVerification' => !Options::sslVerify(),
]);
if (!$http->download($url, $tmp) || $http->getStatus() !== 200) {
    tolkOoReply(1, 'download failed: ' . $url . ' http ' . $http->getStatus());
}

$fileArray = CFile::MakeFileArray($tmp);
$fileArray['name'] = $file->getName();
if (!Locker::uploadVersion($file, $fileArray, $userId, $docKey)) {
    $errors = array_map(static fn($e) => $e->getMessage(), $file->getErrors());
    tolkOoReply(1, 'uploadVersion failed: ' . implode('; ', $errors));
}

$fresh = \Bitrix\Disk\File::loadById($fileId);
if ($status === 2) {
    // Все вышли, версия сохранена — снимаем блокировку и закрываем сессию.
    if ($fresh) {
        Locker::unlock($fresh, $docKey);
    }
    KeyStorage::release($fileId);
} elseif ($fresh) {
    // forcesave: сессия продолжается, блокировка остаётся.
    KeyStorage::onForceSaved($fileId, (int)$fresh->getFileId());
}
tolkOoReply(0);
