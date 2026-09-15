<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->timestamp('replacement_released_at')->nullable();
            // Released visits retain their request, payment and application history.
            $table->unsignedBigInteger('current_request_id')->nullable()
                ->virtualAs('CASE WHEN replacement_released_at IS NULL THEN care_request_id ELSE NULL END');
            $table->unique('current_request_id', 'cb_current_req_uniq');
            $table->index('care_request_id', 'cb_request_history_idx');
        });
        Schema::table('care_bookings', fn (Blueprint $table) => $table->dropUnique('cb_req_uniq'));
    }

    public function down(): void
    {
        if (DB::table('care_bookings')->groupBy('care_request_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Replacement visit history exists. This migration cannot be rolled back without losing history.');
        }
        Schema::table('care_bookings', fn (Blueprint $table) => $table->unique('care_request_id', 'cb_req_uniq'));
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->dropUnique('cb_current_req_uniq');
            $table->dropIndex('cb_request_history_idx');
            $table->dropColumn('current_request_id');
            $table->dropColumn('replacement_released_at');
        });
    }
};
