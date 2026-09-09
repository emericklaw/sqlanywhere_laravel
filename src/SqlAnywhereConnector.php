<?php

declare(strict_types=1);

namespace EmerickLaw\SqlAnywhereLaravel;

use EmerickLaw\SqlAnywherePdo\Internal\ConnectionStringBuilder;
use EmerickLaw\SqlAnywherePdo\SqlAnywherePdo;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;

/**
 * Builds a SqlAnywherePdo connection from a Laravel database config array.
 * Does not extend Illuminate\Database\Connectors\Connector — that base
 * class's createConnection()/createPdoConnection() hardcode `new PDO(...)`,
 * so this implements ConnectorInterface directly and instantiates
 * SqlAnywherePdo itself instead.
 */
final class SqlAnywhereConnector implements ConnectorInterface
{
    /** @var array<int, mixed> */
    protected array $defaultOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];

    public function connect(array $config): SqlAnywherePdo
    {
        $dsn = $this->buildConnectionString($config);
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $options = $this->getOptions($config);

        return new SqlAnywherePdo($dsn, $username, $password, $options);
    }

    protected function buildConnectionString(array $config): string
    {
        $pairs = [];

        // SQL Anywhere's SERVER= key names the database ENGINE, not a
        // host/port pair — that distinction has no natural home in
        // Laravel's standard connection config, so it's opt-in via
        // $config['server']. Network host/port go through LINKS=
        // TCPIP(host=...;port=...) instead (confirmed against a live
        // server). Example working DSN:
        // "UID=u;PWD=p;SERVER=enginename;DBN=db;LINKS=TCPIP(host=h;port=2638)"
        if (!empty($config['server'])) {
            $pairs['SERVER'] = $config['server'];
        }

        if (!empty($config['database'])) {
            $pairs['DBN'] = $config['database'];
        }

        if (!empty($config['host'])) {
            $links = "host={$config['host']}";

            if (!empty($config['port'])) {
                $links .= ";port={$config['port']}";
            }

            $pairs['LINKS'] = "TCPIP({$links})";
        }

        if (!empty($config['charset'])) {
            $pairs['CHARSET'] = $config['charset'];
        }

        foreach ($this->resolveOptionsArray($config) as $key => $value) {
            if (is_string($key)) {
                $pairs[$key] = $value;
            }
        }

        $dsn = implode(';', array_map(
            static fn ($key, $value) => "{$key}={$value}",
            array_keys($pairs),
            array_values($pairs),
        ));

        return ConnectionStringBuilder::build($dsn, null, null);
    }

    /**
     * @return array<int, mixed>
     */
    protected function getOptions(array $config): array
    {
        $options = [];

        foreach ($this->resolveOptionsArray($config) as $key => $value) {
            if (is_int($key)) {
                $options[$key] = $value;
            }
        }

        return array_diff_key($this->defaultOptions, $options) + $options;
    }

    /**
     * $config['options'] is expected to be an array, but `??` only catches
     * null/undefined — a config value resolved from something like
     * env('DB_OPTIONS', '') can come through as an empty string instead,
     * which crashes a bare foreach. Tolerate anything non-array as empty.
     *
     * @return array<int|string, mixed>
     */
    protected function resolveOptionsArray(array $config): array
    {
        $options = $config['options'] ?? [];

        return is_array($options) ? $options : [];
    }
}
