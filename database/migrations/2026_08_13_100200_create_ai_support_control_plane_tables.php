<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_support_control_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('control_key', 120);
            $table->unsignedInteger('version');
            $table->boolean('enabled');
            $table->json('configuration')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamp('effective_at');
            $table->timestamp('replaced_at')->nullable();
            $table->timestamp('retain_until')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['control_key', 'version'], 'ascv_key_version_unique');
            $table->index(['control_key', 'replaced_at'], 'ascv_key_current_idx');
            $table->index('retain_until');
        });

        Schema::create('ai_support_pilot_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('request_key')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bundle_key', 120);
            $table->json('capability_ids');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('grant_reason', 500);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamp('retain_until')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'starts_at', 'expires_at'], 'aspg_user_window_idx');
            $table->index(['revoked_at', 'expires_at'], 'aspg_lifecycle_idx');
            $table->index('retain_until');
        });

        Schema::create('ai_support_admin_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_family', 64);
            $table->string('action', 120);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 120)->nullable();
            $table->string('result', 32);
            $table->string('reason_code', 120)->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->string('policy_version', 120);
            $table->timestamp('retain_until');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_family', 'occurred_at'], 'asae_family_occurred_idx');
            $table->index(['target_user_id', 'occurred_at'], 'asae_target_occurred_idx');
            $table->index(['subject_type', 'subject_id'], 'asae_subject_idx');
            $table->index('retain_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_support_admin_audit_events');
        Schema::dropIfExists('ai_support_pilot_grants');
        Schema::dropIfExists('ai_support_control_versions');
    }
};
