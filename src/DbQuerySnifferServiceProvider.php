<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer;

use Andriichuk\DbQuerySniffer\Listeners\LogDatabaseQuery;
use Andriichuk\DbQuerySniffer\Listeners\LogRedisCommand;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DbQuerySnifferServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-db-query-sniffer')
            ->hasConfigFile('db-query-sniffer');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BindingSanitizer::class, function (): BindingSanitizer {
            return new BindingSanitizer(
                maxStringLength: config('db-query-sniffer.bindings.max_string_length'),
                binaryAsBase64: (bool) config('db-query-sniffer.bindings.binary_as_base64', true),
            );
        });
    }

    public function bootingPackage(): void
    {
        if (! config('db-query-sniffer.enabled')) {
            return;
        }

        if (config('db-query-sniffer.database.enabled')) {
            Event::listen(QueryExecuted::class, LogDatabaseQuery::class);
        }

        if (config('db-query-sniffer.redis.enabled')) {
            Redis::enableEvents();
            Event::listen(CommandExecuted::class, LogRedisCommand::class);
        }
    }
}
