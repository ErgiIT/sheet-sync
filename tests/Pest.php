<?php

declare(strict_types=1);

use App\Contracts\SheetReader;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Fakes\FakeSheetReader;
use Tests\TestCase;

// Not RefreshDatabase: syncs run CREATE/ALTER TABLE, which MySQL commits on the spot, so the
// test transaction can't roll them back. Rebuilding the database per test keeps MySQL runs clean.
uses(TestCase::class, DatabaseMigrations::class)->in('Feature');
uses(TestCase::class)->in('Unit');

function fakeSheets(): FakeSheetReader
{
    $fake = new FakeSheetReader();
    app()->instance(SheetReader::class, $fake);

    return $fake;
}
