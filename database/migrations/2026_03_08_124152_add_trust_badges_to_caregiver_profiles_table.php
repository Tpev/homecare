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
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->timestamp('identity_verified_at')->nullable()->after('reviews_count');
            $table->timestamp('background_check_verified_at')->nullable()->after('identity_verified_at');
            $table->boolean('top_caregiver')->default(false)->after('background_check_verified_at');

            $table->index(['top_caregiver', 'average_rating'], 'cg_top_rating_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropIndex('cg_top_rating_idx');
            $table->dropColumn([
                'identity_verified_at',
                'background_check_verified_at',
                'top_caregiver',
            ]);
        });
    }
};
