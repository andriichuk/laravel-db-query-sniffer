<?php

declare(strict_types=1);

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;

it('captures Laravel Redis connection events inside API requests', function () {
    $handler = new TestHandler;
    Log::channel('redis')->pushHandler($handler);

    $client = Mockery::mock();
    $client->shouldReceive('get')->once()->with('order:7')->andReturn('ready');
    $connection = new PhpRedisConnection($client);
    $connection->setName('orders');
    $connection->setEventDispatcher(app('events'));

    Route::get('/api/order-status', function () use ($connection) {
        return response()->json(['status' => $connection->get('order:7')]);
    });

    $response = $this->getJson('/api/order-status');
    $response->assertOk()->assertJsonPath('status', 'ready');

    expect($handler->getRecords())->toHaveCount(1)
        ->and($handler->getRecords()[0]->context)->toMatchArray([
            'command' => 'get',
            'parameters' => ['order:7'],
            'connection' => 'orders',
            'request_id' => $response->headers->get('X-Query-Sniffer-Request-Id'),
            'request_method' => 'GET',
            'request_path' => '/api/order-status',
        ]);
});
