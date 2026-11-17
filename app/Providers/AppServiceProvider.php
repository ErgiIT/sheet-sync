<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SheetReader;
use App\Services\Google\GoogleSheetReader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SheetReader::class, GoogleSheetReader::class);
    }

    public function boot(): void {}
}
