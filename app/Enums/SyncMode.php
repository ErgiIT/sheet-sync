<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncMode: string
{
    /** Rows previously written from the same spreadsheet tab are deleted and re-inserted. */
    case REPLACE = 'replace';

    /** Rows are matched on the mapping's key columns and updated in place. */
    case UPSERT = 'upsert';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
