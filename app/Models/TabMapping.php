<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncMode;
use Database\Factories\TabMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TabMapping extends Model
{
    /** @use HasFactory<TabMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'spreadsheet_tab_id',
        'target_table',
        'sync_mode',
        'key_columns',
        'is_active',
    ];

    protected $casts = [
        'sync_mode' => SyncMode::class,
        'key_columns' => 'array',
        'is_active' => 'boolean',
    ];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(SpreadsheetTab::class, 'spreadsheet_tab_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ColumnMapping::class)->orderBy('position')->orderBy('id');
    }

    public function keyColumns(): array
    {
        return array_values($this->key_columns ?? []);
    }
}
