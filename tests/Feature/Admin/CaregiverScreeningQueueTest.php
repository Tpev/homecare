<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CaregiverReviewsQueue;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\Language;
use App\Models\ScreeningAuditEvent;
use App\Models\ScreeningBookingReview;
use App\Models\ScreeningDatasetImport;
use App\Models\ScreeningRecord;
use App\Models\ScreeningRecordName;
use App\Models\Skill;
use App\Models\User;
use App\Services\Screening\DiditVerifiedIdentityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
            ->assertSee('Didit-verified identity')->assertSee('Jane Example')
            ->assertSee('OFFENDER-17')->assertSee('Exact normalized name')
            ->assertSee('Date of birth as published')->assertSee('CMCLBRTH')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('No validated import')->assertDontSee('100 source IDs')
            ->assertDontSee('Source coverage')->assertDontSee('searched version')
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

    public function test_open_filters_counts_and_auto_next_only_include_current_successful_real_kyc(): void
    {
        foreach (['missing', 'in_progress', 'declined', 'kyc_expired', 'bypass', 'manual_override', 'no_name', 'unconfirmed', 'missing_current', 'awaiting_kyc'] as $index => $reason) {
            $hidden = $this->makeCase(['initial', 'legacy', 'recurring'][$index % 3], ['screening_legacy_access' => true], $reason !== 'missing');
            $hidden->update(['status' => $reason === 'awaiting_kyc' ? 'awaiting_kyc' : 'awaiting_investigation', 'due_at' => now()->subDays(2)]);
            $hidden->candidates()->create(['source' => 'nc_dac', 'source_record_id' => 'hidden-'.$reason, 'matched_name' => 'Jane Example', 'match_reason' => 'Exact name', 'record_snapshot' => []]);
            $profile = $hidden->profile;
            $verification = $profile->identityVerifications()->first();
            if (in_array($reason, ['in_progress', 'declined', 'kyc_expired'], true)) {
                $verification->update(['status' => $reason]);
            } elseif ($reason === 'bypass') {
                $verification->update(['didit_session_id' => 'bypass_'.$profile->id]);
                $profile->update(['identity_verification_session_id' => $verification->didit_session_id]);
            } elseif ($reason === 'manual_override') {
                $verification->update(['provider_decision_payload' => ['source' => 'admin_override', 'status' => 'Approved', 'id_verifications' => [[
                    'status' => 'Approved', 'first_name' => 'Jane', 'last_name' => 'Example',
                ]]]]);
            } elseif ($reason === 'no_name') {
                $verification->update(['provider_decision_payload' => ['status' => 'Approved', 'id_verifications' => [['status' => 'Approved']]]]);
            } elseif ($reason === 'unconfirmed') {
                $verification->update(['provider_confirmed_at' => null]);
            } elseif ($reason === 'missing_current') {
                $profile->update(['identity_verification_session_id' => 'missing-current-session']);
            }
        }

        $initial = $this->makeCase('initial', ['status' => 'under_review']);
        $legacy = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $recurring = $this->makeCase('recurring', ['screening_initial_approved_at' => now()->subMonths(7)]);
        foreach ([$initial, $legacy, $recurring] as $case) {
            $case->update(['due_at' => now()->subDay()]);
        }
        $recurring->update(['status' => 'awaiting_investigation']);
        $legacy->candidates()->create(['source' => 'nc_dac', 'source_record_id' => 'visible-match', 'matched_name' => 'Jane Example', 'match_reason' => 'Exact name', 'record_snapshot' => []]);

        $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', $initial->id)
            ->assertDontSee('value="awaiting_kyc"', false)
            ->assertViewHas('counts', fn ($counts) => $counts->all() === ['initial' => 1, 'legacy' => 1, 'recurring' => 1]);

        foreach ([
            'open' => [$initial->id, $legacy->id, $recurring->id],
            'initial' => [$initial->id], 'legacy' => [$legacy->id], 'recurring' => [$recurring->id],
            'overdue' => [$initial->id, $legacy->id, $recurring->id],
            'potential_matches' => [$legacy->id], 'awaiting_investigation' => [$recurring->id],
        ] as $filter => $ids) {
            $component->set('filter', $filter)
                ->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->all() === $ids);
        }

        $component->set('filter', 'open')->call('claim')->call('keepPending')->assertHasNoErrors()
            ->assertSet('selectedId', $legacy->id);
        $this->assertSame(0, CaregiverProfile::where('screening_legacy_access', true)->where('status', '!=', 'active')->count());
    }

    public function test_current_unapproved_session_cannot_fall_back_to_older_approved_evidence(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $profile = $case->profile;
        $verification = $profile->identityVerifications()->create([
            'user_id' => $profile->user_id, 'didit_session_id' => 'new-in-progress-session', 'status' => 'in_progress',
        ]);
        $profile->update(['identity_verification_session_id' => $verification->didit_session_id]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', null)->assertViewHas('cases', fn ($cases) => $cases->isEmpty())
            ->assertViewHas('counts', fn ($counts) => $counts->sum() === 0);
        $this->assertTrue($profile->fresh()->screening_legacy_access);
        $this->assertSame('active', $profile->fresh()->status);
    }

    public function test_explicit_current_approved_session_is_used_even_with_a_newer_noncurrent_verification(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $case->profile->identityVerifications()->create([
            'user_id' => $case->caregiver_user_id, 'didit_session_id' => 'noncurrent-declined-session', 'status' => 'declined',
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', $case->id)->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->all() === [$case->id]);
    }

    public function test_a_hidden_unverified_case_cannot_be_selected_by_id(): void
    {
        $hidden = $this->makeCase('initial', ['status' => 'under_review'], false);
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->call('selectCase', $hidden->id);
    }

    public function test_selected_case_is_removed_when_its_current_verification_expires(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $next = $this->makeCase('initial', ['status' => 'under_review']);
        $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', $case->id);
        $case->profile->identityVerifications()->update(['status' => 'kyc_expired']);

        $component->call('$refresh')->assertSet('selectedId', $next->id)
            ->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->all() === [$next->id]);
        $this->assertSame('active', $case->profile->fresh()->status);
        $this->assertTrue($case->profile->fresh()->screening_legacy_access);
    }

    public function test_completed_history_remains_available_after_kyc_expiry_and_profile_deletion(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $case->update(['status' => 'completed', 'open_profile_id' => null, 'decision' => 'approve', 'outcome' => null, 'completed_at' => now()]);
        $case->profile->identityVerifications()->update(['status' => 'kyc_expired']);
        $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', null)
            ->set('filter', 'completed')->assertSet('selectedId', $case->id)->assertSee('Jane Example');

        $case->profile->delete();
        $component->call('$refresh')->assertSet('selectedId', $case->id)
            ->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->all() === [$case->id])
            ->assertSee('Archived case #'.$case->id)->assertSee('Jane Example')
            ->assertSee(route('admin.caregivers.screening.export', $case), false);
    }

    public function test_completed_history_hides_awaiting_kyc_cases_and_cannot_start_an_unverified_follow_up(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $case->update(['status' => 'completed', 'open_profile_id' => null, 'decision' => 'approve', 'completed_at' => now()]);
        $previous = $case->replicate();
        $previous->save();
        $hidden = CaregiverScreeningCase::create([
            'caregiver_profile_id' => $case->caregiver_profile_id, 'caregiver_user_id' => $case->caregiver_user_id,
            'open_profile_id' => $case->caregiver_profile_id, 'kind' => 'recurring', 'status' => 'awaiting_kyc',
            'due_at' => now(), 'lock_version' => 0, 'identity_snapshot' => null, 'search_status' => 'pending',
        ]);
        $case->profile->identityVerifications()->update(['status' => 'kyc_expired']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', null)
            ->set('filter', 'completed')->assertSet('selectedId', $case->id)
            ->assertSee('Jane Example')
            ->assertViewHas('history', fn ($history) => $history->pluck('id')->all() === [$previous->id])
            ->assertDontSee('wire:click="selectCase('.$hidden->id.')"', false)
            ->call('startFollowUp')->assertHasErrors(['screening'])
            ->assertSet('filter', 'completed')->assertSet('selectedId', $case->id);

        $this->assertDatabaseCount('caregiver_screening_cases', 3);
        $this->assertSame('awaiting_kyc', $hidden->fresh()->status);
        $this->assertSame('active', $case->profile->fresh()->status);
        $this->assertTrue($case->profile->fresh()->screening_legacy_access);
    }

    public function test_match_details_keep_missing_primary_warning_without_dataset_coverage_metadata(): void
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
            ->assertSee('source-only-17')->assertSee('Jane Example')
            ->assertSee('Primary identity record unavailable.')
            ->assertDontSee('Ready · source coverage limits')
            ->assertDontSee('101 of 103 source IDs have an available name for matching.')
            ->assertDontSee('searched version')->assertDontSee('Source note:')
            ->assertDontSee('Alias evidence only.')
            ->assertDontSee('<img src=x onerror=unsafe()>', false)
            ->assertDontSee('<script>unsafe()</script>', false);
    }

    public function test_completed_no_match_preserves_history_without_empty_candidates_or_source_coverage_text(): void
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
            ->assertSet('selectedId', $case->id)
            ->assertSee('No Match')->assertSee('Account approved')
            ->assertDontSee('searched version')
            ->assertDontSee('9 of 10 source IDs have an available name for matching.')
            ->assertDontSee('No available name matched in the imported source versions.')
            ->assertDontSee('No Match is limited to the available names in this imported source version.')
            ->assertDontSee('aria-label="Potential matches"', false);

        $this->assertSame(['nc_sbi' => $old->id], $case->fresh()->source_versions);
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

    public function test_pending_review_accepts_a_blank_note_and_never_implies_no_match(): void
    {
        $case = $this->makeCase('initial', ['status' => 'under_review']);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->call('claim')->assertHasNoErrors()
            ->call('keepPending')->assertHasNoErrors();

        $this->assertSame('awaiting_investigation', $case->fresh()->status);
        $this->assertNull($case->fresh()->outcome);
        $this->assertSame('', $case->fresh()->note);
        $this->assertSame('under_review', $case->profile->fresh()->status);
        $this->assertDatabaseHas('screening_audit_events', ['screening_case_id' => $case->id, 'event_type' => 'case_deferred']);
    }

    public function test_admin_can_explicitly_suspend_verified_legacy_work_without_a_note_or_datasets_while_review_stays_open(): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_at' => now(), 'screening_legacy_access' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)->call('claim')
            ->call('suspendNewWork')->assertHasNoErrors()->assertSet('selectedId', $case->id)
            ->assertSee('New work suspended')->assertDontSee('Suspend new work now');

        $this->assertSame('in_review', $case->fresh()->status);
        $this->assertNull($case->fresh()->outcome);
        $this->assertSame('', $case->fresh()->note);
        $this->assertNull($case->fresh()->completed_at);
        $this->assertSame('suspended', $case->profile->fresh()->status);
        $this->assertNotNull($case->profile->fresh()->screening_suspended_at);
        $this->assertDatabaseHas('screening_audit_events', ['screening_case_id' => $case->id, 'event_type' => 'new_work_suspended']);
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

    public function test_approve_and_next_completes_an_incomplete_legacy_profile_without_a_prior_work_access_grant(): void
    {
        $case = $this->makeCase('legacy', ['status' => 'under_review',
            'screening_legacy_at' => now()->subDay(), 'screening_legacy_access' => false]);
        $this->prepareSearch($case);
        $profile = $case->profile;
        $readiness = $profile->marketplaceReadinessChecks();
        $this->assertContains(false, $readiness);
        $next = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->assertSet('selectedId', $case->id)->call('claim')->call('search')->assertHasNoErrors()
            ->call('decide', 'approve')->assertHasNoErrors()->assertSet('selectedId', $next->id);

        $this->assertSame('completed', $case->fresh()->status);
        $this->assertSame('approve', $case->fresh()->decision);
        $this->assertNull($case->fresh()->outcome);
        $this->assertSame('active', $profile->fresh()->status);
        $this->assertNotNull($profile->fresh()->screening_initial_approved_at);
        $this->assertFalse($profile->fresh()->screening_legacy_access);
        $this->assertSame($readiness, $profile->fresh()->marketplaceReadinessChecks());
        $event = ScreeningAuditEvent::where('screening_case_id', $case->id)->where('event_type', 'screening_decided')->sole();
        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame('under_review', $event->payload['before']['status']);
        $this->assertSame('active', $event->payload['after']['status']);
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

        $component = Livewire::actingAs($admin)->test(CaregiverReviewsQueue::class)
            ->call('claim')->call('search')->assertHasNoErrors()
            ->assertDontSee('aria-label="Potential matches"', false);
        // SQLite preserves insertion order; simulate MySQL normalizing the stored JSON object.
        $snapshot = $case->fresh()->identity_snapshot;
        ksort($snapshot);
        $case->update(['identity_snapshot' => $snapshot]);
        $this->assertNotSame(app(DiditVerifiedIdentityService::class)->snapshot($profile), $case->fresh()->identity_snapshot);

        $component->call('decide', 'approve')->assertHasNoErrors()->assertSet('selectedId', $next->id);

        $this->assertSame('completed', $case->fresh()->status);
        $this->assertSame('approve', $case->fresh()->decision);
        $this->assertNull($case->fresh()->outcome);
        $this->assertSame('', $case->fresh()->note);
        $this->assertSame('active', $profile->fresh()->status);
        $this->assertNotNull($profile->fresh()->screening_initial_approved_at);
        $this->assertNotNull($profile->fresh()->screening_next_due_at);
        $event = ScreeningAuditEvent::where('screening_case_id', $case->id)->where('event_type', 'screening_decided')->firstOrFail();
        $this->assertNull($event->payload['outcome']);
        $this->assertSame('approve', $event->payload['decision']);
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

    public function test_approve_with_candidates_accepts_blank_outcome_and_note_without_recording_no_match(): void
    {
        $this->assertBlankDecisionWithCandidate('approve');
    }

    public function test_suspend_with_candidates_accepts_blank_outcome_and_note_without_recording_no_match(): void
    {
        $this->assertBlankDecisionWithCandidate('suspend');
    }

    private function assertBlankDecisionWithCandidate(string $decision): void
    {
        $case = $this->makeCase('legacy', ['screening_legacy_access' => true]);
        $import = $this->prepareSearch($case);
        $record = ScreeningRecord::create(['import_id' => $import->id, 'record_type' => 'profile', 'source_record_id' => '17', 'data' => ['name' => 'Jane Example']]);
        ScreeningRecordName::create([
            'import_id' => $import->id, 'record_id' => $record->id, 'display_name' => 'Jane Example',
            'normalized_name' => 'jane example', 'first_name' => 'jane', 'last_name' => 'example', 'is_alias' => false,
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CaregiverReviewsQueue::class)
            ->call('claim')->call('search')->assertHasNoErrors()
            ->assertSet('outcome', '')->assertSet('note', '')
            ->call('decide', $decision)->assertHasNoErrors();

        $this->assertSame('completed', $case->fresh()->status);
        $this->assertSame($decision, $case->fresh()->decision);
        $this->assertNull($case->fresh()->outcome);
        $this->assertSame('', $case->fresh()->note);
        $this->assertSame($decision === 'approve' ? 'active' : 'suspended', $case->profile->fresh()->status);
        $event = ScreeningAuditEvent::where('screening_case_id', $case->id)->where('event_type', 'screening_decided')->firstOrFail();
        $this->assertNull($event->payload['outcome']);
        $this->assertSame($decision, $event->payload['decision']);
        $this->assertSame('', $event->payload['note']);
        $this->assertCount(1, $event->payload['candidate_ids']);
    }

    private function prepareSearch(CaregiverScreeningCase $case): ScreeningDatasetImport
    {
        foreach (['nc_sbi', 'nc_dac'] as $source) {
            $import = ScreeningDatasetImport::create([
                'source' => $source, 'status' => 'ready', 'is_active' => true, 'record_count' => 1,
                'downloaded_at' => now(), 'validated_at' => now(), 'activated_at' => now(),
            ]);
        }

        return $import;
    }

    private function makeCase(string $kind, array $profileAttributes = [], bool $verified = true): CaregiverScreeningCase
    {
        $user = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::create(['user_id' => $user->id, 'status' => 'active', ...$profileAttributes]);
        if ($verified) {
            $verification = $profile->identityVerifications()->create([
                'user_id' => $user->id, 'didit_session_id' => 'screening-ui-session-'.$profile->id,
                'status' => 'approved', 'provider_confirmed_at' => now(),
                'provider_decision_payload' => ['status' => 'Approved', 'id_verifications' => [[
                    'status' => 'Approved', 'first_name' => 'Jane', 'last_name' => 'Example', 'date_of_birth' => '1980-01-02',
                ]]],
            ]);
            $profile->update(['identity_verification_session_id' => $verification->didit_session_id,
                'identity_verification_status' => 'approved', 'identity_verified_at' => now()]);
        }
        $identity = app(DiditVerifiedIdentityService::class)->snapshot($profile);

        return CaregiverScreeningCase::create([
            'caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $user->id, 'open_profile_id' => $profile->id,
            'kind' => $kind, 'status' => 'queued', 'due_at' => now(), 'lock_version' => 0,
            'identity_snapshot' => $identity, 'identity_verification_id' => $identity['verification_id'] ?? null,
            'search_status' => 'pending',
        ]);
    }
}
