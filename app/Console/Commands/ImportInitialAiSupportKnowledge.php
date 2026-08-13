<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiSupport\InitialKnowledgeBaseImportService;
use DomainException;
use Illuminate\Console\Command;
use Throwable;

class ImportInitialAiSupportKnowledge extends Command
{
    protected $signature = 'ai-support:import-initial-kb
        {--apply : Create missing governed Draft entries}
        {--actor-email= : Authorized Admin actor recorded on applied drafts and audit events}';

    protected $description = 'Dry-run or safely import the approved initial AI Support knowledge pack as Drafts only.';

    public function handle(InitialKnowledgeBaseImportService $importer): int
    {
        try {
            $plan = $importer->plan();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Initial KB manifest validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Initial KB manifest: '.$plan['manifest_version']);
        $this->table(['Result', 'Count'], [
            ['Would create', $plan['counts']['creates']],
            ['Identical Draft no-op', $plan['counts']['noops']],
            ['Conflict/refusal', $plan['counts']['conflicts']],
        ]);

        foreach ($plan['conflicts'] as $conflict) {
            $this->error($conflict['stable_id'].' - '.$conflict['reason']);
        }

        if ($plan['conflicts'] !== []) {
            $this->error('Import refused. Existing content was not changed.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            foreach ($plan['creates'] as $stableId) {
                $this->line('CREATE '.$stableId.' as Draft version 1');
            }
            $this->warn('Dry run only. No knowledge, controls, grants, runtime state, or domain records were changed.');

            return self::SUCCESS;
        }

        $actorEmail = trim((string) $this->option('actor-email'));
        if ($actorEmail === '') {
            $this->error('--actor-email is required with --apply.');

            return self::FAILURE;
        }

        $actor = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($actorEmail)])->first();
        if (! $actor || ! $actor->canManageKnowledgeBase()) {
            $this->error('The requested actor is not an authorized knowledge-base Admin.');

            return self::FAILURE;
        }

        try {
            $result = $importer->apply($actor);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());
            $this->error('Import failed closed. No published content or runtime state was changed.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Initial KB import failed unexpectedly and was rolled back.');

            return self::FAILURE;
        }

        foreach ($result['created'] as $created) {
            $this->line('CREATED '.$created['stable_id'].' - Draft version '.$created['version_number'].' (version ID '.$created['version_id'].')');
        }
        foreach ($result['noops'] as $stableId) {
            $this->line('NO-OP '.$stableId.' - identical Draft already exists');
        }

        $this->info('Initial KB Draft import completed.');
        $this->table(['Invariant', 'Value'], [
            ['Created Drafts', count($result['created'])],
            ['Identical no-ops', count($result['noops'])],
            ['Published before / after', $result['published_count_before'].' / '.$result['published_count_after']],
            ['Model calls', 0],
            ['Pilot grants created', 0],
            ['AI controls changed', 0],
        ]);

        return self::SUCCESS;
    }
}
