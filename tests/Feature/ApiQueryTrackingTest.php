<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;

it('captures real SQL queries from JSON API requests and isolates their context', function () {
    $handler = new TestHandler;
    Log::channel('db')->pushHandler($handler);

    Route::get('/api/orders/{id}', function (int $id) {
        return response()->json(DB::select('select ? as order_id', [$id]));
    });

    $first = $this->getJson('/api/orders/7?token=must-not-be-logged');
    $first->assertOk()->assertJsonPath('0.order_id', 7);
    $firstId = $first->headers->get('X-Query-Sniffer-Request-Id');

    $second = $this->getJson('/api/orders/8');
    $second->assertOk();
    $secondId = $second->headers->get('X-Query-Sniffer-Request-Id');

    DB::select('select 9 as order_id');

    $records = $handler->getRecords();
    expect($records)->toHaveCount(3)
        ->and($firstId)->toMatch('/^[a-f0-9]{32}$/')
        ->and($secondId)->not->toBe($firstId)
        ->and($records[0]->context)->toMatchArray([
            'request_id' => $firstId,
            'request_method' => 'GET',
            'request_path' => '/api/orders/7',
            'connection' => 'testing',
        ])
        ->and($records[0]->context['query'])->toContain('7')
        ->and($records[0]->context['duration_ms'])->toBeFloat()
        ->and($records[1]->context['request_id'])->toBe($secondId)
        ->and($records[2]->context)->not->toHaveKey('request_id')
        ->and(app(RequestContext::class)->values())->toBe([]);
});

it('can disable HTTP context while continuing to record API queries', function () {
    config()->set('db-query-sniffer.request_context', false);
    $handler = new TestHandler;
    Log::channel('db')->pushHandler($handler);

    Route::post('/api/orders', function () {
        return response()->json(DB::select('select 1'));
    });

    $this->postJson('/api/orders')->assertOk()->assertHeaderMissing('X-Query-Sniffer-Request-Id');

    expect($handler->getRecords())->toHaveCount(1)
        ->and($handler->getRecords()[0]->context)->not->toHaveKey('request_id');
});
