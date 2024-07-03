<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request logging
    |--------------------------------------------------------------------------
    |
    | Here you can enable if the application should log each incoming request
    | Logs can be found in the request_logs table
    |
    */

    'enabled' => env('REQUEST_LOG_ENABLED', true),

    'truncateBodyLength' => env('REQUEST_LOG_TRUNCATE_BODY_LENGTH', 10000), // Truncate the length of body of request or response to maximum this size. Set to -1 to disable.

    /*
    | Set of headers and query parameters to redact from the log
    */

    'redact' => [
        'query' => [
            'token',
            'authToken',
            'auth',
            'authorization',
        ],

        'headers' => [
            'Authorization',
            'php-auth-pw',
            'php-auth-user',
            'cf-access-jwt-assertion',
        ],
        'cookies' => [
            'cegosso',
            'refresh_token',
            'access_token',
        ]
    ],

    /*
    | A list of default routes that are always black listed
    */

    'blackListedRoutes' => [
        'livewire/*',       // Example: livewire/livewire.js
        'vendor/*',         // Example: vendor/request-logs
        'vendor/*/*',       // Example: vendor/request-logs/1
        'vendor/*/*/*',     // Example: Vendor/request-logs/1/delete
    ],
];
