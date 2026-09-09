<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel;

use EmerickLaw\SqlAnywhereLaravel\Query\SqlAnywhereGrammar;
use EmerickLaw\SqlAnywhereLaravel\Query\SqlAnywhereQueryProcessor;
use EmerickLaw\SqlAnywhereLaravel\Schema\SqlAnywhereSchemaGrammar;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Schema\Grammars\Grammar as SchemaGrammar;

final class SqlAnywhereConnection extends Connection
{
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        // Illuminate\Database\Connection::withTablePrefix() doesn't exist
        // on Laravel 12 — the grammar already holds $this (the Connection)
        // via its constructor and can read the table prefix from it
        // directly, so no separate setter-injection step is needed.
        return new SqlAnywhereGrammar($this);
    }

    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return new SqlAnywhereSchemaGrammar($this);
    }

    protected function getDefaultPostProcessor(): Processor
    {
        return new SqlAnywhereQueryProcessor();
    }
}
