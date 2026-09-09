<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel;

use Illuminate\Database\Connection;
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
}
