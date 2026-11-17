<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;
use App\Services\Sync\SpreadsheetSyncService;
use Illuminate\Console\Command;

class SyncSheetsCommand extends Command
{
    protected $signature = 'sheets:sync
        {--spreadsheet=* : Only these spreadsheet ids (local id or Google id)}
        {--queue : Dispatch one queued job per spreadsheet instead of running inline}
        {--scheduled : Mark the runs as triggered by the scheduler}';

    protected $description = 'Pull every active Google spreadsheet into its mapped database tables';

    public function handle(SpreadsheetSyncService $syncService): int
    {
        $only = array_map(strval(...), $this->option('spreadsheet'));
        $trigger = $this->option('scheduled') ? SyncTrigger::SCHEDULE : SyncTrigger::CONSOLE;

        $spreadsheets = Spreadsheet::query()
            ->where('is_active', true)
            ->when([] !== $only, fn ($query) => $query->where(
                fn ($query) => $query->whereIn('id', array_filter($only, is_numeric(...)))->orWhereIn('google_spreadsheet_id', $only),
            ))
            ->orderBy('id')
            ->get();

        if ($spreadsheets->isEmpty()) {
            $this->warn('No active spreadsheets to sync.');

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            $spreadsheets->each(fn (Spreadsheet $spreadsheet) => SyncSpreadsheetJob::dispatch($spreadsheet, $trigger));
            $this->info("Queued {$spreadsheets->count()} spreadsheet sync(s).");

            return self::SUCCESS;
        }

        $failed = 0;
        $rows = [];

        foreach ($spreadsheets as $spreadsheet) {
            $this->line("Syncing <comment>{$spreadsheet->name}</comment>...");
            $run = $syncService->sync($spreadsheet, $trigger);

            foreach ($run->messages ?? [] as $message) {
                $this->line("  - {$message}");
            }

            if (in_array($run->status, [SyncStatus::FAILED, SyncStatus::PARTIAL], true)) {
                $failed++;
            }

            $rows[] = [$spreadsheet->name, $run->status->value, $run->rows_read, $run->rows_written, $run->durationForHumans()];
        }

        $this->newLine();
        $this->table(['Spreadsheet', 'Status', 'Rows read', 'Rows written', 'Took'], $rows);

        return 0 === $failed ? self::SUCCESS : self::FAILURE;
    }
}
