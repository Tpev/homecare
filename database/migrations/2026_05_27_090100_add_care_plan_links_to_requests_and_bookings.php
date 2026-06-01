<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->foreignId('care_plan_id')->nullable()->constrained('care_plans')->nullOnDelete();
            $table->index(['care_plan_id', 'status'], 'care_requests_plan_status_idx');
        });

        Schema::table('care_bookings', function (Blueprint $table) {
            $table->foreignId('care_plan_id')->nullable()->constrained('care_plans')->nullOnDelete();
            $table->index(['care_plan_id', 'status'], 'care_bookings_plan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->dropIndex('care_bookings_plan_status_idx');
            $table->dropConstrainedForeignId('care_plan_id');
        });

        Schema::table('care_requests', function (Blueprint $table) {
            $table->dropIndex('care_requests_plan_status_idx');
            $table->dropConstrainedForeignId('care_plan_id');
        });
    }
};
