<?php

declare(strict_types=1);

use App\Contracts\SheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeSheetReader;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

function fakeSheets(): FakeSheetReader
{
    $fake = new FakeSheetReader();
    app()->instance(SheetReader::class, $fake);

    return $fake;
}
