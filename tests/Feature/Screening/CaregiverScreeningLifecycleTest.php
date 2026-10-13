<?php

namespace Tests\Feature\Screening;

use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\ScreeningAuditEvent;
use App\Models\User;
use App\Services\Screening\CaregiverScreeningService;
use App\Services\Screening\CaregiverWorkEligibility;
use App\Services\Screening\DiditVerifiedIdentityService;
use App\Services\Screening\ScreeningAuditService;
use App\Services\Screening\ScreeningMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class CaregiverScreeningLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function profile(array $attributes = [], bool $verified = true): CaregiverProfile
    {
        $user = User::factory()->create(['role' => 'caregiver', 'name' => 'Editable display name']);
        $profile = CaregiverProfile::create(['user_id' => $user->id, 'status' => 'active',
            'screening_legacy_at' => now(), 'screening_legacy_access' => true,
            'identity_verification_status' => 'approved', 'identity_verified_at' => now(), ...$attributes]);
        if ($verified) {
            CaregiverIdentityVerification::create(['caregiver_profile_id' => $profile->id, 'user_id' => $user->id,
                'didit_session_id' => 'real-session-'.$profile->id, 'status' => 'approved', 'approved_at' => now(),
                'provider_confirmed_at' => now(), 'provider_decision_payload' => ['status' => 'Approved', 'decision' => [
                    'id_verifications' => [['status' => 'Approved', 'first_name' => 'Alex', 'last_name' => 'Example',
                        'full_name' => 'Alex Example', 'date_of_birth' => '1980-01-01']],
                ]]]);
        }

        return $profile;
    }

    private function mockSearch(array $candidates = []): void
    {
        $this->mock(ScreeningMatcher::class, function (MockInterface $mock) use ($candidates): void {
            $mock->shouldReceive('search')->andReturn(['source_versions' => ['nc_dac' => 1, 'nc_sbi' => 2],
                'source_coverage' => ['nc_sbi' => ['source_only_profiles' => 2, 'profiles_without_names' => 0]],
                'candidates' => $candidates, 'searched_at' => now()->toIso8601String()]);
            $mock->shouldReceive('assertUsableVersions')->andReturnNull();
        });
    }

    private function claimedSearch(CaregiverProfile $profile, User $admin): CaregiverScreeningCase
    {
        $service = app(CaregiverScreeningService::class);
        $case = $service->enqueue($profile);
        $service->claim($case->id, $admin);

        return $service->search($case->id, $admin);
    }

    public function test_cutover_preserves_only_existing_active_access_and_marks_all_legacy_profiles(): void
    {
        $active = $this->profile();
        $suspended = $this->profile(['status' => 'suspended']);
        $pending = $this->profile(['status' => 'under_review']);
        $migration = require database_path('migrations/2026_10_13_000000_create_caregiver_screening.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($active->fresh()->screening_legacy_access);
        $this->assertFalse($suspended->fresh()->screening_legacy_access);
        $this->assertFalse($pending->fresh()->screening_legacy_access);
        foreach ([$active, $suspended, $pending] as $profile) {
            $this->assertNotNull($profile->fresh()->screening_legacy_at);
            $this->assertSame($profile->status, $profile->fresh()->status);
            $this->assertNull($profile->fresh()->screening_initial_approved_at);
        }
        $new = CaregiverProfile::create(['user_id' => User::factory()->create(['role' => 'caregiver'])->id, 'status' => 'active']);
        $this->assertFalse(app(CaregiverWorkEligibility::class)->allowsNewWork($new->fresh()));
    }

    public function test_legacy_is_enqueued_once_without_changing_access_and_uses_verified_name(): void
    {
        $profile = $this->profile();
        $service = app(CaregiverScreeningService::class);
        $case = $service->enqueue($profile);
        $this->assertSame($case->id, $service->enqueue($profile)->id);
        $this->assertSame('legacy', $case->kind);
        $this->assertSame('Alex Example', $case->identity_snapshot['full_name']);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $this->assertDatabaseCount('caregiver_screening_cases', 1);
    }

    public function test_legacy_missing_didit_stays_available_but_cannot_be_screened(): void
    {
        $profile = $this->profile([], false);
        $case = app(CaregiverScreeningService::class)->enqueue($profile);
        $this->assertSame('awaiting_kyc', $case->status);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $this->assertNull($case->identity_snapshot);
    }

    public function test_kyc_expired_backfill_repairs_the_unknown_status_without_revoking_legacy_access(): void
    {
        $profile = $this->profile();
        $verifiedAt = $profile->identity_verified_at;
        $case = $this->backfillExpiredKyc($profile);

        $this->assertSame('legacy', $case->kind);
        $this->assertTrue($profile->fresh()->screening_legacy_access);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $this->assertTrue($verifiedAt->equalTo($profile->fresh()->identity_verified_at));
        $this->assertNull($profile->fresh()->screening_initial_approved_at);

        $this->artisan('screening:sync', ['--refresh-identities' => true])->assertSuccessful();
        $this->assertDatabaseCount('caregiver_screening_cases', 1);
        $this->assertSame('awaiting_kyc', $case->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_kyc_expired_backfill_does_not_approve_a_new_caregiver_from_an_old_approved_document(): void
    {
        $profile = $this->profile(['screening_legacy_at' => null, 'screening_legacy_access' => false]);
        $case = $this->backfillExpiredKyc($profile);

        $this->assertSame('initial', $case->kind);
        $this->assertFalse($profile->fresh()->screening_legacy_access);
        $this->assertNull($profile->fresh()->screening_initial_approved_at);
        $this->assertFalse(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
    }

    public function test_kyc_expired_backfill_does_not_block_an_approved_nonlegacy_caregivers_recurring_review(): void
    {
        $approvedAt = now()->subMonths(7)->startOfDay();
        $dueAt = now()->subMonth()->startOfDay();
        $profile = $this->profile(['screening_legacy_at' => null, 'screening_legacy_access' => false,
            'screening_initial_approved_at' => $approvedAt, 'screening_next_due_at' => $dueAt]);
        CaregiverScreeningCase::create(['caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $profile->user_id,
            'kind' => 'initial', 'status' => 'completed', 'decision' => 'approve', 'due_at' => $approvedAt, 'completed_at' => $approvedAt]);
        $case = $this->backfillExpiredKyc($profile);

        $this->assertSame('recurring', $case->kind);
        $this->assertFalse($profile->fresh()->screening_legacy_access);
        $this->assertTrue($approvedAt->equalTo($profile->fresh()->screening_initial_approved_at));
        $this->assertTrue($dueAt->equalTo($profile->fresh()->screening_next_due_at));
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
    }

    private function backfillExpiredKyc(CaregiverProfile $profile): CaregiverScreeningCase
    {
        config(['services.didit.api_key' => 'test-key', 'services.didit.base_url' => 'https://verification.didit.me']);
        $verification = $profile->latestIdentityVerification;
        $approvedAt = $verification->approved_at;
        $response = $verification->provider_decision_payload;
        $response['session_id'] = $verification->didit_session_id;
        $response['status'] = 'Kyc Expired';
        $verification->update(['status' => 'error']);
        $profile->update(['identity_verification_status' => 'error', 'identity_verification_session_id' => $verification->didit_session_id]);
        Http::preventStrayRequests();
        Http::fake(['https://verification.didit.me/v3/session/'.$verification->didit_session_id.'/decision/' => Http::response($response)]);

        $this->artisan('screening:sync', ['--refresh-identities' => true])->assertSuccessful();

        $this->assertSame(CaregiverIdentityVerification::STATUS_KYC_EXPIRED, $verification->fresh()->status);
        $this->assertSame(CaregiverIdentityVerification::STATUS_KYC_EXPIRED, $profile->fresh()->identity_verification_status);
        $this->assertSame($response, $verification->fresh()->provider_decision_payload);
        $this->assertNotNull($verification->fresh()->provider_confirmed_at);
        $this->assertTrue($approvedAt->equalTo($verification->fresh()->approved_at));
        $this->assertNull(app(DiditVerifiedIdentityService::class)->snapshot($profile->fresh()));
        $this->assertSame('active', $profile->fresh()->status);
        $this->assertNull($profile->fresh()->screening_suspended_at);
        $case = CaregiverScreeningCase::where('open_profile_id', $profile->id)->sole();
        $this->assertSame('awaiting_kyc', $case->status);
        $this->assertSame('pending', $case->search_status);
        $this->assertNull($case->identity_snapshot);
        $this->assertNull($case->outcome);
        $this->assertNull($case->decision);
        $event = ScreeningAuditEvent::where('event_type', 'didit_reconciled')->sole();
        $this->assertSame($profile->id, $event->payload['caregiver_profile_id']);
        $this->assertSame('kyc_expired', $event->payload['status']);
        Queue::assertNothingPushed();

        return $case;
    }

    public function test_admin_override_and_bypass_do_not_count_as_successful_didit(): void
    {
        $profile = $this->profile([], false);
        $verification = CaregiverIdentityVerification::create(['caregiver_profile_id' => $profile->id,
            'user_id' => $profile->user_id, 'didit_session_id' => 'bypass_123', 'status' => 'approved',
            'decision_payload' => ['status' => 'Approved', 'provider' => 'didit_bypass'], 'approved_at' => now()]);
        $this->assertNull(app(DiditVerifiedIdentityService::class)->snapshot($profile));
        $verification->update(['didit_session_id' => 'real-session', 'decision_payload' => ['source' => 'admin_override'],
            'webhook_payload' => ['source' => 'admin_override'], 'last_webhook_at' => now()]);
        $this->assertNull(app(DiditVerifiedIdentityService::class)->snapshot($profile));
    }

    public function test_recurring_due_overdue_and_potential_matches_never_block_work(): void
    {
        $this->mockSearch([['source' => 'nc_sbi', 'source_record_id' => '123', 'matched_name' => 'Alex Example',
            'match_reason' => 'Exact normalized name', 'record_snapshot' => ['id' => '123']]]);
        $profile = $this->profile(['screening_initial_approved_at' => now()->subMonths(7), 'screening_next_due_at' => now()->subMonth()]);
        CaregiverScreeningCase::create(['caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $profile->user_id,
            'kind' => 'initial', 'status' => 'completed', 'decision' => 'approve', 'due_at' => now()->subMonths(7), 'completed_at' => now()->subMonths(7)]);
        $service = app(CaregiverScreeningService::class);
        $case = $service->sync($profile);
        $this->assertSame('recurring', $case->kind);
        $service->runSearch($case->id);
        $this->assertSame(1, $case->candidates()->count());
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $service->sync($profile);
        $this->assertSame(1, CaregiverScreeningCase::where('open_profile_id', $profile->id)->count());
    }

    public function test_new_caregiver_requires_explicit_approval_and_six_calendar_month_due_date(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 31)->startOfDay());
        $this->mockSearch();
        $profile = $this->profile(['status' => 'under_review', 'screening_legacy_at' => null, 'screening_legacy_access' => false,
            'bio' => 'Caregiver biography', 'years_experience' => 3, 'service_area_zip' => '27601', 'service_radius_miles' => 20]);
        $skill = \App\Models\Skill::create(['name' => 'Companionship', 'slug' => 'companionship']);
        $language = \App\Models\Language::create(['name' => 'English', 'code' => 'en']);
        $profile->skills()->attach($skill->id);
        $profile->languages()->attach($language->id);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '17:00']);
        $admin = User::factory()->create(['role' => 'admin']);
        $case = $this->claimedSearch($profile, $admin);
        $this->assertSame('initial', $case->kind);
        $this->assertFalse(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        app(CaregiverScreeningService::class)->decide($case->id, $admin, 'no_match', 'approve', '', $case->lock_version);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $this->assertSame('2027-02-28', $profile->fresh()->screening_next_due_at->toDateString());
        $searchAudit = ScreeningAuditEvent::where('screening_case_id', $case->id)->where('event_type', 'search_completed')->firstOrFail();
        $this->assertSame(2, data_get($searchAudit->payload, 'source_coverage.nc_sbi.source_only_profiles'));
        $this->assertSame([], $searchAudit->payload['candidates']);
    }

    public function test_explicit_suspension_is_required_and_logged(): void
    {
        $this->mockSearch([['source' => 'nc_dac', 'source_record_id' => '11', 'matched_name' => 'Alex Example',
            'match_reason' => 'Normalized name', 'record_snapshot' => ['id' => '11']]]);
        $profile = $this->profile();
        $admin = User::factory()->create(['role' => 'admin']);
        $case = $this->claimedSearch($profile, $admin);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        app(CaregiverScreeningService::class)->decide($case->id, $admin, 'potential_match', 'suspend', 'Suspend pending identity comparison and investigation.', $case->lock_version);
        $this->assertFalse(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $this->assertDatabaseHas('screening_audit_events', ['screening_case_id' => $case->id, 'event_type' => 'screening_decided', 'actor_user_id' => $admin->id]);
        $this->assertSame('suspended', $profile->fresh()->status);
        $this->actingAs($profile->user)->get(route('caregiver.verification.show'))->assertOk();
    }

    public function test_stale_admin_version_cannot_finalize_a_case(): void
    {
        $this->mockSearch();
        $admin = User::factory()->create(['role' => 'admin']);
        $case = $this->claimedSearch($this->profile(), $admin);
        $this->expectException(ValidationException::class);
        app(CaregiverScreeningService::class)->decide($case->id, $admin, 'no_match', 'approve', '', $case->lock_version - 1);
    }

    public function test_claim_prevents_two_admins_deciding_and_expiry_allows_takeover(): void
    {
        $service = app(CaregiverScreeningService::class);
        $case = $service->enqueue($this->profile());
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $service->claim($case->id, $admin);
        try {
            $service->claim($case->id, $other);
            $this->fail('Concurrent claim was accepted.');
        } catch (ValidationException) {
            $this->assertSame($admin->id, $case->fresh()->claimed_by);
        }
        $this->travel(21)->minutes();
        $this->assertSame($other->id, $service->claim($case->id, $other)->claimed_by);
    }

    public function test_failed_search_is_not_no_match_and_does_not_block_legacy_access(): void
    {
        $this->mock(ScreeningMatcher::class, fn (MockInterface $mock) => $mock->shouldReceive('search')->andThrow(new \RuntimeException('Required source is unavailable.')));
        $profile = $this->profile();
        $admin = User::factory()->create(['role' => 'admin']);
        $case = $this->claimedSearch($profile, $admin);
        $this->assertSame('error', $case->search_status);
        $this->assertNull($case->outcome);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $this->expectException(ValidationException::class);
        app(CaregiverScreeningService::class)->decide($case->id, $admin, 'no_match', 'approve', '', $case->lock_version);
    }

    public function test_changed_verified_identity_requires_a_new_search(): void
    {
        $this->mockSearch();
        $profile = $this->profile();
        $admin = User::factory()->create(['role' => 'admin']);
        $case = $this->claimedSearch($profile, $admin);
        $verification = $profile->latestIdentityVerification;
        $payload = $verification->provider_decision_payload;
        $payload['decision']['id_verifications'][0]['full_name'] = 'Changed Name';
        $verification->update(['provider_decision_payload' => $payload]);
        $this->expectException(ValidationException::class);
        app(CaregiverScreeningService::class)->decide($case->id, $admin, 'no_match', 'approve', '', $case->lock_version);
    }

    public function test_audit_files_retry_without_duplicates_and_survive_profile_deletion(): void
    {
        $directory = storage_path('framework/testing/screening-audit-'.bin2hex(random_bytes(8)));
        config(['screening.audit_directory' => $directory]);
        try {
            $profile = $this->profile();
            $case = app(CaregiverScreeningService::class)->enqueue($profile);
            $audit = app(ScreeningAuditService::class);
            $audit->exportPending();
            $this->assertSame(0, $audit->exportPending());
            ScreeningAuditEvent::query()->update(['exported_at' => null]);
            $audit->exportPending();
            $files = File::files($directory);
            $this->assertCount(1, $files);
            $this->assertCount(1, file($files[0]->getPathname(), FILE_IGNORE_NEW_LINES));
            $profile->delete();
            $this->assertNull($case->fresh()->caregiver_profile_id);
            $this->assertDatabaseCount('screening_audit_events', 1);
        } finally {
            if (is_dir($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }

    public function test_audit_export_and_decisions_require_admin(): void
    {
        $profile = $this->profile();
        $case = app(CaregiverScreeningService::class)->enqueue($profile);
        $this->actingAs($profile->user)->get(route('admin.caregivers.screening.export', $case))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.caregivers.screening.export', $case))->assertOk()->assertHeader('Content-Type', 'application/x-ndjson');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CaregiverScreeningService::class)->claim($case->id, $profile->user);
    }

    public function test_explicit_suspension_can_preserve_an_unfinished_case_without_kyc_or_datasets(): void
    {
        $profile = $this->profile([], false);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(CaregiverScreeningService::class);
        $case = $service->enqueue($profile);
        $case = $service->claim($case->id, $admin);
        $service->suspendNewWork($case->id, $admin, 'Admin investigation requires a temporary new-work restriction.', $case->lock_version);
        $this->assertSame('awaiting_kyc', $case->fresh()->status);
        $this->assertNull($case->fresh()->outcome);
        $this->assertNull($case->fresh()->completed_at);
        $this->assertSame($case->id, $profile->fresh()->screening_suspension_case_id);
        $this->assertFalse(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
    }

    public function test_an_admin_can_explicitly_lift_a_legacy_suspension_after_screening(): void
    {
        $this->mockSearch();
        $profile = $this->profile(['status' => 'suspended']);
        $admin = User::factory()->create(['role' => 'admin']);
        $case = $this->claimedSearch($profile, $admin);
        app(CaregiverScreeningService::class)->decide($case->id, $admin, 'no_match', 'approve', 'The prior restriction was reviewed and is explicitly lifted.', $case->lock_version);
        $this->assertTrue(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
        $event = ScreeningAuditEvent::where('screening_case_id', $case->id)->where('event_type', 'screening_decided')->firstOrFail();
        $this->assertSame('suspended', $event->payload['before']['status']);
    }

    public function test_interrupted_audit_write_is_recovered_before_retry(): void
    {
        $directory = storage_path('framework/testing/screening-audit-'.bin2hex(random_bytes(8)));
        config(['screening.audit_directory' => $directory]);
        try {
            $profile = $this->profile();
            app(CaregiverScreeningService::class)->enqueue($profile);
            $audit = app(ScreeningAuditService::class);
            $audit->exportPending();
            $file = $directory.'/'.now()->utc()->format('Y-m-d').'.jsonl';
            file_put_contents($file, '{"event_id":"interrupted', FILE_APPEND);
            ScreeningAuditEvent::query()->update(['exported_at' => null]);
            $audit->exportPending();
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $this->assertCount(1, $lines);
            $this->assertIsArray(json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR));
            $this->assertCount(1, glob($directory.'/*.recovery'));
            if (PHP_OS_FAMILY !== 'Windows') {
                clearstatcache();
                $this->assertSame(02770, fileperms($directory) & 07777);
                $this->assertSame(0660, fileperms($file) & 07777);
                $this->assertSame(0660, fileperms(glob($directory.'/*.recovery')[0]) & 07777);
            }
        } finally {
            if (is_dir($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }

    public function test_existing_audit_line_must_be_synced_before_retry_is_acknowledged(): void
    {
        $directory = storage_path('framework/testing/screening-audit-'.bin2hex(random_bytes(8)));
        config(['screening.audit_directory' => $directory]);
        File::makeDirectory($directory, 0700, true);
        try {
            $audit = app(ScreeningAuditService::class);
            $event = $audit->record(null, null, 'durability_test');
            $event->update(['exported_at' => null]);
            $file = $directory.'/'.now()->utc()->format('Y-m-d').'.jsonl';
            // Simulate a prior write that reached the file but was not durably synced.
            file_put_contents($file, json_encode($audit->serialize($event), JSON_THROW_ON_ERROR));
            $retry = \Mockery::mock(ScreeningAuditService::class)->makePartial()->shouldAllowMockingProtectedMethods();
            $retry->shouldReceive('persistFile')->once()->andThrow(new \RuntimeException('Simulated disk sync failure.'));
            try {
                $retry->exportPending();
                $this->fail('A failed sync must leave the event retryable.');
            } catch (\RuntimeException $error) {
                $this->assertSame('Simulated disk sync failure.', $error->getMessage());
            }
            $this->assertNull($event->fresh()->exported_at);
            $this->assertSame(1, $audit->exportPending());
            $this->assertNotNull($event->fresh()->exported_at);
            $this->assertCount(1, file($file, FILE_IGNORE_NEW_LINES));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
