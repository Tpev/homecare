<?php

namespace App\Console\Commands;

use App\Services\AiSupport\AiSupportDownstreamExtinctionValidator;

class ValidateAiSupportDownstreamExtinction extends ValidatesAiSupportEvidenceRecord
{
    protected $signature = 'ai-support:validate-downstream-extinction
        {record : JSON record created from the approved content-free template}
        {--expected-commit= : Exact 40-character release commit; defaults to current HEAD}';

    protected $description = 'Validate downstream extinction and isolated restore/re-deletion evidence without writing readiness state.';

    public function handle(AiSupportDownstreamExtinctionValidator $validator): int
    {
        $loaded = $this->loadEvidenceRecord();
        if ($loaded === null) {
            return self::FAILURE;
        }
        $result = $validator->validate($loaded['record'], $loaded['expected_commit']);
        if (! $result['passed']) {
            return $this->renderFailure($result['errors']);
        }

        $this->table(['Check', 'Result'], [
            ['Schema', AiSupportDownstreamExtinctionValidator::SCHEMA],
            ['Release commit', $result['release_commit']],
            ['Completed scoped destinations', $result['destination_count'].' / 18'],
            ['Restore/re-deletion checks', $result['restore_check_count'].' / 6'],
            ['Record SHA-256', $this->contentFreeHash($loaded['path'])],
            ['Application mutation', 'None'],
        ]);
        $this->info('DOWNSTREAM EXTINCTION AND RESTORE RECORD PASSED STRUCTURAL AND GATE VALIDATION');
        $this->warn('This validates the operator record; it does not inspect external systems or record Admin evidence automatically.');

        return self::SUCCESS;
    }
}
