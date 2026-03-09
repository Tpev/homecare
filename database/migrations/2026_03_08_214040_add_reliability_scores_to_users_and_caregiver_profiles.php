<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('family_reliability_score', 5, 2)->default(100)->after('onboarding_completed_at');
            $table->unsignedInteger('family_completed_bookings_count')->default(0)->after('family_reliability_score');
            $table->unsignedInteger('family_cancellation_count')->default(0)->after('family_completed_bookings_count');
            $table->unsignedInteger('family_dispute_count')->default(0)->after('family_cancellation_count');
        });

        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->decimal('reliability_score', 5, 2)->default(100)->after('response_metrics_updated_at');
            $table->unsignedInteger('completed_bookings_count')->default(0)->after('reliability_score');
            $table->unsignedInteger('cancellation_count')->default(0)->after('completed_bookings_count');
            $table->unsignedInteger('dispute_count')->default(0)->after('cancellation_count');
            $table->unsignedInteger('on_time_check_in_count')->default(0)->after('dispute_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'family_reliability_score',
                'family_completed_bookings_count',
                'family_cancellation_count',
                'family_dispute_count',
            ]);
        });

        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'reliability_score',
                'completed_bookings_count',
                'cancellation_count',
                'dispute_count',
                'on_time_check_in_count',
            ]);
        });
    }
};

