<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->decimal('invite_response_rate', 5, 2)->nullable()->after('top_caregiver');
            $table->unsignedInteger('avg_invite_response_minutes')->nullable()->after('invite_response_rate');
            $table->timestamp('response_metrics_updated_at')->nullable()->after('avg_invite_response_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'invite_response_rate',
                'avg_invite_response_minutes',
                'response_metrics_updated_at',
            ]);
        });
    }
};
