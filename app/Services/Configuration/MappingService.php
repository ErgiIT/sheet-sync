<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Enums\SyncMode;
use App\Models\SpreadsheetTab;
use App\Models\TabMapping;
use App\Services\Schema\TargetTableManager;
use Illuminate\Support\Facades\DB;

class MappingService
{
    public function __construct(private readonly TargetTableManager $tables) {}

    /**
     * Saves a mapping with its full column list and brings the target table up to date right away,
     * so a bad mapping fails here rather than at 2am.
     *
     * @return list<string> columns added to the target table
     */
    public function save(SpreadsheetTab $tab, array $data, ?TabMapping $mapping = null): array
    {
        $mapping = DB::transaction(function () use ($tab, $data, $mapping): TabMapping {
            $mapping ??= new TabMapping(['spreadsheet_tab_id' => $tab->id]);
            $mode = $data['sync_mode'] ?? SyncMode::REPLACE->value;
            $isUpsert = SyncMode::UPSERT->value === $mode;

            $mapping->fill([
                'target_table' => $data['target_table'],
                'sync_mode' => $mode,
                'key_columns' => $isUpsert ? array_values($data['key_columns'] ?? []) : null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            $mapping->columns()->delete();

            foreach (array_values($data['columns']) as $position => $column) {
                $mapping->columns()->create([
                    'source_column' => trim($column['source_column']),
                    'target_column' => $column['target_column'],
                    'type' => $column['type'],
                    'position' => $position,
                ]);
            }

            return $mapping;
        });

        return $mapping->is_active ? $this->tables->ensure($mapping->load('columns')) : [];
    }
}
