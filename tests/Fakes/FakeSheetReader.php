<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\SheetReader;
use App\Exceptions\SheetReadException;

class FakeSheetReader implements SheetReader
{
    /** @var array<string, array<string, list<list<mixed>>>> */
    private array $sheets = [];

    public function put(string $spreadsheetId, string $tab, array $rows): static
    {
        $this->sheets[$spreadsheetId][$tab] = $rows;

        return $this;
    }

    public function tabNames(string $spreadsheetId): array
    {
        return array_keys($this->sheets[$spreadsheetId] ?? throw new SheetReadException("Spreadsheet {$spreadsheetId} not found."));
    }

    public function rows(string $spreadsheetId, string $tab): array
    {
        return $this->sheets[$spreadsheetId][$tab] ?? throw new SheetReadException("Tab {$tab} not found in {$spreadsheetId}.");
    }
}
