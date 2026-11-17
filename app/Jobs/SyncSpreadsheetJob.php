<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SyncTrigger;
use App\Models\Spreadsheet;
use App\Services\Sync\SpreadsheetSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSpreadsheetJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public readonly Spreadsheet $spreadsheet,
        public readonly SyncTrigger $trigger = SyncTrigger::MANUAL,
    ) {}

    public function handle(SpreadsheetSyncService $syncService): void
    {
        $syncService->sync($this->spreadsheet, $this->trigger);
    }
}
