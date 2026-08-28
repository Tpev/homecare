<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportIncident;
use App\Models\AiSupportPilotGrant;
use App\Models\AiSupportReadinessEvidence;
use App\Models\AiSupportReleaseDecision;
use App\Models\KnowledgeBaseEntry;
use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportReadinessService
{
    public const SCOPE_INITIAL_PILOT = 'initial_pilot';

    public const SCOPE_EXPANSION = 'expansion';

    /** @return array<string,array{label:string,required:bool,guidance:string,deferable_for_initial_pilot:bool}> */
    public function definitions(): array
    {
        $definitions = [
            'provider_project_configuration' => [
                'label' => 'Configured provider credential',
                'required' => true,
                'guidance' => 'Record that the current server-only OpenAI API credential authenticates at the standard destination and uses the separate safety-identifier secret. Exact Admin API project identity is deferred for the initial pilot.',
            ],
            'provider_data_controls' => [
                'label' => 'Provider no-training and retention controls',
                'required' => true,
                'guidance' => 'Record no-training, store:false, and no-more-than-30-day abuse-monitoring evidence.',
            ],
            'provider_destination_contract' => [
                'label' => 'Provider destination and contract',
                'required' => true,
                'guidance' => 'Record the actual standard provider destination and applicable contractual reference.',
            ],
            'provider_deletion' => [
                'label' => 'Provider deletion behavior',
                'required' => true,
                'guidance' => 'Record application-state deletion behavior and the absence of hosted conversations, files, vector stores, and background mode.',
            ],
            'provider_zdr_request' => [
                'label' => 'Zero Data Retention request',
                'required' => false,
                'guidance' => 'Record whether ZDR was requested, approved, rejected, or remains pending. It is desirable but not a gate for this bounded non-medical pilot.',
            ],
            'downstream_extinction_restore' => [
                'label' => 'Downstream extinction and restore rehearsal',
                'required' => true,
                'guidance' => 'Record caches, indexes, replicas, exports, backups, and restore/re-deletion evidence under DEC-058.',
            ],
            'monitoring_ownership' => [
                'label' => 'Monitoring ownership',
                'required' => true,
                'guidance' => 'Record that both full administrators receive alerts and either may claim incidents and human transfers.',
            ],
            'cost_monitoring' => [
                'label' => 'Cost and performance monitoring',
                'required' => true,
                'guidance' => 'Record the conversation, daily, turn, latency, and tool-action monitors. An external $25 provider alert is recommended but not a gate for the initial pilot.',
            ],
            'operations_alert_delivery' => [
                'label' => 'Operations alert delivery',
                'required' => true,
                'guidance' => 'Record a content-free in-app and email delivery test to both administrators.',
            ],
            'staff_rehearsal' => [
                'label' => 'Isolated staff rehearsal',
                'required' => true,
                'guidance' => 'Record the exact release commit, synthetic browser flow, live-model gate, hashes, timings, cost, and destroyed temporary database.',
            ],
            'rollback_rehearsal' => [
                'label' => 'Human takeover and rollback rehearsal',
                'required' => true,
                'guidance' => 'Record human takeover, 24/7, emergency, cost stop, capability stop, confirmation invalidation, and preserved human support.',
            ],
            'older_adult_usability' => [
                'label' => 'Five-person older-adult usability study',
                'required' => true,
                'guidance' => 'Record at least 27 of 30 unassisted tasks and universal recap, hire, payment, draft, and human-transfer comprehension.',
            ],
            'accessibility' => [
                'label' => 'Accessibility verification',
                'required' => true,
                'guidance' => 'Record 200% zoom, screen reader, keyboard, focus order, contrast, and touch-target results.',
            ],
            'pilot_users_named' => [
                'label' => 'First two Family pilot users named',
                'required' => true,
                'guidance' => 'Record only safe internal user references, 14-day planned dates, and review ownership. Do not create grants here.',
            ],
        ];

        $deferred = $this->initialPilotDeferredEvidenceKeys();

        return collect($definitions)
            ->map(fn (array $definition, string $key): array => [
                ...$definition,
                'deferable_for_initial_pilot' => in_array($key, $deferred, true),
            ])
            ->all();
    }

    /** @return list<string> */
    public function initialPilotDeferredEvidenceKeys(): array
    {
        return array_values(array_filter(
            (array) config('ai_support.initial_pilot.deferred_evidence_keys', []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));
    }

    /** @return list<string> */
    public function supportedScopes(): array
    {
        return [self::SCOPE_INITIAL_PILOT, self::SCOPE_EXPANSION];
    }

    public function record(
        User $actor,
        string $evidenceKey,
        string $status,
        string $summary,
        ?string $sourceReference = null,
        ?CarbonInterface $observedAt = null,
        ?CarbonInterface $expiresAt = null,
        array $safeMetadata = [],
    ): AiSupportReadinessEvidence {
        if (! $actor->canManageAiSupportControls()) {
            throw new AuthorizationException;
        }
        if (! array_key_exists($evidenceKey, $this->definitions())) {
            throw ValidationException::withMessages(['evidenceKey' => 'Select a recognized readiness evidence item.']);
        }
        if (! in_array($status, [
            AiSupportReadinessEvidence::STATUS_PASSED,
            AiSupportReadinessEvidence::STATUS_FAILED,
            AiSupportReadinessEvidence::STATUS_PENDING,
            AiSupportReadinessEvidence::STATUS_DEFERRED,
        ], true)) {
            throw ValidationException::withMessages(['evidenceStatus' => 'Select Passed, Failed, Pending, or Deferred before expansion.']);
        }
        if ($status === AiSupportReadinessEvidence::STATUS_DEFERRED
            && ! in_array($evidenceKey, $this->initialPilotDeferredEvidenceKeys(), true)) {
            throw ValidationException::withMessages([
                'evidenceStatus' => 'Only the six DEC-070 items may be deferred before expansion.',
            ]);
        }
        $summary = trim($summary);
        if (mb_strlen($summary) < 5 || mb_strlen($summary) > 500) {
            throw ValidationException::withMessages(['evidenceSummary' => 'Enter a content-free summary between 5 and 500 characters.']);
        }
        $sourceReference = filled($sourceReference) ? trim((string) $sourceReference) : null;
        if ($sourceReference !== null && (mb_strlen($sourceReference) > 500 || str_contains($sourceReference, "\n"))) {
            throw ValidationException::withMessages(['sourceReference' => 'Use one compact source reference of at most 500 characters.']);
        }
        $observedAt ??= now();
        if ($expiresAt && $expiresAt->lessThanOrEqualTo($observedAt)) {
            throw ValidationException::withMessages(['evidenceExpiresAt' => 'Expiry must be after the observation time.']);
        }

        return DB::transaction(function () use (
            $actor, $evidenceKey, $status, $summary, $sourceReference, $observedAt, $expiresAt, $safeMetadata
        ): AiSupportReadinessEvidence {
            $current = AiSupportReadinessEvidence::query()
                ->where('evidence_key', $evidenceKey)
                ->whereNull('superseded_at')
                ->lockForUpdate()
                ->latest('version')
                ->first();
            $now = now();
            if ($current) {
                $current->forceFill(['superseded_at' => $now])->save();
            }
            $evidence = AiSupportReadinessEvidence::query()->create([
                'id' => (string) Str::uuid(),
                'evidence_key' => $evidenceKey,
                'version' => ((int) ($current?->version ?? 0)) + 1,
                'status' => $status,
                'summary' => $summary,
                'source_reference' => $sourceReference,
                'safe_metadata' => $safeMetadata ?: null,
                'recorded_by_user_id' => $actor->id,
                'observed_at' => $observedAt,
                'expires_at' => $expiresAt,
                'retain_until' => $observedAt->copy()->addMonths((int) config('ai_support.readiness_evidence_months', 24)),
                'created_at' => $now,
            ]);
            AiSupportAdminAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_family' => 'readiness',
                'action' => 'readiness_evidence_recorded',
                'actor_user_id' => $actor->id,
                'subject_type' => AiSupportReadinessEvidence::class,
                'subject_id' => $evidence->id,
                'result' => 'succeeded',
                'reason_code' => $status,
                'reason' => $summary,
                'metadata' => ['evidence_key' => $evidenceKey, 'version' => $evidence->version],
                'policy_version' => (string) config('ai_support.policy_version'),
                'retain_until' => $now->copy()->addMonths((int) config('ai_support.readiness_evidence_months', 24)),
                'occurred_at' => $now,
                'created_at' => $now,
            ]);

            return $evidence;
        }, 3);
    }

    /** @return array{ready:bool,state:string,scope:string,policy_version:string,checks:list<array{id:string,label:string,passed:bool,satisfied:bool,state:string,detail:string}>,evidence:array<string,array<string,mixed>>,open_incidents:int,open_warnings:int,deferred_count:int,release_decision:array<string,mixed>|null} */
    public function snapshot(AiSupportControlService $controls, string $scope = self::SCOPE_EXPANSION): array
    {
        if (! in_array($scope, $this->supportedScopes(), true)) {
            throw ValidationException::withMessages(['scope' => 'Select initial_pilot or expansion readiness scope.']);
        }

        $checks = [];
        $add = function (
            string $id,
            string $label,
            bool $passed,
            string $detail,
            ?bool $satisfied = null,
            ?string $state = null,
        ) use (&$checks): void {
            $satisfied ??= $passed;
            $state ??= $passed ? 'pass' : 'blocked';
            $checks[] = compact('id', 'label', 'passed', 'satisfied', 'state', 'detail');
        };

        $add('runtime_guard', 'Runtime deployment guard remains off', ! (bool) config('ai_support.runtime_available', false), (bool) config('ai_support.runtime_available', false) ? 'On' : 'Off');
        $add('provider_guard', 'Provider deployment guard remains off', ! (bool) config('ai_support.provider_enabled', false), (bool) config('ai_support.provider_enabled', false) ? 'On' : 'Off');

        $unsafeControls = [];
        foreach ((array) config('ai_support.controls', []) as $key => $default) {
            $expected = $key === 'human_only';
            if ($controls->state($key)['enabled'] !== $expected) {
                $unsafeControls[] = $key;
            }
        }
        $add('stored_controls', 'Stored controls fail closed', $unsafeControls === [], $unsafeControls === [] ? 'Only human-only is on' : 'Unexpected: '.implode(', ', $unsafeControls));

        $nonRevokedGrants = AiSupportPilotGrant::query()->notRevoked()->count();
        $add('pilot_grants', 'No active or scheduled exact-user grant', $nonRevokedGrants === 0, $nonRevokedGrants.' non-revoked grant(s)');

        if ($scope === self::SCOPE_INITIAL_PILOT) {
            $approvedIds = collect((array) config('ai_support.initial_pilot.approved_user_ids', []))
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $boundaryValid = (bool) config('ai_support.initial_pilot.enforced', true)
                && $approvedIds === [19, 282]
                && (string) config('ai_support.initial_pilot.approved_bundle_key') === 'family_support_v1'
                && (int) config('ai_support.initial_pilot.maximum_non_revoked_grants') === 2
                && (string) config('ai_support.initial_pilot.starts_on') === '2026-08-15'
                && (string) config('ai_support.initial_pilot.expires_on') === '2026-08-29';
            $add(
                'initial_pilot_boundary',
                'DEC-070 exact two-user boundary is configured',
                $boundaryValid,
                $boundaryValid
                    ? 'Family IDs 19 and 282 only; family_support_v1; maximum 2; August 15-29'
                    : 'The enforced DEC-070 exact-user, bundle, maximum, or date boundary does not match the accepted policy.',
            );
        }

        $published = KnowledgeBaseEntry::query()->active()->whereNotNull('published_version_id')->count();
        $pricing = KnowledgeBaseEntry::query()->active()->where('stable_id', 'KB-CARE-006')
            ->with(['workingVersion', 'publishedVersion'])->first();
        $heldCorrectly = $pricing !== null
            && $pricing->published_version_id === null
            && $pricing->workingVersion?->status === KnowledgeBaseVersion::STATUS_DRAFT;
        $pricingPublished = $pricing?->publishedVersion?->status === KnowledgeBaseVersion::STATUS_PUBLISHED
            && in_array('support_answers_v1', (array) $pricing->publishedVersion->capability_ids, true)
            && str_contains((string) $pricing->publishedVersion->answer_body, '$30 per worked hour');
        $overdue = KnowledgeBaseEntry::query()->active()
            ->whereHas('workingVersion', fn ($query) => $query->whereDate('review_by', '<', today()))
            ->count();
        $knowledgeReady = $published >= 23 && ($heldCorrectly || $pricingPublished) && $overdue === 0;
        $pricingState = $pricingPublished ? 'current pricing published' : ($heldCorrectly ? 'legacy pricing hold valid' : 'pricing invalid');
        $add('knowledge', 'Governed KB and current pricing are ready', $knowledgeReady, "{$published} published; {$pricingState}; {$overdue} overdue");

        $providerConfigurationValid = (string) config('ai_support.model') === 'gpt-5.6-luna'
            && (string) config('ai_support.reasoning_effort') === 'low'
            && (int) config('ai_support.max_output_tokens') <= 900
            && (int) config('ai_support.provider_retry_attempts') <= 2
            && strlen(trim((string) config('ai_support.safety_identifier_secret'))) >= 32
            && trim((string) config('services.openai.api_key')) !== '';
        $add('provider_configuration', 'Bounded provider configuration is present', $providerConfigurationValid, $providerConfigurationValid ? 'Luna low; 900-token ceiling; bounded retry; credential and safety secret present' : 'One or more provider prerequisites are missing');

        $priceValid = (string) config('ai_support.provider_price_version') === 'openai-gpt-5.6-luna-2026-08-14'
            && (float) config('ai_support.provider_input_usd_per_million') === 0.20
            && (float) config('ai_support.provider_cached_input_usd_per_million') === 0.02
            && (float) config('ai_support.provider_output_usd_per_million') === 1.20;
        $add('provider_price', 'Versioned provider price catalog is current', $priceValid, (string) config('ai_support.provider_price_version'));

        $openIncidents = AiSupportIncident::query()
            ->where('status', AiSupportIncident::STATUS_OPEN)
            ->where('severity', AiSupportIncident::SEVERITY_CRITICAL)
            ->count();
        $openWarnings = AiSupportIncident::query()
            ->where('status', AiSupportIncident::STATUS_OPEN)
            ->where('severity', AiSupportIncident::SEVERITY_WARNING)
            ->count();
        $add('incidents', 'No unresolved AI Support incident', $openIncidents === 0, $openIncidents.' open incident(s)');

        $currentEvidence = AiSupportReadinessEvidence::query()->current()->with('recordedBy')->get()->keyBy('evidence_key');
        $evidenceRows = [];
        $deferredCount = 0;
        foreach ($this->definitions() as $key => $definition) {
            $record = $currentEvidence->get($key);
            $passed = $record?->isEffectivePass() ?? false;
            $deferred = $scope === self::SCOPE_INITIAL_PILOT
                && $definition['deferable_for_initial_pilot']
                && ($record?->isEffectiveDeferred() ?? false);
            if ($definition['required']) {
                if ($deferred) {
                    $deferredCount++;
                    $add(
                        'evidence.'.$key,
                        $definition['label'],
                        false,
                        'Deferred before expansion - '.$record->summary,
                        true,
                        'deferred',
                    );
                } else {
                    $add(
                        'evidence.'.$key,
                        $definition['label'],
                        $passed,
                        $record ? ucfirst($record->status).' - '.$record->summary : 'Not recorded',
                    );
                }
            }
            $evidenceRows[$key] = [
                ...$definition,
                'status' => $record?->status ?? 'not_recorded',
                'summary' => $record?->summary,
                'source_reference' => $record?->source_reference,
                'observed_at' => $record?->observed_at,
                'expires_at' => $record?->expires_at,
                'recorded_by' => $record?->recordedBy?->name,
                'effective_pass' => $passed,
                'effective_deferred' => $deferred,
                'satisfies_scope' => $passed || $deferred || ! $definition['required'],
            ];
        }

        $ready = collect($checks)->every(fn (array $check): bool => $check['satisfied']);
        $policyVersion = (string) config('ai_support.initial_pilot.policy_version', 'dec-070-initial-family-v1');
        $releaseDecision = $scope === self::SCOPE_INITIAL_PILOT
            ? AiSupportReleaseDecision::query()
                ->current()
                ->where('scope', self::SCOPE_INITIAL_PILOT)
                ->latest('created_at')
                ->first()
            : null;

        return [
            'ready' => $ready,
            'state' => $ready
                ? ($scope === self::SCOPE_INITIAL_PILOT
                    ? 'READY FOR EXPLICIT INITIAL PILOT APPROVAL'
                    : 'READY FOR EXPLICIT EXPANSION APPROVAL')
                : 'BLOCKED',
            'scope' => $scope,
            'policy_version' => $policyVersion,
            'checks' => $checks,
            'evidence' => $evidenceRows,
            'open_incidents' => $openIncidents,
            'open_warnings' => $openWarnings,
            'deferred_count' => $deferredCount,
            'release_decision' => $releaseDecision ? [
                'id' => $releaseDecision->id,
                'status' => $releaseDecision->status,
                'effective' => app(AiSupportInitialPilotReleaseService::class)->effectiveApproval()?->is($releaseDecision) ?? false,
                'release_commit' => $releaseDecision->release_commit,
                'snapshot_sha256' => $releaseDecision->snapshot_sha256,
                'starts_at' => $releaseDecision->starts_at,
                'expires_at' => $releaseDecision->expires_at,
            ] : null,
        ];
    }
}
