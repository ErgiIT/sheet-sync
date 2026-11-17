<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SourceColumn;

class SqlIdentifier
{
    public const PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    public static function isValid(string $name): bool
    {
        return 1 === preg_match(self::PATTERN, $name);
    }

    public static function isProtectedTable(string $table): bool
    {
        return in_array($table, config('sheets.protected_tables'), true);
    }

    public static function isReservedColumn(string $column): bool
    {
        return in_array($column, SourceColumn::reserved(), true);
    }

    /**
     * Turns a sheet header into a suggested column name: "Product Name (€)" => "product_name".
     */
    public static function fromHeader(string $header): string
    {
        $name = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($header)));
        $name = trim((string) $name, '_');

        if ('' === $name || ! ctype_alpha($name[0])) {
            $name = "col_{$name}";
        }

        return mb_substr(rtrim($name, '_'), 0, 64);
    }
}
