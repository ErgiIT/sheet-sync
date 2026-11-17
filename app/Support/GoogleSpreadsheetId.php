<?php

declare(strict_types=1);

namespace App\Support;

class GoogleSpreadsheetId
{
    /**
     * Accepts either a bare spreadsheet id or any docs.google.com/spreadsheets/d/{id}/... URL.
     */
    public static function parse(string $input): string
    {
        $input = trim($input);

        if (1 === preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $input, $matches)) {
            return $matches[1];
        }

        return $input;
    }

    public static function isValid(string $id): bool
    {
        return 1 === preg_match('/^[a-zA-Z0-9_-]{20,}$/', $id);
    }
}
