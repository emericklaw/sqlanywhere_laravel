<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;

/**
 * IMPORTANT: the exact abstract-method signatures Illuminate\Database\
 * Schema\Grammars\Grammar requires a subclass to implement can differ
 * between Laravel 10 and 11, and could not be checked against the real
 * framework source while writing this (no PHP/Composer/internet access in
 * this environment). This covers the methods this implementation is
 * confident about; running `composer install` + instantiating this class
 * will surface any missing/mismatched abstract method as an immediate
 * fatal error naming exactly what's missing — fix those on first run
 * rather than treating this file as final.
 *
 * Catalog introspection (compileTables/compileColumns/compileIndexes/
 * compileForeignKeys below) targets SQL Anywhere's SYS.SYSTABLE/
 * SYS.SYSCOLUMN system views and has NOT been verified against a live
 * server (none was available) — this is the single riskiest part of the
 * whole plan per the open-risks list and needs hands-on correction.
 */
class SqlAnywhereSchemaGrammar extends Grammar
{
    protected $modifiers = ['nullable', 'default', 'increment'];

    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    public function compileTableExists($schema = null, $table = null): string
    {
        $table = $table ?? $schema;

        return "select count(*) from SYS.SYSTABLE where table_name = " . $this->quoteString($table);
    }

    public function compileTables(): string
    {
        return 'select table_name as name from SYS.SYSTABLE where creator != 0 order by table_name';
    }

    public function compileColumns($table): string
    {
        return 'select c.column_name as name, c.domain_id as type_id, '
            . "c.nulls as nullable, c.\"default\" as \"default\" "
            . 'from SYS.SYSTABCOL c join SYS.SYSTABLE t on t.table_id = c.table_id '
            . 'where t.table_name = ' . $this->quoteString($this->tablePrefix . $table)
            . ' order by c.column_id';
    }

    public function compileIndexes($table): string
    {
        return 'select i.index_name as name '
            . 'from SYS.SYSIDX i join SYS.SYSTABLE t on t.table_id = i.table_id '
            . 'where t.table_name = ' . $this->quoteString($this->tablePrefix . $table);
    }

    public function compileForeignKeys($table): string
    {
        return 'select fk.role as name '
            . 'from SYS.SYSFOREIGNKEY fk join SYS.SYSTABLE t on t.table_id = fk.foreign_table_id '
            . 'where t.table_name = ' . $this->quoteString($this->tablePrefix . $table);
    }

    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection): string
    {
        return sprintf(
            'create table %s (%s)',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint)),
        );
    }

    public function compileAdd(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add (%s)',
            $this->wrapTable($blueprint),
            implode(', ', $this->prefixArray('', $this->getColumns($blueprint))),
        );
    }

    public function compileDrop(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        // A bare "if ... then ... end if" is a procedural (SQL/PSM)
        // statement — SQL Anywhere only accepts it inside a compound
        // BEGIN...END block, not as a standalone statement submitted
        // directly through prepare()/exec(). Wrapping it as an anonymous
        // block is what makes this actually executable as one statement.
        return 'begin if exists (select 1 from SYS.SYSTABLE where table_name = '
            . $this->quoteString($this->tablePrefix . $blueprint->getTable())
            . ') then drop table ' . $this->wrapTable($blueprint) . ' end if end';
    }

    public function compileRename(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s rename %s',
            $this->wrapTable($blueprint),
            $this->wrapTable((object) ['table' => $command->to]),
        );
    }

    public function compilePrimary(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add primary key (%s)',
            $this->wrapTable($blueprint),
            $this->columnize($command->columns),
        );
    }

    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns),
        );
    }

    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns),
        );
    }

    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        return 'alter table ' . $this->wrapTable($blueprint) . ' drop primary key';
    }

    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop index ' . $this->wrap($command->index);
    }

    public function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop index ' . $this->wrap($command->index);
    }

    protected function typeChar(Fluent $column): string
    {
        return "char({$column->length})";
    }

    protected function typeString(Fluent $column): string
    {
        return "varchar({$column->length})";
    }

    protected function typeText(Fluent $column): string
    {
        return 'long varchar';
    }

    protected function typeMediumText(Fluent $column): string
    {
        return 'long varchar';
    }

    protected function typeLongText(Fluent $column): string
    {
        return 'long varchar';
    }

    protected function typeInteger(Fluent $column): string
    {
        return 'integer';
    }

    protected function typeBigInteger(Fluent $column): string
    {
        return 'bigint';
    }

    protected function typeMediumInteger(Fluent $column): string
    {
        return 'integer';
    }

    protected function typeSmallInteger(Fluent $column): string
    {
        return 'smallint';
    }

    protected function typeTinyInteger(Fluent $column): string
    {
        return 'tinyint';
    }

    protected function typeFloat(Fluent $column): string
    {
        return 'float';
    }

    protected function typeDouble(Fluent $column): string
    {
        return 'double';
    }

    protected function typeDecimal(Fluent $column): string
    {
        return "numeric({$column->total}, {$column->places})";
    }

    protected function typeBoolean(Fluent $column): string
    {
        return 'bit';
    }

    protected function typeDate(Fluent $column): string
    {
        return 'date';
    }

    protected function typeDateTime(Fluent $column): string
    {
        return 'timestamp';
    }

    protected function typeDateTimeTz(Fluent $column): string
    {
        return 'timestamp';
    }

    protected function typeTime(Fluent $column): string
    {
        return 'time';
    }

    protected function typeTimeTz(Fluent $column): string
    {
        return 'time';
    }

    protected function typeTimestamp(Fluent $column): string
    {
        return 'timestamp';
    }

    protected function typeTimestampTz(Fluent $column): string
    {
        return 'timestamp';
    }

    protected function typeBinary(Fluent $column): string
    {
        return 'long binary';
    }

    protected function typeUuid(Fluent $column): string
    {
        return 'uniqueidentifier';
    }

    protected function typeJson(Fluent $column): string
    {
        return 'long varchar';
    }

    protected function typeJsonb(Fluent $column): string
    {
        return 'long varchar';
    }

    protected function modifyNullable(Blueprint $blueprint, Fluent $column): ?string
    {
        if (is_null($column->nullable)) {
            return null;
        }

        return $column->nullable ? ' null' : ' not null';
    }

    protected function modifyDefault(Blueprint $blueprint, Fluent $column): ?string
    {
        if (!is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }

        return null;
    }

    protected function modifyIncrement(Blueprint $blueprint, Fluent $column): ?string
    {
        if (in_array($column->type, $this->serials, true) && $column->autoIncrement) {
            return ' default autoincrement primary key';
        }

        return null;
    }
}
