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
            $table->unsignedBigInteger('occurrence_revision')->default(0);
            $table->dropUnique('care_bookings_plan_start_unique');
            $table->unique(['care_plan_id', 'scheduled_start_at', 'occurrence_revision'], 'care_bookings_plan_start_revision_unique');
        });
    }

    public function down(): void
    {
        // Restoring the old constraint cannot represent replacement history. Never discard that history.
        if (DB::table('care_bookings')->whereNotNull('care_plan_id')->select('care_plan_id', 'scheduled_start_at')->groupBy('care_plan_id', 'scheduled_start_at')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Replacement visit history exists. Preserve/export it before rolling back this schema.');
        }
        Schema::table('care_bookings', function (Blueprint $table) {
            $table->dropUnique('care_bookings_plan_start_revision_unique');
            $table->unique(['care_plan_id', 'scheduled_start_at'], 'care_bookings_plan_start_unique');
            $table->dropColumn('occurrence_revision');
        });
    }
};
