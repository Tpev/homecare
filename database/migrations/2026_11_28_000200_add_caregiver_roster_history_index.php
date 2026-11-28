<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_rosters', function (Blueprint $table): void {
            $table->index(['care_request_id', 'created_at', 'id', 'status'], 'caregiver_rosters_request_history_index');
        });
    }

    public function down(): void
    {
        // InnoDB may replace its automatic foreign-key index with this wider index.
        // Restore a supporting index before dropping it on rollback.
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            && ! Schema::hasIndex('caregiver_rosters', ['care_request_id'])) {
            Schema::table('caregiver_rosters', function (Blueprint $table): void {
                $table->index('care_request_id');
            });
        }
        Schema::table('caregiver_rosters', function (Blueprint $table): void {
            $table->dropIndex('caregiver_rosters_request_history_index');
        });
    }
};
