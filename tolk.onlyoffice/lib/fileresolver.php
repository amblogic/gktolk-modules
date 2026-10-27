<?php
namespace Tolk\OnlyOffice;

use Bitrix\Disk\AttachedObject;
use Bitrix\Disk\File;

/**
 * Находит файл и проверяет права пользователя.
 * attachedId — файл, прикреплённый к задаче/посту/чату (права наследуются от сущности);
 * objectId — файл на Диске (права Диска).
 */
final class FileResolver
{
    /** @return array{file: File, canUpdate: bool}|null */
    public static function resolve(int $objectId, int $attachedId, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        if ($attachedId > 0) {
            $attached = AttachedObject::loadById($attachedId, ['OBJECT']);
            if (!$attached) {
                return null;
            }
            $file = $attached->getFile();
            if (!$file || !$attached->canRead($userId)) {
                return null;
            }
            return ['file' => $file, 'canUpdate' => (bool)$attached->canUpdate($userId)];
        }

        $file = File::loadById($objectId, ['STORAGE']);
        if (!$file) {
            return null;
        }
        $sc = $file->getStorage()->getSecurityContext($userId);
        if (!$file->canRead($sc)) {
            return null;
        }
        return ['file' => $file, 'canUpdate' => (bool)$file->canUpdate($sc)];
    }
}
