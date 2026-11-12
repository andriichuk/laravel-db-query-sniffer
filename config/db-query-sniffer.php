<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Query Sniffer
    |--------------------------------------------------------------------------
    |
    | Master switch for SQL and Redis logging. When LOG_DB_QUERIES is not set,
    | the sniffer follows APP_DEBUG (on in debug mode, off in production).
    |
    */
    'enabled' => env('LOG_DB_QUERIES', (bool) env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | Only register listeners in these environments. Use ['*'] for all.
    | Defaults to local to avoid noisy logs during automated tests.
    |
    */
    'environments' => ['local'],

    /* Add request IDs, methods, and paths to HTTP query logs. */
    'request_context' => true,

    /*
    |--------------------------------------------------------------------------
    | Database Query Logging
    |--------------------------------------------------------------------------
    */
    'database' => [
        'enabled' => true,
        'channel' => env('DB_QUERY_LOG_CHANNEL', 'db'),
        'message' => 'SQL Query:',
        'include_connection' => true,

        /*
         * When true, only queries slower than slow_ms are logged.
         * When false (default), every query is logged.
         */
        'slow_only' => false,

        /*
         * Minimum duration in milliseconds when slow_only is enabled.
         */
        'slow_ms' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Command Logging
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'enabled' => true,
        'channel' => env('REDIS_COMMAND_LOG_CHANNEL', 'redis'),
        'message' => 'Redis Command:',

        /*
         * When true, only commands slower than slow_ms are logged.
         * When false (default), every command is logged.
         */
        'slow_only' => false,

        /*
         * Minimum duration in milliseconds when slow_only is enabled.
         */
        'slow_ms' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Binding Sanitization (fallback when toRawSql fails)
    |--------------------------------------------------------------------------
    */
    'bindings' => [
        /*
         * Truncate string bindings longer than this length. null = no truncation.
         */
        'max_string_length' => null,

        /*
         * When true, binary (NUL-containing) strings are logged as base64.
         * When false, only a size placeholder is logged.
         */
        'binary_as_base64' => true,
    ],

];
