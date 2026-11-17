<?php

declare(strict_types=1);

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;
use App\Models\SpreadsheetTab;
use App\Models\SyncRun;
use App\Models\TabMapping;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->withoutVite();
    $this->sheets = fakeSheets();
    $this->user = User::factory()->create(['password' => 'secret-password']);
});

it('requires login for every management page', function (string $uri): void {
    $this->get($uri)->assertRedirect(route('login'));
})->with(['/', '/spreadsheets/create', '/runs', '/configuration']);

it('logs in and out', function (): void {
    $this->post(route('login'), ['email' => $this->user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->post(route('login'), ['email' => $this->user->email, 'password' => 'secret-password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('renders every page', function (): void {
    $spreadsheet = Spreadsheet::factory()->create();
    $tab = SpreadsheetTab::factory()->for($spreadsheet)->create();
    $mapping = TabMapping::factory()->for($tab, 'tab')->create();
    $mapping->columns()->create(['source_column' => 'Name', 'target_column' => 'name', 'type' => 'string', 'position' => 0]);
    $run = SyncRun::create([
        'spreadsheet_id' => $spreadsheet->id,
        'trigger' => SyncTrigger::MANUAL,
        'status' => SyncStatus::PARTIAL,
        'messages' => ['Sales: header(s) not found'],
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $this->actingAs($this->user);

    $this->get(route('dashboard'))->assertOk()->assertSee($spreadsheet->name);
    $this->get(route('spreadsheets.show', $spreadsheet))->assertOk()->assertSee($tab->name);
    $this->get(route('spreadsheets.edit', $spreadsheet))->assertOk();
    $this->get(route('tabs.show', $tab))->assertOk()->assertSee('sales');
    $this->get(route('mappings.create', $tab))->assertOk();
    $this->get(route('mappings.edit', $mapping))->assertOk()->assertSee('name');
    $this->get(route('runs.index'))->assertOk();
    $this->get(route('runs.show', $run))->assertOk()->assertSee('Sales: header(s) not found');
    $this->get(route('configuration.index'))->assertOk()->assertSee($spreadsheet->google_spreadsheet_id);
});

it('adds a spreadsheet from its url', function (): void {
    $id = '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-xyz';

    $this->actingAs($this->user)
        ->post(route('spreadsheets.store'), [
            'name' => 'Sales EU',
            'google_spreadsheet_id' => "https://docs.google.com/spreadsheets/d/{$id}/edit#gid=0",
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect(Spreadsheet::sole())
        ->name->toBe('Sales EU')
        ->google_spreadsheet_id->toBe($id);
});

it('rejects a value that is not a spreadsheet id', function (): void {
    $this->actingAs($this->user)
        ->post(route('spreadsheets.store'), ['name' => 'Bad', 'google_spreadsheet_id' => 'not an id'])
        ->assertSessionHasErrors('google_spreadsheet_id');

    expect(Spreadsheet::count())->toBe(0);
});

it('creates the target table when a mapping is saved', function (): void {
    $tab = SpreadsheetTab::factory()->create();

    $this->actingAs($this->user)
        ->post(route('mappings.store', $tab), [
            'target_table' => 'Sales',
            'sync_mode' => 'replace',
            'is_active' => '1',
            'columns' => [
                ['source_column' => 'Date', 'target_column' => 'date', 'type' => 'date'],
                ['source_column' => 'Product Name', 'target_column' => 'name', 'type' => 'string'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tabs.show', $tab));

    expect(Schema::hasTable('sales'))->toBeTrue()
        ->and(Schema::hasColumns('sales', ['date', 'name', 'source_spreadsheet_id', 'source_row']))->toBeTrue()
        ->and($tab->mappings()->sole()->columns)->toHaveCount(2);
});

it('validates mappings', function (array $payload, string $errorKey): void {
    $tab = SpreadsheetTab::factory()->create();

    $this->actingAs($this->user)
        ->post(route('mappings.store', $tab), array_merge([
            'target_table' => 'sales',
            'sync_mode' => 'replace',
            'columns' => [['source_column' => 'Name', 'target_column' => 'name', 'type' => 'string']],
        ], $payload))
        ->assertSessionHasErrors($errorKey);

    expect(TabMapping::count())->toBe(0);
})->with([
    'system table' => [['target_table' => 'users'], 'target_table'],
    'invalid table name' => [['target_table' => 'drop table;'], 'target_table'],
    'reserved column' => [['columns' => [['source_column' => 'Id', 'target_column' => 'id', 'type' => 'string']]], 'columns.0.target_column'],
    'duplicate column' => [['columns' => [
        ['source_column' => 'A', 'target_column' => 'name', 'type' => 'string'],
        ['source_column' => 'B', 'target_column' => 'name', 'type' => 'string'],
    ]], 'columns.1.target_column'],
    'upsert without key' => [['sync_mode' => 'upsert'], 'key_columns'],
    'upsert key not mapped' => [['sync_mode' => 'upsert', 'key_columns' => ['sku']], 'key_columns.0'],
    'no columns' => [['columns' => []], 'columns'],
]);

it('suggests column names from the sheet headers', function (): void {
    $tab = SpreadsheetTab::factory()->create(['name' => 'Sales', 'header_row' => 2]);
    $this->sheets->put($tab->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['Report for March'],
        ['Date', 'Product Name', '', 'Price (€)'],
    ]);

    $this->actingAs($this->user)
        ->getJson(route('tabs.headers', $tab))
        ->assertOk()
        ->assertJsonPath('headers.1', ['source_column' => 'Product Name', 'target_column' => 'product_name'])
        ->assertJsonCount(3, 'headers');
});

it('reports sheet read errors as json', function (): void {
    $tab = SpreadsheetTab::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('tabs.headers', $tab))
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

it('queues a manual sync', function (): void {
    Queue::fake();
    $spreadsheet = Spreadsheet::factory()->create();
    Spreadsheet::factory()->create(['is_active' => false]);

    $this->actingAs($this->user);
    $this->post(route('spreadsheets.sync', $spreadsheet))->assertRedirect();
    $this->post(route('sync.all'))->assertRedirect();

    Queue::assertPushed(SyncSpreadsheetJob::class, 2);
    Queue::assertPushed(SyncSpreadsheetJob::class, fn (SyncSpreadsheetJob $job): bool => $job->spreadsheet->is($spreadsheet) && SyncTrigger::MANUAL === $job->trigger);
});
