# sqlanywhere-laravel

Laravel database driver for SAP SQL Anywhere, built on [`emericklaw/sqlanywhere-pdo`](../lib_sqlanywhere_pdo).

## Requirements

- PHP >= 1, Laravel 10+
- `ext-sqlanywhere` built and loaded

## Setup

Add the connection to `config/database.php`:

```php
'connections' => [
    'sqlanywhere' => [
        'driver' => 'sqlanywhere',
        'server' => env('DB_SERVER', ''), // SQL Anywhere engine name (SERVER= in the DSN), not host/port
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '2638'),
        'database' => env('DB_DATABASE', ''),
        'username' => env('DB_USERNAME', ''),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf-8'),
        'prefix' => '',
        'options' => [],
    ],
],
```

The service provider auto-registers via Laravel package discovery (`composer.json`'s `extra.laravel.providers`).

## Dialect notes

- Paging compiles to `SELECT TOP n START AT m ...` (SQL Anywhere has no trailing `LIMIT`/`OFFSET`).
- Identifiers are quoted with double quotes (ANSI-style).
- Schema introspection (`Schema::hasTable()`, `Schema::getColumnListing()`, create/drop) queries SQL Anywhere's `SYS.SYSTABLE`/`SYS.SYSTABCOL` system views.

## Verified against a live server

Connection resolution (`DB::connection('sqlanywhere')`), query builder round trips, `DB::transaction()`/commit/rollback, and schema create/drop/`hasTable()`/`getColumnListing()` are all covered by `tests/Feature/*` and pass against a real SQL Anywhere instance.

One real bug found and fixed this way: `SqlAnywhereConnector` built connection strings with a separate `PORT=` key and later with `SERVER=host:port` — neither is valid. SQL Anywhere's `SERVER=` names the database *engine*, not a host/port pair; network host/port go through `LINKS=TCPIP(host=...;port=...)` instead. Fixed, with `server` added as an optional connection config key for the engine name.

## Testing

```
composer install
composer test
```

Unit tests (grammar SQL assertions) need no live server. Feature tests are skipped unless `ext-sqlanywhere` is loaded and `SASQL_TEST_DATABASE` (plus `SASQL_TEST_HOST`/`_PORT`/`_USERNAME`/`_PASSWORD`) point at a reachable server.
