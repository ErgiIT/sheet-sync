<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuration\ConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class ImportConfigCommand extends Command
{
    protected $signature = 'sheets:config:import {path : JSON file, see config/sheet-sync.example.json}';

    protected $description = 'Create or update spreadsheets, tabs and column mappings from a JSON file';

    public function handle(ConfigurationService $configuration): int
    {
        $path = (string) $this->argument('path');

        if ( ! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        try {
            $summary = $configuration->import(json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            $this->error("Invalid JSON: {$e->getMessage()}");

            return self::FAILURE;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->error("{$field}: " . implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $this->info("Imported {$summary['spreadsheets']} spreadsheet(s), {$summary['tabs']} tab(s), {$summary['mappings']} mapping(s).");

        if ([] !== $summary['added_columns']) {
            $this->line('Added columns: ' . implode(', ', $summary['added_columns']));
        }

        return self::SUCCESS;
    }
}
