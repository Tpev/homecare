<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_support_readiness_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('evidence_key', 120);
            $table->unsignedInteger('version');
            $table->string('status', 32);
            $table->string('summary', 500);
            $table->string('source_reference', 500)->nullable();
            $table->json('safe_metadata')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('observed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('retain_until')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['evidence_key', 'version'], 'asre_key_version_unique');
            $table->index(['evidence_key', 'superseded_at'], 'asre_key_current_idx');
            $table->index(['status', 'observed_at'], 'asre_status_observed_idx');
        });

        Schema::create('ai_support_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reason_code', 120);
            $table->string('severity', 32)->default('critical');
            $table->string('status', 32)->default('open');
            $table->string('control_key', 120)->nullable();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary', 500);
            $table->json('safe_metadata')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_reason', 500)->nullable();
            $table->timestamp('retain_until')->index();
            $table->timestamps();

            $table->index(['status', 'severity', 'opened_at'], 'asi_status_severity_idx');
            $table->index(['reason_code', 'control_key'], 'asi_reason_control_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_support_incidents');
        Schema::dropIfExists('ai_support_readiness_evidence');
    }
};
