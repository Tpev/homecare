<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $familyOwnedTables = [
        'care_requests' => 'care_requests_family_account_index',
        'care_request_invitations' => 'care_request_invitations_family_account_index',
        'care_request_conversations' => 'care_request_conversations_family_account_index',
        'care_bookings' => 'care_bookings_family_account_index',
        'family_caregiver_favorites' => 'family_favorites_family_account_index',
        'ai_request_sessions' => 'ai_request_sessions_family_account_index',
        'care_booking_payments' => 'care_booking_payments_family_account_index',
        'family_recipient_profiles' => 'family_recipient_profiles_family_account_index',
        'family_household_profiles' => 'family_household_profiles_family_account_index',
        'care_relationships' => 'care_relationships_family_account_index',
        'care_plans' => 'care_plans_family_account_index',
        'care_booking_time_corrections' => 'time_corrections_family_account_index',
        'continuous_coverage_plans' => 'coverage_plans_family_account_index',
        'completed_extra_visit_requests' => 'extra_visit_requests_family_account_index',
        'support_tickets' => 'support_tickets_family_account_index',
    ];

    public function up(): void
    {
        foreach ($this->familyOwnedTables as $tableName => $indexName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->foreignId('family_account_id')
                    ->nullable()
                    ->constrained('family_accounts')
                    ->restrictOnDelete();
                $table->index(['family_account_id', 'created_at'], $indexName);
            });
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('family_visibility', 20)->nullable()->after('family_account_id');
        });

        Schema::table('care_requests', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('care_request_invitations', function (Blueprint $table) {
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('care_bookings', function (Blueprint $table) {
            $table->foreignId('family_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('care_booking_payments', function (Blueprint $table) {
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: removing this boundary would orphan shared-care history.
    }
};
