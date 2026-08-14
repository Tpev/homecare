<?php

namespace App\Console\Commands;

use App\Services\AiSupport\AiSupportHumanEvidenceValidator;

class ValidateAiSupportOlderAdultStudy extends ValidatesAiSupportEvidenceRecord
{
    protected $signature = 'ai-support:validate-older-adult-study
        {record : JSON record created from the approved content-free template}
        {--expected-commit= : Exact 40-character release commit; defaults to current HEAD}';

    protected $description = 'Validate the five-person older-adult and accessibility study record without writing evidence.';

    public function handle(AiSupportHumanEvidenceValidator $validator): int
    {
        $loaded = $this->loadEvidenceRecord();
        if ($loaded === null) {
            return self::FAILURE;
        }
        $result = $validator->validateStudy($loaded['record'], $loaded['expected_commit']);
        if (! $result['passed']) {
            return $this->renderFailure($result['errors']);
        }

        $this->table(['Check', 'Result'], [
            ['Schema', AiSupportHumanEvidenceValidator::STUDY_SCHEMA],
            ['Release commit', $result['release_commit']],
            ['Qualifying participants', $result['participant_count'].' / 5'],
            ['Unassisted tasks', $result['unassisted_tasks'].' / '.$result['total_tasks']],
            ['Universal comprehension/draft checks', 'PASS'],
            ['Accessibility matrix', 'PASS'],
            ['Record SHA-256', $this->contentFreeHash($loaded['path'])],
            ['Application mutation', 'None'],
        ]);
        $this->info('OLDER-ADULT AND ACCESSIBILITY RECORD PASSED STRUCTURAL AND GATE VALIDATION');
        $this->warn('This validates the moderator record; it does not replace real non-team participants or record Admin evidence automatically.');

        return self::SUCCESS;
    }
}
