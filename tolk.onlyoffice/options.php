<?php
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

$moduleId = 'tolk.onlyoffice';
global $USER, $APPLICATION;
if (!$USER->IsAdmin()) {
    return;
}
\Bitrix\Main\Loader::includeModule($moduleId);

$fields = [
    'doc_server_url' => ['Адрес Document Server (для браузера)', 'https://docs.example.ru'],
    'doc_server_internal_url' => ['Адрес Document Server для сервера Битрикса (если отличается)', 'http://10.0.0.20'],
    'portal_internal_url' => ['Адрес портала для Document Server (пусто — текущий хост)', 'https://portal.example.ru'],
    'jwt_secret' => ['JWT-секрет (совпадает с JWT_SECRET Document Server)', ''],
];

$request = Application::getInstance()->getContext()->getRequest();
if ($request->isPost() && $request->getPost('save') !== null && check_bitrix_sessid()) {
    foreach ($fields as $code => $f) {
        Option::set($moduleId, $code, trim((string)$request->getPost($code)));
    }
    Option::set($moduleId, 'ssl_verify', $request->getPost('ssl_verify') === 'Y' ? 'Y' : 'N');
    CAdminMessage::ShowNote('Настройки сохранены');
}

$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'main', 'TAB' => 'Настройки', 'TITLE' => 'ONLYOFFICE Document Server'],
]);
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); $tabControl->BeginNextTab(); ?>
    <?php foreach ($fields as $code => [$label, $placeholder]): ?>
        <tr>
            <td width="40%"><?= htmlspecialcharsbx($label) ?>:</td>
            <td><input type="text" size="60" name="<?= $code ?>"
                       placeholder="<?= htmlspecialcharsbx($placeholder) ?>"
                       value="<?= htmlspecialcharsbx(Option::get($moduleId, $code, '')) ?>"></td>
        </tr>
    <?php endforeach; ?>
        <tr>
            <td>Проверять SSL-сертификат Document Server:</td>
            <td><input type="checkbox" name="ssl_verify" value="Y"
                <?= Option::get($moduleId, 'ssl_verify', 'Y') === 'Y' ? 'checked' : '' ?>></td>
        </tr>
        <tr>
            <td>Блокировка файлов Диска:</td>
            <td><?= \Bitrix\Main\Loader::includeModule('disk') && \Tolk\OnlyOffice\Locker::isSupported()
                    ? 'доступна — файлы блокируются на время редактирования'
                    : '<b>недоступна</b> — включите блокировку объектов в настройках модуля «Диск»' ?></td>
        </tr>
    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
    <?php $tabControl->End(); ?>
</form>
