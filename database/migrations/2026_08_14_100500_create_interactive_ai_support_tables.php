<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_support_request_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('family_account_id')->nullable();
            $table->string('request_type', 32)->nullable();
            $table->string('state', 40)->default('collecting');
            $table->longText('payload')->nullable();
            $table->string('material_hash', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('discarded_at')->nullable();
            $table->unsignedBigInteger('published_care_request_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->timestamps();

            $table->unique(['support_ticket_id', 'actor_user_id'], 'asrd_ticket_actor_unique');
            $table->index(['actor_user_id', 'expires_at'], 'asrd_actor_expiry_idx');
            $table->index(['family_account_id', 'state'], 'asrd_account_state_idx');
            $table->foreign('support_ticket_id', 'asrd_ticket_fk')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'asrd_actor_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('family_account_id', 'asrd_account_fk')->references('id')->on('family_accounts')->nullOnDelete();
            $table->foreign('published_care_request_id', 'asrd_request_fk')->references('id')->on('care_requests')->nullOnDelete();
        });

        Schema::create('ai_support_message_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('support_ticket_message_id');
            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action_type', 64);
            $table->longText('payload')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 120)->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at'], 'asma_ticket_created_idx');
            $table->index(['actor_user_id', 'action_type'], 'asma_actor_type_idx');
            $table->foreign('support_ticket_message_id', 'asma_message_fk')->references('id')->on('support_ticket_messages')->cascadeOnDelete();
            $table->foreign('support_ticket_id', 'asma_ticket_fk')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('actor_user_id', 'asma_actor_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('care_requests', function (Blueprint $table): void {
            $table->string('origin', 32)->default('manual')->after('is_system_generated')->index();
            $table->foreignId('ai_support_ticket_id')->nullable()->after('origin')->constrained('support_tickets')->nullOnDelete();
            $table->uuid('ai_support_action_evidence_id')->nullable()->after('ai_support_ticket_id');
            $table->index('ai_support_action_evidence_id', 'cr_ai_evidence_idx');
        });
    }

    public function down(): void
    {
        Schema::table('care_requests', function (Blueprint $table): void {
            $table->dropForeign(['ai_support_ticket_id']);
            $table->dropIndex('cr_ai_evidence_idx');
            $table->dropColumn(['origin', 'ai_support_ticket_id', 'ai_support_action_evidence_id']);
        });
        Schema::dropIfExists('ai_support_message_actions');
        Schema::dropIfExists('ai_support_request_drafts');
    }
};
