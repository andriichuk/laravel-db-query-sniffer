<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Andriichuk\DbQuerySniffer\Listeners\LogRedisCommand;
use Andriichuk\DbQuerySniffer\QueryLogger;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Log;

it('does not log redis when sniffer is disabled', function () {
    config()->set('db-query-sniffer.enabled', false);

    Log::shouldReceive('channel')->never();

    $listener = new LogRedisCommand(new BindingSanitizer, app(QueryLogger::class));
    $listener->handle(makeRedisCommand('GET', ['key'], 1.0));
});

it('logs every redis command when slow_only is disabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.redis.enabled', true);
    config()->set('db-query-sniffer.redis.slow_only', false);
    config()->set('db-query-sniffer.redis.slow_ms', 20);
    config()->set('db-query-sniffer.redis.channel', 'redis');

    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once();
    Log::shouldReceive('channel')->with('redis')->andReturn($mockChannel);

    $listener = new LogRedisCommand(new BindingSanitizer, app(QueryLogger::class));
    $listener->handle(makeRedisCommand('GET', ['key'], 5.0));
});

it('skips redis commands faster than slow_ms when slow_only is enabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.redis.enabled', true);
    config()->set('db-query-sniffer.redis.slow_only', true);
    config()->set('db-query-sniffer.redis.slow_ms', 20);

    Log::shouldReceive('channel')->never();

    $listener = new LogRedisCommand(new BindingSanitizer, app(QueryLogger::class));
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
    $mockChannel->shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $context) use (&$logged): bool {
        $logged = ['level' => $level, 'message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('redis')->andReturn($mockChannel);

    $binary = "a\0b";
    $listener = new LogRedisCommand(new BindingSanitizer(binaryAsBase64: false), app(QueryLogger::class));
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

it('does not log when Redis logging is disabled', function () {
    config()->set('db-query-sniffer.redis.enabled', false);
    Log::shouldReceive('channel')->never();

    app(LogRedisCommand::class)->handle(makeRedisCommand('GET', ['key'], 1.0));
});

it('honors the redis slow threshold boundary', function (float $duration, bool $expected) {
    config()->set('db-query-sniffer.redis.slow_only', true);
    config()->set('db-query-sniffer.redis.slow_ms', 50);
    $channel = Mockery::mock();
    if ($expected) {
        Log::shouldReceive('channel')->once()->andReturn($channel);
        $channel->shouldReceive('log')->once();
    } else {
        Log::shouldReceive('channel')->never();
    }

    app(LogRedisCommand::class)->handle(makeRedisCommand('GET', ['key'], $duration));
})->with([[49.99, false], [50.0, true], [50.01, true]]);

it('rejects an invalid redis slow threshold', function () {
    config()->set('db-query-sniffer.redis.slow_only', true);
    config()->set('db-query-sniffer.redis.slow_ms', -1);
    $duration = 1.0;

    expect(fn () => app(LogRedisCommand::class)->handle(makeRedisCommand('GET', ['key'], $duration)))
        ->toThrow(InvalidArgumentException::class);
});
