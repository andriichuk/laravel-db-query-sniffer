<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\QueryLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('does not recursively log queries made by a logging handler', function () {
    $channel = Mockery::mock();
    $channel->shouldReceive('log')->once()->andReturnUsing(function () {
        DB::select('select 1');
    });
    Log::shouldReceive('channel')->once()->with('db')->andReturn($channel);

    app(QueryLogger::class)->write('db', 'outer query', []);
});

it('keeps logging failures from breaking queries and resets the recursion guard', function () {
    $path = tempnam(sys_get_temp_dir(), 'query-sniffer-error-');
    $previous = ini_get('error_log');
    ini_set('error_log', $path);

    $channel = Mockery::mock();
    $channel->shouldReceive('log')->twice()->andThrow(new RuntimeException('handler unavailable'));
    Log::shouldReceive('channel')->twice()->with('db')->andReturn($channel);

    try {
        expect(DB::select('select 1 as value')[0]->value)->toBe(1);
        expect(DB::select('select 2 as value')[0]->value)->toBe(2);
        expect(substr_count(file_get_contents($path), 'handler unavailable'))->toBe(2);
    } finally {
        ini_set('error_log', $previous);
        unlink($path);
    }
});
