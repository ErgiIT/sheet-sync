<?php

declare(strict_types=1);

return [
    'google' => [
        'service_account_json' => env('GOOGLE_SERVICE_ACCOUNT_JSON', storage_path('app/private/google/service-account.json')),
        'api_key' => env('GOOGLE_API_KEY'),
        'application_name' => env('APP_NAME', 'Sheet Sync'),
        'retries' => (int) env('GOOGLE_API_RETRIES', 3),
        // Seconds per request, so a stalled connection fails the run instead of hanging it.
        'timeout' => (int) env('GOOGLE_API_TIMEOUT', 60),
    ],

    // Login for the management UI, created by `php artisan db:seed`.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'schedule_time' => env('SHEETS_SYNC_TIME', '02:00'),

    'insert_chunk_size' => 500,

    'lock_seconds' => 3600,

    /*
     * Tried in order for dates typed as text, so the first match wins for ambiguous values like
     * 03/04/2026. Text that matches none of them is reported as unreadable, never guessed at.
     */
    'date_formats' => array_filter(explode(',', (string) env('SHEETS_DATE_FORMATS', 'Y-m-d,d/m/Y,d.m.Y,m/d/Y,d-m-Y'))),

    'datetime_formats' => array_filter(explode(',', (string) env('SHEETS_DATETIME_FORMATS', 'Y-m-d H:i:s,Y-m-d H:i,d/m/Y H:i:s,d/m/Y H:i,m/d/Y H:i:s,m/d/Y H:i'))),

    'protected_tables' => [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
        'spreadsheets',
        'spreadsheet_tabs',
        'tab_mappings',
        'column_mappings',
        'sync_runs',
    ],
];
