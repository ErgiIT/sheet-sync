<?php

declare(strict_types=1);

use App\Enums\ColumnType;
use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Models\Spreadsheet;
use App\Models\SpreadsheetTab;
use App\Models\TabMapping;
use App\Services\Configuration\MappingService;
use App\Services\Sync\SpreadsheetSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function salesMapping(SpreadsheetTab $tab, array $overrides = []): TabMapping
{
    app(MappingService::class)->save($tab, array_merge([
        'target_table' => 'sales',
        'sync_mode' => 'replace',
        'columns' => [
            ['source_column' => 'date', 'target_column' => 'date', 'type' => 'date'],
            ['source_column' => 'product_name', 'target_column' => 'name', 'type' => 'string'],
            ['source_column' => 'price', 'target_column' => 'product_price', 'type' => 'decimal'],
            ['source_column' => 'amount', 'target_column' => 'amount', 'type' => 'integer'],
        ],
    ], $overrides));

    return $tab->mappings()->where('target_table', $overrides['target_table'] ?? 'sales')->firstOrFail();
}

function syncSheet(Spreadsheet $spreadsheet)
{
    return app(SpreadsheetSyncService::class)->sync($spreadsheet->fresh(), SyncTrigger::CONSOLE);
}

beforeEach(function (): void {
    $this->sheets = fakeSheets();
    $this->spreadsheet = Spreadsheet::factory()->create();
    $this->tab = SpreadsheetTab::factory()->for($this->spreadsheet)->create(['name' => 'Sales']);
});

it('creates the target table and stores mapped rows', function (): void {
    salesMapping($this->tab);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'price', 'amount'],
        ['2026-03-05', 'Apple', 1.5, 10],
        ['2026-03-06', 'Pear', 2.25, 4],
    ]);

    $run = syncSheet($this->spreadsheet);

    expect($run->status)->toBe(SyncStatus::SUCCESS)
        ->and($run->rows_read)->toBe(2)
        ->and($run->rows_written)->toBe(2);

    $first = DB::table('sales')->orderBy('id')->first();

    expect($first->date)->toBe('2026-03-05')
        ->and($first->name)->toBe('Apple')
        ->and((float) $first->product_price)->toBe(1.5)
        ->and((int) $first->amount)->toBe(10)
        ->and((int) $first->source_spreadsheet_id)->toBe($this->spreadsheet->id)
        ->and($first->source_tab)->toBe('Sales')
        ->and((int) $first->source_row)->toBe(2);
});

it('reads files whose columns are reordered or have extras', function (): void {
    salesMapping($this->tab);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['Notes', 'Amount', 'Price', 'Product Name', 'Date', 'Region'],
        ['gift', 3, 9.99, 'Plum', '2026-01-02', 'EU'],
    ]);

    syncSheet($this->spreadsheet);

    $row = DB::table('sales')->first();

    expect($row->name)->toBe('Plum')
        ->and((int) $row->amount)->toBe(3)
        ->and(Schema::hasColumn('sales', 'region'))->toBeFalse();
});

it('honours a header row below the top of the sheet and skips blank rows', function (): void {
    $this->tab->update(['header_row' => 3]);
    salesMapping($this->tab);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['Monthly report'],
        [],
        ['date', 'product_name', 'price', 'amount'],
        ['2026-03-05', 'Apple', 1, 1],
        ['', '', '', ''],
        ['2026-03-07', 'Fig', 1, 1],
    ]);

    syncSheet($this->spreadsheet);

    expect(DB::table('sales')->orderBy('id')->pluck('source_row')->map(fn ($row) => (int) $row)->all())->toBe([4, 6]);
});

it('writes one tab into several tables with different structures', function (): void {
    salesMapping($this->tab);
    salesMapping($this->tab, [
        'target_table' => 'products',
        'sync_mode' => 'upsert',
        'key_columns' => ['title'],
        'columns' => [
            ['source_column' => 'product_name', 'target_column' => 'title', 'type' => 'string'],
            ['source_column' => 'price', 'target_column' => 'last_price', 'type' => 'decimal'],
        ],
    ]);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'price', 'amount'],
        ['2026-03-05', 'Apple', 1.5, 10],
        ['2026-03-06', 'Apple', 1.75, 2],
        ['2026-03-06', 'Pear', 2, 4],
    ]);

    syncSheet($this->spreadsheet);

    expect(DB::table('sales')->count())->toBe(3)
        ->and(DB::table('products')->count())->toBe(2)
        ->and((float) DB::table('products')->where('title', 'Apple')->value('last_price'))->toBe(1.75)
        ->and(Schema::getColumnListing('products'))->not->toContain('amount');
});

it('replaces the previous snapshot instead of duplicating rows on the next run', function (): void {
    salesMapping($this->tab);
    $rows = [['date', 'product_name', 'price', 'amount'], ['2026-03-05', 'Apple', 1, 1]];
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', $rows);

    syncSheet($this->spreadsheet);
    syncSheet($this->spreadsheet);

    expect(DB::table('sales')->count())->toBe(1);
});

it('keeps rows from other spreadsheets that share the target table', function (): void {
    salesMapping($this->tab);
    $other = Spreadsheet::factory()->create();
    salesMapping(SpreadsheetTab::factory()->for($other)->create(['name' => 'Orders']), ['columns' => [
        ['source_column' => 'Product', 'target_column' => 'name', 'type' => 'string'],
    ]]);

    $this->sheets
        ->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [['product_name'], ['Apple']])
        ->put($other->google_spreadsheet_id, 'Orders', [['Product'], ['Kiwi'], ['Lime']]);

    syncSheet($this->spreadsheet);
    syncSheet($other);
    syncSheet($this->spreadsheet);

    expect(DB::table('sales')->orderBy('name')->pluck('name')->all())->toBe(['Apple', 'Kiwi', 'Lime']);
});

it('adds a newly mapped column to the existing table and fills it', function (): void {
    $mapping = salesMapping($this->tab);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'price', 'amount', 'country'],
        ['2026-03-05', 'Apple', 1, 1, 'Albania'],
    ]);
    syncSheet($this->spreadsheet);

    expect(Schema::hasColumn('sales', 'country'))->toBeFalse();

    $mapping->columns()->create(['source_column' => 'country', 'target_column' => 'country', 'type' => ColumnType::STRING, 'position' => 9]);
    $run = syncSheet($this->spreadsheet);

    expect(Schema::hasColumn('sales', 'country'))->toBeTrue()
        ->and(DB::table('sales')->value('country'))->toBe('Albania')
        ->and($run->messages)->toContain('Sales → sales: added column(s) country.');
});

it('adds mapped columns to a table that already existed before the sync', function (): void {
    Schema::create('sales', function ($table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    salesMapping($this->tab);

    expect(Schema::getColumnListing('sales'))->toContain('date', 'product_price', 'amount', 'source_spreadsheet_id', 'synced_at');
});

it('stores empty values and reports headers that are missing from the sheet', function (): void {
    salesMapping($this->tab);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name'],
        ['2026-03-05', 'Apple'],
    ]);

    $run = syncSheet($this->spreadsheet);

    expect($run->status)->toBe(SyncStatus::SUCCESS)
        ->and(DB::table('sales')->value('amount'))->toBeNull()
        ->and(collect($run->messages)->implode(' '))->toContain('header(s) not found in the sheet, stored as empty: price, amount');
});

it('reports unreadable cell values without failing the run', function (): void {
    salesMapping($this->tab);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'price', 'amount'],
        ['soon', 'Apple', 'free', 1],
    ]);

    $run = syncSheet($this->spreadsheet);

    expect($run->status)->toBe(SyncStatus::SUCCESS)
        ->and(collect($run->messages)->implode(' '))->toContain('1 value(s) in "date" could not be read as date');
});

it('fails the run when the sheet cannot be read and keeps existing data', function (): void {
    salesMapping($this->tab);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [['product_name'], ['Apple']]);
    syncSheet($this->spreadsheet);

    $this->tab->update(['name' => 'Renamed']);
    $run = syncSheet($this->spreadsheet);

    expect($run->status)->toBe(SyncStatus::FAILED)
        ->and(DB::table('sales')->count())->toBe(1);
});

it('marks the run partial when only some mappings fail', function (): void {
    salesMapping($this->tab);
    $broken = salesMapping($this->tab, ['target_table' => 'broken', 'columns' => [
        ['source_column' => 'product_name', 'target_column' => 'name', 'type' => 'string'],
    ]]);
    $broken->updateQuietly(['target_table' => 'users']);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [['product_name'], ['Apple']]);

    $run = syncSheet($this->spreadsheet);

    expect($run->status)->toBe(SyncStatus::PARTIAL)
        ->and(collect($run->messages)->implode(' '))->toContain('"users" is not an allowed target table');
});

it('skips upsert rows whose key is empty', function (): void {
    salesMapping($this->tab, [
        'target_table' => 'products',
        'sync_mode' => 'upsert',
        'key_columns' => ['title'],
        'columns' => [['source_column' => 'product_name', 'target_column' => 'title', 'type' => 'string']],
    ]);
    $this->sheets->put($this->spreadsheet->google_spreadsheet_id, 'Sales', [['product_name', 'x'], ['', 'y'], ['Apple', 'z']]);

    syncSheet($this->spreadsheet);
    syncSheet($this->spreadsheet);

    expect(DB::table('products')->count())->toBe(1);
});
