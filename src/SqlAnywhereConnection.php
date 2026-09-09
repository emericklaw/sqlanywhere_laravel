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
        return $this->withTablePrefix(new SqlAnywhereGrammar($this));
    }

    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return $this->withTablePrefix(new SqlAnywhereSchemaGrammar($this));
    }

    protected function getDefaultPostProcessor(): Processor
    {
        return new SqlAnywhereQueryProcessor();
    }
}
