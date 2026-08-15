<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiSupport\AiSupportControlService;
use App\Services\AiSupport\AiSupportInitialPilotReleaseService;
use App\Services\AiSupport\AiSupportReadinessService;
use Illuminate\Console\Command;
use Throwable;

class ApproveAiSupportInitialPilotRelease extends Command
{
    protected $signature = 'ai-support:approve-initial-pilot-release
        {--approve : Record the explicit release decision; omitted is plan-only}
        {--actor-email= : Full Administrator email used only to identify the audited decision owner}
        {--release-commit= : Exact 40-character deployed commit}
        {--reason= : Content-free release decision reason}
        {--confirm= : Must equal APPROVE-EXACT-TWO-USER-PILOT when approving}';

    protected $description = 'Record the explicit DEC-070 two-user release decision without changing controls, grants, or provider state.';

    public function handle(
        AiSupportInitialPilotReleaseService $release,
        AiSupportReadinessService $readiness,
        AiSupportControlService $controls,
    ): int {
        $snapshot = $readiness->snapshot($controls, AiSupportReadinessService::SCOPE_INITIAL_PILOT);
        $this->table(['Check', 'Value'], [
            ['Initial-pilot preflight', $snapshot['ready'] ? 'READY' : 'BLOCKED'],
            ['Passed checks', collect($snapshot['checks'])->where('passed', true)->count()],
            ['Deferred before expansion', $snapshot['deferred_count']],
            ['Blocking checks', collect($snapshot['checks'])->where('satisfied', false)->count()],
            ['Approved safe user IDs', implode(', ', $release->approvedUserIds())],
            ['Grant mutation', 'None'],
            ['Control mutation', 'None'],
        ]);

        if (! $this->option('approve')) {
            $this->info('Plan only. No release decision, control, grant, provider, or application state was changed.');

            return self::SUCCESS;
        }
        if ((string) $this->option('confirm') !== 'APPROVE-EXACT-TWO-USER-PILOT') {
            $this->error('Approval requires --confirm=APPROVE-EXACT-TWO-USER-PILOT.');

            return self::FAILURE;
        }

        $actorEmail = strtolower(trim((string) $this->option('actor-email')));
        $actor = $actorEmail !== '' ? User::query()->whereRaw('LOWER(email) = ?', [$actorEmail])->first() : null;
        if (! $actor || ! $actor->canManageAiSupportControls()) {
            $this->error('Provide the email of an existing full Administrator.');

            return self::FAILURE;
        }

        try {
            $decision = $release->approve(
                $actor,
                (string) $this->option('reason'),
                (string) $this->option('release-commit'),
                $readiness,
                $controls,
            );
        } catch (Throwable $exception) {
            $this->error('Release decision was not recorded: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Explicit initial-pilot release decision recorded.');
        $this->line('Decision reference: '.$decision->id);
        $this->line('Snapshot SHA-256: '.$decision->snapshot_sha256);
        $this->warn('No control, grant, provider, or application state was changed. Activation remains a separate ordered operation.');

        return self::SUCCESS;
    }
}
