<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Models\ColumnMapping;
use App\Models\Spreadsheet;
use App\Models\SpreadsheetTab;
use App\Models\TabMapping;
use App\Support\GoogleSpreadsheetId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Reads and writes the whole sync configuration as one JSON document (see config/sheet-sync.example.json).
 */
class ConfigurationService
{
    public function __construct(private readonly MappingService $mappings) {}

    public function export(): array
    {
        $spreadsheets = Spreadsheet::query()
            ->with('tabs.mappings.columns')
            ->orderBy('id')
            ->get();

        return [
            'spreadsheets' => $spreadsheets->map(fn (Spreadsheet $spreadsheet): array => [
                'name' => $spreadsheet->name,
                'spreadsheet_id' => $spreadsheet->google_spreadsheet_id,
                'is_active' => $spreadsheet->is_active,
                'tabs' => $spreadsheet->tabs->map(fn (SpreadsheetTab $tab): array => [
                    'name' => $tab->name,
                    'header_row' => $tab->header_row,
                    'is_active' => $tab->is_active,
                    'mappings' => $tab->mappings->map(fn (TabMapping $mapping): array => [
                        'target_table' => $mapping->target_table,
                        'sync_mode' => $mapping->sync_mode->value,
                        'key_columns' => $mapping->keyColumns(),
                        'is_active' => $mapping->is_active,
                        'columns' => $mapping->columns->map(fn (ColumnMapping $column): array => [
                            'source_column' => $column->source_column,
                            'target_column' => $column->target_column,
                            'type' => $column->type->value,
                        ])->all(),
                    ])->all(),
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Upserts spreadsheets by Google id, tabs by name and mappings by target table. Anything not in
     * the document is left alone, so importing a partial file never deletes configuration.
     *
     * @return array{spreadsheets: int, tabs: int, mappings: int, added_columns: list<string>}
     *
     * @throws ValidationException
     */
    public function import(array $document): array
    {
        $data = $this->validate($document);
        $summary = ['spreadsheets' => 0, 'tabs' => 0, 'mappings' => 0, 'added_columns' => []];

        // Mapping saves run DDL, which cannot be rolled back on MySQL, so only the plain rows share a transaction.
        $pending = DB::transaction(function () use ($data, &$summary): array {
            $pending = [];

            foreach ($data['spreadsheets'] as $sheetData) {
                $spreadsheet = Spreadsheet::updateOrCreate(
                    ['google_spreadsheet_id' => GoogleSpreadsheetId::parse($sheetData['spreadsheet_id'])],
                    ['name' => $sheetData['name'], 'is_active' => $sheetData['is_active'] ?? true],
                );
                $summary['spreadsheets']++;

                foreach ($sheetData['tabs'] ?? [] as $tabData) {
                    $tab = $spreadsheet->tabs()->updateOrCreate(
                        ['name' => $tabData['name']],
                        ['header_row' => $tabData['header_row'] ?? 1, 'is_active' => $tabData['is_active'] ?? true],
                    );
                    $summary['tabs']++;

                    foreach ($tabData['mappings'] ?? [] as $mappingData) {
                        $pending[] = [$tab, $mappingData];
                    }
                }
            }

            return $pending;
        });

        foreach ($pending as [$tab, $mappingData]) {
            $existing = $tab->mappings()->where('target_table', $mappingData['target_table'])->first();
            $added = $this->mappings->save($tab, $mappingData, $existing);
            $summary['mappings']++;

            foreach ($added as $column) {
                $summary['added_columns'][] = "{$mappingData['target_table']}.{$column}";
            }
        }

        return $summary;
    }

    private function validate(array $document): array
    {
        $prefix = 'spreadsheets.*.tabs.*.mappings.*.';

        $validator = Validator::make($document, [
            'spreadsheets' => ['required', 'array'],
            'spreadsheets.*.name' => ['required', 'string', 'max:255'],
            'spreadsheets.*.spreadsheet_id' => ['required', 'string', 'max:255'],
            'spreadsheets.*.is_active' => ['boolean'],
            'spreadsheets.*.tabs' => ['array'],
            'spreadsheets.*.tabs.*.name' => ['required', 'string', 'max:255'],
            'spreadsheets.*.tabs.*.header_row' => ['integer', 'min:1', 'max:1000'],
            'spreadsheets.*.tabs.*.is_active' => ['boolean'],
            'spreadsheets.*.tabs.*.mappings' => ['array'],
            ...MappingRules::rules($prefix),
        ]);

        $validator->after(function ($validator) use ($document): void {
            foreach ($document['spreadsheets'] ?? [] as $s => $sheet) {
                if ( ! GoogleSpreadsheetId::isValid(GoogleSpreadsheetId::parse((string) ($sheet['spreadsheet_id'] ?? '')))) {
                    $validator->errors()->add("spreadsheets.{$s}.spreadsheet_id", 'Not a valid Google spreadsheet id or URL.');
                }

                foreach ($sheet['tabs'] ?? [] as $t => $tab) {
                    foreach ($tab['mappings'] ?? [] as $m => $mapping) {
                        foreach (MappingRules::crossFieldErrors($mapping, "spreadsheets.{$s}.tabs.{$t}.mappings.{$m}.") as $key => $message) {
                            $validator->errors()->add($key, $message);
                        }
                    }
                }
            }
        });

        return $validator->validate();
    }
}
