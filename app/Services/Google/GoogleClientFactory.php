<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Exceptions\SheetReadException;
use Google\Client;
use Google\Service\Sheets;
use GuzzleHttp\Client as HttpClient;

class GoogleClientFactory
{
    public function make(): Client
    {
        $client = new Client([
            'retry' => ['retries' => config('sheets.google.retries')],
        ]);
        $client->setApplicationName(config('sheets.google.application_name'));
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);

        // The library's default HTTP client has no timeout, so one stalled request would keep the run
        // "running" until the queue kills the job. Same options as the default, plus the timeouts.
        $client->setHttpClient(new HttpClient([
            'base_uri' => $client->getConfig('base_path'),
            'http_errors' => false,
            'connect_timeout' => 10,
            'timeout' => (int) config('sheets.google.timeout'),
        ]));

        $credentialsPath = $this->credentialsPath();

        if (null !== $credentialsPath && is_readable($credentialsPath)) {
            $client->setAuthConfig($credentialsPath);

            return $client;
        }

        $apiKey = (string) config('sheets.google.api_key');

        if ('' !== $apiKey) {
            $client->setDeveloperKey($apiKey);

            return $client;
        }

        throw new SheetReadException(
            'No Google credentials configured. Set GOOGLE_SERVICE_ACCOUNT_JSON or GOOGLE_API_KEY (see docs/google-api.md).'
        );
    }

    /**
     * Relative paths are taken from the project root; the web server runs from public/.
     */
    private function credentialsPath(): ?string
    {
        $path = (string) config('sheets.google.service_account_json');

        if ('' === $path) {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
