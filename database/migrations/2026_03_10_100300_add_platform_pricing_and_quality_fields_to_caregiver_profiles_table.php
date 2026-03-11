<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->string('pricing_tier', 32)->default('standard')->after('hourly_rate');
            $table->decimal('platform_hourly_rate', 8, 2)->default(27.00)->after('pricing_tier');

            $table->string('intro_video_path')->nullable()->after('profile_photo_path');
            $table->timestamp('intro_video_uploaded_at')->nullable()->after('intro_video_path');

            $table->string('insurance_status', 32)->default('not_provided')->after('is_accepting_new_clients');
            $table->string('insurance_document_path')->nullable()->after('insurance_status');
            $table->timestamp('insurance_verified_at')->nullable()->after('insurance_document_path');
        });

        DB::table('caregiver_profiles')
            ->whereNotNull('hourly_rate')
            ->update([
                'platform_hourly_rate' => DB::raw('hourly_rate'),
            ]);
    }

    public function down(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'pricing_tier',
                'platform_hourly_rate',
                'intro_video_path',
                'intro_video_uploaded_at',
                'insurance_status',
                'insurance_document_path',
                'insurance_verified_at',
            ]);
        });
    }
};

