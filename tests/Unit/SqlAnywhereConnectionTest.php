<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel\Tests\Unit;

use EmerickLaw\SqlAnywhereLaravel\SqlAnywhereConnection;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * SQL Anywhere throws its own disconnect message strings that Laravel's
 * built-in Illuminate\Database\DetectsLostConnections trait doesn't
 * recognise (it only knows MySQL/pgsql/sqlsrv/etc. phrasing). Without this
 * override, a dropped persistent connection surfaces as a hard failure
 * instead of Laravel transparently reconnecting and retrying, like it does
 * for the drivers the trait was written for.
 */
final class SqlAnywhereConnectionTest extends TestCase
{
    private function causedByLostConnection(string $message): bool
    {
        $connection = new SqlAnywhereConnection(new PDO('sqlite::memory:'));
        $method = new ReflectionMethod($connection, 'causedByLostConnection');
        $method->setAccessible(true);

        return $method->invoke($connection, new PDOException($message));
    }

    public function test_not_connected_to_a_database_is_detected_as_lost_connection(): void
    {
        self::assertTrue($this->causedByLostConnection('Not connected to a database'));
    }

    public function test_communication_error_is_detected_as_lost_connection(): void
    {
        self::assertTrue($this->causedByLostConnection('Communication error -- some detail'));
    }

    public function test_connection_has_been_reset_is_detected_as_lost_connection(): void
    {
        self::assertTrue($this->causedByLostConnection('The connection has been reset'));
    }

    public function test_connection_was_terminated_is_detected_as_lost_connection(): void
    {
        self::assertTrue($this->causedByLostConnection('Connection was terminated by the server'));
    }

    public function test_unrelated_message_is_not_detected_as_lost_connection(): void
    {
        self::assertFalse($this->causedByLostConnection('Syntax error near "SELCT"'));
    }

    public function test_mysql_style_message_still_detected_via_parent_trait(): void
    {
        self::assertTrue($this->causedByLostConnection('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'));
    }
}
