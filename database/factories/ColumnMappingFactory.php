<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ColumnType;
use App\Models\ColumnMapping;
use App\Models\TabMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColumnMapping>
 */
class ColumnMappingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tab_mapping_id' => TabMapping::factory(),
            'source_column' => 'name',
            'target_column' => 'name',
            'type' => ColumnType::STRING,
            'position' => 0,
        ];
    }
}
