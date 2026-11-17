<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Spreadsheet;
use App\Models\SpreadsheetTab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpreadsheetTab>
 */
class SpreadsheetTabFactory extends Factory
{
    public function definition(): array
    {
        return [
            'spreadsheet_id' => Spreadsheet::factory(),
            'name' => fake()->unique()->word(),
            'header_row' => 1,
            'is_active' => true,
        ];
    }
}
