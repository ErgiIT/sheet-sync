<?php

declare(strict_types=1);

use App\Exceptions\SheetReadException;
use App\Services\Google\GoogleClientFactory;
use App\Services\Google\GoogleSheetReader;
use Google\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * A reader whose HTTP calls get $response; the requests sent are appended to $history.
 */
function readerReturning(Response $response, array &$history = []): GoogleSheetReader
{
    $stack = HandlerStack::create(new MockHandler([$response]));
    $stack->push(Middleware::history($history));

    $client = new Client();
    $client->setDeveloperKey('test-key');
    $client->setHttpClient(new HttpClient(['handler' => $stack]));

    $factory = Mockery::mock(GoogleClientFactory::class);
    $factory->shouldReceive('make')->andReturn($client);

    return new GoogleSheetReader($factory);
}

function googleError(int $code, string $message): Response
{
    return new Response($code, ['Content-Type' => 'application/json'], json_encode([
        'error' => ['code' => $code, 'message' => $message, 'errors' => [['message' => $message, 'domain' => 'global', 'reason' => 'x']]],
    ]));
}

it('asks Google for date cells as serial numbers so the sheet locale cannot swap day and month', function (): void {
    $history = [];
    $reader = readerReturning(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'range' => "'Sales'!A1:B2",
        'majorDimension' => 'ROWS',
        'values' => [['date', 'amount'], [46085, 3]],
    ])), $history);

    $rows = $reader->rows('sheet-id', 'Sales');

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($rows)->toBe([['date', 'amount'], [46085, 3]])
        ->and($query['dateTimeRenderOption'])->toBe('SERIAL_NUMBER')
        ->and($query['valueRenderOption'])->toBe('UNFORMATTED_VALUE');
});

it('turns Google errors into one readable message with a hint', function (int $code, string $reason, string $expected): void {
    expect(fn () => readerReturning(googleError($code, $reason))->rows('sheet-id', 'Salez'))
        ->toThrow(SheetReadException::class, $expected);
})->with([
    'unknown tab' => [400, "Unable to parse range: 'Salez'", "Google Sheets API error (400) for sheet-id / Salez: Unable to parse range: 'Salez'. Check that the tab name matches the sheet exactly."],
    'not shared' => [403, 'The caller does not have permission', 'Google Sheets API error (403) for sheet-id / Salez: The caller does not have permission. Share the spreadsheet with the service account e-mail (Viewer).'],
    'wrong id, no double full stop' => [404, 'Requested entity was not found.', 'Google Sheets API error (404) for sheet-id / Salez: Requested entity was not found. Check the spreadsheet id.'],
]);

it('gives Google requests a timeout so a stalled call cannot hang the sync', function (): void {
    config(['sheets.google.service_account_json' => '', 'sheets.google.api_key' => 'test-key', 'sheets.google.timeout' => 45]);

    $http = (new GoogleClientFactory())->make()->getHttpClient();

    expect($http->getConfig('timeout'))->toBe(45)
        ->and($http->getConfig('connect_timeout'))->toBe(10)
        ->and((string) $http->getConfig('base_uri'))->toBe(Client::API_BASE_PATH);
});
