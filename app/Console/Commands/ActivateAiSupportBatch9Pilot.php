<?php

namespace App\Console\Commands;

use App\Models\AiSupportAdminAuditEvent;
use App\Models\AiSupportPilotGrant;
use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\FamilyAdministrationActionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ActivateAiSupportBatch9Pilot extends Command
{
    protected $signature = 'ai-support:activate-batch9-pilot {--actor-email= : Full Administrator email}';

    protected $description = 'Enable Batches 8 and 9 Family administration, history, notifications, and human-owned support for the existing two-user pilot only.';

    public function handle(AiSupportControlService $controls): int
    {
        $actor = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $this->option('actor-email')))])->first();
        if (! $actor?->canManageAiSupportControls()) {
            $this->error('A valid Full Administrator --actor-email is required.');

            return self::FAILURE;
        }
        if ($controls->enabled('general_release_enabled')) {
            $this->error('Live for everyone is on. Switch to Pilot only before activating this pilot batch.');

            return self::FAILURE;
        }
        $approvedIds = collect((array) config('ai_support.initial_pilot.approved_user_ids', []))->map(fn ($id): int => (int) $id)->filter()->unique()->sort()->values();
        $grants = AiSupportPilotGrant::query()->effectiveAt()->orderBy('user_id')->get();
        if ($approvedIds->count() !== 2 || $grants->count() !== 2
            || $grants->pluck('user_id')->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $approvedIds->all()) {
            $this->error('The active pilot is not the exact configured two-user pilot. Nothing was changed.');

            return self::FAILURE;
        }
        $toolIds = collect((array) config('ai_support.tools', []))
            ->filter(fn (array $definition): bool => ($definition['capability_id'] ?? null) === FamilyAdministrationActionService::CAPABILITY)
            ->keys()->values();
        try {
            DB::transaction(function () use ($actor, $controls, $grants, $toolIds): void {
                foreach ($grants as $grant) {
                    $locked = AiSupportPilotGrant::query()->lockForUpdate()->findOrFail($grant->id);
                    $capabilities = collect((array) $locked->capability_ids)->push(FamilyAdministrationActionService::CAPABILITY)->unique()->values()->all();
                    if ($capabilities !== $locked->capability_ids) {
                        $locked->forceFill(['capability_ids' => $capabilities])->save();
                        $now = now();
                        AiSupportAdminAuditEvent::query()->create([
                            'id' => (string) Str::uuid(), 'event_family' => 'pilot_access', 'action' => 'pilot_grant_batch9_extended',
                            'actor_user_id' => $actor->id, 'target_user_id' => $locked->user_id,
                            'subject_type' => AiSupportPilotGrant::class, 'subject_id' => $locked->id,
                            'result' => 'succeeded', 'reason_code' => 'batch9_pilot_capability',
                            'reason' => 'Enable approved Batches 8 and 9 Family administration and support for the existing pilot.',
                            'metadata' => ['capability_id' => FamilyAdministrationActionService::CAPABILITY],
                            'policy_version' => (string) config('ai_support.policy_version'),
                            'retain_until' => $now->copy()->addMonths((int) config('ai_support.grant_history_months', 24)),
                            'occurred_at' => $now, 'created_at' => $now,
                        ]);
                    }
                }
                $reason = 'Enable approved Batches 8 and 9 actions for the exact two-user pilot.';
                foreach ($toolIds->map(fn (string $tool): string => 'tool.'.$tool)->prepend('capability.'.FamilyAdministrationActionService::CAPABILITY) as $control) {
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
            $this->error('Batches 8 and 9 pilot activation failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->table(['Check', 'Result'], [
            ['Pilot users', $approvedIds->implode(', ')], ['Batches 8 and 9 capability', 'Enabled'],
            ['Batches 8 and 9 tools', $toolIds->count().' enabled'], ['Continuous Coverage mutations', 'Human-owned'],
            ['Live for everyone', 'Off'],
        ]);
        $this->info('Batches 8 and 9 are active for the existing two-user pilot only.');

        return self::SUCCESS;
    }
}
