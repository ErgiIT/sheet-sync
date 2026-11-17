<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Contracts\SheetReader;
use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Models\Spreadsheet;
use App\Models\SpreadsheetTab;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SpreadsheetSyncService
{
    public function __construct(
        private readonly SheetReader $reader,
        private readonly TabMappingWriter $writer,
    ) {}

    public function sync(Spreadsheet $spreadsheet, SyncTrigger $trigger): SyncRun
    {
        $run = $spreadsheet->syncRuns()->create([
            'trigger' => $trigger,
            'status' => SyncStatus::RUNNING,
            'started_at' => now(),
            // Set explicitly so the returned model shows 0, not null, when nothing was read.
            'rows_read' => 0,
            'rows_written' => 0,
        ]);

        $lock = Cache::lock("sheet-sync:{$spreadsheet->id}", (int) config('sheets.lock_seconds'));

        if ( ! $lock->get()) {
            return $this->finish($run, SyncStatus::SKIPPED, ['Another sync of this spreadsheet is still running.']);
        }

        try {
            return $this->run($spreadsheet, $run);
        } catch (Throwable $e) {
            Log::error("Sheet sync failed for spreadsheet {$spreadsheet->id}: {$e->getMessage()}", ['exception' => $e]);

            return $this->finish($run, SyncStatus::FAILED, [$e->getMessage()]);
        } finally {
            $lock->release();
        }
    }

    private function run(Spreadsheet $spreadsheet, SyncRun $run): SyncRun
    {
        $tabs = $spreadsheet->tabs()
            ->where('is_active', true)
            ->with(['mappings' => fn ($query) => $query->where('is_active', true)->with('columns')])
            ->get();

        $messages = [];
        $failures = 0;
        $attempts = 0;

        if ($tabs->isEmpty()) {
            $messages[] = 'No active tabs configured.';
        }

        foreach ($tabs as $tab) {
            if ($tab->mappings->isEmpty()) {
                $messages[] = "{$tab->name}: no active mappings, skipped.";

                continue;
            }

            try {
                $rows = $this->reader->rows($spreadsheet->google_spreadsheet_id, $tab->name);
            } catch (Throwable $e) {
                $messages[] = "{$tab->name}: {$e->getMessage()}";
                $failures += $tab->mappings->count();
                $attempts += $tab->mappings->count();

                continue;
            }

            [$headerRow, $dataRows] = $this->split($tab, $rows);
            $run->increment('rows_read', count($dataRows));

            foreach ($tab->mappings as $mapping) {
                $attempts++;

                try {
                    $result = $this->writer->write($mapping, $tab, $headerRow, $dataRows);
                    $run->increment('rows_written', $result['written']);
                    array_push($messages, ...$result['messages']);
                } catch (Throwable $e) {
                    $failures++;
                    $messages[] = "{$tab->name} → {$mapping->target_table}: {$e->getMessage()}";
                    Log::warning("Sheet sync mapping {$mapping->id} failed: {$e->getMessage()}", ['exception' => $e]);
                }
            }
        }

        $status = match (true) {
            0 === $failures => SyncStatus::SUCCESS,
            $failures < $attempts => SyncStatus::PARTIAL,
            default => SyncStatus::FAILED,
        };

        if (SyncStatus::FAILED !== $status) {
            $spreadsheet->update(['last_synced_at' => now()]);
        }

        return $this->finish($run, $status, $messages);
    }

    /**
     * @return array{0: list<mixed>, 1: array<int, list<mixed>>} header row, and data rows keyed by sheet row number
     */
    private function split(SpreadsheetTab $tab, array $rows): array
    {
        $headerIndex = max(1, $tab->header_row) - 1;
        $headerRow = $rows[$headerIndex] ?? [];
        $dataRows = [];

        foreach (array_slice($rows, $headerIndex + 1, preserve_keys: true) as $index => $row) {
            if ($this->isBlank($row)) {
                continue;
            }

            $dataRows[$index + 1] = $row;
        }

        return [$headerRow, $dataRows];
    }

    private function isBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if (null !== $cell && '' !== trim((string) $cell)) {
                return false;
            }
        }

        return true;
    }

    private function finish(SyncRun $run, SyncStatus $status, array $messages): SyncRun
    {
        $run->update([
            'status' => $status,
            'messages' => $messages,
            'finished_at' => now(),
        ]);

        return $run;
    }
}
