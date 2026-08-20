<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiSupport\FamilyAdministrationKnowledgeBaseImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportFamilyAdministrationAiSupportKnowledge extends Command
{
    protected $signature = 'ai-support:import-family-administration-kb
        {--apply : Create the exact package as governed Drafts}
        {--publish : Create and publish the exact package in one operation}
        {--actor-email= : Authorized Admin actor}
        {--reason= : Publication reason}
        {--confirm= : Exact publication confirmation}';

    protected $description = 'Plan, import, or publish the approved Batches 8 and 9 Family administration, history, coverage, and support knowledge package.';

    public function handle(FamilyAdministrationKnowledgeBaseImportService $importer): int
    {
        try {
            $plan = $importer->plan();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Batches 8 and 9 KB validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Batches 8 and 9 KB manifest: '.$plan['manifest_version']);
        $this->table(['Result', 'Count'], [['Would create', $plan['counts']['creates']], ['Exact no-op', $plan['counts']['noops']], ['Conflict/refusal', $plan['counts']['conflicts']]]);
        if ($plan['conflicts'] !== []) {
            return self::FAILURE;
        }
        $publish = (bool) $this->option('publish');
        if (! ((bool) $this->option('apply') || $publish)) {
            $this->warn('Plan only. No knowledge, pilot access, account, notification, history, coverage, or support state was changed.');

            return self::SUCCESS;
        }
        $actor = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $this->option('actor-email')))])->first();
        if (! $actor?->canManageKnowledgeBase()) {
            $this->error('A valid authorized Admin --actor-email is required.');

            return self::FAILURE;
        }
        if ($publish && (string) $this->option('confirm') !== 'PUBLISH-FAMILY-ADMINISTRATION-SUPPORT-KB') {
            $this->error('Publication requires --confirm=PUBLISH-FAMILY-ADMINISTRATION-SUPPORT-KB.');

            return self::FAILURE;
        }
        $reason = trim((string) $this->option('reason'));
        if ($publish && mb_strlen($reason) < 5) {
            $this->error('Publication requires a concise --reason.');

            return self::FAILURE;
        }
        try {
            $result = $publish ? $importer->publishPackage($actor, $reason) : $importer->apply($actor);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Batches 8 and 9 KB operation failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info(count($result['created']).' entries created.');
        $this->info($publish ? count($result['published']).' entries published; '.count($result['already_published']).' exact published no-ops.' : 'Draft import completed; nothing was published.');
        $this->line('Assistant availability, pilot users, account access, notifications, history, Continuous Coverage, and support records were not changed.');

        return self::SUCCESS;
    }
}
