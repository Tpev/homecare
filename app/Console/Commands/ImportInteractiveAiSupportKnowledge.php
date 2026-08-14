<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiSupport\InteractiveKnowledgeBaseImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportInteractiveAiSupportKnowledge extends Command
{
    protected $signature = 'ai-support:import-interactive-kb
        {--apply : Create missing governed Draft entries}
        {--actor-email= : Authorized Admin actor recorded on applied drafts and audit events}';

    protected $description = 'Dry-run or safely import the approved interactive AI Support knowledge pack as Drafts only.';

    public function handle(InteractiveKnowledgeBaseImportService $importer): int
    {
        try {
            $plan = $importer->plan();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Interactive KB manifest validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Interactive KB manifest: '.$plan['manifest_version']);
        $this->table(['Result', 'Count'], [
            ['Would create', $plan['counts']['creates']],
            ['Governed entry no-op', $plan['counts']['noops']],
            ['Conflict/refusal', $plan['counts']['conflicts']],
        ]);
        if ($plan['conflicts'] !== []) {
            $this->error('Import refused. Existing content was not changed.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->warn('Dry run only. No knowledge, controls, grants, runtime state, or domain records were changed.');

            return self::SUCCESS;
        }

        $email = trim((string) $this->option('actor-email'));
        $actor = $email !== '' ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first() : null;
        if (! $actor?->canManageKnowledgeBase()) {
            $this->error('A valid authorized Admin --actor-email is required with --apply.');

            return self::FAILURE;
        }

        try {
            $result = $importer->apply($actor);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Interactive KB import failed closed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result['created'] as $created) {
            $this->line('CREATED '.$created['stable_id'].' - Draft version '.$created['version_number']);
        }
        foreach ($result['noops'] as $stableId) {
            $this->line('NO-OP '.$stableId.' - already under governance');
        }
        $this->info('Interactive KB Draft import completed; nothing was published.');

        return self::SUCCESS;
    }
}
