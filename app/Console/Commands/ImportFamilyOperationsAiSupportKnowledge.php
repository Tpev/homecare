<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiSupport\FamilyOperationsKnowledgeBaseImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportFamilyOperationsAiSupportKnowledge extends Command
{
    protected $signature = 'ai-support:import-family-operations-kb
        {--apply : Create the exact package as governed Drafts}
        {--publish : Create/revise and publish the exact package in one operation}
        {--actor-email= : Authorized Admin actor}
        {--reason= : Publication reason}
        {--confirm= : Exact publication confirmation}';

    protected $description = 'Plan, import, or publish the approved Family operations AI Support knowledge package.';

    public function handle(FamilyOperationsKnowledgeBaseImportService $importer): int
    {
        try {
            $plan = $importer->plan();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Family operations KB validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Family operations KB manifest: '.$plan['manifest_version']);
        $this->table(['Result', 'Count'], [
            ['Would create', $plan['counts']['creates']],
            ['Would revise', $plan['counts']['revisions']],
            ['Exact no-op', $plan['counts']['noops']],
            ['Conflict/refusal', $plan['counts']['conflicts']],
        ]);
        if ($plan['conflicts'] !== []) {
            foreach ($plan['conflicts'] as $conflict) {
                $this->error($conflict['stable_id'].': '.$conflict['reason']);
            }
            $this->error('Import refused. Existing content was not changed.');

            return self::FAILURE;
        }

        $publish = (bool) $this->option('publish');
        $apply = (bool) $this->option('apply') || $publish;
        if (! $apply) {
            $this->warn('Plan only. No knowledge, controls, grants, pilot access, or domain records were changed.');

            return self::SUCCESS;
        }

        $email = trim((string) $this->option('actor-email'));
        $actor = $email !== '' ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first() : null;
        if (! $actor?->canManageKnowledgeBase()) {
            $this->error('A valid authorized Admin --actor-email is required.');

            return self::FAILURE;
        }
        if ($publish && (string) $this->option('confirm') !== 'PUBLISH-FAMILY-OPERATIONS-KB') {
            $this->error('Publication requires --confirm=PUBLISH-FAMILY-OPERATIONS-KB.');

            return self::FAILURE;
        }
        $reason = trim((string) $this->option('reason'));
        if ($publish && mb_strlen($reason) < 5) {
            $this->error('Publication requires a concise --reason.');

            return self::FAILURE;
        }

        try {
            $result = $publish
                ? $importer->publishPackage($actor, $reason)
                : $importer->apply($actor);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Family operations KB operation failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(count($result['created']).' new entries created; '.count($result['revised']).' existing entries revised.');
        if ($publish) {
            $this->info(count($result['published']).' entries published; '.count($result['already_published']).' already published exact no-ops.');
            $this->line('Assistant availability and the two-user pilot boundary were not changed.');
        } else {
            $this->info('Draft import completed; nothing was published.');
        }

        return self::SUCCESS;
    }
}
