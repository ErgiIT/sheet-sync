<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Spreadsheet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Spreadsheet>
 */
class SpreadsheetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'google_spreadsheet_id' => Str::random(44),
            'is_active' => true,
        ];
    }
}
