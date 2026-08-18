<?php

namespace App\Console\Commands;

use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportPilotGrant;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ActivateAiSupportBatch5Pilot extends Command
{
    protected $signature = 'ai-support:activate-batch5-pilot {--actor-email= : Full Administrator email}';

    protected $description = 'Enable Batch 5 profile/request actions for the existing two-user pilot without enabling general release.';

    public function handle(AiSupportControlService $controls): int
    {
        $actor = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $this->option('actor-email')))])
            ->first();
        if (! $actor?->canManageAiSupportControls()) {
            $this->error('A valid Full Administrator --actor-email is required.');

            return self::FAILURE;
        }
        if ($controls->enabled('general_release_enabled')) {
            $this->error('Live for everyone is on. Switch to Pilot only before activating this pilot batch.');

            return self::FAILURE;
        }

        $approvedIds = collect((array) config('ai_support.initial_pilot.approved_user_ids', []))
            ->map(fn ($id): int => (int) $id)->filter()->unique()->sort()->values();
        $grants = AiSupportPilotGrant::query()->effectiveAt()->orderBy('user_id')->get();
        if ($approvedIds->count() !== 2 || $grants->count() !== 2
            || $grants->pluck('user_id')->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $approvedIds->all()) {
            $this->error('The active pilot is not the exact configured two-user pilot. Nothing was changed.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($actor, $controls, $grants): void {
                foreach ($grants as $grant) {
                    $locked = AiSupportPilotGrant::query()->lockForUpdate()->findOrFail($grant->id);
                    $capabilities = collect((array) $locked->capability_ids)
                        ->push('family_lifecycle_action_v1')->unique()->values()->all();
                    if ($capabilities !== $locked->capability_ids) {
                        $locked->forceFill(['capability_ids' => $capabilities])->save();
                        $now = now();
                        AiSupportAdminAuditEvent::query()->create([
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'event_family' => 'pilot_access',
                            'action' => 'pilot_grant_batch5_extended',
                            'actor_user_id' => $actor->id,
                            'target_user_id' => $locked->user_id,
                            'subject_type' => AiSupportPilotGrant::class,
                            'subject_id' => $locked->id,
                            'result' => 'succeeded',
                            'reason_code' => 'batch5_pilot_capability',
                            'reason' => 'Enable approved Batch 5 profile and request lifecycle actions for the existing pilot.',
                            'metadata' => ['capability_id' => 'family_lifecycle_action_v1'],
                            'policy_version' => (string) config('ai_support.policy_version'),
                            'retain_until' => $now->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                            'occurred_at' => $now,
                            'created_at' => $now,
                        ]);
                    }
                }

                $reason = 'Enable approved Batch 5 actions for the exact two-user pilot.';
                foreach ([
                    'capability.family_lifecycle_action_v1',
                    'tool.family-profile.save-draft',
                    'tool.family-profile.make-ready',
                    'tool.family-profile.make-default',
                    'tool.family-profile.archive',
                    'tool.family-profile.restore',
                    'tool.care-request.withdraw',
                ] as $control) {
                    if (! $controls->enabled($control)) {
                        $controls->set($actor, $control, true, $reason);
                    }
                }

                if ($controls->enabled('general_release_enabled')) {
                    throw new RuntimeException('General release changed during activation.');
                }
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Batch 5 pilot activation failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Check', 'Result'], [
            ['Pilot users', $approvedIds->implode(', ')],
            ['Batch 5 capability', 'Enabled'],
            ['Batch 5 tools', '6 enabled'],
            ['Live for everyone', 'Off'],
        ]);
        $this->info('Batch 5 is active for the existing two-user pilot only.');

        return self::SUCCESS;
    }
}
