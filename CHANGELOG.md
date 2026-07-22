# Changelog

All notable changes to `laravel-db-query-sniffer` will be documented in this file.

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
