<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiSupport\FamilyExperienceKnowledgeBaseImportService;
use Illuminate\Console\Command;
use Throwable;

class RealignFamilyAiSupportKnowledge extends Command
{
    protected $signature = 'ai-support:realign-family-kb
        {--apply : Create validated revisions without publishing}
        {--publish : Create and publish the revisions}
        {--actor-email= : Authorized Admin actor}
        {--reason=Realign Family AI guidance with the current application experience. : Publication reason}';

    protected $description = 'Plan, apply, or publish the current Family experience KB realignment.';

    public function handle(FamilyExperienceKnowledgeBaseImportService $importer): int
    {
        try {
            $plan = $importer->plan();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Family KB realignment validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Family experience KB package: '.$plan['manifest_version']);
        $this->table(['Result', 'Count'], [
            ['Would update an unpublished draft', $plan['counts']['updates']],
            ['Would revise', $plan['counts']['revisions']],
            ['Exact no-op', $plan['counts']['noops']],
            ['Conflict/refusal', $plan['counts']['conflicts']],
        ]);
        foreach ($plan['conflicts'] as $conflict) {
            $this->error($conflict['stable_id'].': '.$conflict['reason']);
        }
        if ($plan['conflicts'] !== []) {
            return self::FAILURE;
        }

        $publish = (bool) $this->option('publish');
        if (! $publish && ! (bool) $this->option('apply')) {
            $this->warn('Plan only. No knowledge or application state changed.');

            return self::SUCCESS;
        }

        $email = mb_strtolower(trim((string) $this->option('actor-email')));
        $actor = $email !== '' ? User::query()->whereRaw('LOWER(email) = ?', [$email])->first() : null;
        if (! $actor?->canManageKnowledgeBase()) {
            $this->error('A valid authorized Admin --actor-email is required.');

            return self::FAILURE;
        }

        try {
            $result = $publish
                ? $importer->publishPackage($actor, trim((string) $this->option('reason')))
                : $importer->apply($actor);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Family KB realignment failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(count($result['updated']).' unpublished drafts updated; '.count($result['revised']).' revisions created.');
        if ($publish) {
            $this->info(count($result['published']).' revisions published; '.count($result['already_published']).' exact published no-ops.');
        }
        $this->line('AI availability, pilot access, care, payment, and user records were not changed.');

        return self::SUCCESS;
    }
}
