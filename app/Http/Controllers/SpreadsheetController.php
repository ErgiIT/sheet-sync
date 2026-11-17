<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\SheetReader;
use App\Enums\SyncTrigger;
use App\Exceptions\SheetReadException;
use App\Http\Requests\SpreadsheetRequest;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SpreadsheetController extends Controller
{
    public function create(): View
    {
        return view('spreadsheets.form', ['spreadsheet' => new Spreadsheet(['is_active' => true])]);
    }

    public function store(SpreadsheetRequest $request): RedirectResponse
    {
        $spreadsheet = Spreadsheet::create($request->validated());

        return redirect()->route('spreadsheets.show', $spreadsheet)->with('status', 'Spreadsheet added. Now add the tabs to read.');
    }

    public function show(Spreadsheet $spreadsheet): View
    {
        $spreadsheet->load(['tabs' => fn ($query) => $query->withCount('mappings')->orderBy('name')]);

        return view('spreadsheets.show', [
            'spreadsheet' => $spreadsheet,
            'runs' => $spreadsheet->syncRuns()->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Spreadsheet $spreadsheet): View
    {
        return view('spreadsheets.form', ['spreadsheet' => $spreadsheet]);
    }

    public function update(SpreadsheetRequest $request, Spreadsheet $spreadsheet): RedirectResponse
    {
        $spreadsheet->update($request->validated());

        return redirect()->route('spreadsheets.show', $spreadsheet)->with('status', 'Spreadsheet updated.');
    }

    public function destroy(Spreadsheet $spreadsheet): RedirectResponse
    {
        $spreadsheet->delete();

        return redirect()->route('dashboard')->with('status', "\"{$spreadsheet->name}\" removed. Data already synced was kept.");
    }

    public function sync(Spreadsheet $spreadsheet): RedirectResponse
    {
        SyncSpreadsheetJob::dispatch($spreadsheet, SyncTrigger::MANUAL);

        return back()->with('status', "Sync started for \"{$spreadsheet->name}\".");
    }

    public function tabNames(Spreadsheet $spreadsheet, SheetReader $reader): JsonResponse
    {
        try {
            return response()->json(['tabs' => $reader->tabNames($spreadsheet->google_spreadsheet_id)]);
        } catch (SheetReadException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
