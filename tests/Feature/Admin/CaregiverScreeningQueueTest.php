<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CaregiverReviewsQueue;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\Language;
use App\Models\ScreeningBookingReview;
use App\Models\ScreeningDatasetImport;
use App\Models\ScreeningRecord;
use App\Models\ScreeningRecordName;
use App\Models\Skill;
use App\Models\User;
use App\Services\Screening\DiditVerifiedIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverScreeningQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_screening_component_rejects_non_admins_even_without_route_middleware(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'family']))
            ->test(CaregiverReviewsQueue::class)->assertForbidden();
    }

    public function test_queue_renders_legacy_access_identity_candidates_and_escaped_source_evidence(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_at' => now(), 'screening_legacy_access' => true]);
        $case->candidates()->create([
            'source' => 'nc_dac', 'source_record_id' => 'OFFENDER-17', 'matched_name' => 'Jane Example',
            'match_reason' => 'Exact normalized name',
            'record_snapshot' => ['profile' => ['name' => '<script>alert(1)</script>', 'date_of_birth' => '1980-01-02',
                'table' => 'OFNT3AA1', 'fields' => ['CMCLBRTH' => '1980-01-02']],
                'field_labels' => ['OFNT3AA1' => ['CMCLBRTH' => 'Date of birth as published']]],
        ]);
        ScreeningDatasetImport::create([
            'source' => 'nc_dac', 'status' => 'ready', 'is_active' => true, 'record_count' => 100,
            'downloaded_at' => now(), 'validated_at' => now(), 'activated_at' => now(),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->assertSee('Caregiver screening')->assertSee('Legacy work access preserved')
            ->assertSee('Six-month reviews never automatically block work')
            ->assertSee('Didit-verified identity')->assertSee('Jane Example')
            ->assertSee('OFFENDER-17')->assertSee('Exact normalized name')
            ->assertSee('Date of birth as published')->assertSee('CMCLBRTH')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('No validated import')->assertSee('100 source IDs')
            ->assertDontSee('toggleIdentityVerification')->assertDontSee('toggleBackgroundCheck')
            ->assertSee(route('admin.caregivers.screening.export', $case), false);
    }

    public function test_filters_and_search_select_the_correct_case_without_claiming_or_changing_access(): void
    {
        $initial = $this->makeCase('initial', ['status' => 'under_review']);
        $legacy = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $recurring = $this->makeCase('recurring', ['screening_initial_approved_at' => now()->subMonths(7)]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->set('filter', 'recurring')->assertSet('selectedId', $recurring->id)
            ->set('filter', 'legacy')->assertSet('selectedId', $legacy->id)
            ->set('filter', 'initial')->assertSet('selectedId', $initial->id)
            ->set('query', 'not-a-caregiver')->assertSet('selectedId', null);

        $this->assertNull($legacy->fresh()->claimed_by);
        $this->assertSame('active', $legacy->profile->fresh()->status);
        $this->assertSame('under_review', $initial->profile->fresh()->status);
    }

    public function test_source_coverage_limits_and_missing_primary_candidate_are_prominent_and_escaped(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $coverage = [
            'profile_count' => 103, 'primary_profiles' => 100, 'source_only_profiles' => 3,
            'searchable_profiles' => 101, 'profiles_without_names' => 2,
            'limitations' => ['Source note: <script>unsafe()</script>'],
        ];
        $import = ScreeningDatasetImport::create([
            'source' => 'nc_sbi', 'status' => 'ready', 'is_active' => true, 'record_count' => 103,
            'downloaded_at' => now(), 'validated_at' => now(), 'activated_at' => now(),
            'file_manifest' => ['name_coverage' => $coverage],
        ]);
        $case->update(['search_status' => 'completed', 'source_versions' => ['nc_sbi' => $import->id]]);
        $case->candidates()->create([
            'source' => 'nc_sbi', 'source_record_id' => 'source-only-17', 'matched_name' => 'Jane Example',
            'match_reason' => 'Exact normalized source alias. Identity requires admin confirmation.',
            'record_snapshot' => [
                'profile' => ['missing_primary_record' => true], 'missing_primary_record' => true,
                'coverage_warning' => 'Alias evidence only. <img src=x onerror=unsafe()>', 'source_coverage' => $coverage,
            ],
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->assertSee('Ready · source coverage limits')
            ->assertSee('101 of 103 source IDs have an available name for matching.')
            ->assertSee('3 source IDs have supporting records but no primary individual record.')
            ->assertSee('2 source IDs have no available name and cannot be matched by name.')
            ->assertSee('NC SBI · searched version #'.$import->id.' coverage')
            ->assertSee('Primary individual record missing from this source')
            ->assertSee('Alias evidence only. &lt;img src=x onerror=unsafe()&gt;', false)
            ->assertSee('Source note: &lt;script&gt;unsafe()&lt;/script&gt;', false)
            ->assertDontSee('<img src=x onerror=unsafe()>', false)
            ->assertDontSee('<script>unsafe()</script>', false);
    }

    public function test_completed_no_match_keeps_historical_source_coverage_visible_after_a_new_import(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $old = ScreeningDatasetImport::create([
            'source' => 'nc_sbi', 'status' => 'ready', 'is_active' => false, 'record_count' => 10,
            'downloaded_at' => now()->subDay(), 'validated_at' => now()->subDay(), 'activated_at' => now()->subDay(),
            'file_manifest' => ['name_coverage' => [
                'profile_count' => 10, 'primary_profiles' => 10, 'source_only_profiles' => 0,
                'searchable_profiles' => 9, 'profiles_without_names' => 1, 'limitations' => [],
            ]],
        ]);
        ScreeningDatasetImport::create([
            'source' => 'nc_sbi', 'status' => 'ready', 'is_active' => true, 'record_count' => 10,
            'downloaded_at' => now(), 'validated_at' => now(), 'activated_at' => now(),
            'file_manifest' => ['name_coverage' => [
                'profile_count' => 10, 'primary_profiles' => 10, 'source_only_profiles' => 0,
                'searchable_profiles' => 10, 'profiles_without_names' => 0, 'limitations' => [],
            ]],
        ]);
        $case->update([
            'status' => 'completed', 'open_profile_id' => null, 'outcome' => 'no_match', 'decision' => 'approve',
            'completed_at' => now()->subDay(), 'search_status' => 'completed', 'source_versions' => ['nc_sbi' => $old->id],
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->set('filter', 'completed')
            ->assertSee('NC SBI · searched version #'.$old->id.' coverage')
            ->assertSee('9 of 10 source IDs have an available name for matching.')
            ->assertSee('1 source ID has no available name and cannot be matched by name.')
            ->assertSee('No available name matched in the imported source versions.')
            ->assertSee('No Match is limited to the available names in this imported source version.')
            ->assertDontSee('Primary individual record missing from this source');
    }

    public function test_claim_and_keep_pending_preserve_legacy_access_and_move_to_next_case(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $next = $this->makeCase('initial', ['status' => 'under_review']);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', $case->id)
            ->call('claim')->assertHasNoErrors()
            ->set('note', 'Compare birth date against the source documentation.')
            ->call('keepPending')->assertHasNoErrors()
            ->assertSet('selectedId', $next->id);

        $this->assertSame('awaiting_investigation', $case->fresh()->status);
        $this->assertNull($case->fresh()->claimed_by);
        $this->assertSame('active', $case->profile->fresh()->status);
        $this->assertNull($case->profile->fresh()->screening_suspended_at);
        $this->assertDatabaseHas('screening_audit_events', ['screening_case_id' => $case->id, 'event_type' => 'case_deferred']);
    }

    public function test_stale_selected_version_cannot_defer_a_changed_case(): void
    {
        $case = $this->makeCase('recurring', ['screening_initial_approved_at' => now()->subMonths(7)]);
        $admin = User::factory()->create(['role' => 'admin']);
        $component = Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)->call('claim');
        $case->fresh()->increment('lock_version');

        $component->set('note', 'Investigate this candidate further.')->call('keepPending')
            ->assertHasErrors(['screening'])->assertSee('This case changed');
        $this->assertSame('in_review', $case->fresh()->status);
        $this->assertSame('active', $case->profile->fresh()->status);
    }

    public function test_another_admins_claim_cannot_be_taken_over_until_it_expires(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $owner = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $case->update(['claimed_by' => $owner->id, 'claimed_until' => now()->addMinutes(10), 'status' => 'in_review']);

        Livewire::actingAs($other)->test(CaregiverReviewsQueue::class)->call('claim')
            ->assertHasErrors(['screening'])->assertSee('Another administrator is reviewing this case');
        $this->assertSame($owner->id, $case->fresh()->claimed_by);
    }

    public function test_pending_review_requires_a_note_and_never_implies_no_match(): void
    {
        $case = $this->makeCase('initial', ['status' => 'under_review']);
        $case->update(['status' => 'awaiting_kyc', 'identity_snapshot' => null]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->assertSee('Awaiting successful Didit KYC')
            ->assertSee('An uncompleted search is not a No Match result.')
            ->call('claim')->call('keepPending')->assertHasErrors(['note'])
            ->set('note', 'Successful provider verification is still required.')
            ->call('keepPending')->assertHasNoErrors();

        $this->assertSame('awaiting_kyc', $case->fresh()->status);
        $this->assertNull($case->fresh()->outcome);
        $this->assertSame('under_review', $case->profile->fresh()->status);
    }

    public function test_admin_can_explicitly_suspend_legacy_work_without_kyc_or_datasets_while_review_stays_open(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_at' => now(), 'screening_legacy_access' => true]);
        $case->update(['status' => 'awaiting_kyc', 'identity_snapshot' => null]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)->call('claim')
            ->set('note', 'Admin investigated an urgent concern and explicitly restricted new work.')
            ->call('suspendNewWork')->assertHasNoErrors()->assertSet('selectedId', $case->id)
            ->assertSee('New work suspended')->assertDontSee('Suspend new work now');

        $this->assertSame('awaiting_kyc', $case->fresh()->status);
        $this->assertNull($case->fresh()->outcome);
        $this->assertNull($case->fresh()->completed_at);
        $this->assertSame('suspended', $case->profile->fresh()->status);
        $this->assertNotNull($case->profile->fresh()->screening_suspended_at);
    }

    public function test_booking_review_resolution_is_recorded_and_cannot_be_repeated(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $review = ScreeningBookingReview::create([
            'screening_case_id' => $case->id, 'subject_type' => 'booking', 'subject_id' => 9001,
            'status' => 'open', 'reason' => 'An existing commitment requires an explicit review.',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $component = Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->assertSee('1 awaiting review')
            ->set('bookingResolutions.'.$review->id, 'reviewed_no_change')
            ->set('bookingNotes.'.$review->id, 'Reviewed the existing agreement and retained the booking.')
            ->call('resolveBookingReview', $review->id)->assertHasNoErrors()
            ->assertSee('0 awaiting review');

        $this->assertSame('resolved', $review->fresh()->status);
        $this->assertSame($admin->id, $review->fresh()->resolved_by);
        $this->assertDatabaseHas('screening_audit_events', ['screening_case_id' => $case->id, 'event_type' => 'booking_review_resolved']);

        $component->set('bookingResolutions.'.$review->id, 'action_taken')
            ->set('bookingNotes.'.$review->id, 'A different administrator attempted a second resolution.')
            ->call('resolveBookingReview', $review->id)->assertHasErrors(['screening']);
        $this->assertSame('reviewed_no_change', $review->fresh()->resolution);
    }

    public function test_approve_and_next_completes_an_initial_screening_after_real_search(): void
    {
        $case = $this->makeCase('initial', ['status' => 'under_review']);
        $this->prepareSearch($case);
        $profile = $case->profile;
        $profile->update([
            'bio' => 'Experienced caregiver ready to help.', 'years_experience' => 5,
            'service_area_zip' => '27601', 'service_radius_miles' => 15,
            'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
        ]);
        $profile->skills()->attach(Skill::create(['name' => 'Companionship'])->id);
        $profile->languages()->attach(Language::create(['name' => 'English'])->id);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00']);
        $next = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->call('claim')->call('search')->assertHasNoErrors()
            ->assertSee('No available name matched in the imported source versions')
            ->set('outcome', 'no_match')
            ->call('decide', 'approve')->assertHasNoErrors()->assertSet('selectedId', $next->id);

        $this->assertSame('completed', $case->fresh()->status);
        $this->assertSame('approve', $case->fresh()->decision);
        $this->assertSame('active', $profile->fresh()->status);
        $this->assertNotNull($profile->fresh()->screening_initial_approved_at);
        $this->assertNotNull($profile->fresh()->screening_next_due_at);
    }

    public function test_explicit_suspend_and_next_records_candidate_outcome_then_opens_a_follow_up_without_reactivation(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true, 'screening_legacy_at' => now()]);
        $import = $this->prepareSearch($case);
        $record = ScreeningRecord::create(['import_id' => $import->id, 'record_type' => 'profile', 'source_record_id' => '17', 'data' => ['name' => 'Jane Example']]);
        ScreeningRecordName::create([
            'import_id' => $import->id, 'record_id' => $record->id, 'display_name' => 'Jane Example',
            'normalized_name' => 'jane example', 'first_name' => 'jane', 'last_name' => 'example', 'is_alias' => false,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $component = Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->call('claim')->call('search')->assertHasNoErrors()
            ->set('outcome', 'potential_match')->set('note', 'Record #17 requires further identity investigation; suspend new work.')
            ->call('decide', 'suspend')->assertHasNoErrors()->assertSet('selectedId', null);

        $this->assertSame('potential_match', $case->fresh()->outcome);
        $this->assertSame('suspend', $case->fresh()->decision);
        $this->assertSame('suspended', $case->profile->fresh()->status);
        $this->assertNotNull($case->profile->fresh()->screening_suspended_at);

        $component->set('filter', 'completed')->assertSet('selectedId', $case->id)
            ->call('startFollowUp')->assertHasNoErrors()->assertSet('filter', 'open');
        $this->assertSame('suspended', $case->profile->fresh()->status);
        $this->assertDatabaseCount('caregiver_screening_cases', 2);
    }

    private function prepareSearch(CaregiverScreeningCase $case): ScreeningDatasetImport
    {
        $case->profile->identityVerifications()->create([
            'user_id' => $case->caregiver_user_id, 'didit_session_id' => 'screening-ui-session-'.$case->id,
            'status' => 'approved', 'provider_confirmed_at' => now(),
            'provider_decision_payload' => ['status' => 'Approved', 'id_verifications' => [[
                'status' => 'Approved', 'first_name' => 'Jane', 'last_name' => 'Example', 'date_of_birth' => '1980-01-02',
            ]]],
        ]);
        $identity = app(DiditVerifiedIdentityService::class)->snapshot($case->profile);
        $case->update(['identity_snapshot' => $identity, 'identity_verification_id' => $identity['verification_id']]);
        foreach (['nc_sbi', 'nc_dac'] as $source) {
            $import = ScreeningDatasetImport::create([
                'source' => $source, 'status' => 'ready', 'is_active' => true, 'record_count' => 1,
                'downloaded_at' => now(), 'validated_at' => now(), 'activated_at' => now(),
            ]);
        }

        return $import;
    }

    private function makeCase(string $kind, array $profileAttributes = []): CaregiverScreeningCase
    {
        $user = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::create(['user_id' => $user->id, 'status' => 'active', ...$profileAttributes]);

        return CaregiverScreeningCase::create([
            'caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $user->id, 'open_profile_id' => $profile->id,
            'kind' => $kind, 'status' => 'queued', 'due_at' => now(), 'lock_version' => 0,
            'identity_snapshot' => ['full_name' => 'Jane Example', 'first_name' => 'Jane', 'last_name' => 'Example', 'date_of_birth' => '1980-01-02'],
            'search_status' => 'pending',
        ]);
    }
}
