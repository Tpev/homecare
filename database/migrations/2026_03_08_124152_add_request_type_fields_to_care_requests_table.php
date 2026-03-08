<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->string('request_type', 20)->default('one_time')->after('status');
            $table->json('recurring_days')->nullable()->after('requested_end_at');
            $table->time('recurring_start_time')->nullable()->after('recurring_days');
            $table->time('recurring_end_time')->nullable()->after('recurring_start_time');
            $table->date('recurring_starts_on')->nullable()->after('recurring_end_time');
            $table->date('recurring_ends_on')->nullable()->after('recurring_starts_on');

            $table->index(['request_type', 'status'], 'cr_req_type_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->dropIndex('cr_req_type_status_idx');
            $table->dropColumn([
                'request_type',
                'recurring_days',
                'recurring_start_time',
                'recurring_end_time',
                'recurring_starts_on',
                'recurring_ends_on',
            ]);
        });
    }
};
