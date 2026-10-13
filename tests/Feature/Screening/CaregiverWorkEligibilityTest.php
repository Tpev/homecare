<?php

namespace Tests\Feature\Screening;

use App\Livewire\Caregiver\OnboardingWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\ContinuousCoverage\ContinuousCoverageBookingAdapter;
use App\Services\ContinuousCoverage\ContinuousCoverageRosterService;
use App\Services\ContinuousCoverage\ContinuousCoverageScheduleService;
use App\Services\Marketplace\CareRequestHiringService;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Services\Screening\CaregiverWorkEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CaregiverWorkEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('marketplace.continuous_coverage.enabled', true);
        config()->set('marketplace.continuous_coverage.pilot_emails', []);
        config()->set('marketplace.continuous_coverage.generation_weeks', 1);
    }

    public function test_only_initial_approval_or_preserved_legacy_access_permits_new_work(): void
    {
        $gate = app(CaregiverWorkEligibility::class);
        $fresh = $this->profile();
        $legacy = $this->profile(['screening_legacy_at' => now(), 'screening_legacy_access' => true]);
        $approved = $this->profile(['screening_initial_approved_at' => now()->subYear(), 'screening_next_due_at' => now()->subMonths(6)]);
        $inactiveLegacy = $this->profile(['status' => 'pending_review', 'screening_legacy_at' => now()]);
        $suspended = $this->profile(['screening_legacy_access' => true, 'screening_suspended_at' => now()]);

        foreach ([$fresh, $inactiveLegacy, $suspended] as $profile) {
            $this->assertFalse($gate->allowsNewWork($profile));
        }
        foreach ([$legacy, $approved] as $profile) {
            $this->assertTrue($gate->allowsNewWork($profile));
        }
        $this->assertEqualsCanonicalizing([$legacy->id, $approved->id], CaregiverProfile::discoverable()->pluck('id')->all());

        CaregiverScreeningCase::create([
            'caregiver_profile_id' => $approved->id, 'caregiver_user_id' => $approved->user_id,
            'kind' => 'recurring', 'status' => 'awaiting_investigation', 'due_at' => now()->subMonths(6),
            'outcome' => 'potential_match',
        ]);
        DB::transaction(fn () => $gate->assertCanStartNewWork($approved));
        $this->assertTrue($gate->allowsNewWork($approved->fresh()));
    }

    public function test_a_stale_loaded_profile_cannot_bypass_explicit_suspension(): void
    {
        $profile = $this->profile(['screening_legacy_access' => true]);
        CaregiverProfile::whereKey($profile->id)->update(['screening_suspended_at' => now()]);

        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(CaregiverWorkEligibility::class)->assertCanStartNewWork($profile));
    }

    public function test_stale_onboarding_submission_preserves_old_and_new_suspensions_and_enqueues_review(): void
    {
        Mail::fake();
        foreach ([null, now()] as $suspendedAt) {
            $profile = $this->profile([
                'status' => 'draft', 'bio' => str_repeat('Experienced caregiver. ', 4), 'years_experience' => 3,
                'service_area_zip' => '27601', 'service_radius_miles' => 10,
                'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
            ]);
            $caregiver = $profile->user;
            $caregiver->update(['city' => 'Raleigh', 'state' => 'NC', 'date_of_birth' => now()->subYears(25)->toDateString()]);
            $profile->skills()->attach(Skill::create(['name' => 'Companionship '.$profile->id]));
            $profile->languages()->attach(Language::create(['name' => 'English '.$profile->id]));
            $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);
            $component = Livewire::actingAs($caregiver)->test(OnboardingWizard::class);
            $profile->update(['status' => 'suspended', 'screening_suspended_at' => $suspendedAt]);

            $component->call('submitForReview')->assertHasNoErrors();

            $this->assertSame('suspended', $profile->fresh()->status);
            $this->assertSame($suspendedAt?->toDateTimeString(), $profile->fresh()->screening_suspended_at?->toDateTimeString());
            $this->assertFalse(app(CaregiverWorkEligibility::class)->allowsNewWork($profile->fresh()));
            $this->assertDatabaseHas('caregiver_screening_cases', ['caregiver_profile_id' => $profile->id, 'status' => 'awaiting_kyc']);
        }
    }

    public function test_service_and_administered_family_hiring_recheck_old_applications(): void
    {
        [$family, $profile, $request, $application] = $this->application();
        $profile->update(['screening_suspended_at' => now()]);

        try {
            app(CareRequestHiringService::class)->hire($family, $application);
            $this->fail('The service must reject an old application after suspension.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('caregiver', $exception->errors());
        }
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id)->assertHasErrors('caregiver');

        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertSame(CareRequestApplication::STATUS_APPLIED, $application->fresh()->status);
        $this->assertDatabaseMissing('care_bookings', ['care_request_id' => $request->id]);
    }

    public function test_existing_regular_schedule_materializes_and_is_flagged_but_extra_work_is_rejected(): void
    {
        $profile = $this->profile(['screening_legacy_access' => true]);
        $family = User::factory()->create(['role' => 'family']);
        $first = now()->addDays(2)->startOfDay();
        $plan = CarePlan::create([
            'family_user_id' => $family->id, 'caregiver_user_id' => $profile->user_id,
            'status' => CarePlan::STATUS_ACTIVE, 'title' => 'Existing agreed visits',
            'starts_on' => $first->toDateString(), 'ends_on' => $first->toDateString(),
            'timezone' => config('app.timezone'), 'schedule_days' => [$first->dayOfWeek],
            'schedule_start_time' => '09:00', 'schedule_end_time' => '12:00',
            'hourly_rate' => 30, 'accepted_at' => now()->subMonth(),
            'recipient_snapshot' => ['full_name' => 'Recipient', 'relationship_to_family' => 'Mother'],
            'address_snapshot' => ['address_line1' => '1 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'],
        ]);
        $case = $this->suspend($profile);
        $result = app(CarePlanOccurrenceService::class)->materialize($plan, through: $first->copy()->endOfDay());
        $this->assertCount(1, $result['created']);
        $booking = $result['created']->first();
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        $this->assertDatabaseHas('screening_booking_reviews', ['screening_case_id' => $case->id, 'subject_type' => 'booking', 'subject_id' => $booking->id]);

        $this->expectException(ValidationException::class);
        app(CarePlanOccurrenceService::class)->createExtraVisit($plan, $first->copy()->setTime(14, 0), $first->copy()->setTime(15, 0));
    }

    public function test_coverage_acceptance_is_rechecked_while_existing_accepted_shifts_keep_materializing(): void
    {
        $profile = $this->profile(['screening_legacy_access' => true]);
        $caregiver = $profile->user;
        $family = User::factory()->create(['role' => 'family']);
        $schedule = app(ContinuousCoverageScheduleService::class);
        $roster = app(ContinuousCoverageRosterService::class);
        $plan = $schedule->createPlan($family, [
            'title' => 'Existing coverage', 'timezone' => config('app.timezone'),
            'starts_on' => now()->addDay()->toDateString(), 'ends_on' => null,
            'coverage_pattern' => ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
            'shift_length_minutes' => 720, 'coverage_start_time' => '07:00', 'coverage_end_time' => '07:00',
            'custom_windows' => [], 'hourly_rate' => 30,
            'recipient_snapshot' => ['full_name' => 'Recipient', 'relationship_to_family' => 'Mother'],
            'address_snapshot' => ['address_line1' => '1 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'],
            'task_snapshot' => [], 'replacement_confirmation_mode' => ContinuousCoveragePlan::CONFIRM_FAMILY,
        ]);
        $member = $roster->caregiverAccept($roster->familyApprove($plan, $family, $caregiver), $caregiver);
        $templates = $plan->templates()->limit(2)->get();
        $accepted = $roster->offerLane($templates[0], $member, $family);
        $roster->acceptLane($accepted, $caregiver);
        $pending = $roster->offerLane($templates[1], $member, $family);
        $case = $this->suspend($profile);
        try {
            $roster->acceptLane($pending, $caregiver);
            $this->fail('A pending lane offer must be rejected after suspension.');
        } catch (ValidationException) {
            $this->assertSame(ContinuousCoverageShiftTemplate::STATUS_OFFERED, $pending->fresh()->status);
        }

        $schedule->generate($plan, now()->addWeeks(3));
        $shift = $accepted->shifts()->where('scheduled_start_at', '>', now()->addWeek())
            ->where('status', ContinuousCoverageShift::STATUS_CONFIRMED)->firstOrFail();
        $this->assertDatabaseHas('screening_booking_reviews', ['screening_case_id' => $case->id, 'subject_type' => 'coverage_shift', 'subject_id' => $shift->id]);
        $booking = app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        $this->assertDatabaseHas('screening_booking_reviews', ['screening_case_id' => $case->id, 'subject_type' => 'booking', 'subject_id' => $booking->id]);

        // The recurring lane can have a different caregiver on a replacement date.
        $replacement = $this->profile(['screening_legacy_access' => true]);
        $replacementCase = $this->suspend($replacement);
        $staleShift = $accepted->shifts()->where('scheduled_start_at', '>', now()->addWeek())
            ->whereNull('care_booking_id')->where('status', ContinuousCoverageShift::STATUS_CONFIRMED)->firstOrFail();
        ContinuousCoverageShift::whereKey($staleShift->id)->update(['assigned_caregiver_user_id' => $replacement->user_id]);
        $schedule->generate($plan, now()->addWeeks(3));
        $this->assertDatabaseHas('screening_booking_reviews', ['screening_case_id' => $replacementCase->id, 'subject_type' => 'coverage_shift', 'subject_id' => $staleShift->id]);
        $replacementBooking = app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($staleShift);
        $this->assertSame($replacement->user_id, $replacementBooking->caregiver_user_id);
        $this->assertDatabaseHas('screening_booking_reviews', ['screening_case_id' => $replacementCase->id, 'subject_type' => 'booking', 'subject_id' => $replacementBooking->id]);
    }

    public function test_suspension_allows_existing_visit_rescheduling_but_not_extra_hours(): void
    {
        [$family, $profile, $request, $application] = $this->application();
        $booking = CareBooking::create([
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $profile->user_id,
            'status' => CareBooking::STATUS_SCHEDULED, 'scheduled_start_at' => now()->addDay()->setTime(9, 0),
            'scheduled_end_at' => now()->addDay()->setTime(12, 0),
        ]);
        $this->suspend($profile);
        $change = new CareBookingChangeRequest(['type' => CareBookingChangeRequest::TYPE_RESCHEDULE,
            'proposed_start_at' => now()->addDays(2)->setTime(9, 0), 'proposed_end_at' => now()->addDays(2)->setTime(12, 0)]);
        DB::transaction(fn () => app(CaregiverWorkEligibility::class)->assertBookingChangeAllowed($booking, $change));
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->fresh()->status);
        DB::transaction(fn () => app(CaregiverWorkEligibility::class)->assertBookingChangeAllowed($booking, new CareBookingChangeRequest(['type' => CareBookingChangeRequest::TYPE_CANCEL])));

        $change->proposed_end_at = now()->addDays(2)->setTime(13, 0);
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(CaregiverWorkEligibility::class)->assertBookingChangeAllowed($booking, $change));
    }

    private function profile(array $attributes = []): CaregiverProfile
    {
        return CaregiverProfile::create(array_merge(['user_id' => User::factory()->create(['role' => 'caregiver'])->id, 'status' => 'active'], $attributes));
    }

    private function suspend(CaregiverProfile $profile): CaregiverScreeningCase
    {
        $profile->update(['screening_suspended_at' => now()]);

        return CaregiverScreeningCase::create([
            'caregiver_profile_id' => $profile->id, 'caregiver_user_id' => $profile->user_id,
            'kind' => 'legacy', 'status' => 'completed', 'due_at' => now(),
            'outcome' => 'potential_match', 'decision' => 'suspend', 'completed_at' => now(),
        ]);
    }

    private function application(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $profile = $this->profile(['screening_legacy_access' => true]);
        $request = CareRequest::create([
            'family_user_id' => $family->id, 'title' => 'Companionship', 'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME, 'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0), 'address_line1' => '1 Main St',
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $application = CareRequestApplication::create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $profile->user_id,
            'status' => CareRequestApplication::STATUS_APPLIED, 'proposed_rate' => 30,
        ]);

        return [$family, $profile, $request, $application];
    }
}
