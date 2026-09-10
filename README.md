# sqlanywhere-laravel

Laravel database driver for SAP SQL Anywhere, built on [`emericklaw/sqlanywhere-pdo`](../lib_sqlanywhere_pdo).

## Requirements

- PHP >= 8.1, Laravel 10+
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

## Horizon / `queue:work` deadlocks (fixed automatically)

SAP's closed-source SQL Anywhere client library doesn't tolerate PHP's `pcntl_async_signals(true)` — which Laravel's queue `Worker` enables only in daemon mode (`queue:work`, including everything Horizon runs), never in `--once` mode (what `queue:listen` spawns per job). With async signals on, a signal can interrupt execution mid-syscall inside this package's `sasql_*` calls, and the client library's internal blocking wait doesn't recover from that — it hangs forever on the next `prepare()`/`execute()` on that connection. This reproduces reliably under Horizon/`queue:work` and never under `queue:listen` or `tinker`, regardless of how many jobs have run — it's not a resource leak, it's this specific interaction.

`SqlAnywhereServiceProvider::boot()` registers `Queue::before()`/`Queue::after()` listeners that disable async signals for the duration of each job and re-enable them between jobs, automatically, for any app using this package — no per-job workaround needed.

## Testing

```
composer install
composer test
```

Unit tests (grammar SQL assertions) need no live server. Feature tests are skipped unless `ext-sqlanywhere` is loaded and `SASQL_TEST_DATABASE` (plus `SASQL_TEST_HOST`/`_PORT`/`_USERNAME`/`_PASSWORD`) point at a reachable server.
