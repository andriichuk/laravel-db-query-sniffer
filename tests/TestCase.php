<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer\Tests;

use Andriichuk\DbQuerySniffer\DbQuerySnifferServiceProvider;
use Monolog\Handler\NullHandler;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DbQuerySnifferServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $config = require __DIR__.'/../config/db-query-sniffer.php';
        $config['enabled'] = true;
        $config['database']['enabled'] = true;
        $config['redis']['enabled'] = true;
        $config['environments'] = ['*'];
        $app['config']->set('db-query-sniffer', $config);

        $app['config']->set('logging.channels.db', [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ]);

        $app['config']->set('logging.channels.redis', [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ]);
    }
}
