<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\SourceColumn;
use App\Enums\SyncMode;
use App\Models\ColumnMapping;
use App\Models\SpreadsheetTab;
use App\Models\TabMapping;
use App\Services\Schema\TargetTableManager;
use Illuminate\Support\Facades\DB;

class TabMappingWriter
{
    public function __construct(
        private readonly TargetTableManager $tables,
        private readonly HeaderMatcher $headers,
        private readonly ValueCaster $caster,
    ) {}

    /**
     * @param  list<mixed>  $headerRow
     * @param  array<int, list<mixed>>  $dataRows  keyed by 1-based sheet row number
     * @return array{written: int, messages: list<string>}
     */
    public function write(TabMapping $mapping, SpreadsheetTab $tab, array $headerRow, array $dataRows): array
    {
        $messages = [];
        $label = "{$tab->name} → {$mapping->target_table}";

        // DDL commits implicitly on MySQL, so the schema is settled before the write transaction opens.
        $added = $this->tables->ensure($mapping);

        if ([] !== $added) {
            $messages[] = "{$label}: added column(s) " . implode(', ', $added) . '.';
        }

        $columns = $mapping->columns;
        $positions = $this->headers->match($headerRow, $columns->pluck('source_column')->all());

        $missing = $columns->reject(fn (ColumnMapping $column): bool => isset($positions[$column->source_column]));

        if ($missing->isNotEmpty()) {
            $messages[] = "{$label}: header(s) not found in the sheet, stored as empty: " . $missing->pluck('source_column')->implode(', ') . '.';
        }

        $syncedAt = now();
        $records = [];
        $invalid = [];

        foreach ($dataRows as $rowNumber => $row) {
            $record = [
                SourceColumn::SPREADSHEET->value => $tab->spreadsheet_id,
                SourceColumn::TAB->value => $tab->name,
                SourceColumn::ROW->value => $rowNumber,
                SourceColumn::SYNCED_AT->value => $syncedAt,
            ];

            foreach ($columns as $column) {
                $raw = isset($positions[$column->source_column]) ? ($row[$positions[$column->source_column]] ?? null) : null;
                [$value, $dropped] = $this->caster->cast($raw, $column->type);

                if ($dropped) {
                    $invalid[$column->target_column] = ($invalid[$column->target_column] ?? 0) + 1;
                }

                $record[$column->target_column] = $value;
            }

            $records[] = $record;
        }

        foreach ($invalid as $column => $count) {
            $messages[] = "{$label}: {$count} value(s) in \"{$column}\" could not be read as {$columns->firstWhere('target_column', $column)->type->value} and were stored as empty.";
        }

        if (SyncMode::UPSERT === $mapping->sync_mode) {
            $records = $this->dropRowsWithoutKey($records, $mapping->keyColumns(), $label, $messages);
        }

        DB::transaction(fn () => $this->persist($mapping, $tab, $records));

        return ['written' => count($records), 'messages' => $messages];
    }

    private function persist(TabMapping $mapping, SpreadsheetTab $tab, array $records): void
    {
        $query = DB::table($mapping->target_table);
        $chunkSize = (int) config('sheets.insert_chunk_size');

        if (SyncMode::REPLACE === $mapping->sync_mode) {
            $query->clone()
                ->where(SourceColumn::SPREADSHEET->value, $tab->spreadsheet_id)
                ->where(SourceColumn::TAB->value, $tab->name)
                ->delete();

            foreach (array_chunk($records, $chunkSize) as $chunk) {
                $query->clone()->insert($chunk);
            }

            return;
        }

        $keys = $mapping->keyColumns();
        $update = array_values(array_diff(array_keys($records[0] ?? []), $keys));

        foreach (array_chunk($records, $chunkSize) as $chunk) {
            $query->clone()->upsert($chunk, $keys, $update);
        }
    }

    /**
     * MySQL treats NULLs as distinct in a unique index, so a keyless row would be inserted again on every run.
     */
    private function dropRowsWithoutKey(array $records, array $keys, string $label, array &$messages): array
    {
        $kept = array_values(array_filter(
            $records,
            fn (array $record): bool => [] === array_filter($keys, fn (string $key): bool => null === $record[$key]),
        ));

        $dropped = count($records) - count($kept);

        if ($dropped > 0) {
            $messages[] = "{$label}: skipped {$dropped} row(s) with an empty key (" . implode(', ', $keys) . ').';
        }

        return $kept;
    }
}
