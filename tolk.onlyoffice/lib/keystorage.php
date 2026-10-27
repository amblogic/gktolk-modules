<?php
namespace Tolk\OnlyOffice;

use Bitrix\Disk\File;
use Bitrix\Main\Application;

/**
 * Ключ документа (document.key) должен оставаться одинаковым, пока сессия
 * совместного редактирования жива (в том числе после forcesave), и меняться,
 * когда файл изменён вне ONLYOFFICE или сессия закрыта.
 * LAST_SEEN — время последнего сигнала от Document Server (для агента очистки).
 */
final class KeyStorage
{
    private const TABLE = 'tolk_onlyoffice_key';

    public static function getKey(File $file): string
    {
        $c = Application::getConnection();
        $id = (int)$file->getId();
        $bFileId = (int)$file->getFileId();

        $row = $c->query('SELECT DOC_KEY, B_FILE_ID FROM ' . self::TABLE . ' WHERE FILE_ID = ' . $id)->fetch();
        if ($row && (int)$row['B_FILE_ID'] === $bFileId) {
            return (string)$row['DOC_KEY'];
        }

        $key = $id . '_' . $bFileId . '_' . bin2hex(random_bytes(6));
        $c->queryExecute('DELETE FROM ' . self::TABLE . ' WHERE FILE_ID = ' . $id);
        $c->queryExecute(
            'INSERT INTO ' . self::TABLE . ' (FILE_ID, B_FILE_ID, DOC_KEY, LAST_SEEN) VALUES ('
            . $id . ', ' . $bFileId . ", '" . $c->getSqlHelper()->forSql($key) . "', " . time() . ')'
        );
        return $key;
    }

    public static function onForceSaved(int $fileId, int $newBFileId): void
    {
        Application::getConnection()->queryExecute(
            'UPDATE ' . self::TABLE . ' SET B_FILE_ID = ' . $newBFileId . ', LAST_SEEN = ' . time()
            . ' WHERE FILE_ID = ' . $fileId
        );
    }

    public static function touch(int $fileId): void
    {
        Application::getConnection()->queryExecute(
            'UPDATE ' . self::TABLE . ' SET LAST_SEEN = ' . time() . ' WHERE FILE_ID = ' . $fileId
        );
    }

    public static function release(int $fileId): void
    {
        Application::getConnection()->queryExecute(
            'DELETE FROM ' . self::TABLE . ' WHERE FILE_ID = ' . $fileId
        );
    }

    /** Сессии, от которых давно не было сигналов. */
    public static function stale(int $olderThan, int $limit = 50): array
    {
        return Application::getConnection()->query(
            'SELECT FILE_ID, DOC_KEY FROM ' . self::TABLE
            . ' WHERE LAST_SEEN < ' . $olderThan . ' ORDER BY LAST_SEEN LIMIT ' . $limit
        )->fetchAll();
    }
}
