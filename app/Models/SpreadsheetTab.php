<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SpreadsheetTabFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
