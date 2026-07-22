<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Andriichuk\DbQuerySniffer\Listeners\LogRedisCommand;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Log;

it('does not log redis when sniffer is disabled', function () {
    config()->set('db-query-sniffer.enabled', false);

    Log::shouldReceive('channel')->never();

    $listener = new LogRedisCommand(new BindingSanitizer);
    $listener->handle(makeRedisCommand('GET', ['key'], 1.0));
});

it('logs every redis command when slow_only is disabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.redis.enabled', true);
    config()->set('db-query-sniffer.redis.slow_only', false);
    config()->set('db-query-sniffer.redis.slow_ms', 20);
    config()->set('db-query-sniffer.redis.channel', 'redis');

    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('redis')->andReturn($mockChannel);

    $listener = new LogRedisCommand(new BindingSanitizer);
    $listener->handle(makeRedisCommand('GET', ['key'], 5.0));
});

it('skips redis commands faster than slow_ms when slow_only is enabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.redis.enabled', true);
    config()->set('db-query-sniffer.redis.slow_only', true);
    config()->set('db-query-sniffer.redis.slow_ms', 20);

    Log::shouldReceive('channel')->never();

    $listener = new LogRedisCommand(new BindingSanitizer);
    $listener->handle(makeRedisCommand('GET', ['key'], 5.0));
});

it('logs redis commands with sanitized parameters', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.redis.enabled', true);
    config()->set('db-query-sniffer.redis.channel', 'redis');
    config()->set('db-query-sniffer.redis.message', 'Redis Command:');
    config()->set('db-query-sniffer.redis.slow_only', false);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('info')->once()->withArgs(function (string $message, array $context) use (&$logged): bool {
        $logged = ['message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('redis')->andReturn($mockChannel);

    $binary = "a\0b";
    $listener = new LogRedisCommand(new BindingSanitizer(binaryAsBase64: false));
    $listener->handle(makeRedisCommand('SET', ['key', $binary], 2.5));

    expect($logged['message'])->toBe('Redis Command:');
    expect($logged['context']['command'])->toBe('SET');
    expect($logged['context']['parameters'])->toBe(['key', '[binary:3 bytes]']);
    expect($logged['context']['time'])->toBe('2.5ms');
    expect($logged['context']['connection'])->toBe('default');
});

/**
 * @param  array<int, mixed>  $parameters
 */
function makeRedisCommand(string $command, array $parameters, float $time): CommandExecuted
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getName')->andReturn('default');

    return new CommandExecuted($command, $parameters, $time, $connection);
}
