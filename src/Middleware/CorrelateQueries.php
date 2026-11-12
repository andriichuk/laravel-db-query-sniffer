<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer\Middleware;

use Andriichuk\DbQuerySniffer\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CorrelateQueries
{
    public function __construct(private readonly RequestContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('db-query-sniffer.enabled') || ! config('db-query-sniffer.request_context', true)) {
            return $next($request);
        }

        return $this->context->run($request, $next);
    }
}
