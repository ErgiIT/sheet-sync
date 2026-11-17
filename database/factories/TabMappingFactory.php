<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SyncMode;
use App\Models\SpreadsheetTab;
use App\Models\TabMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabMapping>
 */
class TabMappingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'spreadsheet_tab_id' => SpreadsheetTab::factory(),
            'target_table' => 'sales',
            'sync_mode' => SyncMode::REPLACE,
            'key_columns' => null,
            'is_active' => true,
        ];
    }

    public function upsert(array $keyColumns): static
    {
        return $this->state(['sync_mode' => SyncMode::UPSERT, 'key_columns' => $keyColumns]);
    }
}
