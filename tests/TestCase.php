<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer\Tests;

use Andriichuk\DbQuerySniffer\DbQuerySnifferServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DbQuerySnifferServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $config = require __DIR__.'/../config/db-query-sniffer.php';
        $config['enabled'] = true;
        $app['config']->set('db-query-sniffer', $config);

        $app['config']->set('logging.channels.db', [
            'driver' => 'null',
        ]);

        $app['config']->set('logging.channels.redis', [
            'driver' => 'null',
        ]);
    }
}
