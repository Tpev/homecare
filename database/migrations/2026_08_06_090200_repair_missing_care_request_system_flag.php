<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('care_requests', 'is_system_generated')) {
            return;
        }

        Schema::table('care_requests', function (Blueprint $table) {
            $table->boolean('is_system_generated')->default(false)->after('care_plan_id');
            $table->index(['is_system_generated', 'status'], 'care_requests_system_status_idx');
        });
    }

    public function down(): void
    {
        // This repairs a column owned by the earlier regular-care migration.
        // Keep it in place when rolling this compatibility migration back.
    }
};
