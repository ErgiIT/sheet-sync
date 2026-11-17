<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncStatus: string
{
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case PARTIAL = 'partial';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
