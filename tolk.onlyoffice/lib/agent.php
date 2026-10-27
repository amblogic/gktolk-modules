<?php
namespace Tolk\OnlyOffice;

use Bitrix\Disk\File;
use Bitrix\Main\Loader;

final class Agent
{
    /** Через сколько секунд тишины спрашивать Document Server о сессии. */
    private const SILENCE = 1800;

    public static function cleanup(): string
    {
        if (Loader::includeModule('disk')) {
            foreach (KeyStorage::stale(time() - self::SILENCE) as $row) {
                $fileId = (int)$row['FILE_ID'];
                $key = (string)$row['DOC_KEY'];
                $code = CommandService::info($key);

                if ($code === 1) {
                    // Document Server сессию не знает — значит, она умерла без callback.
                    $file = File::loadById($fileId);
                    if ($file) {
                        Locker::unlock($file, $key);
                    }
                    KeyStorage::release($fileId);
                    AddMessage2Log('stale session released, file ' . $fileId, 'tolk.onlyoffice');
                } elseif ($code === 0) {
                    KeyStorage::touch($fileId); // сессия жива
                }
                // null — Document Server недоступен: ничего не трогаем, повторим позже.
            }
        }
        return '\\Tolk\\OnlyOffice\\Agent::cleanup();';
    }
}
