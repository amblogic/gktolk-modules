<?php
// Создаёт пустой документ в папке Диска и возвращает его id.
use Bitrix\Disk\Folder;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

define('NO_KEEP_STATISTIC', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

function tolkOoJson(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    die();
}

global $USER;
if (!$USER->IsAuthorized() || !check_bitrix_sessid()) {
    tolkOoJson(['error' => 'Нет авторизации'], 403);
}
if (!Loader::includeModule('disk') || !Loader::includeModule('tolk.onlyoffice')) {
    tolkOoJson(['error' => 'Модуль не установлен'], 500);
}

$types = [
    'docx' => 'Новый документ',
    'xlsx' => 'Новая таблица',
    'pptx' => 'Новая презентация',
];

$request = Application::getInstance()->getContext()->getRequest();
$type = (string)$request->getPost('type');
$folderId = (int)$request->getPost('folderId');
$userId = (int)$USER->GetID();

if (!isset($types[$type])) {
    tolkOoJson(['error' => 'Неизвестный тип документа'], 400);
}

$folder = Folder::loadById($folderId, ['STORAGE']);
if (!$folder) {
    tolkOoJson(['error' => 'Папка не найдена'], 404);
}
$sc = $folder->getStorage()->getSecurityContext($userId);
if (!$folder->canAdd($sc)) {
    tolkOoJson(['error' => 'Нет прав на создание файлов в этой папке'], 403);
}

$template = Application::getDocumentRoot() . getLocalPath('modules/tolk.onlyoffice/templates') . '/new.' . $type;
if (!is_file($template)) {
    tolkOoJson(['error' => 'Не найден шаблон new.' . $type], 500);
}

// Копируем шаблон во временный файл, чтобы Битрикс не переместил оригинал.
$tmp = CTempFile::GetFileName('new.' . $type);
CheckDirPath($tmp);
copy($template, $tmp);
$fileArray = CFile::MakeFileArray($tmp);
$fileArray['name'] = $types[$type] . '.' . $type;

$file = $folder->uploadFile(
    $fileArray,
    ['NAME' => $fileArray['name'], 'CREATED_BY' => $userId],
    [],
    true // уникальное имя: «Новый документ (1).docx»
);
if (!$file) {
    $errors = array_map(static fn($e) => $e->getMessage(), $folder->getErrors());
    tolkOoJson(['error' => 'Не удалось создать файл: ' . implode('; ', $errors)], 500);
}

tolkOoJson(['objectId' => (int)$file->getId(), 'name' => $file->getName()]);
