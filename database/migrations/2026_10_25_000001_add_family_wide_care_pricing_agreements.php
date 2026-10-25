<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_pricing_agreements', function (Blueprint $table): void {
            // NULL is a pair agreement; true is the single account-wide agreement.
            // Keep the original caregiver/source booking as the agreement's provenance.
            $table->boolean('all_caregivers')->nullable();
            $table->unique(['family_account_id', 'all_caregivers'], 'care_pricing_one_family_agreement');
        });
    }

    public function down(): void
    {
        Schema::table('care_pricing_agreements', function (Blueprint $table): void {
            $table->dropUnique('care_pricing_one_family_agreement');
            $table->dropColumn('all_caregivers');
        });
    }
};
