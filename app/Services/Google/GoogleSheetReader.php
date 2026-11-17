<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Contracts\SheetReader;
use App\Exceptions\SheetReadException;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Sheets;
use Google\Service\Sheets\Sheet;

class GoogleSheetReader implements SheetReader
{
    private ?Sheets $service = null;

    public function __construct(private readonly GoogleClientFactory $clientFactory) {}

    public function tabNames(string $spreadsheetId): array
    {
        try {
            $spreadsheet = $this->service()->spreadsheets->get($spreadsheetId, ['fields' => 'sheets.properties.title']);
        } catch (GoogleServiceException $e) {
            throw $this->wrap($e, $spreadsheetId);
        }

        return array_map(fn (Sheet $sheet): string => $sheet->getProperties()->getTitle(), $spreadsheet->getSheets());
    }

    public function rows(string $spreadsheetId, string $tab): array
    {
        try {
            // Numbers come back raw (no currency or thousands formatting) and date cells as serial
            // numbers, so dates don't depend on the sheet's locale: a US sheet showing 3/4/2026 means
            // 4 March, which no text format list can tell apart from 3 April.
            $response = $this->service()->spreadsheets_values->get($spreadsheetId, $this->quoteTab($tab), [
                'valueRenderOption' => 'UNFORMATTED_VALUE',
                'dateTimeRenderOption' => 'SERIAL_NUMBER',
                'majorDimension' => 'ROWS',
            ]);
        } catch (GoogleServiceException $e) {
            throw $this->wrap($e, $spreadsheetId, $tab);
        }

        return array_map(array_values(...), $response->getValues() ?? []);
    }

    private function service(): Sheets
    {
        return $this->service ??= new Sheets($this->clientFactory->make());
    }

    private function quoteTab(string $tab): string
    {
        return "'" . str_replace("'", "''", $tab) . "'";
    }

    private function wrap(GoogleServiceException $e, string $spreadsheetId, ?string $tab = null): SheetReadException
    {
        $reason = $e->getErrors()[0]['message'] ?? $e->getMessage();
        $target = null === $tab ? $spreadsheetId : "{$spreadsheetId} / {$tab}";

        $hint = match ($e->getCode()) {
            403 => ' Share the spreadsheet with the service account e-mail (Viewer).',
            404 => ' Check the spreadsheet id.',
            default => '',
        };

        return new SheetReadException("Google Sheets API error ({$e->getCode()}) for {$target}: {$reason}.{$hint}", $e->getCode(), $e);
    }
}
