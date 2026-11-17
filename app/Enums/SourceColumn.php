<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bookkeeping columns every target table gets, so a row can be traced back to its sheet.
 */
enum SourceColumn: string
{
    case SPREADSHEET = 'source_spreadsheet_id';
    case TAB = 'source_tab';
    case ROW = 'source_row';
    case SYNCED_AT = 'synced_at';

    public static function reserved(): array
    {
        return ['id', ...array_column(self::cases(), 'value')];
    }
}
