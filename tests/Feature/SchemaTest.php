<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Tests\Feature;

use EmerickLaw\SqlAnywhereLaravel\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exercises SqlAnywhereSchemaGrammar's catalog introspection — the riskiest
 * untested piece of the package, since its SYS.SYSTABLE/SYS.SYSTABCOL
 * queries (and the DDL compile methods) were written without a live server
 * to verify against.
 */
final class SchemaTest extends TestCase
{
    private const TABLE = 'sqlanywhere_laravel_schema_test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireLiveSqlAnywhereConnection();
        Schema::connection('sqlanywhere')->dropIfExists(self::TABLE);
    }

    protected function tearDown(): void
    {
        if (extension_loaded('sqlanywhere') && getenv('SASQL_TEST_DATABASE') !== false) {
            Schema::connection('sqlanywhere')->dropIfExists(self::TABLE);
        }

        parent::tearDown();
    }

    public function test_create_table_and_list_columns(): void
    {
        Schema::connection('sqlanywhere')->create(self::TABLE, function (Blueprint $table) {
            $table->integer('id');
            $table->string('name', 100);
            $table->boolean('active');
            $table->timestamp('created_at')->nullable();
        });

        self::assertTrue(Schema::connection('sqlanywhere')->hasTable(self::TABLE));

        $columns = array_map('strtolower', Schema::connection('sqlanywhere')->getColumnListing(self::TABLE));

        self::assertContains('id', $columns);
        self::assertContains('name', $columns);
        self::assertContains('active', $columns);
        self::assertContains('created_at', $columns);
    }

    public function test_drop_table(): void
    {
        Schema::connection('sqlanywhere')->create(self::TABLE, function (Blueprint $table) {
            $table->integer('id');
        });

        self::assertTrue(Schema::connection('sqlanywhere')->hasTable(self::TABLE));

        Schema::connection('sqlanywhere')->drop(self::TABLE);

        self::assertFalse(Schema::connection('sqlanywhere')->hasTable(self::TABLE));
    }

    public function test_has_table_returns_false_for_nonexistent_table(): void
    {
        self::assertFalse(Schema::connection('sqlanywhere')->hasTable('table_that_should_not_exist_anywhere'));
    }
}
