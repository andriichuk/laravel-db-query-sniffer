<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer\Listeners;

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Log;

final class LogRedisCommand
{
    public function __construct(
        private readonly BindingSanitizer $bindingSanitizer,
    ) {}

    public function handle(CommandExecuted $event): void
    {
        if (! config('db-query-sniffer.enabled') || ! config('db-query-sniffer.redis.enabled')) {
            return;
        }

        $slowMs = config('db-query-sniffer.redis.slow_ms');

        if ($slowMs !== null && $event->time < (float) $slowMs) {
            return;
        }

        $channel = (string) config('db-query-sniffer.redis.channel', 'redis');
        $message = (string) config('db-query-sniffer.redis.message', 'Redis Command:');

        Log::channel($channel)->info($message, [
            'command' => $event->command,
            'parameters' => $this->bindingSanitizer->sanitize($event->parameters),
            'time' => $event->time.'ms',
            'connection' => $event->connectionName,
        ]);
    }
}
