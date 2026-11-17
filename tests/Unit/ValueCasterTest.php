<?php

declare(strict_types=1);

use App\Enums\ColumnType;
use App\Services\Sync\ValueCaster;

dataset('casts', [
    'string trims' => [ColumnType::STRING, '  Apple  ', 'Apple'],
    'string from whole float' => [ColumnType::STRING, 12.0, '12'],
    'integer from float' => [ColumnType::INTEGER, 4.6, 5],
    'integer with thousands' => [ColumnType::INTEGER, '1,234', 1234],
    'decimal raw number' => [ColumnType::DECIMAL, 19.99, '19.99'],
    'decimal with currency' => [ColumnType::DECIMAL, '€ 1.234,50', '1234.50'],
    'decimal us format' => [ColumnType::DECIMAL, '$1,234.50', '1234.50'],
    'decimal comma decimal' => [ColumnType::DECIMAL, '12,5', '12.5'],
    'boolean yes' => [ColumnType::BOOLEAN, 'Yes', true],
    'boolean native' => [ColumnType::BOOLEAN, false, false],
    'date iso' => [ColumnType::DATE, '2026-03-05', '2026-03-05'],
    'date european' => [ColumnType::DATE, '5/3/2026', '2026-03-05'],
    'date sheets serial' => [ColumnType::DATE, 46086, '2026-03-05'],
    'date serial of us 3/4/2026' => [ColumnType::DATE, 46085, '2026-03-04'],
    'datetime sheets serial' => [ColumnType::DATETIME, 46086.6041666667, '2026-03-05 14:30:00'],
    'datetime' => [ColumnType::DATETIME, '2026-03-05 14:30', '2026-03-05 14:30:00'],
]);

it('casts sheet values', function (ColumnType $type, mixed $input, mixed $expected): void {
    [$value, $dropped] = (new ValueCaster())->cast($input, $type);

    expect($value)->toBe($expected)->and($dropped)->toBeFalse();
})->with('casts');

it('treats blank cells as null without flagging them', function (ColumnType $type): void {
    expect((new ValueCaster())->cast('   ', $type))->toBe([null, false]);
})->with(ColumnType::cases());

it('flags values it cannot read', function (ColumnType $type, mixed $input): void {
    expect((new ValueCaster())->cast($input, $type))->toBe([null, true]);
})->with([
    [ColumnType::INTEGER, 'n/a'],
    [ColumnType::DECIMAL, 'abc'],
    [ColumnType::BOOLEAN, 'maybe'],
    [ColumnType::DATE, 'not a date'],
]);

it('rejects impossible dates instead of rolling them over', function (): void {
    [$value] = (new ValueCaster())->cast('31/02/2026', ColumnType::DATE);

    expect($value)->not->toBe('2026-03-03');
});
