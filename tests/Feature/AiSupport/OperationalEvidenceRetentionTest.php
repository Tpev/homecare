<?php

namespace Tests\Feature\AiSupport;

use App\Console\Commands\ApplyAiSupportRetention;
use App\Models\AiSupportIncident;
use App\Models\AiSupportReadinessEvidence;
use App\Models\AiSupportReleaseDecision;
use App\Models\DataRetentionHold;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalEvidenceRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_and_execution_prune_expired_evidence_but_preserve_active_and_held_records(): void
    {
        $dueIncident = $this->incident();
        $openIncident = $this->incident(['status' => 'open', 'resolved_at' => null]);
        $heldIncident = $this->incident();
        $dueReadiness = $this->readiness();
        $heldReadiness = $this->readiness();
        $recentReadiness = $this->readiness(['retain_until' => now()->addDay()]);
        $dueRelease = $this->release();
        $activeRelease = $this->release(['expires_at' => now()->addDay()]);
        $heldRelease = $this->release();
        foreach ([$heldIncident, $heldReadiness, $heldRelease] as $record) {
            DataRetentionHold::query()->create([
                'id' => (string) Str::uuid(), 'scope_type' => $record::class, 'scope_id' => (string) $record->id,
                'reason_category' => 'security', 'authority_reference' => 'Synthetic-retention-hold',
                'starts_at' => now()->subDay(), 'review_at' => now()->addDay(), 'created_at' => now(),
            ]);
        }

        $this->artisan('ai-support:apply-retention')->expectsOutputToContain('Dry run only')->assertSuccessful();
        $this->assertDatabaseCount('ai_support_incidents', 3);
        $this->assertDatabaseCount('ai_support_readiness_evidence', 3);
        $this->assertDatabaseCount('ai_support_release_decisions', 3);
        $this->assertNull(Cache::get(ApplyAiSupportRetention::HEARTBEAT_KEY));
        $this->assertDatabaseCount('data_deletion_evidence', 0);

        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();
        foreach ([$dueIncident, $dueReadiness, $dueRelease] as $record) {
            $this->assertNull($record->fresh());
        }
        foreach ([$openIncident, $heldIncident, $heldReadiness, $recentReadiness, $activeRelease, $heldRelease] as $record) {
            $this->assertNotNull($record->fresh());
        }
        foreach (['resolved_incident_evidence', 'readiness_evidence', 'inactive_release_decisions'] as $class) {
            $this->assertDatabaseHas('data_deletion_evidence', ['data_class' => $class, 'record_count' => 1, 'result' => 'passed']);
        }
        $this->assertSame('succeeded', Cache::get(ApplyAiSupportRetention::HEARTBEAT_KEY)['status']);
        $this->assertStringNotContainsString('Synthetic evidence content', json_encode(DB::table('data_deletion_evidence')->get()));
        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();
        $this->assertDatabaseCount('data_deletion_evidence', 3);
    }

    public function test_successful_zero_work_execution_still_records_a_heartbeat(): void
    {
        $this->artisan('ai-support:apply-retention --execute')->assertSuccessful();
        $this->assertSame('succeeded', Cache::get(ApplyAiSupportRetention::HEARTBEAT_KEY)['status']);
        $this->assertNotEmpty(Cache::get(ApplyAiSupportRetention::HEARTBEAT_KEY)['last_success_at']);
        $this->assertDatabaseCount('data_deletion_evidence', 0);
    }

    private function incident(array $attributes = []): AiSupportIncident
    {
        return AiSupportIncident::query()->create([
            'id' => (string) Str::uuid(), 'reason_code' => 'synthetic_expired', 'severity' => 'critical',
            'status' => 'resolved', 'summary' => 'Synthetic evidence content', 'opened_at' => now()->subYears(3),
            'resolved_at' => now()->subYears(2), 'retain_until' => now()->subDay(), ...$attributes,
        ]);
    }

    private function readiness(array $attributes = []): AiSupportReadinessEvidence
    {
        return AiSupportReadinessEvidence::query()->create([
            'id' => (string) Str::uuid(), 'evidence_key' => 'synthetic_'.Str::random(10), 'version' => 1,
            'status' => 'passed', 'summary' => 'Synthetic evidence content', 'observed_at' => now()->subYears(3),
            'expires_at' => now()->subYears(2), 'retain_until' => now()->subDay(), 'created_at' => now()->subYears(3), ...$attributes,
        ]);
    }

    private function release(array $attributes = []): AiSupportReleaseDecision
    {
        return AiSupportReleaseDecision::query()->create([
            'id' => (string) Str::uuid(), 'scope' => 'initial_pilot', 'policy_version' => 'synthetic-policy',
            'status' => 'approved', 'reason' => 'Synthetic evidence content', 'release_commit' => str_repeat('a', 40),
            'snapshot_sha256' => str_repeat('b', 64), 'approved_user_ids' => [],
            'starts_at' => now()->subYears(3), 'expires_at' => now()->subYears(2),
            'retain_until' => now()->subDay(), 'created_at' => now()->subYears(3), ...$attributes,
        ]);
    }
}
