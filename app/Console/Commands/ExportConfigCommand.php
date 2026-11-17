<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Configuration\ConfigurationService;
use Illuminate\Console\Command;

class ExportConfigCommand extends Command
{
    protected $signature = 'sheets:config:export {path? : Write to this file instead of stdout}';

    protected $description = 'Dump the current sync configuration as JSON';

    public function handle(ConfigurationService $configuration): int
    {
        $json = json_encode($configuration->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $path = $this->argument('path');

        if (null === $path) {
            $this->line($json);

            return self::SUCCESS;
        }

        file_put_contents($path, $json . PHP_EOL);
        $this->info("Configuration written to {$path}.");

        return self::SUCCESS;
    }
}
