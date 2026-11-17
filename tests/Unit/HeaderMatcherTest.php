<?php

declare(strict_types=1);

use App\Services\Sync\HeaderMatcher;

it('matches headers regardless of position, case and separators', function (): void {
    $matched = (new HeaderMatcher())->match(
        ['Notes', 'Amount', ' PRODUCT-NAME ', 'date', 'Price'],
        ['date', 'product_name', 'price', 'amount'],
    );

    expect($matched)->toBe(['date' => 3, 'product_name' => 2, 'price' => 4, 'amount' => 1]);
});

it('leaves out source columns the sheet does not have', function (): void {
    $matched = (new HeaderMatcher())->match(['date', 'price'], ['date', 'amount']);

    expect($matched)->toBe(['date' => 0]);
});

it('uses the first of duplicated headers', function (): void {
    expect((new HeaderMatcher())->match(['price', 'Price'], ['price']))->toBe(['price' => 0]);
});
