<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Tests\Feature;

use EmerickLaw\SqlAnywhereLaravel\SqlAnywhereConnection;
use EmerickLaw\SqlAnywhereLaravel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class ConnectionResolutionTest extends TestCase
{
    public function test_sqlanywhere_driver_resolves_to_sqlanywhere_connection(): void
    {
        if (!extension_loaded('sqlanywhere')) {
            self::markTestSkipped('ext-sqlanywhere is not loaded, so SqlAnywhereConnector::connect() cannot open a PDO handle.');
        }

        $this->requireLiveSqlAnywhereConnection();

        $connection = DB::connection('sqlanywhere');

        self::assertInstanceOf(SqlAnywhereConnection::class, $connection);
    }
}
