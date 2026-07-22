<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Andriichuk\DbQuerySniffer\Listeners\LogDatabaseQuery;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

it('does not log when sniffer is disabled', function () {
    config()->set('db-query-sniffer.enabled', false);

    Log::shouldReceive('channel')->never();

    $listener = new LogDatabaseQuery(new BindingSanitizer);
    $listener->handle(makeQueryExecuted('select 1', [], 1.0));
});

it('does not log when database logging is disabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.database.enabled', false);

    Log::shouldReceive('channel')->never();

    $listener = new LogDatabaseQuery(new BindingSanitizer);
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
    $mockChannel->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('db')->andReturn($mockChannel);

    $listener = new LogDatabaseQuery(new BindingSanitizer);
    $listener->handle(makeQueryExecuted('select 1', [], 10.0));
});

it('skips queries faster than slow_ms when slow_only is enabled', function () {
    config()->set('db-query-sniffer.enabled', true);
    config()->set('db-query-sniffer.database.enabled', true);
    config()->set('db-query-sniffer.database.slow_only', true);
    config()->set('db-query-sniffer.database.slow_ms', 50);

    Log::shouldReceive('channel')->never();

    $listener = new LogDatabaseQuery(new BindingSanitizer);
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
    $mockChannel->shouldReceive('info')->once()->withArgs(function (string $message, array $context) use (&$logged): bool {
        $logged = ['message' => $message, 'context' => $context];

        return true;
    });
    Log::shouldReceive('channel')->with('db')->andReturn($mockChannel);

    $event = makeQueryExecuted(
        sql: 'select * from users where id = ?',
        bindings: [1],
        time: 12.5,
        rawSql: 'select * from users where id = 1',
    );

    $listener = new LogDatabaseQuery(new BindingSanitizer);
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
    $mockChannel->shouldReceive('warning')->once()->withArgs(function (string $message, array $context) use (&$logged): bool {
        $logged = ['message' => $message, 'context' => $context];

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

    $listener = new LogDatabaseQuery(new BindingSanitizer(binaryAsBase64: false));
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
