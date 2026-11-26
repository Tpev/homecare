<?php

namespace App\Services\Reporting;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReportingConnection
{
    public function open(): Connection
    {
        // Laravel has already resolved DB_URL and any write-host configuration here.
        // Clone the settings, never the PDO: the application's writer must stay untouched.
        $settings = DB::connection()->getConfig();
        $driver = $settings['driver'] ?? '';
        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported reporting database driver.');
        }
        foreach (['url', 'name', 'read', 'write', 'sticky'] as $key) {
            unset($settings[$key]);
        }
        $settings['options'][\PDO::ATTR_PERSISTENT] = false;
        if ($driver === 'sqlite') {
            // Do not inherit persistent file changes or a write-locking transaction mode.
            unset($settings['journal_mode'], $settings['synchronous']);
            $settings['pragmas'] = ['query_only' => 1];
            $settings['transaction_mode'] = 'DEFERRED';
        }
        config(['database.connections.reporting_export' => $settings]);
        $db = DB::connection('reporting_export');
        $db->disableQueryLog();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $db->statement('SET SESSION TRANSACTION READ ONLY');
        } elseif ($driver === 'pgsql') {
            $db->statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
        } else {
            $db->statement('PRAGMA query_only = ON');
        }

        return $db;
    }

    public function beginSnapshot(Connection $db, array $tables): void
    {
        if ($db->transactionLevel() !== 0) {
            throw new RuntimeException('Reporting connection already has a transaction.');
        }
        if (in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            $engines = $db->table('information_schema.tables')->where('TABLE_SCHEMA', $db->getDatabaseName())
                ->whereIn('TABLE_NAME', $tables)->pluck('ENGINE');
            if ($engines->contains(fn ($engine) => strtoupper((string) $engine) !== 'INNODB')) {
                throw new RuntimeException('Export requires transactional InnoDB tables.');
            }
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->statement('SET TRANSACTION READ ONLY');
        }
        $db->beginTransaction();
        if ($db->getDriverName() === 'pgsql') {
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
        }
        $db->table('users')->select('id')->limit(1)->get(); // Establish the consistent read view now.
    }

    public function close(): void
    {
        if (array_key_exists('reporting_export', DB::getConnections())) {
            $db = DB::connection('reporting_export');
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
            DB::purge('reporting_export');
        }
    }
}
