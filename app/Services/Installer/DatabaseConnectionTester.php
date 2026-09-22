<?php

namespace App\Services\Installer;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

/**
 * Tries a database connection with credentials the operator just typed,
 * without ever touching the application's own `mysql`/`sqlite` connections.
 *
 * A wrong password tested against the real connection name would leave that
 * connection's PDO handle cached with bad credentials for the rest of the
 * request — the next thing to use it would fail with a confusing error far
 * from where the actual mistake was made. A disposable, uniquely-named
 * connection avoids that entirely: nothing else in the app ever resolves it.
 */
class DatabaseConnectionTester
{
    private const CONNECTION_NAME = 'installer_test';

    /**
     * @param  array{connection: string, host?: string, port?: string, database: string, username?: string, password?: string}  $config
     * @return array{ok: bool, message: string}
     */
    public function test(array $config): array
    {
        config(['database.connections.'.self::CONNECTION_NAME => $this->connectionConfig($config)]);

        try {
            DB::connection(self::CONNECTION_NAME)->getPdo();

            return ['ok' => true, 'message' => __('Connection successful.')];
        } catch (PDOException $e) {
            // MySQL error 1049: the server itself is reachable, only the
            // named database doesn't exist yet — exactly the case an
            // operator pointing this at a brand-new server hits, and the
            // one case worth recovering from automatically rather than
            // making them go create it by hand first.
            if ($config['connection'] === 'mysql' && stripos($e->getMessage(), 'Unknown database') !== false) {
                return $this->createDatabaseAndRetry($config);
            }

            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            DB::purge(self::CONNECTION_NAME);
        }
    }

    /**
     * @param  array{connection: string, host?: string, port?: string, database: string, username?: string, password?: string}  $config
     * @return array{ok: bool, message: string}
     */
    private function createDatabaseAndRetry(array $config): array
    {
        try {
            $withoutDatabase = $this->connectionConfig($config);
            unset($withoutDatabase['database']);

            config(['database.connections.'.self::CONNECTION_NAME => $withoutDatabase]);
            DB::purge(self::CONNECTION_NAME);

            $identifier = str_replace('`', '``', $config['database']);
            DB::connection(self::CONNECTION_NAME)->statement(
                "CREATE DATABASE IF NOT EXISTS `{$identifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            config(['database.connections.'.self::CONNECTION_NAME => $this->connectionConfig($config)]);
            DB::purge(self::CONNECTION_NAME);
            DB::connection(self::CONNECTION_NAME)->getPdo();

            return ['ok' => true, 'message' => __('Database created and connection successful.')];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array{connection: string, host?: string, port?: string, database: string, username?: string, password?: string}  $config
     * @return array<string, mixed>
     */
    private function connectionConfig(array $config): array
    {
        if ($config['connection'] === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $config['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? '3306',
            'database' => $config['database'],
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::ATTR_TIMEOUT => 5,
            ],
        ];
    }
}
