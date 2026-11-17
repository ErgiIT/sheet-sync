<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\SheetReadException;

interface SheetReader
{
    /**
     * @return list<string>
     *
     * @throws SheetReadException
     */
    public function tabNames(string $spreadsheetId): array;

    /**
     * Raw cell values of a tab, row by row. Trailing empty cells are not returned.
     *
     * @return list<list<mixed>>
     *
     * @throws SheetReadException
     */
    public function rows(string $spreadsheetId, string $tab): array;
}
