<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequestContext
{
    /** @var array{request_id?: string, request_method?: string, request_path?: string} */
    private array $context = [];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function run(Request $request, Closure $next): Response
    {
        $previous = $this->context;
        $requestId = bin2hex(random_bytes(16));
        $this->context = [
            'request_id' => $requestId,
            'request_method' => $request->method(),
            'request_path' => $request->getPathInfo(),
        ];

        try {
            $response = $next($request);
            $response->headers->set('X-Query-Sniffer-Request-Id', $requestId);

            return $response;
        } finally {
            $this->context = $previous;
        }
    }

    /**
     * @return array{request_id?: string, request_method?: string, request_path?: string}
     */
    public function values(): array
    {
        return $this->context;
    }
}
