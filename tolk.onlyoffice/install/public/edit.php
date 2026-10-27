<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Tolk\OnlyOffice\FileResolver;
use Tolk\OnlyOffice\Formats;
use Tolk\OnlyOffice\Jwt;
use Tolk\OnlyOffice\KeyStorage;
use Tolk\OnlyOffice\Locker;
use Tolk\OnlyOffice\Options;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

function tolkOoFail(string $msg, int $code = 403): void
{
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Ошибка</title></head>'
        . '<body style="font:16px sans-serif;padding:32px">' . htmlspecialcharsbx($msg) . '</body></html>';
    die();
}

function tolkOoUserName(int $id): string
{
    $u = $id > 0 ? CUser::GetByID($id)->Fetch() : null;
    return $u ? CUser::FormatName(CSite::GetNameFormat(false), $u, true, false) : ('пользователь #' . $id);
}

if (!Loader::includeModule('disk') || !Loader::includeModule('tolk.onlyoffice')) {
    tolkOoFail('Модуль не установлен', 500);
}
if (Options::docServerUrl() === '' || Options::jwtSecret() === '') {
    tolkOoFail('Модуль ONLYOFFICE не настроен', 500);
}

global $USER, $APPLICATION;
$request = Application::getInstance()->getContext()->getRequest();
$objectId = (int)$request->getQuery('fileId');
$attachedId = (int)$request->getQuery('attachedId');

// Кто открывает: одноразовая ссылка (клиент Битрикс24 / внешний браузер) или сессия портала.
$userId = 0;
$t = (string)$request->getQuery('t');
if ($t !== '') {
    $d = Jwt::decode($t, Options::linkSecret());
    if ($d && ($d['p'] ?? '') === 'open' && (int)$d['f'] === $objectId && (int)$d['a'] === $attachedId) {
        $userId = (int)$d['u'];
    }
}
if ($userId <= 0 && $USER->IsAuthorized()) {
    $userId = (int)$USER->GetID();
}
if ($userId <= 0) {
    if ($t !== '') {
        tolkOoFail('Ссылка устарела. Откройте документ из портала ещё раз.');
    }
    LocalRedirect('/auth/?backurl=' . urlencode($APPLICATION->GetCurPageParam()));
}

$res = FileResolver::resolve($objectId, $attachedId, $userId);
if (!$res) {
    tolkOoFail('Файл не найден или нет доступа', 404);
}
$file = $res['file'];
$fileId = (int)$file->getId();
$ext = mb_strtolower((string)$file->getExtension());
$docType = Formats::documentType($ext);
if ($docType === null) {
    tolkOoFail('Формат .' . $ext . ' не поддерживается');
}
$canEdit = $res['canUpdate'] && Formats::isEditable($ext);
$docKey = KeyStorage::getKey($file);
$lockNotice = '';

if ($canEdit) {
    $lock = Locker::state($file, $docKey);
    if ($lock['state'] === 'stale') {
        Locker::unlockStale($file);
        $lock = ['state' => 'none', 'userId' => 0];
    }
    if ($lock['state'] === 'foreign') {
        $canEdit = false;
        $lockNotice = 'Файл заблокирован: ' . tolkOoUserName($lock['userId']) . '. Открыт только для просмотра.';
    } elseif ($lock['state'] === 'none' && !Locker::lock($file, $docKey, $userId)) {
        $canEdit = false;
        $lockNotice = 'Не удалось заблокировать файл для редактирования. Открыт только для просмотра.';
    }
    // 'ours' — идёт сессия совместного редактирования, просто присоединяемся.
}

$portal = Options::portalInternalUrl();
$linkSecret = Options::linkSecret();

$downloadUrl = $portal . '/onlyoffice/download.php?' . http_build_query([
    't' => Jwt::encode(['p' => 'dl', 'f' => $fileId, 'a' => $attachedId, 'u' => $userId, 'exp' => time() + 43200], $linkSecret),
]);
$callbackUrl = $portal . '/onlyoffice/callback.php?' . http_build_query([
    't' => Jwt::encode(['p' => 'cb', 'f' => $fileId, 'a' => $attachedId, 'u' => $userId, 'exp' => time() + 7 * 86400], $linkSecret),
]);

$userName = tolkOoUserName($userId);
$isMobile = (bool)preg_match('/Mobi|Android|iPhone|iPad/i', (string)$request->getUserAgent());

$config = [
    'type' => $isMobile ? 'mobile' : 'desktop',
    'width' => '100%',
    'height' => '100%',
    'documentType' => $docType,
    'document' => [
        'fileType' => $ext,
        'key' => $docKey,
        'title' => $file->getName(),
        'url' => $downloadUrl,
        'permissions' => [
            'edit' => $canEdit,
            'comment' => $canEdit,
            'review' => $canEdit,
            'download' => true,
            'print' => true,
        ],
    ],
    'editorConfig' => [
        'mode' => $canEdit ? 'edit' : 'view',
        'lang' => 'ru',
        'user' => ['id' => (string)$userId, 'name' => $userName],
        'customization' => [
            'forcesave' => true,   // Ctrl+S / «Сохранить» сразу пишет версию в Битрикс
            'autosave' => true,
            'compactHeader' => false,
        ],
    ],
];
if ($canEdit) {
    $config['editorConfig']['callbackUrl'] = $callbackUrl;
}
$config['token'] = Jwt::encode($config, Options::jwtSecret());

$cfgJson = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
$apiJs = Options::docServerUrl() . '/web-apps/apps/api/documents/api.js';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialcharsbx($file->getName()) ?></title>
    <style>
        html, body { height: 100%; margin: 0; overflow: hidden; }
        body { display: flex; flex-direction: column; }
        #notice { padding: 8px 16px; font: 14px sans-serif; background: #fff4ce; color: #5c4400; border-bottom: 1px solid #e6d28a; }
        #wrap { flex: 1; min-height: 0; }
    </style>
</head>
<body>
<?php if ($lockNotice !== ''): ?>
<div id="notice"><?= htmlspecialcharsbx($lockNotice) ?></div>
<?php endif; ?>
<div id="wrap"><div id="oo"></div></div>
<script src="<?= htmlspecialcharsbx($apiJs) ?>"></script>
<script>
(function () {
    if (typeof DocsAPI === 'undefined') {
        document.body.innerHTML = '<p style="font:16px sans-serif;padding:32px">Сервер документов недоступен. Обратитесь в ИТ-отдел.</p>';
        return;
    }
    var cfg = <?= $cfgJson ?>;
    cfg.events = {
        onRequestClose: function () { window.close(); }
    };
    new DocsAPI.DocEditor('oo', cfg);
})();
</script>
</body>
</html>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
