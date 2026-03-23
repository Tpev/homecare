<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caregiver_profiles')) {
            return;
        }

        $targetRate = 30.00;
        $now = now();

        DB::table('caregiver_profiles')->update([
            'pricing_tier' => 'standard',
            'platform_hourly_rate' => $targetRate,
            'updated_at' => $now,
        ]);

        if (Schema::hasTable('care_request_applications')) {
            DB::table('care_request_applications')
                ->whereIn('status', ['applied', 'shortlisted', 'hired'])
                ->update([
                    'proposed_rate' => $targetRate,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally left empty: pricing migration is forward-only.
    }
};
