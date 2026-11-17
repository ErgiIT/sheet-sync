<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncTrigger: string
{
    case SCHEDULE = 'schedule';
    case CONSOLE = 'console';
    case MANUAL = 'manual';
}
