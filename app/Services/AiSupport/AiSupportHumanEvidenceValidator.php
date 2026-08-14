<?php

namespace App\Services\AiSupport;

use Carbon\CarbonImmutable;
use Throwable;

class AiSupportHumanEvidenceValidator
{
    public const SAFETY_SCHEMA = 'ai-support-safety-rehearsal-v1';

    public const STUDY_SCHEMA = 'ai-support-older-adult-study-v1';

    /** @var list<string> */
    public const SAFETY_OBSERVATIONS = [
        'active_recap_before_takeover',
        'human_takeover_before_automated_reply',
        'pending_recap_invalidated',
        'stale_confirmation_blocked',
        'emergency_911_preceded_transfer',
        'emergency_skipped_provider',
        'continuous_coverage_transferred_without_queue_or_time_promise',
        'automatic_stop_opened_one_incident',
        'both_admins_received_content_free_stop_and_handoff_alerts',
        'incident_resolution_did_not_reenable',
        'rollback_human_only_enabled',
        'rollback_confirmations_invalidated',
        'rollback_preserved_valid_records_and_receipts',
        'human_chat_available_throughout',
    ];

    /** @var list<string> */
    public const ACCESSIBILITY_CHECKS = [
        'zoom_200_without_overflow',
        'keyboard_and_focus_order',
        'screen_reader_names_and_states',
        'contrast',
        'primary_touch_targets_44px',
        'focus_return_after_error',
        'short_singular_questions',
        'safe_draft_survived_refresh_navigation_timeout_and_expiry',
    ];

    /** @var list<string> */
    private const TASKS = ['t1', 't2', 't3', 't4', 't5', 't6'];

    /** @var list<string> */
    private const COMPREHENSION = [
        'recap_understood',
        'live_is_not_hired_understood',
        'no_payment_authorization_understood',
        'human_transfer_understood',
        'draft_preserved',
    ];

    /** @return array{passed:bool,errors:list<string>,reference:?string,release_commit:?string,observation_count:int} */
    public function validateSafety(array $record, string $expectedCommit): array
    {
        $errors = [];
        $this->exactKeys($record, [
            'schema_version', 'rehearsal_reference', 'release_commit', 'environment',
            'conducted_at', 'operator_reference', 'observations',
        ], 'Safety record', $errors);

        $this->equals($record['schema_version'] ?? null, self::SAFETY_SCHEMA, 'Safety record schema is not recognized.', $errors);
        $this->safeReference($record['rehearsal_reference'] ?? null, 'SR-', 'Safety rehearsal reference is invalid.', $errors);
        $this->releaseCommit($record['release_commit'] ?? null, $expectedCommit, $errors);
        $this->equals($record['environment'] ?? null, 'synthetic', 'Safety rehearsal environment must be synthetic.', $errors);
        $this->pastTimestamp($record['conducted_at'] ?? null, 'Safety rehearsal timestamp is invalid or in the future.', $errors);
        if (! is_string($record['operator_reference'] ?? null)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{1,79}$/', $record['operator_reference']) !== 1) {
            $errors[] = 'Safety rehearsal operator reference is invalid.';
        }

        $observations = is_array($record['observations'] ?? null) ? $record['observations'] : [];
        if (! is_array($record['observations'] ?? null)) {
            $errors[] = 'Safety observations must be an object.';
        }
        $this->exactKeys($observations, self::SAFETY_OBSERVATIONS, 'Safety observations', $errors);
        foreach (self::SAFETY_OBSERVATIONS as $observation) {
            if (($observations[$observation] ?? null) !== true) {
                $errors[] = 'Required safety observation did not pass: '.$observation.'.';
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'reference' => is_string($record['rehearsal_reference'] ?? null) ? $record['rehearsal_reference'] : null,
            'release_commit' => is_string($record['release_commit'] ?? null) ? $record['release_commit'] : null,
            'observation_count' => count(array_filter(
                self::SAFETY_OBSERVATIONS,
                fn (string $key): bool => ($observations[$key] ?? null) === true,
            )),
        ];
    }

    /** @return array{passed:bool,errors:list<string>,reference:?string,release_commit:?string,participant_count:int,unassisted_tasks:int,total_tasks:int} */
    public function validateStudy(array $record, string $expectedCommit): array
    {
        $errors = [];
        $this->exactKeys($record, [
            'schema_version', 'study_reference', 'release_commit', 'conducted_from',
            'conducted_to', 'participants', 'accessibility',
        ], 'Study record', $errors);

        $this->equals($record['schema_version'] ?? null, self::STUDY_SCHEMA, 'Study record schema is not recognized.', $errors);
        $this->safeReference($record['study_reference'] ?? null, 'OA-STUDY-', 'Study reference is invalid.', $errors);
        $this->releaseCommit($record['release_commit'] ?? null, $expectedCommit, $errors);
        $from = $this->pastDate($record['conducted_from'] ?? null, 'Study start date is invalid or in the future.', $errors);
        $to = $this->pastDate($record['conducted_to'] ?? null, 'Study end date is invalid or in the future.', $errors);
        if ($from && $to && $to->lessThan($from)) {
            $errors[] = 'Study end date cannot precede its start date.';
        }

        $participants = is_array($record['participants'] ?? null) && array_is_list($record['participants'])
            ? $record['participants']
            : [];
        if (! is_array($record['participants'] ?? null) || ! array_is_list($record['participants'] ?? [])) {
            $errors[] = 'Study participants must be a list.';
        }
        if (count($participants) !== 5) {
            $errors[] = 'The study must contain exactly five participants.';
        }

        $participantIds = [];
        $age75Plus = 0;
        $lowConfidence = 0;
        $mobile = 0;
        $accessibilityUser = 0;
        $unassistedTasks = 0;
        foreach ($participants as $index => $participant) {
            $row = $index + 1;
            if (! is_array($participant)) {
                $errors[] = "Participant row {$row} must be an object.";

                continue;
            }
            $this->exactKeys($participant, [
                'participant_id', 'age_band', 'digital_confidence', 'primary_device',
                'accessibility_setting', 'team_member', 'tasks', 'comprehension',
            ], "Participant row {$row}", $errors);

            $participantId = $participant['participant_id'] ?? null;
            if (! is_string($participantId) || preg_match('/^OA-0[1-5]$/', $participantId) !== 1) {
                $errors[] = "Participant row {$row} has an invalid participant ID.";
            } else {
                $participantIds[] = $participantId;
            }

            $ageBand = $participant['age_band'] ?? null;
            if (! in_array($ageBand, ['65-74', '75-84', '85+'], true)) {
                $errors[] = "Participant row {$row} has an invalid age band.";
            } elseif ($ageBand !== '65-74') {
                $age75Plus++;
            }

            $confidence = $participant['digital_confidence'] ?? null;
            if (! in_array($confidence, ['low', 'medium', 'high'], true)) {
                $errors[] = "Participant row {$row} has an invalid digital-confidence value.";
            } elseif ($confidence === 'low') {
                $lowConfidence++;
            }

            $device = $participant['primary_device'] ?? null;
            if (! in_array($device, ['mobile', 'desktop'], true)) {
                $errors[] = "Participant row {$row} has an invalid primary device.";
            } elseif ($device === 'mobile') {
                $mobile++;
            }

            $setting = $participant['accessibility_setting'] ?? null;
            if (! in_array($setting, ['none', 'enlarged_text', 'screen_reader', 'keyboard', 'other'], true)) {
                $errors[] = "Participant row {$row} has an invalid accessibility setting.";
            } elseif ($setting !== 'none') {
                $accessibilityUser++;
            }

            if (($participant['team_member'] ?? null) !== false) {
                $errors[] = "Participant row {$row} must be a non-team participant.";
            }

            $tasks = is_array($participant['tasks'] ?? null) ? $participant['tasks'] : [];
            if (! is_array($participant['tasks'] ?? null)) {
                $errors[] = "Participant row {$row} tasks must be an object.";
            }
            $this->exactKeys($tasks, self::TASKS, "Participant row {$row} tasks", $errors);
            foreach (self::TASKS as $task) {
                $result = $tasks[$task] ?? null;
                if (! in_array($result, ['pass_unassisted', 'completed_with_assistance', 'not_completed'], true)) {
                    $errors[] = "Participant row {$row} has an invalid task result.";
                } elseif ($result === 'pass_unassisted') {
                    $unassistedTasks++;
                }
            }
            if (($tasks['t6'] ?? null) !== 'pass_unassisted') {
                $errors[] = "Participant row {$row} did not reach a person unassisted.";
            }

            $comprehension = is_array($participant['comprehension'] ?? null) ? $participant['comprehension'] : [];
            if (! is_array($participant['comprehension'] ?? null)) {
                $errors[] = "Participant row {$row} comprehension must be an object.";
            }
            $this->exactKeys($comprehension, self::COMPREHENSION, "Participant row {$row} comprehension", $errors);
            foreach (self::COMPREHENSION as $check) {
                if (($comprehension[$check] ?? null) !== true) {
                    $errors[] = "Participant row {$row} failed a universal comprehension or draft-preservation check.";

                    break;
                }
            }
        }

        if (count(array_unique($participantIds)) !== 5 || array_values(array_unique($participantIds)) !== ['OA-01', 'OA-02', 'OA-03', 'OA-04', 'OA-05']) {
            sort($participantIds);
            if (count(array_unique($participantIds)) !== 5 || $participantIds !== ['OA-01', 'OA-02', 'OA-03', 'OA-04', 'OA-05']) {
                $errors[] = 'Participant IDs must be the unique set OA-01 through OA-05.';
            }
        }
        if ($age75Plus < 2) {
            $errors[] = 'At least two participants must be age 75 or older.';
        }
        if ($lowConfidence < 2) {
            $errors[] = 'At least two participants must report low digital confidence.';
        }
        if ($mobile < 3) {
            $errors[] = 'At least three participants must primarily use mobile.';
        }
        if ($accessibilityUser < 1) {
            $errors[] = 'At least one participant must use an accessibility setting.';
        }
        if ($unassistedTasks < 27) {
            $errors[] = 'At least 27 of 30 tasks must pass unassisted.';
        }

        $accessibility = is_array($record['accessibility'] ?? null) ? $record['accessibility'] : [];
        if (! is_array($record['accessibility'] ?? null)) {
            $errors[] = 'Accessibility results must be an object.';
        }
        $this->exactKeys($accessibility, self::ACCESSIBILITY_CHECKS, 'Accessibility results', $errors);
        foreach (self::ACCESSIBILITY_CHECKS as $check) {
            if (($accessibility[$check] ?? null) !== true) {
                $errors[] = 'Required accessibility check did not pass: '.$check.'.';
            }
        }

        return [
            'passed' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'reference' => is_string($record['study_reference'] ?? null) ? $record['study_reference'] : null,
            'release_commit' => is_string($record['release_commit'] ?? null) ? $record['release_commit'] : null,
            'participant_count' => count($participants),
            'unassisted_tasks' => $unassistedTasks,
            'total_tasks' => count($participants) * count(self::TASKS),
        ];
    }

    /** @param list<string> $expected @param list<string> $errors */
    private function exactKeys(array $value, array $expected, string $label, array &$errors): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            $errors[] = $label.' has missing or unsupported fields.';
        }
    }

    /** @param list<string> $errors */
    private function equals(mixed $actual, string $expected, string $message, array &$errors): void
    {
        if ($actual !== $expected) {
            $errors[] = $message;
        }
    }

    /** @param list<string> $errors */
    private function safeReference(mixed $value, string $prefix, string $message, array &$errors): void
    {
        if (! is_string($value) || preg_match('/^'.preg_quote($prefix, '/').'[A-Za-z0-9._-]{3,80}$/', $value) !== 1) {
            $errors[] = $message;
        }
    }

    /** @param list<string> $errors */
    private function releaseCommit(mixed $actual, string $expected, array &$errors): void
    {
        if (! is_string($actual) || preg_match('/^[a-f0-9]{40}$/', $actual) !== 1) {
            $errors[] = 'Release commit must be a full 40-character lowercase Git commit.';

            return;
        }
        if (! hash_equals($expected, $actual)) {
            $errors[] = 'Release commit does not match the expected commit.';
        }
    }

    /** @param list<string> $errors */
    private function pastTimestamp(mixed $value, string $message, array &$errors): ?CarbonImmutable
    {
        if (! is_string($value)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            $errors[] = $message;

            return null;
        }
        try {
            $timestamp = CarbonImmutable::parse($value);
        } catch (Throwable) {
            $errors[] = $message;

            return null;
        }
        if ($timestamp->greaterThan(now()->addMinutes(5))) {
            $errors[] = $message;

            return null;
        }

        return $timestamp;
    }

    /** @param list<string> $errors */
    private function pastDate(mixed $value, string $message, array &$errors): ?CarbonImmutable
    {
        if (! is_string($value)) {
            $errors[] = $message;

            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            $errors[] = $message;

            return null;
        }
        if ($date === false || $date->format('Y-m-d') !== $value || $date->isAfter(today())) {
            $errors[] = $message;

            return null;
        }

        return $date;
    }
}
