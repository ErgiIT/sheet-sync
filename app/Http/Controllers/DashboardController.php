<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SyncTrigger;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;
use App\Models\SyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'spreadsheets' => Spreadsheet::query()
                ->withCount('tabs')
                ->with('latestSyncRun')
                ->orderBy('name')
                ->get(),
            'recentRuns' => SyncRun::query()->with('spreadsheet')->latest('id')->limit(10)->get(),
        ]);
    }

    public function syncAll(): RedirectResponse
    {
        $spreadsheets = Spreadsheet::query()->where('is_active', true)->get();
        $spreadsheets->each(fn (Spreadsheet $spreadsheet) => SyncSpreadsheetJob::dispatch($spreadsheet, SyncTrigger::MANUAL));

        return back()->with('status', "Sync started for {$spreadsheets->count()} spreadsheet(s).");
    }
}
