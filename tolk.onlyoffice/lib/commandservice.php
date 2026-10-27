<?php
namespace Tolk\OnlyOffice;

use Bitrix\Main\Web\HttpClient;

/** Command Service Document Server: проверка, жива ли сессия редактирования. */
final class CommandService
{
    /**
     * @return int|null код ошибки Document Server (0 — сессия существует,
     *                  1 — документа с таким ключом нет), null — сервер недоступен.
     */
    public static function info(string $docKey): ?int
    {
        $base = Options::docServerInternalUrl() ?: Options::docServerUrl();
        if ($base === '') {
            return null;
        }
        $payload = ['c' => 'info', 'key' => $docKey];
        $payload['token'] = Jwt::encode($payload, Options::jwtSecret());

        $http = new HttpClient([
            'socketTimeout' => 10,
            'streamTimeout' => 10,
            'disableSslVerification' => !Options::sslVerify(),
        ]);
        $http->setHeader('Content-Type', 'application/json');
        $resp = $http->post($base . '/coauthoring/CommandService.ashx', json_encode($payload));
        if ($resp === false || $http->getStatus() !== 200) {
            return null;
        }
        $data = json_decode((string)$resp, true);
        return isset($data['error']) ? (int)$data['error'] : null;
    }
}
