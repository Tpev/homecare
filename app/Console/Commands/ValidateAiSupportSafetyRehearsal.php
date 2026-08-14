<?php

namespace App\Console\Commands;

use App\Services\AiSupport\AiSupportHumanEvidenceValidator;

class ValidateAiSupportSafetyRehearsal extends ValidatesAiSupportEvidenceRecord
{
    protected $signature = 'ai-support:validate-safety-rehearsal
        {record : JSON record created from the approved content-free template}
        {--expected-commit= : Exact 40-character release commit; defaults to current HEAD}';

    protected $description = 'Validate the staffed AI Support safety and rollback rehearsal record without writing evidence.';

    public function handle(AiSupportHumanEvidenceValidator $validator): int
    {
        $loaded = $this->loadEvidenceRecord();
        if ($loaded === null) {
            return self::FAILURE;
        }
        $result = $validator->validateSafety($loaded['record'], $loaded['expected_commit']);
        if (! $result['passed']) {
            return $this->renderFailure($result['errors']);
        }

        $this->table(['Check', 'Result'], [
            ['Schema', AiSupportHumanEvidenceValidator::SAFETY_SCHEMA],
            ['Release commit', $result['release_commit']],
            ['Required observations', $result['observation_count'].' / '.count(AiSupportHumanEvidenceValidator::SAFETY_OBSERVATIONS)],
            ['Record SHA-256', $this->contentFreeHash($loaded['path'])],
            ['Application mutation', 'None'],
        ]);
        $this->info('STAFFED SAFETY REHEARSAL RECORD PASSED STRUCTURAL AND GATE VALIDATION');
        $this->warn('This validates the operator record; it does not replace the witnessed rehearsal or record Admin evidence automatically.');

        return self::SUCCESS;
    }
}
