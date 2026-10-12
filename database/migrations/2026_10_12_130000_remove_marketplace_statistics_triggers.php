<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $triggerNames = [
            'marketplace_caregivers_insert',
            'marketplace_caregivers_update',
            'marketplace_caregivers_delete',
        ];
        $usersTable = DB::connection()->getTablePrefix().'users';

        // Most deployments have no legacy triggers. Inspect first so recovering
        // the failed migration does not require any trigger-management grants.
        // Metadata tables themselves must not inherit a connection table prefix.
        $existingTriggers = collect(match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::select(
                'SELECT TRIGGER_NAME AS trigger_name FROM information_schema.TRIGGERS
                    WHERE TRIGGER_SCHEMA = ? AND EVENT_OBJECT_TABLE = ? AND TRIGGER_NAME IN (?, ?, ?)',
                [DB::connection()->getDatabaseName(), $usersTable, ...$triggerNames],
            ),
            'sqlite' => DB::select(
                'SELECT name AS trigger_name FROM sqlite_master
                    WHERE type = ? AND tbl_name = ? AND name IN (?, ?, ?)',
                ['trigger', $usersTable, ...$triggerNames],
            ),
            default => [],
        })->pluck('trigger_name');

        foreach ($triggerNames as $triggerName) {
            if ($existingTriggers->contains($triggerName)) {
                DB::unprepared('DROP TRIGGER '.$triggerName);
            }
        }

        DB::table('marketplace_statistics')->updateOrInsert(
            ['name' => 'registered_caregivers'],
            ['value' => DB::table('users')->where('role', 'caregiver')->count()],
        );
    }

    public function down(): void
    {
        // Deliberately do not reinstall triggers: doing so would reintroduce
        // the privileged DDL that prevented deployment. Application events
        // maintain the count while the statistics table remains in place.
    }
};
