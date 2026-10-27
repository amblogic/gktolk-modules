<?php
namespace Tolk\OnlyOffice;

use Bitrix\Disk\File;

/**
 * Штатная блокировка Диска (та же, что ставится вручную через «Заблокировать»).
 * Токен блокировки = "onlyoffice:" + ключ сессии, чтобы отличать свои блокировки от чужих.
 *
 * Сигнатуры File::lock()/unlock()/getLock() сверьте со своей версией модуля disk:
 *   grep -n "function lock\|function unlock\|function getLock" bitrix/modules/disk/lib/file.php
 */
final class Locker
{
    public const PREFIX = 'onlyoffice:';

    public static function isSupported(): bool
    {
        if (!method_exists(File::class, 'lock') || !method_exists(File::class, 'getLock')) {
            return false;
        }
        $cfg = '\\Bitrix\\Disk\\Configuration';
        if (class_exists($cfg) && method_exists($cfg, 'isEnabledObjectLock')) {
            return (bool)$cfg::isEnabledObjectLock();
        }
        return true;
    }

    public static function token(string $docKey): string
    {
        return self::PREFIX . $docKey;
    }

    /**
     * none    — блокировки нет;
     * ours    — блокировка текущей сессии ONLYOFFICE (можно присоединяться);
     * stale   — наша, но от старой сессии (осталась после сбоя);
     * foreign — поставлена вручную или другим приложением.
     * @return array{state: string, userId: int}
     */
    public static function state(File $file, string $docKey): array
    {
        if (!self::isSupported()) {
            return ['state' => 'none', 'userId' => 0];
        }
        $lock = $file->getLock();
        if (!$lock) {
            return ['state' => 'none', 'userId' => 0];
        }
        $token = (string)$lock->getToken();
        if ($token === self::token($docKey)) {
            $state = 'ours';
        } elseif (strpos($token, self::PREFIX) === 0) {
            $state = 'stale';
        } else {
            $state = 'foreign';
        }
        return ['state' => $state, 'userId' => (int)$lock->getCreatedBy()];
    }

    public static function lock(File $file, string $docKey, int $userId): bool
    {
        if (!self::isSupported()) {
            return true;
        }
        if ($file->lock($userId, self::token($docKey))) {
            return true;
        }
        self::log('lock failed', $file);
        return false;
    }

    /** Снимает только свою блокировку этой сессии. */
    public static function unlock(File $file, string $docKey): void
    {
        if (!self::isSupported()) {
            return;
        }
        $lock = $file->getLock();
        if ($lock && (string)$lock->getToken() === self::token($docKey)) {
            if (!$file->unlock((int)$lock->getCreatedBy(), self::token($docKey))) {
                self::log('unlock failed', $file);
            }
        }
    }

    /** Снимает блокировку, оставшуюся от прошлой (упавшей) сессии ONLYOFFICE. */
    public static function unlockStale(File $file): void
    {
        $lock = self::isSupported() ? $file->getLock() : null;
        if ($lock && strpos((string)$lock->getToken(), self::PREFIX) === 0) {
            $file->unlock((int)$lock->getCreatedBy(), (string)$lock->getToken());
        }
    }

    /**
     * Сохраняет версию от имени последнего редактора. Если блокировка нашей сессии
     * принадлежит другому участнику, временно снимает её и ставит обратно.
     */
    public static function uploadVersion(File $file, array $fileArray, int $userId, string $docKey)
    {
        $lock = self::isSupported() ? $file->getLock() : null;
        $ours = $lock && (string)$lock->getToken() === self::token($docKey);
        $owner = $ours ? (int)$lock->getCreatedBy() : 0;
        $swap = $ours && $owner !== $userId;

        if ($swap) {
            $file->unlock($owner, self::token($docKey));
        }
        try {
            return $file->uploadVersion($fileArray, $userId);
        } finally {
            if ($swap) {
                $file->lock($owner, self::token($docKey));
            }
        }
    }

    private static function log(string $msg, File $file): void
    {
        $errors = array_map(static fn($e) => $e->getMessage(), $file->getErrors());
        AddMessage2Log($msg . ', file ' . $file->getId() . ': ' . implode('; ', $errors), 'tolk.onlyoffice');
    }
}
