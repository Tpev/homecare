<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('responder_mode', 32)->default('human_only')->after('source')->index();
            $table->timestamp('transferred_to_human_at')->nullable()->after('claimed_at');
            $table->timestamp('returned_to_automation_at')->nullable()->after('transferred_to_human_at');
            $table->string('handoff_reason_code', 120)->nullable()->after('returned_to_automation_at');
            $table->timestamp('retention_started_at')->nullable()->after('resolved_at')->index();
            $table->timestamp('transcript_delete_after')->nullable()->after('retention_started_at')->index();
            $table->timestamp('transcript_deleted_at')->nullable()->after('transcript_delete_after');
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->string('responder_type', 32)->nullable()->after('kind')->index();
        });

        DB::table('support_ticket_messages')->update([
            'responder_type' => DB::raw("CASE WHEN sender_user_id IS NULL THEN 'system' ELSE 'human' END"),
        ]);

        $resolvedTickets = DB::table('support_tickets')
            ->whereIn('status', ['resolved', 'closed'])
            ->orderBy('id')
            ->get(['id', 'resolved_at', 'updated_at']);
        foreach ($resolvedTickets as $ticket) {
            $startedAt = $ticket->resolved_at ?: $ticket->updated_at;
            if (! $startedAt) {
                continue;
            }
            $start = \Illuminate\Support\Carbon::parse($startedAt);
            DB::table('support_tickets')->where('id', $ticket->id)->update([
                'retention_started_at' => $start,
                'transcript_delete_after' => $start->copy()->addMonths(12),
            ]);
        }

        Schema::create('data_retention_holds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope_type', 120);
            $table->string('scope_id', 120);
            $table->string('reason_category', 120);
            $table->string('authority_reference', 255);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('review_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['scope_type', 'scope_id', 'released_at'], 'drh_scope_active_idx');
            $table->index('review_at');
            $table->index('expires_at');
        });

        Schema::create('ai_support_interaction_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('support_ticket_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('pilot_grant_id')->nullable();
            $table->string('event_type', 120);
            $table->string('capability_id', 120)->nullable();
            $table->string('reason_code', 120)->nullable();
            $table->string('model_configuration_version', 120)->nullable();
            $table->string('prompt_schema_version', 120)->nullable();
            $table->json('knowledge_version_ids')->nullable();
            $table->string('navigation_target_id', 120)->nullable();
            $table->string('tool_id', 120)->nullable();
            $table->string('tool_version', 120)->nullable();
            $table->string('result_code', 120)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('cost_microunits')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->string('event_contract_version', 120);
            $table->timestamp('retention_started_at')->nullable();
            $table->timestamp('delete_after')->nullable()->index();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('pilot_grant_id')->references('id')->on('ai_support_pilot_grants')->nullOnDelete();
            $table->index(['support_ticket_id', 'occurred_at'], 'asie_ticket_occurred_idx');
            $table->index(['event_type', 'occurred_at'], 'asie_type_occurred_idx');
        });

        Schema::create('ai_support_action_previews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('capability_id', 120);
            $table->string('tool_id', 120);
            $table->string('tool_version', 120);
            $table->longText('preview_payload')->nullable();
            $table->string('material_hash', 64);
            $table->string('confirmation_reference_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 120)->nullable();
            $table->timestamp('content_deleted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_support_confirmed_action_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('preview_id')->nullable();
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('pilot_grant_id')->nullable();
            $table->string('capability_id', 120);
            $table->string('tool_id', 120);
            $table->string('tool_version', 120);
            $table->string('material_hash', 64);
            $table->string('confirmation_reference_hash', 64);
            $table->uuid('idempotency_key')->unique();
            $table->string('confirmation_action', 120);
            $table->string('policy_result', 120);
            $table->string('outcome_code', 120);
            $table->string('domain_reference_type', 120);
            $table->string('domain_reference_id', 120);
            $table->string('receipt_reference', 120);
            $table->timestamp('confirmed_at');
            $table->timestamp('committed_at');
            $table->timestamp('retain_until')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('pilot_grant_id')->references('id')->on('ai_support_pilot_grants')->nullOnDelete();
            $table->index(['capability_id', 'committed_at'], 'ascae_capability_committed_idx');
            $table->index(['support_ticket_id', 'committed_at'], 'ascae_ticket_committed_idx');
        });

        Schema::create('data_deletion_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('data_class', 120);
            $table->string('retention_policy_version', 120);
            $table->string('environment', 64);
            $table->string('run_reference', 120);
            $table->unsignedBigInteger('record_count');
            $table->string('result', 32);
            $table->string('exception_reference', 120)->nullable();
            $table->timestamp('completed_at');
            $table->timestamp('retain_until')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['data_class', 'completed_at'], 'dde_class_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_deletion_evidence');
        Schema::dropIfExists('ai_support_confirmed_action_evidence');
        Schema::dropIfExists('ai_support_action_previews');
        Schema::dropIfExists('ai_support_interaction_events');
        Schema::dropIfExists('data_retention_holds');
        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->dropColumn('responder_type');
        });
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'responder_mode', 'transferred_to_human_at', 'returned_to_automation_at',
                'handoff_reason_code', 'retention_started_at', 'transcript_delete_after',
                'transcript_deleted_at',
            ]);
        });
    }
};
