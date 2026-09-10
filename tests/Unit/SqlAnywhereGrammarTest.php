<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Tests\Unit;

use EmerickLaw\SqlAnywhereLaravel\Query\SqlAnywhereGrammar;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Pure grammar-compilation assertions — no SQL Anywhere connection needed,
 * only a throwaway sqlite PDO handle to satisfy Illuminate\Database\
 * Connection's constructor (the grammar never executes anything against it).
 */
final class SqlAnywhereGrammarTest extends TestCase
{
    private function makeBuilder(): Builder
    {
        $connection = new Connection(new PDO('sqlite::memory:'));
        $grammar = new SqlAnywhereGrammar($connection);
        $processor = new Processor();

        return new Builder($connection, $grammar, $processor);
    }

    public function test_limit_without_offset_compiles_to_top_clause(): void
    {
        $sql = $this->makeBuilder()->from('users')->limit(10)->toSql();

        self::assertSame('select top 10 * from "users"', $sql);
    }

    public function test_limit_with_offset_compiles_to_top_start_at_clause(): void
    {
        $sql = $this->makeBuilder()->from('users')->limit(10)->offset(5)->toSql();

        self::assertSame('select top 10 start at 6 * from "users"', $sql);
    }

    public function test_identifiers_are_wrapped_in_double_quotes(): void
    {
        $sql = $this->makeBuilder()->from('users')->select('name')->toSql();

        self::assertSame('select "name" from "users"', $sql);
    }

    public function test_no_limit_or_offset_omits_top_clause(): void
    {
        $sql = $this->makeBuilder()->from('users')->toSql();

        self::assertSame('select * from "users"', $sql);
    }

    public function test_exists_compiles_to_case_when_instead_of_select_exists_as(): void
    {
        $query = $this->makeBuilder()->from('users')->where('id', 1);
        $sql = $query->getGrammar()->compileExists($query);

        self::assertSame(
            'select case when exists(select * from "users" where "id" = ?) then 1 else 0 end as "exists"',
            $sql,
        );
    }
}
