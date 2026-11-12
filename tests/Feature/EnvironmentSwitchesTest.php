<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\DbQuerySnifferServiceProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

it('applies environment switches to SQL and Redis capture', function (?string $database, ?string $redis, bool $expectedDatabase, bool $expectedRedis) {
    $values = ['DB_QUERY_LOG_ENABLED' => $database, 'REDIS_COMMAND_LOG_ENABLED' => $redis];
    $originalEnv = array_intersect_key($_ENV, $values);
    $originalServer = array_intersect_key($_SERVER, $values);
    $originalProcess = [];

    try {
        foreach ($values as $key => $value) {
            $originalProcess[$key] = getenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            if ($value !== null) {
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv($key.'='.$value);
            }
        }

        $config = require __DIR__.'/../../config/db-query-sniffer.php';
        expect($config['database']['enabled'])->toBe($expectedDatabase)
            ->and($config['redis']['enabled'])->toBe($expectedRedis);

        $config['enabled'] = true;
        $config['environments'] = ['*'];
        config()->set('db-query-sniffer', $config);
        Event::forget(QueryExecuted::class);
        Event::forget(CommandExecuted::class);
        (new DbQuerySnifferServiceProvider($this->app))->bootingPackage();

        $sqlHandler = new TestHandler;
        $redisHandler = new TestHandler;
        Log::channel('db')->pushHandler($sqlHandler);
        Log::channel('redis')->pushHandler($redisHandler);

        DB::select('select 1');

        $client = Mockery::mock();
        $client->shouldReceive('get')->once()->with('key')->andReturn('value');
        $connection = new PhpRedisConnection($client);
        $connection->setEventDispatcher(app('events'));
        $connection->get('key');

        expect($sqlHandler->getRecords())->toHaveCount($expectedDatabase ? 1 : 0)
            ->and($redisHandler->getRecords())->toHaveCount($expectedRedis ? 1 : 0);
    } finally {
        foreach ($originalProcess as $key => $value) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($value === false ? $key : $key.'='.$value);
        }

        $_ENV = array_replace($_ENV, $originalEnv);
        $_SERVER = array_replace($_SERVER, $originalServer);
    }
})->with([
    'defaults' => [null, null, true, false],
    'SQL disabled' => ['false', null, false, false],
    'Redis enabled' => [null, 'true', true, true],
    'explicit defaults' => ['true', 'false', true, false],
    'Redis only' => ['false', 'true', false, true],
    'both disabled' => ['false', 'false', false, false],
]);
