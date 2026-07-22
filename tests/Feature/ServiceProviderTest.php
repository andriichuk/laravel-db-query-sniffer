<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\DbQuerySnifferServiceProvider;
use Andriichuk\DbQuerySniffer\Listeners\LogDatabaseQuery;
use Andriichuk\DbQuerySniffer\Listeners\LogRedisCommand;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;

it('registers the package service provider', function () {
    expect($this->app->providerIsLoaded(DbQuerySnifferServiceProvider::class))->toBeTrue();
});

it('publishes the config file', function () {
    expect(config('db-query-sniffer.enabled'))->not->toBeNull();
    expect(config('db-query-sniffer.database.channel'))->toBe('db');
    expect(config('db-query-sniffer.redis.channel'))->toBe('redis');
});

it('registers database and redis listeners when enabled', function () {
    expect(Event::hasListeners(QueryExecuted::class))->toBeTrue();
    expect(Event::hasListeners(CommandExecuted::class))->toBeTrue();

    $dbListeners = Event::getRawListeners()[QueryExecuted::class] ?? [];
    $redisListeners = Event::getRawListeners()[CommandExecuted::class] ?? [];

    $flatDb = collect($dbListeners)->flatten()->all();
    $flatRedis = collect($redisListeners)->flatten()->all();

    expect($flatDb)->toContain(LogDatabaseQuery::class);
    expect($flatRedis)->toContain(LogRedisCommand::class);
});
