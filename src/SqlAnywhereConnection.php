<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel;

use EmerickLaw\SqlAnywhereLaravel\Query\SqlAnywhereGrammar;
use EmerickLaw\SqlAnywhereLaravel\Query\SqlAnywhereQueryProcessor;
use EmerickLaw\SqlAnywhereLaravel\Schema\SqlAnywhereSchemaGrammar;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Query\Grammars\Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Schema\Grammars\Grammar as SchemaGrammar;
use Illuminate\Support\Str;
use PDOException;
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
        'Unable to connect to SQL Anywhere',
    ];

    /**
     * Sleep (ms) before each extra reconnect attempt when the server can't
     * be reached at all (SQLSTATE 08xxx), e.g. a brief restart or failover.
     * Stale-connection errors still get a single immediate retry.
     *
     * @var list<int>
     */
    private const CONNECT_RETRY_DELAYS_MS = [200, 500, 1000, 2000];

    protected function causedByLostConnection(Throwable $e): bool
    {
        return parent::causedByLostConnection($e)
            || $this->isConnectFailure($e)
            || Str::contains($e->getMessage(), self::LOST_CONNECTION_MESSAGES);
    }

    /**
     * ANSI SQLSTATE class 08 = connection exception (e.g. 08001 when the
     * server refuses/can't be reached while (re)connecting).
     */
    private function isConnectFailure(Throwable $e): bool
    {
        $sqlstate = $e instanceof PDOException ? ($e->errorInfo[0] ?? null) : null;

        return is_string($sqlstate) && str_starts_with($sqlstate, '08');
    }

    protected function tryAgainIfCausedByLostConnection(QueryException $e, $query, $bindings, Closure $callback)
    {
        $previous = $e->getPrevious();

        if ($previous === null || ! $this->causedByLostConnection($previous)) {
            throw $e;
        }

        $delays = $this->isConnectFailure($previous) ? self::CONNECT_RETRY_DELAYS_MS : [0];
        $last = count($delays) - 1;

        foreach ($delays as $attempt => $delay) {
            if ($delay > 0) {
                usleep($delay * 1000);
            }

            try {
                $this->reconnect();

                return $this->runQueryCallback($query, $bindings, $callback);
            } catch (Throwable $retry) {
                // reconnect() throws the raw driver exception; the re-run
                // throws a QueryException wrapping it.
                $cause = $retry instanceof QueryException ? $retry->getPrevious() : $retry;

                if ($attempt === $last || $cause === null || ! $this->isConnectFailure($cause)) {
                    throw $retry;
                }
            }
        }

        throw $e;
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
