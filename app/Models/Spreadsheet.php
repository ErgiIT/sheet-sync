<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SpreadsheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Spreadsheet extends Model
{
    /** @use HasFactory<SpreadsheetFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'google_spreadsheet_id',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tabs(): HasMany
    {
        return $this->hasMany(SpreadsheetTab::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function latestSyncRun(): HasOne
    {
        return $this->hasOne(SyncRun::class)->latestOfMany();
    }

    public function googleUrl(): string
    {
        return "https://docs.google.com/spreadsheets/d/{$this->google_spreadsheet_id}/edit";
    }
}
