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
    | Database Query Logging
    |--------------------------------------------------------------------------
    */
    'database' => [
        'enabled' => true,
        'channel' => env('DB_QUERY_LOG_CHANNEL', 'db'),
        'message' => 'SQL Query:',
        'include_connection' => true,

        /*
         * Only log queries that take at least this many milliseconds.
         * Set to null to log every query.
         */
        'slow_ms' => null,
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
         * Only log Redis commands that take at least this many milliseconds.
         * Set to null to log every command.
         */
        'slow_ms' => null,
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
