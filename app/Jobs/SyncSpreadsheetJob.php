<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Models\Spreadsheet;
use App\Services\Sync\SpreadsheetSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

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

    /**
     * Runs when the worker gave up on the job: it hit the timeout, or the worker died and the job ran out of
     * attempts. The sync never reached its own error handling, so its run would stay "running" and its lock
     * would block every sync of this spreadsheet until it expired. While that lock is held no other sync can
     * start, so any run still marked running for this spreadsheet is the one that died.
     */
    public function failed(?Throwable $exception): void
    {
        $reason = rtrim($exception?->getMessage() ?: 'unknown error', '.');

        $this->spreadsheet->syncRuns()
            ->where('status', SyncStatus::RUNNING)
            ->get()
            ->each->update([
                'status' => SyncStatus::FAILED,
                'messages' => ["The sync job stopped before finishing: {$reason}."],
                'finished_at' => now(),
            ]);

        Cache::lock("sheet-sync:{$this->spreadsheet->id}")->forceRelease();
    }
}
