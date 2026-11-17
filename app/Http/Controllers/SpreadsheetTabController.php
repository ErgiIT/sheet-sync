<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\SheetReader;
use App\Exceptions\SheetReadException;
use App\Http\Requests\SpreadsheetTabRequest;
use App\Models\Spreadsheet;
use App\Models\SpreadsheetTab;
use App\Support\SqlIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SpreadsheetTabController extends Controller
{
    public function store(SpreadsheetTabRequest $request, Spreadsheet $spreadsheet): RedirectResponse
    {
        $tab = $spreadsheet->tabs()->create($request->validated());

        return redirect()->route('tabs.show', $tab)->with('status', "Tab \"{$tab->name}\" added. Map it to a table next.");
    }

    public function show(SpreadsheetTab $tab): View
    {
        $tab->load(['spreadsheet', 'mappings.columns']);

        return view('tabs.show', ['tab' => $tab]);
    }

    public function update(SpreadsheetTabRequest $request, SpreadsheetTab $tab): RedirectResponse
    {
        $tab->update($request->validated());

        return back()->with('status', 'Tab updated.');
    }

    public function destroy(SpreadsheetTab $tab): RedirectResponse
    {
        $tab->delete();

        return redirect()->route('spreadsheets.show', $tab->spreadsheet_id)->with('status', "Tab \"{$tab->name}\" removed.");
    }

    public function headers(SpreadsheetTab $tab, SheetReader $reader): JsonResponse
    {
        try {
            $rows = $reader->rows($tab->spreadsheet->google_spreadsheet_id, $tab->name);
        } catch (SheetReadException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $headers = array_values(array_filter(
            array_map(fn (mixed $cell): string => trim((string) $cell), $rows[$tab->header_row - 1] ?? []),
            fn (string $header): bool => '' !== $header,
        ));

        return response()->json([
            'headers' => array_map(fn (string $header): array => [
                'source_column' => $header,
                'target_column' => SqlIdentifier::fromHeader($header),
            ], $headers),
        ]);
    }
}
