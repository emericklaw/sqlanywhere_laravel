<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

final class SqlAnywhereServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving('db', function ($db): void {
            $db->extend('sqlanywhere', function (array $config, string $name): Connection {
                $config['name'] = $name;

                $pdo = (new SqlAnywhereConnector())->connect($config);

                return new SqlAnywhereConnection(
                    $pdo,
                    $config['database'] ?? '',
                    $config['prefix'] ?? '',
                    $config,
                );
            });
        });
    }

    public function boot(): void
    {
        // Laravel's queue Worker enables pcntl_async_signals(true) only in
        // daemon mode (queue:work / Horizon's continuous loop) to support
        // graceful shutdown and job timeouts — `queue:work --once` (what
        // queue:listen spawns per job) never touches it. With async
        // signals on, PHP installs real OS-level signal handlers that can
        // interrupt execution at any point, including mid-syscall inside
        // this package's sasql_* calls. SAP's closed-source client
        // library's internal semaphore/futex wait doesn't tolerate that
        // interruption — confirmed live: a Horizon worker deadlocked
        // inside sqlany_prepare() on exactly this, disabling async
        // signals for the job's duration fixed it, queue:listen (which
        // never enables them) never reproduced it in the first place.
        // Re-enabled between jobs since Laravel is only enabling it per
        // job anyway (registerTimeoutHandler() runs before each
        // JobProcessing), so there's no persistent loss of responsive
        // shutdown signal handling while idle between jobs.
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        Queue::before(function (): void {
            pcntl_async_signals(false);
        });

        Queue::after(function (): void {
            pcntl_async_signals(true);
        });
    }
}
