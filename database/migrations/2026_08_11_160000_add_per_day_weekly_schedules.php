<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->json('recurring_schedule')->nullable()->after('recurring_end_time');
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->json('schedule_slots')->nullable()->after('schedule_end_time');
            $table->json('counter_schedule_slots')->nullable()->after('counter_schedule_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn(['schedule_slots', 'counter_schedule_slots']);
        });

        Schema::table('care_requests', function (Blueprint $table) {
            $table->dropColumn('recurring_schedule');
        });
    }
};
