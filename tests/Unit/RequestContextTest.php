<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\RequestContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('clears context when request handling throws', function () {
    $context = new RequestContext;

    expect(fn () => $context->run(Request::create('/api/orders'), function () {
        throw new RuntimeException('Request failed');
    }))->toThrow(RuntimeException::class, 'Request failed');

    expect($context->values())->toBe([]);
});

it('restores the outer context after nested requests', function () {
    $context = new RequestContext;

    $context->run(Request::create('/outer'), function () use ($context) {
        $outer = $context->values();

        $context->run(Request::create('/inner'), function () use ($context) {
            expect($context->values()['request_path'])->toBe('/inner');

            return new Response;
        });

        expect($context->values())->toBe($outer);

        return new Response;
    });

    expect($context->values())->toBe([]);
});
