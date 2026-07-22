<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer\Listeners;

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LogDatabaseQuery
{
    public function __construct(
        private readonly BindingSanitizer $bindingSanitizer,
    ) {}

    public function handle(QueryExecuted $query): void
    {
        if (! config('db-query-sniffer.enabled') || ! config('db-query-sniffer.database.enabled')) {
            return;
        }

        if (config('db-query-sniffer.database.slow_only')
            && $query->time < (float) config('db-query-sniffer.database.slow_ms', 100)
        ) {
            return;
        }

        $channel = (string) config('db-query-sniffer.database.channel', 'db');
        $message = (string) config('db-query-sniffer.database.message', 'SQL Query:');
        $includeConnection = (bool) config('db-query-sniffer.database.include_connection', true);

        try {
            $context = [
                'query' => $query->toRawSql(),
                'time' => $query->time.'ms',
            ];

            if ($includeConnection) {
                $context['connection'] = $query->connectionName;
            }

            Log::channel($channel)->info($message, $context);
        } catch (Throwable $exception) {
            $context = [
                'error' => $exception->getMessage(),
                'sql' => $query->sql,
                'bindings' => $this->bindingSanitizer->sanitize($query->bindings),
                'time' => $query->time.'ms',
            ];

            if ($includeConnection) {
                $context['connection'] = $query->connectionName;
            }

            Log::channel($channel)->warning(
                'Failed to build raw SQL query string. Logging fallback payload.',
                $context,
            );
        }
    }
}
