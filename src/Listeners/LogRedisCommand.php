<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer\Listeners;

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Andriichuk\DbQuerySniffer\QueryLogger;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final class LogRedisCommand
{
    public function __construct(
        private readonly BindingSanitizer $bindingSanitizer,
        private readonly QueryLogger $logger,
    ) {}

    public function handle(CommandExecuted $event): void
    {
        if (! config('db-query-sniffer.enabled') || ! config('db-query-sniffer.redis.enabled')) {
            return;
        }

        if (config('db-query-sniffer.redis.slow_only')) {
            $slowMs = config('db-query-sniffer.redis.slow_ms', 100);

            if (! is_numeric($slowMs) || (float) $slowMs < 0) {
                throw new InvalidArgumentException('The slow query threshold must be a non-negative number.');
            }

            if ($event->time < (float) $slowMs) {
                return;
            }
        }

        $channel = Config::string('db-query-sniffer.redis.channel', 'redis');
        $message = Config::string('db-query-sniffer.redis.message', 'Redis Command:');

        $this->logger->write($channel, $message, [
            'command' => $event->command,
            'parameters' => $this->bindingSanitizer->sanitize($event->parameters),
            'time' => $event->time.'ms',
            'duration_ms' => (float) $event->time,
            'connection' => $event->connectionName,
        ]);
    }
}
