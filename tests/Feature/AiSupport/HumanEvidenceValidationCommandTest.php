<?php

namespace Tests\Feature\AiSupport;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HumanEvidenceValidationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_staffed_safety_record_passes_without_application_mutation(): void
    {
        $commit = $this->commitHash();
        $path = $this->writeRecord('safety.json', $this->validSafetyRecord($commit));

        $this->artisan('ai-support:validate-safety-rehearsal', [
            'record' => $path,
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('14 / 14')
            ->expectsOutputToContain('Application mutation')
            ->expectsOutputToContain('PASSED STRUCTURAL AND GATE VALIDATION')
            ->assertSuccessful();

        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
    }

    public function test_incomplete_or_wrong_commit_safety_record_is_blocked(): void
    {
        $commit = $this->commitHash();
        $record = $this->validSafetyRecord($commit);
        $record['release_commit'] = str_repeat('a', 40);
        $record['observations']['human_chat_available_throughout'] = false;
        $record['customer_transcript'] = 'must never be accepted or echoed';
        $path = $this->writeRecord('bad-safety.json', $record);

        $this->artisan('ai-support:validate-safety-rehearsal', [
            'record' => $path,
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('EVIDENCE RECORD BLOCKED')
            ->expectsOutputToContain('Release commit does not match')
            ->expectsOutputToContain('human_chat_available_throughout')
            ->doesntExpectOutputToContain('must never be accepted or echoed')
            ->assertFailed();

        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
    }

    public function test_qualifying_five_person_study_passes_at_exact_unassisted_threshold(): void
    {
        $commit = $this->commitHash();
        $record = $this->validStudyRecord($commit);
        $record['participants'][0]['tasks']['t1'] = 'completed_with_assistance';
        $record['participants'][0]['tasks']['t2'] = 'completed_with_assistance';
        $record['participants'][0]['tasks']['t3'] = 'completed_with_assistance';
        $path = $this->writeRecord('study.json', $record);

        $this->artisan('ai-support:validate-older-adult-study', [
            'record' => $path,
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('5 / 5')
            ->expectsOutputToContain('27 / 30')
            ->expectsOutputToContain('Universal comprehension/draft checks')
            ->expectsOutputToContain('Accessibility matrix')
            ->expectsOutputToContain('PASSED STRUCTURAL AND GATE VALIDATION')
            ->assertSuccessful();

        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
    }

    public function test_study_blocks_weak_cohort_comprehension_human_transfer_and_accessibility(): void
    {
        $commit = $this->commitHash();
        $record = $this->validStudyRecord($commit);
        foreach ($record['participants'] as &$participant) {
            $participant['age_band'] = '65-74';
            $participant['digital_confidence'] = 'high';
            $participant['primary_device'] = 'desktop';
            $participant['accessibility_setting'] = 'none';
        }
        unset($participant);
        $record['participants'][0]['tasks']['t6'] = 'completed_with_assistance';
        $record['participants'][1]['comprehension']['live_is_not_hired_understood'] = false;
        $record['accessibility']['screen_reader_names_and_states'] = false;
        $path = $this->writeRecord('bad-study.json', $record);

        $this->artisan('ai-support:validate-older-adult-study', [
            'record' => $path,
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('EVIDENCE RECORD BLOCKED')
            ->expectsOutputToContain('At least two participants must be age 75 or older')
            ->expectsOutputToContain('At least three participants must primarily use mobile')
            ->expectsOutputToContain('did not reach a person unassisted')
            ->expectsOutputToContain('screen_reader_names_and_states')
            ->assertFailed();

        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
    }

    public function test_shipped_pending_templates_cannot_be_mistaken_for_evidence(): void
    {
        $commit = $this->commitHash();
        $this->artisan('ai-support:validate-safety-rehearsal', [
            'record' => base_path('docs/product/support-agent/templates/safety-rehearsal-record.template.json'),
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('EVIDENCE RECORD BLOCKED')
            ->assertFailed();

        $this->artisan('ai-support:validate-older-adult-study', [
            'record' => base_path('docs/product/support-agent/templates/older-adult-study-record.template.json'),
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('EVIDENCE RECORD BLOCKED')
            ->assertFailed();
    }

    /** @return array<string,mixed> */
    private function validSafetyRecord(string $commit): array
    {
        return [
            'schema_version' => 'ai-support-safety-rehearsal-v1',
            'rehearsal_reference' => 'SR-2026-08-15-A',
            'release_commit' => $commit,
            'environment' => 'synthetic',
            'conducted_at' => now()->subMinute()->toIso8601String(),
            'operator_reference' => 'Operator 1',
            'observations' => [
                'active_recap_before_takeover' => true,
                'human_takeover_before_automated_reply' => true,
                'pending_recap_invalidated' => true,
                'stale_confirmation_blocked' => true,
                'emergency_911_preceded_transfer' => true,
                'emergency_skipped_provider' => true,
                'continuous_coverage_transferred_without_queue_or_time_promise' => true,
                'automatic_stop_opened_one_incident' => true,
                'both_admins_received_content_free_stop_and_handoff_alerts' => true,
                'incident_resolution_did_not_reenable' => true,
                'rollback_human_only_enabled' => true,
                'rollback_confirmations_invalidated' => true,
                'rollback_preserved_valid_records_and_receipts' => true,
                'human_chat_available_throughout' => true,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function validStudyRecord(string $commit): array
    {
        $participants = [];
        foreach (range(1, 5) as $number) {
            $participants[] = [
                'participant_id' => 'OA-0'.$number,
                'age_band' => $number <= 2 ? '75-84' : '65-74',
                'digital_confidence' => $number <= 2 ? 'low' : 'medium',
                'primary_device' => $number <= 3 ? 'mobile' : 'desktop',
                'accessibility_setting' => $number === 1 ? 'enlarged_text' : 'none',
                'team_member' => false,
                'tasks' => array_fill_keys(['t1', 't2', 't3', 't4', 't5', 't6'], 'pass_unassisted'),
                'comprehension' => [
                    'recap_understood' => true,
                    'live_is_not_hired_understood' => true,
                    'no_payment_authorization_understood' => true,
                    'human_transfer_understood' => true,
                    'draft_preserved' => true,
                ],
            ];
        }

        return [
            'schema_version' => 'ai-support-older-adult-study-v1',
            'study_reference' => 'OA-STUDY-2026-08-15-A',
            'release_commit' => $commit,
            'conducted_from' => today()->subDay()->format('Y-m-d'),
            'conducted_to' => today()->format('Y-m-d'),
            'participants' => $participants,
            'accessibility' => [
                'zoom_200_without_overflow' => true,
                'keyboard_and_focus_order' => true,
                'screen_reader_names_and_states' => true,
                'contrast' => true,
                'primary_touch_targets_44px' => true,
                'focus_return_after_error' => true,
                'short_singular_questions' => true,
                'safe_draft_survived_refresh_navigation_timeout_and_expiry' => true,
            ],
        ];
    }

    /** @param array<string,mixed> $record */
    private function writeRecord(string $name, array $record): string
    {
        Storage::fake('local');
        Storage::disk('local')->put($name, json_encode($record, JSON_THROW_ON_ERROR));

        return Storage::disk('local')->path($name);
    }

    private function commitHash(): string
    {
        $result = Process::path(base_path())->run(['git', 'rev-parse', 'HEAD']);
        $this->assertTrue($result->successful());

        return trim($result->output());
    }
}
