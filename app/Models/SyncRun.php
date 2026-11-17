<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $fillable = [
        'spreadsheet_id',
        'trigger',
        'status',
        'rows_read',
        'rows_written',
        'messages',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'trigger' => SyncTrigger::class,
        'status' => SyncStatus::class,
        'rows_read' => 'integer',
        'rows_written' => 'integer',
        'messages' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function spreadsheet(): BelongsTo
    {
        return $this->belongsTo(Spreadsheet::class);
    }

    public function durationForHumans(): ?string
    {
        if (null === $this->finished_at) {
            return null;
        }

        return CarbonInterval::seconds((int) $this->started_at->diffInSeconds($this->finished_at))
            ->cascade()
            ->forHumans(short: true);
    }
}
