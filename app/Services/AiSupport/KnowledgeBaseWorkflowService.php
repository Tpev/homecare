<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportAdminAuditEvent;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseSource;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KnowledgeBaseWorkflowService
{
    private const CONTENT_FIELDS = [
        'type', 'title', 'answer_body', 'sensitivity', 'product_area', 'locale', 'roles',
        'membership_states', 'route_target_ids', 'capability_ids', 'facts_may_state',
        'facts_must_not_infer', 'next_actions', 'escalation_conditions',
        'retrieval_examples_match', 'retrieval_examples_no_match', 'evaluation_ids',
        'change_note', 'review_by', 'expires_on',
    ];

    public function __construct(private readonly KnowledgeBaseValidationService $validation) {}

    /** @param array<string, mixed> $payload @param list<array<string, mixed>> $sources */
    public function createDraft(User $actor, array $payload, array $sources): KnowledgeBaseEntry
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $payload, $sources): KnowledgeBaseEntry {
            $now = now();
            $entry = KnowledgeBaseEntry::query()->create([
                'stable_id' => 'KB-'.Str::upper((string) Str::ulid()),
                'ever_released' => false,
                'created_by_user_id' => $actor->id,
            ]);
            $version = KnowledgeBaseVersion::query()->create([
                ...$this->contentPayload($payload),
                'knowledge_base_entry_id' => $entry->id,
                'version_number' => 1,
                'edit_version' => 1,
                'status' => KnowledgeBaseVersion::STATUS_DRAFT,
                'authored_by_user_id' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->replaceSources($version, $sources);
            $entry->forceFill(['working_version_id' => $version->id])->save();
            $this->audit($actor, 'knowledge_entry_created', $entry, $version, 'draft_created', $version->change_note);

            return $entry->fresh(['workingVersion.sources', 'versions']);
        }, 3);
    }

    /** @param array<string, mixed> $payload @param list<array<string, mixed>> $sources */
    public function updateWorkingVersion(
        User $actor,
        KnowledgeBaseVersion $version,
        int $expectedEditVersion,
        array $payload,
        array $sources,
    ): KnowledgeBaseVersion {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $version, $expectedEditVersion, $payload, $sources): KnowledgeBaseVersion {
            $locked = KnowledgeBaseVersion::query()->lockForUpdate()->findOrFail($version->id);
            if (! in_array($locked->status, [
                KnowledgeBaseVersion::STATUS_DRAFT,
                KnowledgeBaseVersion::STATUS_IN_REVIEW,
                KnowledgeBaseVersion::STATUS_APPROVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'version' => 'Released or retired content is immutable. Create a new draft version.',
                ]);
            }

            if ($locked->edit_version !== $expectedEditVersion) {
                throw ValidationException::withMessages([
                    'version' => 'This draft changed in another session. Reload before saving.',
                ]);
            }

            $locked->forceFill([
                ...$this->contentPayload($payload),
                'edit_version' => $locked->edit_version + 1,
                'status' => KnowledgeBaseVersion::STATUS_DRAFT,
                'authored_by_user_id' => $actor->id,
                'validation_results' => null,
                'validated_at' => null,
                'submitted_for_review_at' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'published_by_user_id' => null,
            ])->save();
            $this->replaceSources($locked, $sources);
            $locked->entry()->update(['working_version_id' => $locked->id]);
            $this->audit($actor, 'knowledge_draft_saved', $locked->entry, $locked, 'draft_updated', $locked->change_note);

            return $locked->fresh('sources');
        }, 3);
    }

    /** @return array{passed: bool, errors: array<string, list<string>>, checked_at: string, contract_version: string} */
    public function validateAndStore(User $actor, KnowledgeBaseVersion $version): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $version): array {
            $locked = KnowledgeBaseVersion::query()->with('sources')->lockForUpdate()->findOrFail($version->id);
            $result = $this->validation->validate($locked);
            $locked->forceFill([
                'validation_results' => $result,
                'validated_at' => now(),
            ])->save();
            $this->audit(
                $actor,
                'knowledge_validation_run',
                $locked->entry,
                $locked,
                $result['passed'] ? 'validation_passed' : 'validation_failed',
                null,
                ['error_fields' => array_keys($result['errors'])],
            );

            return $result;
        }, 3);
    }

    public function submitForReview(User $actor, KnowledgeBaseVersion $version): KnowledgeBaseVersion
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $version): KnowledgeBaseVersion {
            $locked = KnowledgeBaseVersion::query()->with('sources')->lockForUpdate()->findOrFail($version->id);
            $this->ensureState($locked, [KnowledgeBaseVersion::STATUS_DRAFT]);
            $result = $this->validation->validate($locked);
            $this->requireValidationPass($result);
            $now = now();
            $locked->forceFill([
                'status' => KnowledgeBaseVersion::STATUS_IN_REVIEW,
                'validation_results' => $result,
                'validated_at' => $now,
                'submitted_for_review_at' => $now,
            ])->save();
            $this->audit($actor, 'knowledge_submitted_for_review', $locked->entry, $locked, 'in_review', $locked->change_note);

            return $locked->fresh('sources');
        }, 3);
    }

    public function approve(User $actor, KnowledgeBaseVersion $version): KnowledgeBaseVersion
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $version): KnowledgeBaseVersion {
            $locked = KnowledgeBaseVersion::query()->with('sources')->lockForUpdate()->findOrFail($version->id);
            $this->ensureState($locked, [KnowledgeBaseVersion::STATUS_IN_REVIEW]);
            $result = $this->validation->validate($locked);
            $this->requireValidationPass($result);
            $now = now();
            $locked->forceFill([
                'status' => KnowledgeBaseVersion::STATUS_APPROVED,
                'validation_results' => $result,
                'validated_at' => $now,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => $now,
                'approved_by_user_id' => $actor->id,
                'approved_at' => $now,
            ])->save();
            $this->audit($actor, 'knowledge_approved', $locked->entry, $locked, 'approved', $locked->change_note);

            return $locked->fresh('sources');
        }, 3);
    }

    public function publish(User $actor, KnowledgeBaseVersion $version, string $reason): KnowledgeBaseVersion
    {
        $this->authorize($actor);
        $reason = $this->validatedReason($reason);

        return DB::transaction(function () use ($actor, $version, $reason): KnowledgeBaseVersion {
            $entry = KnowledgeBaseEntry::query()->lockForUpdate()->findOrFail($version->knowledge_base_entry_id);
            $locked = KnowledgeBaseVersion::query()->with('sources')->lockForUpdate()->findOrFail($version->id);
            $this->ensureState($locked, [KnowledgeBaseVersion::STATUS_APPROVED]);
            $result = $this->validation->validate($locked);
            $this->requireValidationPass($result);
            $now = now();

            if ($entry->published_version_id && (int) $entry->published_version_id !== (int) $locked->id) {
                $previous = KnowledgeBaseVersion::query()->lockForUpdate()->findOrFail($entry->published_version_id);
                $previous->forceFill([
                    'status' => KnowledgeBaseVersion::STATUS_SUPERSEDED,
                    'superseded_at' => $now,
                    'retired_at' => $now,
                    'replaced_by_version_id' => $locked->id,
                    'full_content_retain_until' => $now->copy()->addMonths((int) config('ai_support.kb_full_history_months', 36)),
                ])->save();
            }

            $locked->forceFill([
                'status' => KnowledgeBaseVersion::STATUS_PUBLISHED,
                'validation_results' => $result,
                'validated_at' => $now,
                'published_by_user_id' => $actor->id,
                'published_at' => $locked->published_at ?: $now,
                'paused_at' => null,
                'retired_at' => null,
                'full_content_retain_until' => null,
            ])->save();
            $entry->forceFill([
                'working_version_id' => $locked->id,
                'published_version_id' => $locked->id,
                'ever_released' => true,
                'deleted_at' => null,
                'deleted_by_user_id' => null,
                'deletion_reason' => null,
            ])->save();
            $this->audit($actor, 'knowledge_published', $entry, $locked, 'published_now', $reason);

            return $locked->fresh('sources');
        }, 3);
    }

    public function pause(User $actor, KnowledgeBaseVersion $version, string $reason): KnowledgeBaseVersion
    {
        return $this->changeReleasedState($actor, $version, [KnowledgeBaseVersion::STATUS_PUBLISHED], KnowledgeBaseVersion::STATUS_PAUSED, 'knowledge_paused', $reason);
    }

    public function resume(User $actor, KnowledgeBaseVersion $version, string $reason): KnowledgeBaseVersion
    {
        return $this->changeReleasedState($actor, $version, [KnowledgeBaseVersion::STATUS_PAUSED], KnowledgeBaseVersion::STATUS_PUBLISHED, 'knowledge_resumed', $reason);
    }

    public function withdraw(User $actor, KnowledgeBaseVersion $version, string $reason): KnowledgeBaseVersion
    {
        $this->authorize($actor);
        $reason = $this->validatedReason($reason);

        return DB::transaction(function () use ($actor, $version, $reason): KnowledgeBaseVersion {
            $entry = KnowledgeBaseEntry::query()->lockForUpdate()->findOrFail($version->knowledge_base_entry_id);
            $locked = KnowledgeBaseVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->ensureState($locked, [KnowledgeBaseVersion::STATUS_PUBLISHED, KnowledgeBaseVersion::STATUS_PAUSED]);
            $now = now();
            $locked->forceFill([
                'status' => KnowledgeBaseVersion::STATUS_WITHDRAWN,
                'withdrawn_at' => $now,
                'retired_at' => $now,
                'full_content_retain_until' => $now->copy()->addMonths((int) config('ai_support.kb_full_history_months', 36)),
            ])->save();
            if ((int) $entry->published_version_id === (int) $locked->id) {
                $entry->forceFill(['published_version_id' => null])->save();
            }
            $this->audit($actor, 'knowledge_withdrawn', $entry, $locked, 'withdrawn', $reason);

            return $locked->fresh('sources');
        }, 3);
    }

    public function createDraftFrom(User $actor, KnowledgeBaseVersion $sourceVersion, string $changeNote): KnowledgeBaseVersion
    {
        $this->authorize($actor);
        $changeNote = $this->validatedReason($changeNote);

        return DB::transaction(function () use ($actor, $sourceVersion, $changeNote): KnowledgeBaseVersion {
            $source = KnowledgeBaseVersion::query()->with(['sources', 'entry'])->lockForUpdate()->findOrFail($sourceVersion->id);
            if ($source->entry->deleted_at) {
                throw ValidationException::withMessages(['version' => 'A deleted stable entry cannot be revived. Create a new entry.']);
            }
            $nextNumber = ((int) KnowledgeBaseVersion::query()
                ->where('knowledge_base_entry_id', $source->knowledge_base_entry_id)
                ->max('version_number')) + 1;
            $payload = collect(self::CONTENT_FIELDS)->mapWithKeys(
                fn (string $field): array => [$field => $source->getAttribute($field)]
            )->all();
            $payload['change_note'] = $changeNote;
            $now = now();
            $draft = KnowledgeBaseVersion::query()->create([
                ...$payload,
                'knowledge_base_entry_id' => $source->knowledge_base_entry_id,
                'version_number' => $nextNumber,
                'edit_version' => 1,
                'status' => KnowledgeBaseVersion::STATUS_DRAFT,
                'authored_by_user_id' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->replaceSources($draft, $source->sources->map(fn (KnowledgeBaseSource $item): array => [
                'source_id' => $item->source_id,
                'title' => $item->title,
                'url' => $item->url,
                'section_anchor' => $item->section_anchor,
                'fact_supported' => $item->fact_supported,
            ])->all());
            $source->entry->forceFill(['working_version_id' => $draft->id])->save();
            $this->audit($actor, 'knowledge_new_version_created', $source->entry, $draft, 'draft_from_released', $changeNote, ['source_version' => $source->version_number]);

            return $draft->fresh('sources');
        }, 3);
    }

    public function delete(User $actor, KnowledgeBaseVersion $version, string $reason): void
    {
        $this->authorize($actor);
        $reason = $this->validatedReason($reason);

        DB::transaction(function () use ($actor, $version, $reason): void {
            $entry = KnowledgeBaseEntry::query()->lockForUpdate()->findOrFail($version->knowledge_base_entry_id);
            $locked = KnowledgeBaseVersion::query()->with('dependencies')->lockForUpdate()->findOrFail($version->id);

            if (! $locked->wasReleased()) {
                if ($locked->dependencies->isNotEmpty()) {
                    throw ValidationException::withMessages(['delete' => 'This draft has protected dependencies and cannot be permanently deleted.']);
                }
                $versionNumber = $locked->version_number;
                $locked->delete();
                $replacementWorkingId = KnowledgeBaseVersion::query()
                    ->where('knowledge_base_entry_id', $entry->id)
                    ->latest('version_number')
                    ->value('id');
                $entry->forceFill(['working_version_id' => $replacementWorkingId])->save();
                if (! $replacementWorkingId) {
                    $entry->forceFill([
                        'deleted_at' => now(),
                        'deleted_by_user_id' => $actor->id,
                        'deletion_reason' => $reason,
                    ])->save();
                }
                $this->audit($actor, 'knowledge_draft_deleted', $entry, null, 'hard_deleted', $reason, ['version_number' => $versionNumber]);

                return;
            }

            $now = now();
            $locked->forceFill([
                'status' => KnowledgeBaseVersion::STATUS_DELETED,
                'retired_at' => $now,
                'full_content_retain_until' => $now->copy()->addMonths((int) config('ai_support.kb_full_history_months', 36)),
            ])->save();
            $entry->forceFill([
                'published_version_id' => null,
                'working_version_id' => $locked->id,
                'deleted_at' => $now,
                'deleted_by_user_id' => $actor->id,
                'deletion_reason' => $reason,
            ])->save();
            $this->audit($actor, 'knowledge_released_entry_deleted', $entry, $locked, 'withdrawn_and_retained', $reason);
        }, 3);
    }

    private function changeReleasedState(
        User $actor,
        KnowledgeBaseVersion $version,
        array $fromStates,
        string $toState,
        string $action,
        string $reason,
    ): KnowledgeBaseVersion {
        $this->authorize($actor);
        $reason = $this->validatedReason($reason);

        return DB::transaction(function () use ($actor, $version, $fromStates, $toState, $action, $reason): KnowledgeBaseVersion {
            $locked = KnowledgeBaseVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->ensureState($locked, $fromStates);
            $locked->forceFill([
                'status' => $toState,
                'paused_at' => $toState === KnowledgeBaseVersion::STATUS_PAUSED ? now() : null,
            ])->save();
            $this->audit($actor, $action, $locked->entry, $locked, $toState, $reason);

            return $locked->fresh('sources');
        }, 3);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function contentPayload(array $payload): array
    {
        return collect(self::CONTENT_FIELDS)->mapWithKeys(function (string $field) use ($payload): array {
            $value = $payload[$field] ?? null;
            if (in_array($field, ['roles', 'membership_states', 'route_target_ids', 'capability_ids', 'facts_may_state', 'facts_must_not_infer', 'next_actions', 'escalation_conditions', 'retrieval_examples_match', 'retrieval_examples_no_match', 'evaluation_ids'], true)) {
                $value = array_values(array_unique(array_filter(array_map(
                    fn (mixed $item): string => trim((string) $item),
                    (array) $value,
                ))));
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            return [$field => $value];
        })->all();
    }

    /** @param list<array<string, mixed>> $sources */
    private function replaceSources(KnowledgeBaseVersion $version, array $sources): void
    {
        $version->sources()->delete();
        foreach (array_values($sources) as $position => $source) {
            if (collect($source)->filter(fn (mixed $value): bool => trim((string) $value) !== '')->isEmpty()) {
                continue;
            }
            $version->sources()->create([
                'position' => $position,
                'source_id' => trim((string) ($source['source_id'] ?? '')),
                'title' => trim((string) ($source['title'] ?? '')),
                'url' => $this->nullableString($source['url'] ?? null),
                'section_anchor' => $this->nullableString($source['section_anchor'] ?? null),
                'fact_supported' => trim((string) ($source['fact_supported'] ?? '')),
                'verified_at' => now(),
            ]);
        }
    }

    /** @param array{passed: bool, errors: array<string, list<string>>} $result */
    private function requireValidationPass(array $result): void
    {
        if (! $result['passed']) {
            throw ValidationException::withMessages([
                'validation' => 'Resolve the blocking validation fields: '.implode(', ', array_keys($result['errors'])).'.',
            ]);
        }
    }

    /** @param list<string> $allowed */
    private function ensureState(KnowledgeBaseVersion $version, array $allowed): void
    {
        if (! in_array($version->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'version' => 'This lifecycle action is not valid from '.$version->status.'.',
            ]);
        }
    }

    private function validatedReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['lifecycleReason' => 'Enter a concise reason between 5 and 500 characters.']);
        }

        return $reason;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->canManageKnowledgeBase()) {
            throw new AuthorizationException;
        }
    }

    /** @param array<string, mixed> $metadata */
    private function audit(
        User $actor,
        string $action,
        KnowledgeBaseEntry $entry,
        ?KnowledgeBaseVersion $version,
        string $reasonCode,
        ?string $reason,
        array $metadata = [],
    ): void {
        $now = now();
        AiSupportAdminAuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_family' => 'knowledge_base',
            'action' => $action,
            'actor_user_id' => $actor->id,
            'subject_type' => KnowledgeBaseEntry::class,
            'subject_id' => $entry->stable_id,
            'result' => 'succeeded',
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'metadata' => [
                'stable_id' => $entry->stable_id,
                'version_number' => $version?->version_number,
                'version_id' => $version?->id,
                ...$metadata,
            ],
            'policy_version' => 'kb-governance-v1',
            'retain_until' => $now->copy()->addMonths((int) config('ai_support.kb_full_history_months', 36)),
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
