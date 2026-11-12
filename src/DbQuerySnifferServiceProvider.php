<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer;

use Andriichuk\DbQuerySniffer\Listeners\LogDatabaseQuery;
use Andriichuk\DbQuerySniffer\Listeners\LogRedisCommand;
use Andriichuk\DbQuerySniffer\Middleware\CorrelateQueries;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
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
        $this->app->scoped(RequestContext::class);
        $this->app->scoped(QueryLogger::class);

        $this->app->singleton(BindingSanitizer::class, function (): BindingSanitizer {
            $maxStringLength = config('db-query-sniffer.bindings.max_string_length');

            if ($maxStringLength !== null && ! is_int($maxStringLength)) {
                throw new InvalidArgumentException('The maximum binding string length must be an integer or null.');
            }

            return new BindingSanitizer(
                maxStringLength: $maxStringLength,
                binaryAsBase64: (bool) config('db-query-sniffer.bindings.binary_as_base64', true),
            );
        });
    }

    public function bootingPackage(): void
    {
        if (! config('db-query-sniffer.enabled')) {
            return;
        }

        /** @var list<string> $environments */
        $environments = config('db-query-sniffer.environments', ['local']);

        if ($environments !== ['*'] && ! $this->app->environment($environments)) {
            return;
        }

        foreach (['db', 'redis'] as $channel) {
            if (! config()->has('logging.channels.'.$channel)) {
                config()->set('logging.channels.'.$channel, [
                    'driver' => 'daily',
                    'path' => storage_path('logs/'.$channel.'.log'),
                    'level' => 'debug',
                    'days' => 2,
                ]);
            }
        }

        if (config('db-query-sniffer.request_context', true)) {
            $this->callAfterResolving(HttpKernel::class, static function (Kernel $kernel): void {
                $kernel->prependMiddleware(CorrelateQueries::class);
            });
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
