<?php
namespace Tolk\OnlyOffice;

final class Formats
{
    private const EDIT = [
        'docx' => 'word', 'odt' => 'word', 'txt' => 'word',
        'xlsx' => 'cell', 'ods' => 'cell', 'csv' => 'cell',
        'pptx' => 'slide', 'odp' => 'slide',
    ];

    // Старые форматы — только просмотр (редактирование требует конвертации в OOXML).
    // documentType "pdf" поддерживается Document Server 8.1+.
    private const VIEW = [
        'doc' => 'word', 'rtf' => 'word',
        'xls' => 'cell', 'ppt' => 'slide', 'pdf' => 'pdf',
    ];

    public static function documentType(string $ext): ?string
    {
        return self::EDIT[$ext] ?? self::VIEW[$ext] ?? null;
    }

    public static function isEditable(string $ext): bool
    {
        return isset(self::EDIT[$ext]);
    }

    /** Расширения, клик по которым перехватывается на портале. */
    public static function intercepted(): array
    {
        return array_keys(self::EDIT);
    }
}
