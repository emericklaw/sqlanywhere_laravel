<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

/**
 * SQL Anywhere dialect differences from the ANSI-ish base Grammar:
 *  - paging uses `SELECT TOP n START AT m ...` (1-based start row) instead
 *    of trailing LIMIT/OFFSET, so 'limit'/'offset' are dropped from the
 *    compiled component list and folded into compileColumns() instead.
 *  - delimited identifiers use double quotes (ANSI-style, like Postgres),
 *    not MySQL's backtick or SQL Server's [brackets].
 *  - YEAR()/MONTH()/DAY() date-part functions for whereDate()/whereYear()
 *    etc. — not verified against a live server yet (none was available
 *    while writing this); flagged as a Phase 2 follow-up in the plan.
 */
class SqlAnywhereGrammar extends Grammar
{
    /** @var string[] */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'lock',
    ];

    public function compileColumns(Builder $query, $columns)
    {
        if (!is_null($query->aggregate)) {
            return null;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';
        $select .= $this->compileTopClause($query);

        return $select . $this->columnize($columns);
    }

    public function compileLimit(Builder $query, $limit)
    {
        return '';
    }

    public function compileOffset(Builder $query, $offset)
    {
        return '';
    }

    public function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function compileRandom($seed)
    {
        return 'RAND()';
    }

    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        $function = match ($type) {
            'Date' => null,
            'Time' => null,
            default => strtolower($type),
        };

        if ($function === null) {
            $cast = $type === 'Date' ? 'date' : 'time';

            return 'cast(' . $this->wrap($where['column']) . " as {$cast}) {$where['operator']} {$value}";
        }

        return "{$function}(" . $this->wrap($where['column']) . ") {$where['operator']} {$value}";
    }

    protected function compileTopClause(Builder $query): string
    {
        if ($query->limit === null && $query->offset === null) {
            return '';
        }

        $top = $query->limit !== null ? (int) $query->limit : 'all';
        $clause = "top {$top} ";

        if ($query->offset !== null && (int) $query->offset > 0) {
            $startAt = (int) $query->offset + 1;
            $clause .= "start at {$startAt} ";
        }

        return $clause;
    }
}
