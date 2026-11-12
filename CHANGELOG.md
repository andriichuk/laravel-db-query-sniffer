# Changelog

All notable changes to `laravel-db-query-sniffer` will be documented in this file.

## 0.2.0 - 2026-11-12

### Added

- Automatic API request correlation with generated request IDs, HTTP methods, paths, and a response header.
- Numeric `duration_ms` fields alongside the existing timing strings.
- Default rotating SQL and Redis file channels that preserve existing application configuration.
- Reentrancy protection and isolated log handler failures.
- Real HTTP/SQLite integration tests, maximum-level static analysis, compatible CI test dependencies, and a 95% source coverage gate.

### Fixed

- Invalid UTF-8 bindings without NUL bytes are now handled as binary.
- Invalid binding length and slow-query threshold settings are rejected explicitly.
- Environment gating tests now exercise provider registration.

### Changed

- CI checks formatting without modifying repository contents.
- Listener constructors now receive the internal query logger.

## 0.1.2 - 2026-07-22

### Added

- **`database.slow_only`** / **`redis.slow_only`** (default `false`) — when disabled, every query/command is logged; when enabled, only those ≥ `slow_ms` are logged.

### Changed

- **`slow_ms`** defaults to `100` and is only applied when the matching `slow_only` option is enabled.

## 0.1.1 - 2026-07-22

### Added

- **`environments`** config (default `['local']`) so listeners are not registered during automated tests unless explicitly allowed (`['*']`).

## 0.1.0 - 2026-07-22

### Added

- Initial release: log SQL queries (raw SQL via `toRawSql()`) and Redis commands.
- Configurable enable switch (`LOG_DB_QUERIES` / `APP_DEBUG` fallback).
- Separate database and Redis toggles, channels, and slow thresholds.
- Binary-safe binding sanitization with optional truncation and base64 encoding.
- Fallback logging when raw SQL substitution fails.
