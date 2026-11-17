<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ColumnType;
use App\Enums\SyncMode;
use App\Http\Requests\TabMappingRequest;
use App\Models\SpreadsheetTab;
use App\Models\TabMapping;
use App\Services\Configuration\MappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class TabMappingController extends Controller
{
    public function create(SpreadsheetTab $tab): View
    {
        return $this->form($tab, new TabMapping(['sync_mode' => SyncMode::REPLACE, 'is_active' => true]));
    }

    public function store(TabMappingRequest $request, SpreadsheetTab $tab, MappingService $mappings): RedirectResponse
    {
        return $this->save($request, $tab, $mappings);
    }

    public function edit(TabMapping $mapping): View
    {
        return $this->form($mapping->tab, $mapping->load('columns'));
    }

    public function update(TabMappingRequest $request, TabMapping $mapping, MappingService $mappings): RedirectResponse
    {
        return $this->save($request, $mapping->tab, $mappings, $mapping);
    }

    public function destroy(TabMapping $mapping): RedirectResponse
    {
        $mapping->delete();

        return redirect()->route('tabs.show', $mapping->spreadsheet_tab_id)
            ->with('status', "Mapping to \"{$mapping->target_table}\" removed. The table and its data were kept.");
    }

    private function save(TabMappingRequest $request, SpreadsheetTab $tab, MappingService $mappings, ?TabMapping $mapping = null): RedirectResponse
    {
        try {
            $added = $mappings->save($tab, $request->validated(), $mapping);
        } catch (Throwable $e) {
            Log::error("Saving mapping for tab {$tab->id} failed: {$e->getMessage()}", ['exception' => $e]);

            return back()->withInput()->withErrors(['target_table' => "The mapping was saved but the table could not be updated: {$e->getMessage()}"]);
        }

        $status = 'Mapping saved.';

        if ([] !== $added) {
            $status .= ' Added to the table: ' . implode(', ', $added) . '.';
        }

        return redirect()->route('tabs.show', $tab)->with('status', $status);
    }

    private function form(SpreadsheetTab $tab, TabMapping $mapping): View
    {
        $columns = old('columns', $mapping->exists
            ? $mapping->columns->map(fn ($column): array => [
                'source_column' => $column->source_column,
                'target_column' => $column->target_column,
                'type' => $column->type->value,
            ])->all()
            : []);

        return view('mappings.form', [
            'tab' => $tab->load('spreadsheet'),
            'mapping' => $mapping,
            'columns' => array_values($columns),
            'keyColumns' => old('key_columns', $mapping->keyColumns()),
            'types' => ColumnType::cases(),
        ]);
    }
}
