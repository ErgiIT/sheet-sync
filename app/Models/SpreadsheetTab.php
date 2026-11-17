<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourceColumn;
use Database\Factories\SpreadsheetTabFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpreadsheetTab extends Model
{
    /** @use HasFactory<SpreadsheetTabFactory> */
    use HasFactory;

    protected $fillable = [
        'spreadsheet_id',
        'name',
        'header_row',
        'is_active',
    ];

    protected $casts = [
        'header_row' => 'integer',
        'is_active' => 'boolean',
    ];

    public function spreadsheet(): BelongsTo
    {
        return $this->belongsTo(Spreadsheet::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(TabMapping::class);
    }

    /**
     * Replace mode clears a tab's rows by its name, so after a rename the rows synced under the
     * old name would never be cleared. Moving them to the new name keeps one copy per sheet row.
     */
    protected static function booted(): void
    {
        static::updated(function (SpreadsheetTab $tab): void {
            if ( ! $tab->wasChanged('name')) {
                return;
            }

            foreach ($tab->mappings()->pluck('target_table')->unique() as $table) {
                if ( ! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)
                    ->where(SourceColumn::SPREADSHEET->value, $tab->spreadsheet_id)
                    ->where(SourceColumn::TAB->value, $tab->getOriginal('name'))
                    ->update([SourceColumn::TAB->value => $tab->name]);
            }
        });
    }
}
