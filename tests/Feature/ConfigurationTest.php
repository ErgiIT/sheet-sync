<?php

declare(strict_types=1);

use App\Enums\SyncStatus;
use App\Models\Spreadsheet;
use App\Models\TabMapping;
use App\Models\User;
use App\Services\Configuration\ConfigurationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function exampleConfig(): array
{
    return json_decode(file_get_contents(base_path('config/sheet-sync.example.json')), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    $this->withoutVite();
    $this->sheets = fakeSheets();
});

it('imports the example config and creates the tables', function (): void {
    $summary = app(ConfigurationService::class)->import(exampleConfig());

    expect($summary['spreadsheets'])->toBe(2)
        ->and(TabMapping::count())->toBe($summary['mappings'])
        ->and(Schema::hasTable('sales'))->toBeTrue()
        ->and(Schema::hasTable('products'))->toBeTrue();
});

it('round-trips through export and is idempotent', function (): void {
    $service = app(ConfigurationService::class);
    $service->import(exampleConfig());
    $exported = $service->export();

    $service->import($exported);

    expect($service->export())->toBe($exported)
        ->and(Spreadsheet::count())->toBe(2);
});

it('rejects an invalid config without saving anything', function (): void {
    $config = exampleConfig();
    $config['spreadsheets'][0]['tabs'][0]['mappings'][0]['target_table'] = 'users';

    expect(fn () => app(ConfigurationService::class)->import($config))->toThrow(ValidationException::class)
        ->and(Spreadsheet::count())->toBe(0);
});

it('imports and exports through the ui', function (): void {
    $this->actingAs(User::factory()->create());
    $file = UploadedFile::fake()->createWithContent('config.json', json_encode(exampleConfig()));

    $this->post(route('configuration.import'), ['file' => $file])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('configuration.index'));

    expect(Spreadsheet::count())->toBe(2);

    $this->get(route('configuration.export'))
        ->assertOk()
        ->assertDownload();
});

it('reports broken json on import', function (): void {
    $this->actingAs(User::factory()->create());

    $this->post(route('configuration.import'), ['file' => UploadedFile::fake()->createWithContent('config.json', '{nope')])
        ->assertSessionHasErrors('file');
});

it('syncs from the console command', function (): void {
    app(ConfigurationService::class)->import(exampleConfig());
    $spreadsheet = Spreadsheet::query()->orderBy('id')->firstOrFail();

    foreach ($spreadsheet->tabs as $tab) {
        $this->sheets->put($spreadsheet->google_spreadsheet_id, $tab->name, [
            ['Date', 'Product Name', 'Price', 'Amount', 'SKU'],
            ['2026-03-05', 'Apple', '1.50', '10', 'A-1'],
        ]);
    }

    $this->artisan('sheets:sync', ['--spreadsheet' => [$spreadsheet->id]])->assertSuccessful();

    expect($spreadsheet->syncRuns()->sole()->status)->toBe(SyncStatus::SUCCESS)
        ->and(DB::table('sales')->count())->toBe(1);
});
