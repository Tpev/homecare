<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException('Marketplace statistics triggers require MySQL, MariaDB, or SQLite.');
        }

        Schema::create('marketplace_statistics', function (Blueprint $table): void {
            $table->string('name')->primary();
            // Signed during bootstrap: deletions can arrive before the initial backfill.
            $table->bigInteger('value')->default(0);
        });

        DB::table('marketplace_statistics')->insert([
            'name' => 'registered_caregivers',
            'value' => 0,
        ]);

        // Keep the counter in the same transaction as every account change,
        // including writes from the old release during a rolling deployment.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER marketplace_caregivers_insert AFTER INSERT ON users
            FOR EACH ROW
            BEGIN
                UPDATE marketplace_statistics SET value = value + 1
                WHERE name = 'registered_caregivers' AND NEW.role = 'caregiver';
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER marketplace_caregivers_update AFTER UPDATE ON users
            FOR EACH ROW
            BEGIN
                UPDATE marketplace_statistics
                SET value = value
                    + CASE WHEN NEW.role = 'caregiver' THEN 1 ELSE 0 END
                    - CASE WHEN OLD.role = 'caregiver' THEN 1 ELSE 0 END
                WHERE name = 'registered_caregivers'
                    AND (CASE WHEN NEW.role = 'caregiver' THEN 1 ELSE 0 END)
                        <> (CASE WHEN OLD.role = 'caregiver' THEN 1 ELSE 0 END);
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER marketplace_caregivers_delete AFTER DELETE ON users
            FOR EACH ROW
            BEGIN
                UPDATE marketplace_statistics SET value = value - 1
                WHERE name = 'registered_caregivers' AND OLD.role = 'caregiver';
            END
            SQL);

        // Install all triggers before taking the snapshot. Locking the counter
        // makes concurrent account changes apply their deltas after this backfill.
        DB::transaction(function (): void {
            DB::table('marketplace_statistics')
                ->where('name', 'registered_caregivers')->lockForUpdate()->first();

            $count = DB::table('users')->where('role', 'caregiver')->count();

            DB::table('marketplace_statistics')
                ->where('name', 'registered_caregivers')->update(['value' => $count]);
        }, 3);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS marketplace_caregivers_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS marketplace_caregivers_update');
        DB::unprepared('DROP TRIGGER IF EXISTS marketplace_caregivers_delete');
        Schema::dropIfExists('marketplace_statistics');
    }
};
