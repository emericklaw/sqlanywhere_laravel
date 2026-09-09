<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Query;

use Illuminate\Database\Query\Processors\Processor;

/**
 * The base Processor's processInsertGetId() already delegates to
 * Connection::lastInsertId(), which SqlAnywherePdo implements via
 * sasql_insert_id() — no SQL-Anywhere-specific override needed yet.
 */
final class SqlAnywhereQueryProcessor extends Processor
{
}
