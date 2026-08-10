<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_recipient_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_account_id')->constrained('family_accounts')->restrictOnDelete();
            $table->foreignId('legacy_family_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('legacy_family_recipient_profile_id')->nullable()->unique('crp_legacy_profile_unique')->constrained('family_recipient_profiles', indexName: 'crp_legacy_profile_fk')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->boolean('recipient_is_requester')->default(false);
            $table->string('full_name', 120)->nullable();
            $table->string('preferred_name', 80)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('age_range', 24)->nullable();
            $table->string('pronouns', 40)->nullable();
            $table->string('relationship_to_family', 120)->nullable();
            $table->text('about_them')->nullable();
            $table->text('interests_and_comforts')->nullable();
            $table->text('good_visit_notes')->nullable();
            $table->json('communication_preferences')->nullable();
            $table->text('communication_notes')->nullable();
            $table->text('everyday_health_context')->nullable();
            $table->json('support_areas')->nullable();
            $table->json('support_details')->nullable();
            $table->string('mobility_level', 40)->nullable();
            $table->text('mobility_notes')->nullable();
            $table->text('routine_notes')->nullable();
            $table->text('food_and_drink_notes')->nullable();
            $table->text('personal_care_preferences')->nullable();
            $table->text('sleep_overnight_notes')->nullable();
            $table->json('comfort_needs')->nullable();
            $table->text('distress_triggers')->nullable();
            $table->text('calming_approaches')->nullable();
            $table->json('safety_items')->nullable();
            $table->text('safety_notes')->nullable();
            $table->json('caregiver_quality_preferences')->nullable();
            $table->text('caregiver_do_notes')->nullable();
            $table->text('caregiver_avoid_notes')->nullable();
            $table->boolean('include_additional_contact')->default(false);
            $table->string('additional_contact_name', 120)->nullable();
            $table->string('additional_contact_relationship', 120)->nullable();
            $table->string('additional_contact_phone', 30)->nullable();
            $table->string('additional_contact_email')->nullable();
            $table->text('assigned_escalation_notes')->nullable();
            $table->timestamp('sharing_acknowledged_at')->nullable();
            $table->foreignId('sharing_acknowledged_by_user_id')->nullable()->constrained('users', indexName: 'crp_ack_user_fk')->nullOnDelete();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['family_account_id', 'status'], 'crp_account_status_index');
            $table->index(['family_account_id', 'preferred_name'], 'crp_account_name_index');
        });

        Schema::create('care_recipient_profile_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_recipient_profile_id')->constrained('care_recipient_profiles', indexName: 'crpv_profile_fk')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('candidate_snapshot');
            $table->json('assigned_snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['care_recipient_profile_id', 'version_number'], 'crpv_profile_version_unique');
        });

        Schema::table('care_recipient_profiles', function (Blueprint $table) {
            $table->foreignId('latest_ready_version_id')->nullable()->constrained('care_recipient_profile_versions', indexName: 'crp_latest_version_fk')->nullOnDelete();
        });

        Schema::table('family_accounts', function (Blueprint $table) {
            $table->foreignId('default_care_recipient_profile_id')->nullable()->constrained('care_recipient_profiles', indexName: 'fa_default_crp_fk')->nullOnDelete();
        });

        Schema::table('care_request_recipients', function (Blueprint $table) {
            $table->foreignId('care_recipient_profile_id')->nullable()->constrained('care_recipient_profiles', indexName: 'crr_profile_fk')->nullOnDelete();
            $table->foreignId('care_recipient_profile_version_id')->nullable()->constrained('care_recipient_profile_versions', indexName: 'crr_profile_version_fk')->nullOnDelete();
        });

        Schema::table('care_relationships', function (Blueprint $table) {
            $table->foreignId('care_recipient_profile_id')->nullable()->constrained('care_recipient_profiles', indexName: 'care_rel_profile_fk')->nullOnDelete();
            $table->index(
                ['family_account_id', 'caregiver_user_id', 'care_recipient_profile_id'],
                'care_relationship_recipient_index'
            );
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->foreignId('care_recipient_profile_id')->nullable()->constrained('care_recipient_profiles', indexName: 'care_plan_profile_fk')->nullOnDelete();
            $table->foreignId('care_recipient_profile_version_id')->nullable()->constrained('care_recipient_profile_versions', indexName: 'care_plan_profile_version_fk')->nullOnDelete();
        });

        Schema::table('continuous_coverage_plans', function (Blueprint $table) {
            $table->foreignId('care_recipient_profile_id')->nullable()->constrained('care_recipient_profiles', indexName: 'ccp_profile_fk')->nullOnDelete();
            $table->foreignId('care_recipient_profile_version_id')->nullable()->constrained('care_recipient_profile_versions', indexName: 'ccp_profile_version_fk')->nullOnDelete();
        });

        Schema::create('care_recipient_profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_recipient_profile_version_id')->constrained('care_recipient_profile_versions', indexName: 'crpvw_version_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['care_recipient_profile_version_id', 'user_id'], 'crpvw_version_user_unique');
        });
    }

    public function down(): void
    {
        // Additive care history and disclosure records are intentionally retained.
    }
};
