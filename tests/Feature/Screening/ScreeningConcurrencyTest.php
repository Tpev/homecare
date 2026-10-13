<?php

namespace Tests\Feature\Screening;

use App\Jobs\RunCaregiverScreening;
use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverModerationLog;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\ScreeningAuditEvent;
use App\Models\User;
use App\Services\Screening\CaregiverScreeningService;
use App\Services\Screening\CaregiverWorkEligibility;
use App\Services\Screening\DiditVerifiedIdentityService;
use App\Services\Screening\ScreeningMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class ScreeningConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 30, 0));
        config([
            'services.didit.api_key' => 'synthetic-test-key',
            'services.didit.base_url' => 'https://didit.example.test',
            'services.didit.webhook_secret' => 'synthetic-webhook-secret',
        ]);
    }

    public function test_stale_scheduler_profile_does_not_requeue_after_an_admin_completed_the_review(): void
    {
        [$profile] = $this->verifiedProfile();
        $profile->update(['screening_initial_approved_at' => now()->subMonths(7), 'screening_next_due_at' => now()->subDay()]);
        CaregiverScreeningCase::create([
            'caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $profile->user_id,
            'kind' => 'legacy', 'status' => 'completed', 'decision' => 'approve',
            'due_at' => now()->subMonths(7), 'completed_at' => now()->subMonths(7),
        ]);
        $this->mock(ScreeningMatcher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('search')->once()->andReturn([
                'source_versions' => ['nc_dac' => 1, 'nc_sbi' => 2], 'candidates' => [], 'searched_at' => now()->toIso8601String(),
            ]);
            $mock->shouldReceive('assertUsableVersions')->once()->andReturnNull();
        });
        $service = app(CaregiverScreeningService::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $case = $service->sync($profile);
        $staleSchedulerProfile = $profile->fresh();
        $this->assertTrue($staleSchedulerProfile->screening_next_due_at->isPast());

        $service->claim($case->id, $admin);
        $searched = $service->search($case->id, $admin);
        $service->decide($case->id, $admin, 'no_match', 'approve', '', $searched->lock_version);

        $this->assertNull($service->sync($staleSchedulerProfile));
        $this->assertNull($service->sync($staleSchedulerProfile));
        $this->assertSame('2027-04-13', $profile->fresh()->screening_next_due_at->toDateString());
        $this->assertDatabaseCount('caregiver_screening_cases', 2);
        $this->assertSame(0, CaregiverScreeningCase::where('open_profile_id', $profile->id)->count());
        Queue::assertPushed(RunCaregiverScreening::class, 1);
    }

    public function test_reconciliation_does_not_overwrite_a_same_second_status_change_during_http_fetch(): void
    {
        [$profile, $verification] = $this->verifiedProfile();
        $originalUpdatedAt = $verification->updated_at->toDateTimeString();
        Http::fake(function () use ($verification, $originalUpdatedAt) {
            $verification->fresh()->update(['status' => 'declined']);
            $this->assertSame($originalUpdatedAt, $verification->fresh()->updated_at->toDateTimeString());

            return Http::response($this->approvedDecision('Stale', 'Identity'));
        });

        app(DiditVerifiedIdentityService::class)->reconcile($profile);

        $this->assertSame('declined', $verification->fresh()->status);
        $this->assertNull(app(DiditVerifiedIdentityService::class)->snapshot($profile));
        $this->assertSame(0, ScreeningAuditEvent::where('event_type', 'didit_reconciled')->count());
        Http::assertSentCount(1);
    }

    public function test_reconciliation_does_not_overwrite_same_second_provider_evidence_change_during_http_fetch(): void
    {
        [$profile, $verification] = $this->verifiedProfile();
        $originalUpdatedAt = $verification->updated_at->toDateTimeString();
        $newEvidence = $this->approvedDecision('Alex', 'Corrected');
        Http::fake(function () use ($verification, $originalUpdatedAt, $newEvidence) {
            $verification->fresh()->update(['provider_decision_payload' => $newEvidence]);
            $this->assertSame($originalUpdatedAt, $verification->fresh()->updated_at->toDateTimeString());

            return Http::response($this->approvedDecision('Stale', 'Identity'));
        });

        app(DiditVerifiedIdentityService::class)->reconcile($profile);

        $this->assertSame($newEvidence, $verification->fresh()->provider_decision_payload);
        $this->assertSame('Alex Corrected', app(DiditVerifiedIdentityService::class)->snapshot($profile)['full_name']);
        $this->assertSame(0, ScreeningAuditEvent::where('event_type', 'didit_reconciled')->count());
        Http::assertSentCount(1);
    }

    public function test_reconciliation_does_not_overwrite_same_second_webhook_change_during_http_fetch(): void
    {
        [$profile, $verification] = $this->verifiedProfile();
        $originalEvidence = $verification->provider_decision_payload;
        $originalUpdatedAt = $verification->updated_at->toDateTimeString();
        $newWebhook = ['session_id' => $verification->didit_session_id, 'status' => 'Approved', 'timestamp' => 1791887401];
        Http::fake(function () use ($verification, $originalUpdatedAt, $newWebhook) {
            $verification->fresh()->update(['webhook_payload' => $newWebhook]);
            $this->assertSame($originalUpdatedAt, $verification->fresh()->updated_at->toDateTimeString());

            return Http::response($this->approvedDecision('Stale', 'Identity'));
        });

        app(DiditVerifiedIdentityService::class)->reconcile($profile);

        $this->assertSame($newWebhook, $verification->fresh()->webhook_payload);
        $this->assertSame($originalEvidence, $verification->fresh()->provider_decision_payload);
        $this->assertSame(0, ScreeningAuditEvent::where('event_type', 'didit_reconciled')->count());
        Http::assertSentCount(1);
    }

    public function test_duplicate_and_older_webhooks_preserve_latest_provider_evidence_without_requeueing(): void
    {
        [$profile, $verification] = $this->verifiedProfile();
        $payload = ['session_id' => $verification->didit_session_id, 'status' => 'Approved', 'timestamp' => 1791887402,
            'decision' => $this->approvedDecision('Alex', 'Current')['decision']];

        $this->webhook($payload);
        $latestEvidence = $verification->fresh()->provider_decision_payload;
        $latestWebhook = $verification->fresh()->webhook_payload;
        $auditCount = ScreeningAuditEvent::count();
        $moderationCount = CaregiverModerationLog::count();
        $this->webhook($payload);
        $this->webhook(['session_id' => $verification->didit_session_id, 'status' => 'Declined', 'timestamp' => 1791887401,
            'decision' => ['id_verifications' => [['status' => 'Declined', 'first_name' => 'Older', 'last_name' => 'Record']]]]);

        $this->assertSame('approved', $verification->fresh()->status);
        $this->assertSame($latestEvidence, $verification->fresh()->provider_decision_payload);
        $this->assertSame($latestWebhook, $verification->fresh()->webhook_payload);
        $this->assertSame('Alex Current', app(DiditVerifiedIdentityService::class)->snapshot($profile)['full_name']);
        $this->assertSame($auditCount, ScreeningAuditEvent::count());
        $this->assertSame($moderationCount, CaregiverModerationLog::count());
        $this->assertDatabaseCount('caregiver_screening_cases', 1);
        Queue::assertPushed(RunCaregiverScreening::class, 1);
    }

    public function test_delayed_older_session_webhook_cannot_replace_current_identity_or_work_access(): void
    {
        [$profile, $oldVerification] = $this->verifiedProfile();
        $newVerification = $profile->identityVerifications()->create([
            'user_id' => $profile->user_id, 'didit_session_id' => 'current-session-'.$profile->id,
            'status' => 'approved', 'provider_confirmed_at' => now(), 'provider_decision_payload' => $this->approvedDecision('Alex', 'Current'),
        ]);
        $profile->update(['identity_verification_session_id' => $newVerification->didit_session_id]);
        $case = app(CaregiverScreeningService::class)->enqueue($profile->fresh());
        $before = $case->fresh()->identity_snapshot;

        $this->webhook(['session_id' => $oldVerification->didit_session_id, 'status' => 'Approved', 'timestamp' => 1791887500,
            'decision' => $this->approvedDecision('Older', 'Person')['decision']]);

        $this->assertSame($newVerification->didit_session_id, $profile->fresh()->identity_verification_session_id);
        $this->assertSame('Alex Current', app(DiditVerifiedIdentityService::class)->snapshot($profile->fresh())['full_name']);
        $this->assertSame($before, $case->fresh()->identity_snapshot);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $this->assertDatabaseCount('caregiver_screening_cases', 1);
        Queue::assertPushed(RunCaregiverScreening::class, 1);
    }

    /** @return array{CaregiverProfile, CaregiverIdentityVerification} */
    private function verifiedProfile(): array
    {
        $user = User::factory()->create(['role' => 'caregiver', 'name' => 'Editable profile name']);
        $profile = CaregiverProfile::create([
            'user_id' => $user->id, 'status' => 'active', 'screening_legacy_at' => now(), 'screening_legacy_access' => true,
            'identity_verification_status' => 'approved', 'identity_verified_at' => now(),
        ]);
        $verification = $profile->identityVerifications()->create([
            'user_id' => $user->id, 'didit_session_id' => 'verified-session-'.$profile->id, 'status' => 'approved',
            'provider_confirmed_at' => now(), 'provider_decision_payload' => $this->approvedDecision('Alex', 'Example'),
        ]);
        $profile->update(['identity_verification_session_id' => $verification->didit_session_id]);

        return [$profile->fresh(), $verification->fresh()];
    }

    private function approvedDecision(string $first, string $last): array
    {
        return ['status' => 'Approved', 'decision' => ['id_verifications' => [[
            'status' => 'Approved', 'first_name' => $first, 'last_name' => $last,
            'full_name' => $first.' '.$last, 'date_of_birth' => '1980-01-01',
        ]]]];
    }

    private function webhook(array $payload): void
    {
        $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), 'synthetic-webhook-secret');
        $this->postJson(route('webhooks.didit.identity'), $payload, ['X-Signature-V2' => $signature])->assertOk();
    }
}
