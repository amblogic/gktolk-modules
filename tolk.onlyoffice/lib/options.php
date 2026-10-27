<?php
namespace Tolk\OnlyOffice;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;

final class Options
{
    public const MODULE_ID = 'tolk.onlyoffice';

    private static function get(string $name, string $default = ''): string
    {
        return (string)Option::get(self::MODULE_ID, $name, $default);
    }

    /** Адрес Document Server, как его видит браузер пользователя. */
    public static function docServerUrl(): string
    {
        return rtrim(self::get('doc_server_url'), '/');
    }

    /** Адрес Document Server, как его видит сервер Битрикса (если отличается). */
    public static function docServerInternalUrl(): string
    {
        return rtrim(self::get('doc_server_internal_url'), '/');
    }

    /** Адрес портала, по которому Document Server скачивает файлы и шлёт callback. */
    public static function portalInternalUrl(): string
    {
        $v = rtrim(self::get('portal_internal_url'), '/');
        return $v !== '' ? $v : self::currentHostUrl();
    }

    public static function currentHostUrl(): string
    {
        $r = Context::getCurrent()->getRequest();
        return ($r->isHttps() ? 'https' : 'http') . '://' . $r->getHttpHost();
    }

    /** Общий секрет с Document Server (JWT_SECRET). */
    public static function jwtSecret(): string
    {
        return self::get('jwt_secret');
    }

    /** Внутренний секрет для подписи ссылок модуля; генерируется при установке. */
    public static function linkSecret(): string
    {
        return self::get('link_secret');
    }

    public static function sslVerify(): bool
    {
        return self::get('ssl_verify', 'Y') === 'Y';
    }
}
