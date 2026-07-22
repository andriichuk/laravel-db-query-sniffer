# Laravel DB Query Sniffer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andriichuk/laravel-db-query-sniffer.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-db-query-sniffer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-db-query-sniffer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/andriichuk/laravel-db-query-sniffer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/andriichuk/laravel-db-query-sniffer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/andriichuk/laravel-db-query-sniffer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/andriichuk/laravel-db-query-sniffer.svg?style=flat-square)](https://packagist.org/packages/andriichuk/laravel-db-query-sniffer)

Log **SQL queries** and **Redis commands** in Laravel during local development — with raw SQL, binary-safe binding fallbacks, and optional slow-query filters.

## Requirements

- PHP **8.3+**
- Laravel **11.x**, **12.x**, or **13.x**

## Installation

```bash
composer require andriichuk/laravel-db-query-sniffer --dev
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="db-query-sniffer-config"
```

Add dedicated log channels in `config/logging.php` (recommended):

```php
'channels' => [
    // ...
    'db' => [
        'driver' => 'daily',
        'path' => storage_path('logs/db.log'),
        'level' => 'debug',
        'days' => 2,
    ],
    'redis' => [
        'driver' => 'daily',
        'path' => storage_path('logs/redis.log'),
        'level' => 'debug',
        'days' => 2,
    ],
],
```

> **When is logging on?** If `LOG_DB_QUERIES` is not set, the sniffer follows **`APP_DEBUG`**. Set `LOG_DB_QUERIES=true` or `false` to force it. Override channels with `DB_QUERY_LOG_CHANNEL` and `REDIS_COMMAND_LOG_CHANNEL`.

## Configuration

| Key | Description | Default |
| --- | --- | --- |
| `enabled` | Master switch | `env('LOG_DB_QUERIES', APP_DEBUG)` |
| `database.enabled` | Log SQL queries | `true` |
| `database.channel` | Log channel for SQL | `db` |
| `database.slow_ms` | Only log queries ≥ N ms (`null` = all) | `null` |
| `database.include_connection` | Include connection name in context | `true` |
| `redis.enabled` | Log Redis commands | `true` |
| `redis.channel` | Log channel for Redis | `redis` |
| `redis.slow_ms` | Only log commands ≥ N ms (`null` = all) | `null` |
| `bindings.max_string_length` | Truncate string bindings (`null` = no limit) | `null` |
| `bindings.binary_as_base64` | Include base64 for binary bindings | `true` |

## Example log output

```
[2026-07-22 06:50:01] local.INFO: SQL Query: {"query":"select * from `users` where `id` = 1","time":"1.24ms","connection":"mysql"}
[2026-07-22 06:50:01] local.INFO: Redis Command: {"command":"GET","parameters":["user:1"],"time":"0.31ms","connection":"default"}
```

When `toRawSql()` fails (e.g. binary bindings), a warning is logged with sanitized bindings instead of crashing:

```
[2026-07-22 06:50:02] local.WARNING: Failed to build raw SQL query string. Logging fallback payload. {"error":"...","sql":"select ?","bindings":["[binary:16 bytes;base64=...]"],"time":"0.5ms","connection":"mysql"}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
