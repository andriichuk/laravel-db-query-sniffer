<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer\Listeners;

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Andriichuk\DbQuerySniffer\QueryLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Throwable;

final class LogDatabaseQuery
{
    public function __construct(
        private readonly BindingSanitizer $bindingSanitizer,
        private readonly QueryLogger $logger,
    ) {}

    public function handle(QueryExecuted $query): void
    {
        if (! config('db-query-sniffer.enabled') || ! config('db-query-sniffer.database.enabled')) {
            return;
        }

        if (config('db-query-sniffer.database.slow_only')) {
            $slowMs = config('db-query-sniffer.database.slow_ms', 100);

            if (! is_numeric($slowMs) || (float) $slowMs < 0) {
                throw new InvalidArgumentException('The slow query threshold must be a non-negative number.');
            }

            if ($query->time < (float) $slowMs) {
                return;
            }
        }

        $channel = Config::string('db-query-sniffer.database.channel', 'db');
        $message = Config::string('db-query-sniffer.database.message', 'SQL Query:');
        $includeConnection = (bool) config('db-query-sniffer.database.include_connection', true);

        $context = [
            'time' => $query->time.'ms',
            'duration_ms' => (float) $query->time,
        ];

        if ($includeConnection) {
            $context['connection'] = $query->connectionName;
        }

        $level = 'info';

        try {
            $context['query'] = $query->toRawSql();
        } catch (Throwable $exception) {
            $level = 'warning';
            $message = 'Failed to build raw SQL query string. Logging fallback payload.';
            $context['error'] = $exception->getMessage();
            $context['sql'] = $query->sql;
            $context['bindings'] = $this->bindingSanitizer->sanitize($query->bindings);
        }

        $this->logger->write($channel, $message, $context, $level);
    }
}
