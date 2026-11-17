<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('sheets:sync --scheduled')
    ->dailyAt(config('sheets.schedule_time'))
    ->withoutOverlapping()
    ->runInBackground();
