<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer;

use Illuminate\Support\Facades\Log;
use Throwable;

final class QueryLogger
{
    private bool $writing = false;

    public function __construct(private readonly RequestContext $requestContext) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  'info'|'warning'  $level
     */
    public function write(string $channel, string $message, array $context, string $level = 'info'): void
    {
        if ($this->writing) {
            return;
        }

        $this->writing = true;

        try {
            Log::channel($channel)->log($level, $message, [...$context, ...$this->requestContext->values()]);
        } catch (Throwable $exception) {
            error_log('DB Query Sniffer could not write to channel "'.$channel.'": '.$exception->getMessage());
        } finally {
            $this->writing = false;
        }
    }
}
