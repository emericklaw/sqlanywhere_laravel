<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Tests;

use EmerickLaw\SqlAnywhereLaravel\SqlAnywhereServiceProvider;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SqlAnywhereServiceProvider::class];
    }

    /**
     * Testbench boots a fresh application (and so a fresh SqlAnywherePdo
     * connection) per test within the SAME PHP process. Without an
     * explicit disconnect, the previous test's connection only closes
     * whenever PHP's refcounting happens to GC it, which isn't guaranteed
     * to happen before the next test opens a new one — across enough
     * tests this can exhaust the server's connection limit. Force a
     * deterministic close instead of relying on that timing.
     */
    protected function tearDown(): void
    {
        if (extension_loaded('sqlanywhere')) {
            try {
                DB::connection('sqlanywhere')->disconnect();
            } catch (\Throwable) {
                // No connection was ever opened for this test — nothing to close.
            }
        }

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.connections.sqlanywhere', [
            'driver' => 'sqlanywhere',
            'server' => getenv('SASQL_TEST_SERVER') ?: '',
            'host' => getenv('SASQL_TEST_HOST') ?: 'localhost',
            'port' => getenv('SASQL_TEST_PORT') ?: '2638',
            'database' => getenv('SASQL_TEST_DATABASE') ?: '',
            'username' => getenv('SASQL_TEST_USERNAME') ?: '',
            'password' => getenv('SASQL_TEST_PASSWORD') ?: '',
            'charset' => 'utf-8',
            'prefix' => '',
            'options' => [],
        ]);
    }

    protected function requireLiveSqlAnywhereConnection(): void
    {
        if (!extension_loaded('sqlanywhere')) {
            self::markTestSkipped('ext-sqlanywhere is not loaded.');
        }

        if (getenv('SASQL_TEST_DATABASE') === false) {
            self::markTestSkipped('SASQL_TEST_DATABASE is not set; no SQL Anywhere test server configured.');
        }
    }
}
