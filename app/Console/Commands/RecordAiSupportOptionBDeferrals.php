<?php

namespace App\Console\Commands;

use App\Models\AiSupportPilotGrant;
use App\Models\AiSupportReadinessEvidence;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecordAiSupportOptionBDeferrals extends Command
{
    protected $signature = 'ai-support:record-option-b-deferrals
        {--apply : Record the six DEC-070 deferred-before-expansion evidence versions}
        {--actor-email= : Full Administrator email used only to identify the audited actor}
        {--confirm= : Must equal DEFER-SIX-GATES-BEFORE-EXPANSION when applying}';

    protected $description = 'Record the accepted DEC-070 deferrals without marking evidence Passed or changing controls and grants.';

    /** @var array<string,string> */
    private const SUMMARIES = [
        'provider_data_controls' => 'Deferred before expansion under DEC-070. Application store:false and absence of provider-hosted conversations, files, vector stores, tools, and background mode are verified. Account-owned sharing and effective-retention proof remains required before expansion.',
        'provider_destination_contract' => 'Deferred before expansion under DEC-070. The configured credential authenticated at the standard API destination and Product accepts standard processing with no residency claim for the exact pilot. Contractual reference retention remains required before expansion.',
        'downstream_extinction_restore' => 'Deferred before expansion under DEC-070. Primary legacy data destruction is recorded. Both destination inventories and the isolated restore/re-deletion rehearsal remain an open Phase 0 obligation required before expansion.',
        'rollback_rehearsal' => 'Deferred before expansion under DEC-070. Automated safety matrices, exact rehearsal, and both-Administrator alerts are verified. The witnessed staffed takeover and rollback drill remains required during the controlled pilot and before expansion.',
        'older_adult_usability' => 'Deferred before expansion under DEC-070. The five-person non-team older-adult study, comprehension correction, and any required retest remain mandatory before adding a third pilot user.',
        'accessibility' => 'Deferred before expansion under DEC-070. Automated zoom, reflow, keyboard, focus, contrast, touch-target, accessible-name, and draft-preservation checks passed. A real screen-reader session remains mandatory before expansion.',
    ];

    public function handle(AiSupportReadinessService $readiness, AiSupportControlService $controls): int
    {
        $this->table(['Item', 'Action'], collect(self::SUMMARIES)->keys()
            ->map(fn (string $key): array => [$key, 'Deferred before expansion; never Passed'])
            ->all());

        if (! $this->option('apply')) {
            $this->info('Plan only. No evidence, control, grant, provider, or application state was changed.');

            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'DEFER-SIX-GATES-BEFORE-EXPANSION') {
            $this->error('Applying requires --confirm=DEFER-SIX-GATES-BEFORE-EXPANSION.');

            return self::FAILURE;
        }

        $actorEmail = strtolower(trim((string) $this->option('actor-email')));
        $actor = $actorEmail !== '' ? User::query()->whereRaw('LOWER(email) = ?', [$actorEmail])->first() : null;
        if (! $actor || ! $actor->canManageAiSupportControls()) {
            $this->error('Provide the email of an existing full Administrator.');

            return self::FAILURE;
        }

        $expansion = $readiness->snapshot($controls, AiSupportReadinessService::SCOPE_EXPANSION);
        $requiredFoundationIds = ['runtime_guard', 'provider_guard', 'stored_controls', 'pilot_grants'];
        $unsafeFoundation = collect($expansion['checks'])
            ->whereIn('id', $requiredFoundationIds)
            ->first(fn (array $check): bool => ! $check['passed']);
        if ($unsafeFoundation || AiSupportPilotGrant::query()->notRevoked()->exists()) {
            $this->error('Both guards must be off, stored controls must fail closed, and zero non-revoked grants must exist.');

            return self::FAILURE;
        }

        foreach (array_keys(self::SUMMARIES) as $key) {
            $current = AiSupportReadinessEvidence::query()->current()->where('evidence_key', $key)->first();
            if ($current?->status === AiSupportReadinessEvidence::STATUS_FAILED) {
                $this->error("{$key} is Failed and cannot be converted into a deferral.");

                return self::FAILURE;
            }
        }

        $observedAt = CarbonImmutable::now();
        $expiresAt = CarbonImmutable::parse((string) config('ai_support.initial_pilot.expires_on'))->endOfDay();
        $recorded = 0;
        DB::transaction(function () use ($readiness, $actor, $observedAt, $expiresAt, &$recorded): void {
            foreach (self::SUMMARIES as $key => $summary) {
                $current = AiSupportReadinessEvidence::query()->current()->where('evidence_key', $key)->lockForUpdate()->first();
                if ($current?->isEffectivePass() || $current?->isEffectiveDeferred()) {
                    continue;
                }
                $readiness->record(
                    $actor,
                    $key,
                    AiSupportReadinessEvidence::STATUS_DEFERRED,
                    $summary,
                    'DEC-070; Product approved Option B on 2026-08-15',
                    $observedAt,
                    $expiresAt,
                );
                $recorded++;
            }
        }, 3);

        $this->info("Recorded {$recorded} deferred-before-expansion evidence version(s).");
        $this->warn('No item was marked Passed. No control, grant, provider, or application state was changed.');

        return self::SUCCESS;
    }
}
