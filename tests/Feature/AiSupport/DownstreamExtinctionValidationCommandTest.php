<?php

namespace Tests\Feature\AiSupport;

use App\Services\AiSupport\AiSupportDownstreamExtinctionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownstreamExtinctionValidationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_scoped_inventory_and_restore_record_passes_without_mutation(): void
    {
        $commit = $this->commitHash();
        $path = $this->writeRecord('extinction.json', $this->validRecord($commit));

        $this->artisan('ai-support:validate-downstream-extinction', [
            'record' => $path,
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('18 / 18')
            ->expectsOutputToContain('6 / 6')
            ->expectsOutputToContain('Application mutation')
            ->expectsOutputToContain('PASSED STRUCTURAL AND GATE VALIDATION')
            ->assertSuccessful();

        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
        $this->assertDatabaseCount('ai_support_pilot_grants', 0);
        $this->assertDatabaseCount('ai_support_control_versions', 0);
    }

    public function test_pending_duplicate_or_failed_restore_record_is_blocked_without_echoing_extra_content(): void
    {
        $commit = $this->commitHash();
        $record = $this->validRecord($commit);
        $record['destinations'][2]['status'] = 'controlled_expiry_pending';
        $record['destinations'][17]['category'] = 'primary_database';
        $record['restore_redeletion_rehearsal']['checks']['human_support_available_after_release'] = false;
        $record['customer_content'] = 'must never be accepted or echoed';
        $path = $this->writeRecord('bad-extinction.json', $record);

        $this->artisan('ai-support:validate-downstream-extinction', [
            'record' => $path,
            '--expected-commit' => $commit,
        ])->expectsOutputToContain('EVIDENCE RECORD BLOCKED')
            ->expectsOutputToContain('pending or has an invalid extinction status')
            ->expectsOutputToContain('Every scope/category combination must appear exactly once')
            ->expectsOutputToContain('human_support_available_after_release')
            ->doesntExpectOutputToContain('must never be accepted or echoed')
            ->assertFailed();

        $this->assertDatabaseCount('ai_support_readiness_evidence', 0);
    }

    public function test_shipped_pending_extinction_template_cannot_pass(): void
    {
        $this->artisan('ai-support:validate-downstream-extinction', [
            'record' => base_path('docs/product/support-agent/templates/downstream-extinction-record.template.json'),
            '--expected-commit' => $this->commitHash(),
        ])->expectsOutputToContain('EVIDENCE RECORD BLOCKED')
            ->assertFailed();
    }

    /** @return array<string,mixed> */
    private function validRecord(string $commit): array
    {
        $checkedAt = now()->subMinute()->toIso8601String();
        $destinations = [];
        $number = 1;
        foreach (AiSupportDownstreamExtinctionValidator::SCOPES as $scope) {
            foreach (AiSupportDownstreamExtinctionValidator::DESTINATION_CATEGORIES as $category) {
                $status = 'not_present';
                if ($category === 'primary_database') {
                    $status = $scope === 'current_ai_support' ? 'verified_zero' : 'destroyed';
                }
                $destinations[] = [
                    'scope' => $scope,
                    'category' => $category,
                    'status' => $status,
                    'checked_at' => $checkedAt,
                    'evidence_reference' => 'EVD-SYNTHETIC-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                ];
                $number++;
            }
        }

        return [
            'schema_version' => AiSupportDownstreamExtinctionValidator::SCHEMA,
            'evidence_reference' => 'EXT-SYNTHETIC-2026-08-15',
            'release_commit' => $commit,
            'observed_at' => $checkedAt,
            'operator_reference' => 'Operator 1',
            'destinations' => $destinations,
            'restore_redeletion_rehearsal' => [
                'rehearsal_reference' => 'RESTORE-SYNTHETIC-2026-08-15',
                'performed_at' => $checkedAt,
                'environment' => 'isolated',
                'checks' => array_fill_keys(AiSupportDownstreamExtinctionValidator::RESTORE_CHECKS, true),
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
