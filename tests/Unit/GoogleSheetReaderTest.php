<?php

declare(strict_types=1);

use App\Services\Google\GoogleClientFactory;
use App\Services\Google\GoogleSheetReader;
use Google\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

it('asks Google for date cells as serial numbers so the sheet locale cannot swap day and month', function (): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'range' => "'Sales'!A1:B2",
            'majorDimension' => 'ROWS',
            'values' => [['date', 'amount'], [46085, 3]],
        ])),
    ]));
    $stack->push(Middleware::history($history));

    $client = new Client();
    $client->setDeveloperKey('test-key');
    $client->setHttpClient(new HttpClient(['handler' => $stack]));

    $factory = Mockery::mock(GoogleClientFactory::class);
    $factory->shouldReceive('make')->andReturn($client);

    $rows = (new GoogleSheetReader($factory))->rows('sheet-id', 'Sales');

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($rows)->toBe([['date', 'amount'], [46085, 3]])
        ->and($query['dateTimeRenderOption'])->toBe('SERIAL_NUMBER')
        ->and($query['valueRenderOption'])->toBe('UNFORMATTED_VALUE');
});
