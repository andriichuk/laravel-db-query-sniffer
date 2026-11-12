<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Andriichuk\DbQuerySniffer\Listeners\LogDatabaseQuery;
use Andriichuk\DbQuerySniffer\QueryLogger;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

it('does not log when sniffer is disabled', function () {
    config()->set('db-query-sniffer.enabled', false);

    Log::shouldReceive('channel')->never();

    $listener = new LogDatabaseQuery(new BindingSanitizer, app(QueryLogger::class));
    $listener->handle(makeQueryExecuted('select 1', [], 1.0));
});

it('does not log when database logging is disabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.database.enabled', false);

    Log::shouldReceive('channel')->never();

    $listener = new LogDatabaseQuery(new BindingSanitizer, app(QueryLogger::class));
    $listener->handle(makeQueryExecuted('select 1', [], 1.0));
});

it('logs every query when slow_only is disabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.database.enabled', true);
    config()->set('db-query-sniffer.database.slow_only', false);
    config()->set('db-query-sniffer.database.slow_ms', 50);
    config()->set('db-query-sniffer.database.channel', 'db');
    config()->set('db-query-sniffer.database.include_connection', false);

    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once();
    Log::shouldReceive('channel')->with('db')->andReturn($mockChannel);

    $listener = new LogDatabaseQuery(new BindingSanitizer, app(QueryLogger::class));
    $listener->handle(makeQueryExecuted('select 1', [], 10.0));
});

it('skips queries faster than slow_ms when slow_only is enabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.database.enabled', true);
    config()->set('db-query-sniffer.database.slow_only', true);
    config()->set('db-query-sniffer.database.slow_ms', 50);

    Log::shouldReceive('channel')->never();

    $listener = new LogDatabaseQuery(new BindingSanitizer, app(QueryLogger::class));
    $listener->handle(makeQueryExecuted('select 1', [], 10.0));
});

it('logs raw sql with connection and time', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.database.enabled', true);
    config()->set('db-query-sniffer.database.channel', 'db');
    config()->set('db-query-sniffer.database.message', 'SQL Query:');
    config()->set('db-query-sniffer.database.include_connection', true);
    config()->set('db-query-sniffer.database.slow_only', false);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $context) use (&$logged): bool {
        $logged = ['level' => $level, 'message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('db')->andReturn($mockChannel);

    $event = makeQueryExecuted(
        sql: 'select * from users where id = ?',
        bindings: [1],
        time: 12.5,
        rawSql: 'select * from users where id = 1',
    );

    $listener = new LogDatabaseQuery(new BindingSanitizer, app(QueryLogger::class));
    $listener->handle($event);

    expect($logged['message'])->toBe('SQL Query:');
    expect($logged['context']['query'])->toBe('select * from users where id = 1');
    expect($logged['context']['time'])->toBe('12.5ms');
    expect($logged['context']['connection'])->toBe('testing');
});

it('falls back to sanitized bindings when raw sql fails', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.database.enabled', true);
    config()->set('db-query-sniffer.database.channel', 'db');
    config()->set('db-query-sniffer.database.include_connection', true);

    $logged = [];
    $mockChannel = Mockery::mock();
    $mockChannel->shouldReceive('log')->once()->withArgs(function (string $level, string $message, array $context) use (&$logged): bool {
        $logged = ['level' => $level, 'message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('db')->andReturn($mockChannel);

    $binary = "a\0b";
    $event = makeQueryExecuted(
        sql: 'select ?',
        bindings: [$binary],
        time: 3.0,
        rawSqlException: new RuntimeException('cannot substitute'),
    );

    $listener = new LogDatabaseQuery(new BindingSanitizer(binaryAsBase64: false), app(QueryLogger::class));
    $listener->handle($event);

    expect($logged['message'])->toBe('Failed to build raw SQL query string. Logging fallback payload.');
    expect($logged['context']['sql'])->toBe('select ?');
    expect($logged['context']['bindings'])->toBe(['[binary:3 bytes]']);
    expect($logged['context']['error'])->toBe('cannot substitute');
    expect($logged['context']['connection'])->toBe('testing');
});

/**
 * @param  array<int, mixed>  $bindings
 */
function makeQueryExecuted(
    string $sql,
    array $bindings,
    float $time,
    ?string $rawSql = null,
    ?Throwable $rawSqlException = null,
): QueryExecuted {
    $grammar = Mockery::mock();
    $query = Mockery::mock();
    $query->shouldReceive('getGrammar')->andReturn($grammar);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getName')->andReturn('testing');
    $connection->shouldReceive('query')->andReturn($query);
    $connection->shouldReceive('prepareBindings')->andReturn($bindings);

    if ($rawSqlException !== null) {
        $grammar->shouldReceive('substituteBindingsIntoRawSql')->andThrow($rawSqlException);
    } else {
        $grammar->shouldReceive('substituteBindingsIntoRawSql')->andReturn($rawSql ?? $sql);
    }

    return new QueryExecuted($sql, $bindings, $time, $connection);
}

it('honors the database slow threshold boundary', function (float $duration, bool $expected) {
    config()->set('db-query-sniffer.database.slow_only', true);
    config()->set('db-query-sniffer.database.slow_ms', 50);
    $channel = Mockery::mock();
    if ($expected) {
        Log::shouldReceive('channel')->once()->andReturn($channel);
        $channel->shouldReceive('log')->once();
    } else {
        Log::shouldReceive('channel')->never();
    }

    app(LogDatabaseQuery::class)->handle(makeQueryExecuted('select 1', [], $duration));
})->with([[49.99, false], [50.0, true], [50.01, true]]);

it('rejects an invalid database slow threshold', function () {
    config()->set('db-query-sniffer.database.slow_only', true);
    config()->set('db-query-sniffer.database.slow_ms', -1);
    $duration = 1.0;

    expect(fn () => app(LogDatabaseQuery::class)->handle(makeQueryExecuted('select 1', [], $duration)))
        ->toThrow(InvalidArgumentException::class);
});
