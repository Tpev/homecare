<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('email');
            $table->index('stripe_customer_id', 'users_stripe_customer_idx');
        });

        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->string('stripe_connect_account_id')->nullable()->after('identity_verification_checked_at');
            $table->timestamp('stripe_connect_onboarding_completed_at')->nullable()->after('stripe_connect_account_id');
            $table->boolean('stripe_charges_enabled')->default(false)->after('stripe_connect_onboarding_completed_at');
            $table->boolean('stripe_payouts_enabled')->default(false)->after('stripe_charges_enabled');
            $table->timestamp('stripe_connect_last_synced_at')->nullable()->after('stripe_payouts_enabled');

            $table->index('stripe_connect_account_id', 'cp_stripe_account_idx');
        });
    }

    public function down(): void
    {
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropIndex('cp_stripe_account_idx');
            $table->dropColumn([
                'stripe_connect_account_id',
                'stripe_connect_onboarding_completed_at',
                'stripe_charges_enabled',
                'stripe_payouts_enabled',
                'stripe_connect_last_synced_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_stripe_customer_idx');
            $table->dropColumn('stripe_customer_id');
        });
    }
};
