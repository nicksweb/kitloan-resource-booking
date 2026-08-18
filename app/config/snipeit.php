<?php

return [

    'enabled' => env('SNIPEIT_ENABLED', false),

    'url' => rtrim((string) env('SNIPEIT_URL'), '/'),

    'api_token' => env('SNIPEIT_API_TOKEN'),

    'timeout' => env('SNIPEIT_TIMEOUT', 10),

    // How often the scheduled sync job runs against linked assets.
    'sync_interval_minutes' => env('SNIPEIT_SYNC_INTERVAL_MINUTES', 30),
];
