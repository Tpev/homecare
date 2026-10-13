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
            $table->timestamp('screening_legacy_at')->nullable();
            $table->boolean('screening_legacy_access')->default(false);
            $table->timestamp('screening_initial_approved_at')->nullable();
            $table->timestamp('screening_suspended_at')->nullable();
            $table->unsignedBigInteger('screening_suspension_case_id')->nullable();
            $table->timestamp('screening_next_due_at')->nullable()->index();
        });

        // A one-time snapshot, never inferred from a mutable creation date.
        // New profiles default to no legacy exemption. Existing access is unchanged.
        DB::table('caregiver_profiles')->update([
            'screening_legacy_at' => now(),
            'screening_legacy_access' => DB::raw("CASE WHEN status = 'active' THEN 1 ELSE 0 END"),
        ]);

        Schema::table('caregiver_identity_verifications', function (Blueprint $table) {
            $table->timestamp('provider_confirmed_at')->nullable();
            $table->json('provider_decision_payload')->nullable();
        });

        Schema::create('caregiver_screening_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('caregiver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('open_profile_id')->nullable()->unique();
            $table->foreignId('identity_verification_id')->nullable()->constrained('caregiver_identity_verifications', indexName: 'screening_identity_fk')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('status', 30)->default('awaiting_kyc');
            $table->timestamp('due_at')->index();
            $table->json('identity_snapshot')->nullable();
            $table->string('search_status', 20)->default('pending');
            $table->timestamp('searched_at')->nullable();
            $table->json('source_versions')->nullable();
            $table->text('search_error')->nullable();
            $table->string('outcome', 30)->nullable();
            $table->string('decision', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_until')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->index(['status', 'due_at']);
            $table->index(['caregiver_profile_id', 'completed_at'], 'screening_profile_history');
        });

        Schema::create('caregiver_screening_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screening_case_id')->constrained('caregiver_screening_cases', indexName: 'screening_candidate_case_fk')->restrictOnDelete();
            $table->string('source', 30);
            $table->string('source_record_id');
            $table->string('matched_name');
            $table->text('match_reason');
            $table->json('record_snapshot');
            $table->timestamps();
        });

        Schema::create('screening_booking_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screening_case_id')->constrained('caregiver_screening_cases')->restrictOnDelete();
            $table->string('subject_type', 30);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 20)->default('open');
            $table->text('reason');
            $table->string('resolution', 40)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['screening_case_id', 'subject_type', 'subject_id'], 'screening_booking_review_unique');
            $table->index(['status', 'created_at']);
        });

        Schema::create('screening_audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('screening_case_id')->nullable()->constrained('caregiver_screening_cases')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('exported_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_audit_events');
        Schema::dropIfExists('screening_booking_reviews');
        Schema::dropIfExists('caregiver_screening_candidates');
        Schema::dropIfExists('caregiver_screening_cases');
        Schema::table('caregiver_identity_verifications', fn (Blueprint $table) => $table->dropColumn(['provider_confirmed_at', 'provider_decision_payload']));
        Schema::table('caregiver_profiles', function (Blueprint $table) {
            $table->dropIndex(['screening_next_due_at']);
            $table->dropColumn(['screening_legacy_at', 'screening_legacy_access', 'screening_initial_approved_at', 'screening_suspended_at', 'screening_suspension_case_id', 'screening_next_due_at']);
        });
    }
};
