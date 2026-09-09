<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Tests\Feature;

use EmerickLaw\SqlAnywhereLaravel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class QueryBuilderIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireLiveSqlAnywhereConnection();
    }

    public function test_basic_select_round_trip(): void
    {
        $row = DB::connection('sqlanywhere')->selectOne('select 1 as one');

        self::assertSame(1, (int) $row->one);
    }

    public function test_transaction_commit_and_rollback(): void
    {
        $connection = DB::connection('sqlanywhere');

        $connection->beginTransaction();
        self::assertTrue($connection->getPdo()->inTransaction());
        $connection->rollBack();
        self::assertFalse($connection->getPdo()->inTransaction());
    }
}
