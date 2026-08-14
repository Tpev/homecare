<?php

namespace App\Console\Commands;

use App\Models\AiSupportActionPreview;
use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportControlVersion;
use App\Models\AiSupportInteractionEvent;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPilotGrant;
use App\Models\AiSupportRequestDraft;
use App\Models\DataRetentionHold;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\SupportTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyAiSupportRetention extends Command
{
    protected $signature = 'ai-support:apply-retention {--execute : Apply due content deletion and evidence expiry}';

    protected $description = 'Dry-run or apply the approved AI support retention policies.';

    public function handle(): int
    {
        $counts = $this->dueCounts();
        $this->table(['Data class', 'Due records'], collect($counts)->map(fn (int $count, string $class): array => [$class, $count])->values()->all());

        if (! $this->option('execute')) {
            $this->warn('Dry run only. No records or content were changed.');

            return self::SUCCESS;
        }

        $runReference = (string) Str::uuid();
        $this->retireOrphanedGrants();
        $this->pruneTranscripts($runReference);
        $this->prunePreviews($runReference);
        $this->pruneRequestDrafts($runReference);
        $this->pruneInteractionEvents($runReference);
        $this->pruneActionEvidence($runReference);
        $this->pruneKnowledgeContent($runReference);
        $this->pruneKnowledgeTombstones($runReference);
        $this->pruneGrantAndControlHistory($runReference);
        $this->pruneDeletionEvidence();
        $this->info('Due AI support retention work was applied. Active legal/security holds were skipped.');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function dueCounts(): array
    {
        return [
            'support_transcripts' => SupportTicket::query()->whereNull('transcript_deleted_at')->where('transcript_delete_after', '<=', now())->count(),
            'action_preview_records' => AiSupportActionPreview::query()->where(fn ($query) => $query
                ->where('expires_at', '<=', now())
                ->orWhereNotNull('invalidated_at')
                ->orWhereNotNull('content_deleted_at'))->count(),
            'private_request_drafts' => AiSupportRequestDraft::query()
                ->whereNotNull('payload')->where('expires_at', '<=', now())->count(),
            'interaction_events' => AiSupportInteractionEvent::query()->where('delete_after', '<=', now())->count(),
            'confirmed_action_evidence' => AiSupportConfirmedActionEvidence::query()->where('retain_until', '<=', now())->count(),
            'knowledge_full_content' => KnowledgeBaseVersion::query()->whereNull('content_deleted_at')->where('full_content_retain_until', '<=', now())->count(),
            'knowledge_tombstones' => KnowledgeBaseVersion::query()->whereNotNull('content_deleted_at')->where('tombstone_retain_until', '<=', now())->count(),
            'orphaned_pilot_grants' => AiSupportPilotGrant::query()->whereNull('user_id')->whereNull('revoked_at')->count(),
            'pilot_grants' => AiSupportPilotGrant::query()->where('retain_until', '<=', now())->count(),
            'control_versions' => AiSupportControlVersion::query()->where('retain_until', '<=', now())->count(),
            'admin_audit_events' => AiSupportAdminAuditEvent::query()->where('retain_until', '<=', now())->count(),
        ];
    }

    private function pruneTranscripts(string $runReference): void
    {
        SupportTicket::query()
            ->whereNull('transcript_deleted_at')
            ->where('transcript_delete_after', '<=', now())
            ->orderBy('id')
            ->each(function (SupportTicket $ticket) use ($runReference): void {
                if ($this->held(SupportTicket::class, (string) $ticket->id)) {
                    return;
                }

                DB::transaction(function () use ($ticket, $runReference): void {
                    $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                    if ($locked->transcript_deleted_at
                        || ! $locked->transcript_delete_after
                        || $locked->transcript_delete_after->isAfter(now())) {
                        return;
                    }
                    $messageCount = $locked->messages()->count();
                    $locked->messages()->delete();
                    $locked->forceFill([
                        'subject' => 'Support conversation content deleted',
                        'description' => 'Content deleted under the approved support retention policy.',
                        'admin_note' => null,
                        'origin_path' => null,
                        'initial_client_message_id' => null,
                        'responder_mode' => SupportTicket::RESPONDER_MODE_HUMAN_ONLY,
                        'status' => SupportTicket::STATUS_CLOSED,
                        'transcript_deleted_at' => now(),
                    ])->saveQuietly();
                    $this->evidence('support_transcript_content', $messageCount + 1, $runReference);
                }, 3);
            });
    }

    private function prunePreviews(string $runReference): void
    {
        $ids = AiSupportActionPreview::query()
            ->where(fn ($query) => $query
                ->where('expires_at', '<=', now())
                ->orWhereNotNull('invalidated_at')
                ->orWhereNotNull('content_deleted_at'))
            ->get(['id', 'support_ticket_id'])
            ->filter(fn (AiSupportActionPreview $preview): bool => ! $this->held(AiSupportActionPreview::class, $preview->id)
                && ! ($preview->support_ticket_id && $this->held(SupportTicket::class, (string) $preview->support_ticket_id)))
            ->pluck('id');
        $this->deleteWithEvidence(AiSupportActionPreview::class, $ids->all(), 'action_preview_records', $runReference);
    }

    private function pruneRequestDrafts(string $runReference): void
    {
        AiSupportRequestDraft::query()
            ->whereNotNull('payload')
            ->where('expires_at', '<=', now())
            ->orderBy('created_at')
            ->each(function (AiSupportRequestDraft $draft) use ($runReference): void {
                if ($this->held(AiSupportRequestDraft::class, $draft->id)
                    || $this->held(SupportTicket::class, (string) $draft->support_ticket_id)) {
                    return;
                }

                DB::transaction(function () use ($draft, $runReference): void {
                    $locked = AiSupportRequestDraft::query()->lockForUpdate()->findOrFail($draft->id);
                    if (! $locked->payload || $locked->expires_at->isFuture()) {
                        return;
                    }
                    $locked->forceFill([
                        'payload' => null,
                        'material_hash' => null,
                        'state' => $locked->published_at
                            ? AiSupportRequestDraft::STATE_PUBLISHED
                            : ($locked->discarded_at ? AiSupportRequestDraft::STATE_DISCARDED : AiSupportRequestDraft::STATE_EXPIRED),
                    ])->save();
                    AiSupportMessageAction::query()
                        ->where('support_ticket_id', $locked->support_ticket_id)
                        ->where('actor_user_id', $locked->actor_user_id)
                        ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
                        ->whereNull('consumed_at')
                        ->update([
                            'payload' => null,
                            'invalidated_at' => now(),
                            'invalidation_reason' => 'draft_expired',
                        ]);
                    $this->evidence('private_request_draft_content', 1, $runReference);
                }, 3);
            });
    }

    private function pruneInteractionEvents(string $runReference): void
    {
        $ids = AiSupportInteractionEvent::query()->where('delete_after', '<=', now())->get(['id', 'support_ticket_id'])
            ->filter(fn (AiSupportInteractionEvent $event): bool => ! $this->held(AiSupportInteractionEvent::class, $event->id)
                && ! ($event->support_ticket_id && $this->held(SupportTicket::class, (string) $event->support_ticket_id)))
            ->pluck('id');
        $this->deleteWithEvidence(AiSupportInteractionEvent::class, $ids->all(), 'compact_interaction_events', $runReference);
    }

    private function pruneActionEvidence(string $runReference): void
    {
        $ids = AiSupportConfirmedActionEvidence::query()
            ->where('retain_until', '<=', now())
            ->get(['id', 'support_ticket_id'])
            ->filter(fn (AiSupportConfirmedActionEvidence $evidence): bool => ! $this->held(AiSupportConfirmedActionEvidence::class, $evidence->id)
                && ! ($evidence->support_ticket_id && $this->held(SupportTicket::class, (string) $evidence->support_ticket_id)))
            ->pluck('id');
        $this->deleteWithEvidence(AiSupportConfirmedActionEvidence::class, $ids->all(), 'confirmed_action_evidence', $runReference);
    }

    private function pruneKnowledgeContent(string $runReference): void
    {
        KnowledgeBaseVersion::query()
            ->whereNull('content_deleted_at')
            ->where('full_content_retain_until', '<=', now())
            ->orderBy('id')
            ->each(function (KnowledgeBaseVersion $version) use ($runReference): void {
                if ($this->held(KnowledgeBaseVersion::class, (string) $version->id)
                    || $version->dependencies()->where(fn ($dependency) => $dependency->whereNull('protect_until')->orWhere('protect_until', '>', now()))->exists()) {
                    return;
                }
                DB::transaction(function () use ($version, $runReference): void {
                    $locked = KnowledgeBaseVersion::query()->lockForUpdate()->findOrFail($version->id);
                    $locked->sources()->delete();
                    $locked->forceFill([
                        'title' => 'Knowledge content deleted',
                        'answer_body' => '',
                        'roles' => [],
                        'membership_states' => [],
                        'route_target_ids' => [],
                        'capability_ids' => [],
                        'facts_may_state' => [],
                        'facts_must_not_infer' => [],
                        'next_actions' => [],
                        'escalation_conditions' => [],
                        'retrieval_examples_match' => [],
                        'retrieval_examples_no_match' => [],
                        'evaluation_ids' => [],
                        'validation_results' => null,
                        'change_note' => 'Content deleted under retention policy.',
                        'content_deleted_at' => now(),
                        'tombstone_retain_until' => now()->addMonths((int) config('ai_support.kb_tombstone_months', 24)),
                    ])->save();
                    $entryHasFullContent = KnowledgeBaseVersion::query()
                        ->where('knowledge_base_entry_id', $locked->knowledge_base_entry_id)
                        ->whereNull('content_deleted_at')
                        ->exists();
                    if (! $entryHasFullContent) {
                        KnowledgeBaseEntry::query()->whereKey($locked->knowledge_base_entry_id)->update([
                            'deletion_reason' => null,
                        ]);
                    }
                    $this->evidence('released_knowledge_full_content', 1, $runReference);
                }, 3);
            });
    }

    private function pruneKnowledgeTombstones(string $runReference): void
    {
        KnowledgeBaseVersion::query()
            ->whereNotNull('content_deleted_at')
            ->where('tombstone_retain_until', '<=', now())
            ->orderBy('id')
            ->each(function (KnowledgeBaseVersion $version) use ($runReference): void {
                if ($this->held(KnowledgeBaseVersion::class, (string) $version->id) || $version->dependencies()->exists()) {
                    return;
                }
                DB::transaction(function () use ($version, $runReference): void {
                    KnowledgeBaseEntry::query()->where('working_version_id', $version->id)->update(['working_version_id' => null]);
                    KnowledgeBaseEntry::query()->where('published_version_id', $version->id)->update(['published_version_id' => null]);
                    $version->delete();
                    $this->evidence('released_knowledge_tombstone', 1, $runReference);
                }, 3);
            });
    }

    private function pruneGrantAndControlHistory(string $runReference): void
    {
        $grantIds = AiSupportPilotGrant::query()->where('retain_until', '<=', now())->pluck('id')
            ->filter(fn (string $id): bool => ! $this->held(AiSupportPilotGrant::class, $id)
                && ! AiSupportInteractionEvent::query()->where('pilot_grant_id', $id)->exists());
        $this->deleteWithEvidence(AiSupportPilotGrant::class, $grantIds->all(), 'pilot_grant_history', $runReference);

        $controlIds = AiSupportControlVersion::query()->where('retain_until', '<=', now())->pluck('id')
            ->filter(fn ($id): bool => ! $this->held(AiSupportControlVersion::class, (string) $id));
        $this->deleteWithEvidence(AiSupportControlVersion::class, $controlIds->all(), 'control_version_history', $runReference);

        $auditIds = AiSupportAdminAuditEvent::query()->where('retain_until', '<=', now())->pluck('id')
            ->filter(fn (string $id): bool => ! $this->held(AiSupportAdminAuditEvent::class, $id));
        $this->deleteWithEvidence(AiSupportAdminAuditEvent::class, $auditIds->all(), 'admin_control_audit', $runReference);
    }

    private function retireOrphanedGrants(): void
    {
        AiSupportPilotGrant::query()
            ->whereNull('user_id')
            ->whereNull('revoked_at')
            ->orderBy('created_at')
            ->each(function (AiSupportPilotGrant $grant): void {
                DB::transaction(function () use ($grant): void {
                    $locked = AiSupportPilotGrant::query()->lockForUpdate()->findOrFail($grant->id);
                    if ($locked->user_id !== null || $locked->revoked_at !== null) {
                        return;
                    }

                    $now = now();
                    $locked->forceFill([
                        'revoked_at' => $now,
                        'revocation_reason' => 'target_user_deleted',
                        'retain_until' => $now->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                    ])->save();
                    AiSupportAdminAuditEvent::query()->create([
                        'id' => (string) Str::uuid(),
                        'event_family' => 'pilot_access',
                        'action' => 'pilot_grant_retired_after_user_deletion',
                        'subject_type' => AiSupportPilotGrant::class,
                        'subject_id' => $locked->id,
                        'result' => 'succeeded',
                        'reason_code' => 'target_user_deleted',
                        'policy_version' => (string) config('ai_support.policy_version'),
                        'retain_until' => $now->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                        'occurred_at' => $now,
                        'created_at' => $now,
                    ]);
                }, 3);
            });
    }

    private function pruneDeletionEvidence(): void
    {
        DB::table('data_deletion_evidence')
            ->where('result', 'passed')
            ->where('retain_until', '<=', now())
            ->delete();
    }

    /** @param list<int|string> $ids */
    private function deleteWithEvidence(string $modelClass, array $ids, string $dataClass, string $runReference): void
    {
        if ($ids === []) {
            return;
        }
        DB::transaction(function () use ($modelClass, $ids, $dataClass, $runReference): void {
            $count = $modelClass::query()->whereKey($ids)->delete();
            $this->evidence($dataClass, $count, $runReference);
        }, 3);
    }

    private function held(string $scopeType, string $scopeId): bool
    {
        return DataRetentionHold::query()->active()->where('scope_type', $scopeType)->where('scope_id', $scopeId)->exists();
    }

    private function evidence(string $dataClass, int $count, string $runReference): void
    {
        if ($count < 1) {
            return;
        }
        DB::table('data_deletion_evidence')->insert([
            'id' => (string) Str::uuid(),
            'data_class' => $dataClass,
            'retention_policy_version' => (string) config('ai_support.retention_policy_version'),
            'environment' => app()->environment(),
            'run_reference' => $runReference,
            'record_count' => $count,
            'result' => 'passed',
            'completed_at' => now(),
            'retain_until' => now()->addMonths((int) config('ai_support.deletion_evidence_months', 36)),
            'created_at' => now(),
        ]);
    }
}
