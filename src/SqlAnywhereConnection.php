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
use Illuminate\Support\Str;
use Throwable;

final class SqlAnywhereConnection extends Connection
{
    /**
     * Error substrings SQL Anywhere throws when the underlying connection
     * resource has already died (idle/session timeout on the server,
     * a stale persistent connection, etc.) that Laravel's own
     * DetectsLostConnections trait doesn't recognise.
     *
     * @var list<string>
     */
    private const LOST_CONNECTION_MESSAGES = [
        'Not connected to a database',
        'Communication error',
        'connection has been reset',
        'Connection was terminated',
    ];

    protected function causedByLostConnection(Throwable $e): bool
    {
        return parent::causedByLostConnection($e)
            || Str::contains($e->getMessage(), self::LOST_CONNECTION_MESSAGES);
    }

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
