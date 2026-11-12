<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\BindingSanitizer;
use Andriichuk\DbQuerySniffer\DbQuerySnifferServiceProvider;
use Andriichuk\DbQuerySniffer\Listeners\LogDatabaseQuery;
use Andriichuk\DbQuerySniffer\Listeners\LogRedisCommand;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;
use Monolog\Handler\NullHandler;

it('registers the package service provider', function () {
    expect($this->app->providerIsLoaded(DbQuerySnifferServiceProvider::class))->toBeTrue();
});

it('loads the package configuration', function () {
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

it('registers only the enabled listeners in an allowed environment', function (bool $enabled, bool $database, bool $redis, array $environments, bool $expectedDatabase, bool $expectedRedis) {
    Event::forget(QueryExecuted::class);
    Event::forget(CommandExecuted::class);
    config()->set('db-query-sniffer.enabled', $enabled);
    config()->set('db-query-sniffer.database.enabled', $database);
    config()->set('db-query-sniffer.redis.enabled', $redis);
    config()->set('db-query-sniffer.environments', $environments);

    (new DbQuerySnifferServiceProvider($this->app))->bootingPackage();

    expect(Event::hasListeners(QueryExecuted::class))->toBe($expectedDatabase)
        ->and(Event::hasListeners(CommandExecuted::class))->toBe($expectedRedis);
})->with([
    'disabled' => [false, true, true, ['*'], false, false],
    'disallowed environment' => [true, true, true, ['local'], false, false],
    'empty environment list' => [true, true, true, [], false, false],
    'allowed environment' => [true, true, true, ['testing'], true, true],
    'all environments' => [true, true, true, ['*'], true, true],
    'SQL only' => [true, true, false, ['*'], true, false],
    'Redis only' => [true, false, true, ['*'], false, true],
    'both disabled' => [true, false, false, ['*'], false, false],
]);

it('provides rotating file channels without replacing application channels', function () {
    $channels = config('logging.channels');
    unset($channels['db']);
    config()->set('logging.channels', $channels);
    config()->set('logging.channels.redis', ['driver' => 'monolog', 'handler' => NullHandler::class]);

    (new DbQuerySnifferServiceProvider($this->app))->bootingPackage();

    expect(config('logging.channels.db'))->toMatchArray([
        'driver' => 'daily',
        'path' => storage_path('logs/db.log'),
        'days' => 2,
    ])->and(config('logging.channels.redis'))->toBe(['driver' => 'monolog', 'handler' => NullHandler::class]);
});

it('rejects invalid binding length configuration', function () {
    config()->set('db-query-sniffer.bindings.max_string_length', 'invalid');
    $this->app->forgetInstance(BindingSanitizer::class);

    expect(fn () => app(BindingSanitizer::class))
        ->toThrow(InvalidArgumentException::class);
});
