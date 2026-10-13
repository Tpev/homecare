<?php

namespace Tests\Feature\RegularCare;

use App\Livewire\Family\SharedCareShow;
use App\Models\CareArrangement;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Services\RegularCare\CarePlanService;
use App\Services\RegularCare\SharedCareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SharedCareFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $family;

    private User $alice;

    private User $ben;

    private CareRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-18 09:00:00', config('app.timezone')));
        config()->set('services.stripe.bypass', true);
        $this->family = User::factory()->create(['role' => 'family', 'email_verified_at' => now()]);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($this->family, 'shared_care_test');
        $this->alice = $this->caregiver('Alice');
        $this->ben = $this->caregiver('Ben');
        $this->request = CareRequest::create(['family_user_id' => $this->family->id, 'family_account_id' => $account->id,
            'title' => 'Six days of care', 'status' => 'open', 'request_type' => 'recurring',
            'recurring_days' => [1, 2, 3, 4, 5, 6], 'recurring_start_time' => '08:00', 'recurring_end_time' => '14:00',
            'recurring_schedule' => array_map(fn ($day) => ['day' => $day, 'start_time' => '08:00', 'end_time' => '14:00'], [1, 2, 3, 4, 5, 6]),
            'recurring_starts_on' => '2026-09-21', 'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $this->request->recipient()->create(['full_name' => 'John', 'relationship_to_family' => 'Self']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function caregiver(string $name): User
    {
        $user = User::factory()->create(['role' => 'caregiver', 'name' => $name, 'email_verified_at' => now()]);
        CaregiverProfile::create(['user_id' => $user->id, 'slug' => strtolower($name).'-'.$user->id, 'status' => 'active', 'platform_hourly_rate' => 28,
            'screening_initial_approved_at' => now(),
            'bio' => str_repeat('Experienced and reliable caregiver. ', 5), 'years_experience' => 5,
            'service_area_zip' => '27601', 'service_radius_miles' => 12, 'identity_verified_at' => now(), 'identity_verification_status' => 'approved']);

        return $user;
    }

    private function apply(User $caregiver, array $days): CareRequestApplication
    {
        $shared = app(SharedCareService::class);

        return CareRequestApplication::create(['care_request_id' => $this->request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'applied',
            'proposed_rate' => 28, 'selected_days' => $days, 'offered_schedule' => $shared->offer($this->request, $days), 'schedule_fingerprint' => $shared->fingerprint($this->request)]);
    }

    private function hire(User $caregiver, array $days): CarePlan
    {
        return app(CarePlanService::class)->activateFromRecurringRequest($this->request->fresh(), $this->apply($caregiver, $days), $this->family);
    }

    public function test_two_partial_hires_preserve_separate_visits_and_complete_recruitment(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $this->assertSame('open', $this->request->fresh()->status);
        $this->assertSame([1, 3, 5], $alice->schedule_days);
        $ben = $this->hire($this->ben, [2, 4, 6]);
        $this->assertSame('filled', $this->request->fresh()->status);
        $this->assertSame($alice->care_arrangement_id, $ben->care_arrangement_id);
        $this->assertNotSame($alice->source_care_request_id, $ben->source_care_request_id);
        $this->assertNull($this->request->fresh()->booking);
        $this->assertSame(2, $this->request->applications()->where('status', 'hired')->count());
        $this->assertTrue($alice->generatedBookings->every(fn ($visit) => in_array($visit->scheduled_start_at->dayOfWeek, [1, 3, 5]) && $visit->caregiver_user_id === $this->alice->id));
        $this->assertTrue($ben->generatedBookings->every(fn ($visit) => in_array($visit->scheduled_start_at->dayOfWeek, [2, 4, 6]) && $visit->caregiver_user_id === $this->ben->id));
        Livewire::actingAs($this->family)->test(SharedCareShow::class, ['arrangement' => $alice->care_arrangement_id])->assertOk()->assertSee('Alice')->assertSee('Ben')->assertSee('0 visits need a caregiver');
    }

    public function test_repeated_hire_returns_the_same_plan_without_duplicate_visits(): void
    {
        $application = $this->apply($this->alice, [1, 3, 5]);
        $service = app(CarePlanService::class);
        $first = $service->activateFromRecurringRequest($this->request, $application, $this->family);
        $count = CareBooking::count();
        $second = $service->activateFromRecurringRequest($this->request->fresh(), $application, $this->family);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($count, CareBooking::count());
    }

    public function test_family_home_opens_the_care_calendar_and_warns_about_next_weeks_gaps(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $ben = $this->hire($this->ben, [2, 4, 6]);
        app(SharedCareService::class)->endAssignment($ben, $this->family, '2026-09-28');

        Livewire::actingAs($this->family)->test(\App\Livewire\Family\RequestsIndex::class)
            ->assertSeeLivewire(SharedCareShow::class)->assertSee('Care for John');
        Livewire::actingAs($this->family)->test(SharedCareShow::class, ['arrangement' => $alice->care_arrangement_id])
            ->assertViewHas('coveredCount', 6)->assertSee('Next week needs a little attention')
            ->call('moveWeek', 1)->assertViewHas('missingCount', 3)->assertViewHas('coveredCount', 3)
            ->assertSee('Unfilled')->assertSee('No care planned');
    }

    public function test_reviewing_a_skipped_visit_does_not_cancel_it_until_confirmation(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $visit = $plan->generatedBookings()->orderBy('scheduled_start_at')->first();
        $component = Livewire::actingAs($this->family)->test(SharedCareShow::class, ['arrangement' => $plan->care_arrangement_id])
            ->call('openCancel', $visit->care_arrangement_need_id, false)->assertSee('Skip this visit');
        $this->assertSame('scheduled', $visit->fresh()->status);
        $component->call('confirmCancel')->assertHasNoErrors()->assertSee('Care not needed');
        $this->assertSame('cancelled', $visit->fresh()->status);
        $this->assertDatabaseHas('care_arrangement_needs', ['id' => $visit->care_arrangement_need_id, 'status' => 'waived']);
    }

    public function test_extra_request_opens_its_week_and_stays_unfilled_until_accepted(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        Livewire::actingAs($this->family)->test(SharedCareShow::class, ['arrangement' => $plan->care_arrangement_id])
            ->call('openDate', '2026-10-04')->set('visitStart', '10:00')->set('visitEnd', '12:00')->set('planId', $plan->id)
            ->call('sendExtra')->assertHasNoErrors()->assertSet('week', '2026-09-28')
            ->assertViewHas('pendingCount', 1)->assertViewHas('coveredCount', 3)->assertSee('Waiting for reply');
        $offer = $plan->scheduleChanges()->where('type', 'extra_visit')->sole();
        $this->assertSame('pending', $offer->status);
        $this->assertSame(0, CareBooking::where('care_arrangement_need_id', $offer->care_arrangement_need_id)->count());
    }

    public function test_missing_checkin_is_visible_without_declaring_a_no_show(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $visit = $plan->generatedBookings()->orderBy('scheduled_start_at')->first();
        Carbon::setTestNow($visit->scheduled_start_at->copy()->addMinutes(20));
        Livewire::actingAs($this->family)->test(SharedCareShow::class, ['arrangement' => $plan->care_arrangement_id])
            ->assertSee('Check arrival')->assertSee('No caregiver check-in recorded yet.')
            ->assertSee('Get help with this visit')->assertViewHas('coveredCount', 2);
        $this->assertSame('scheduled', $visit->fresh()->status);
        $this->assertFalse((bool) $visit->fresh()->no_show_flag);
    }

    public function test_month_calendar_includes_unfilled_care_without_turning_it_into_a_booking(): void
    {
        $this->hire($this->alice, [1, 3, 5]);
        $bookingCount = CareBooking::count();
        Livewire::actingAs($this->family)->test(\App\Livewire\Family\CareSchedule::class)
            ->call('setView', 'calendar')->call('selectDate', '2026-09-22')
            ->assertViewHas('selectedVisits', fn ($visits) => $visits->count() === 1
                && $visits->first()['is_care_need'] && $visits->first()['status']['label'] === 'Needs caregiver')
            ->assertSee('Arrange this care')->assertDontSee('No upcoming visits on this date.');
        $this->assertSame($bookingCount, CareBooking::count());
    }

    public function test_extra_visit_picker_excludes_agreements_ending_before_the_requested_date(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $ben = $this->hire($this->ben, [2, 4, 6]);
        app(SharedCareService::class)->endAssignment($ben, $this->family, '2026-09-28');
        Livewire::actingAs($this->family)->test(SharedCareShow::class, ['arrangement' => $alice->care_arrangement_id])
            ->call('openDate', '2026-10-04')
            ->assertViewHas('eligiblePlans', fn ($plans) => $plans->count() === 1 && $plans->first()->id === $alice->id);
    }

    public function test_care_home_keeps_other_one_time_visits_visible(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $request = CareRequest::create(['family_user_id' => $this->family->id, 'family_account_id' => $plan->family_account_id,
            'title' => 'Care for Anne', 'status' => 'filled', 'request_type' => 'one_time',
            'address_line1' => '456 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $request->recipient()->create(['full_name' => 'Anne', 'relationship_to_family' => 'Parent']);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'family_user_id' => $this->family->id,
            'family_account_id' => $plan->family_account_id, 'caregiver_user_id' => $this->ben->id,
            'status' => 'scheduled', 'scheduled_start_at' => '2026-09-19 08:00:00', 'scheduled_end_at' => '2026-09-19 10:00:00', 'hourly_rate' => 28]);
        Livewire::actingAs($this->family)->test(\App\Livewire\Family\RequestsIndex::class)
            ->assertSee('Care for John')->assertSee('Other upcoming care')->assertSee('Anne')
            ->assertViewHas('otherUpcomingVisits', fn ($visits) => $visits->count() === 1 && $visits->first()['id'] === $booking->id);
    }

    public function test_conflicting_application_cannot_be_silently_reduced(): void
    {
        $conflicting = $this->apply($this->ben, [1, 2]);
        $this->hire($this->alice, [1, 3, 5]);
        $this->expectException(ValidationException::class);
        app(CarePlanService::class)->activateFromRecurringRequest($this->request->fresh(), $conflicting, $this->family);
    }

    public function test_future_removal_leaves_gaps_and_preserves_other_caregiver_and_history(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $ben = $this->hire($this->ben, [2, 4, 6]);
        $before = $ben->generatedBookings()->where('status', 'scheduled')->count();
        app(SharedCareService::class)->endAssignment($alice, $this->family, '2026-09-28');
        $this->assertSame($before, $ben->generatedBookings()->where('status', 'scheduled')->count());
        $this->assertSame(3, $alice->generatedBookings()->where('status', 'scheduled')->count());
        $this->assertSame('open', $this->request->fresh()->status);
        $care = CareArrangement::findOrFail($alice->care_arrangement_id);
        $needs = app(SharedCareService::class)->syncNeeds($care, Carbon::parse('2026-09-28'), Carbon::parse('2026-10-03'));
        $this->assertCount(6, $needs);
        $this->assertCount(3, $needs->filter(fn ($need) => ! $need->bookings->contains(fn ($visit) => $visit->status !== 'cancelled')));
    }

    public function test_cancel_with_cover_preserves_need_but_skip_waives_need_and_generation_does_not_revive_it(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $visits = $alice->generatedBookings()->orderBy('scheduled_start_at')->take(2)->get();
        $shared = app(SharedCareService::class);
        $shared->cancelNeed(\App\Models\CareArrangementNeed::findOrFail($visits[0]->care_arrangement_need_id), $this->family, true);
        $shared->cancelNeed(\App\Models\CareArrangementNeed::findOrFail($visits[1]->care_arrangement_need_id), $this->family, false);
        app(CarePlanOccurrenceService::class)->materialize($alice->fresh());
        $this->assertSame('cancelled', $visits[0]->fresh()->status);
        $this->assertSame('cancelled', $visits[1]->fresh()->status);
        $this->assertDatabaseHas('care_arrangement_needs', ['id' => $visits[0]->care_arrangement_need_id, 'status' => 'needed']);
        $this->assertDatabaseHas('care_arrangement_needs', ['id' => $visits[1]->care_arrangement_need_id, 'status' => 'waived']);
    }

    public function test_replacement_fills_existing_need_after_explicit_acceptance(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $ben = $this->hire($this->ben, [2, 4, 6]);
        $visit = $alice->generatedBookings()->orderBy('scheduled_start_at')->first();
        $need = \App\Models\CareArrangementNeed::findOrFail($visit->care_arrangement_need_id);
        $shared = app(SharedCareService::class);
        $shared->cancelNeed($need, $this->family, true);
        $change = $shared->requestVisit(CareArrangement::findOrFail($alice->care_arrangement_id), $this->family, $ben->id, $need->starts_at, $need->ends_at, $need->id);
        $this->assertSame(0, $need->bookings()->where('status', 'scheduled')->count());
        app(CarePlanService::class)->respondToScheduleChange($change, $this->ben, true);
        app(CarePlanService::class)->respondToScheduleChange($change, $this->ben, true);
        $this->assertSame(1, $need->bookings()->where('status', 'scheduled')->count());
        $this->assertSame($this->ben->id, $need->bookings()->where('status', 'scheduled')->first()->caregiver_user_id);
        $this->assertSame('cancelled', $visit->fresh()->status);
    }

    public function test_extra_visit_does_not_change_recurring_days_and_withdrawn_offer_cannot_book(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $shared = app(SharedCareService::class);
        $care = CareArrangement::findOrFail($alice->care_arrangement_id);
        $change = $shared->requestVisit($care, $this->family, $alice->id, Carbon::parse('2026-09-27 10:00'), Carbon::parse('2026-09-27 12:00'));
        $need = $care->needs()->findOrFail($change->care_arrangement_need_id);
        $shared->withdrawOffer($need, $this->family);
        app(CarePlanService::class)->respondToScheduleChange($change, $this->alice, true);
        $this->assertSame(0, $need->bookings()->count());
        $newOffer = $shared->requestVisit($care, $this->family, $alice->id, $need->starts_at, $need->ends_at, $need->id);
        app(CarePlanService::class)->respondToScheduleChange($newOffer, $this->alice, true);
        $this->assertSame(1, $need->bookings()->where('status', 'scheduled')->count());
        $this->assertSame([1, 3, 5], $alice->fresh()->schedule_days);
    }

    public function test_other_family_cannot_access_shared_schedule(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $outsider = User::factory()->create(['role' => 'family']);
        app(FamilyAccountProvisioner::class)->provisionOwner($outsider, 'shared_care_test');
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($outsider)->test(SharedCareShow::class, ['arrangement' => $alice->care_arrangement_id]);
    }

    public function test_same_caregiver_can_accept_a_new_offer_for_a_cancelled_visit(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $original = $plan->generatedBookings()->orderBy('scheduled_start_at')->first();
        $need = \App\Models\CareArrangementNeed::findOrFail($original->care_arrangement_need_id);
        $service = app(SharedCareService::class);
        $service->cancelNeed($need, $this->family, false);
        $service->restoreNeed($need->fresh(), $this->family);
        $offer = $service->requestVisit($need->arrangement, $this->family, $plan->id, $need->starts_at, $need->ends_at, $need->id);
        app(CarePlanService::class)->respondToScheduleChange($offer, $this->alice, true);
        $replacement = $need->bookings()->where('status', 'scheduled')->sole();
        $this->assertNotSame($original->id, $replacement->id);
        $this->assertSame('cancelled', $original->fresh()->status);
        $service->cancelNeed($need->fresh(), $this->family, true);
        $second = $service->requestVisit($need->arrangement, $this->family, $plan->id, $need->starts_at, $need->ends_at, $need->id);
        app(CarePlanService::class)->respondToScheduleChange($second, $this->alice, true);
        $this->assertSame(3, $need->bookings()->count());
        $this->assertSame(1, $need->bookings()->where('status', 'scheduled')->count());
    }

    public function test_restoring_one_date_survives_a_permanent_no_care_exception(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $service = app(SharedCareService::class);
        $service->endAssignment($plan, $this->family, '2026-09-21', false);
        $care = CareArrangement::findOrFail($plan->care_arrangement_id);
        $need = $care->needs()->whereDate('starts_at', '2026-09-21')->firstOrFail();
        $this->assertSame('waived', $need->status);
        $service->restoreNeed($need, $this->family);
        $service->syncNeeds($care->fresh(), Carbon::parse('2026-09-21'), Carbon::parse('2026-09-28'));
        $this->assertSame('needed', $need->fresh()->status);
        $this->assertSame('waived', $care->needs()->whereDate('starts_at', '2026-09-28')->firstOrFail()->status);
    }

    public function test_a_family_cannot_hire_a_caregiver_already_committed_to_another_family(): void
    {
        $this->hire($this->alice, [1, 3, 5]);
        $other = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($other, 'shared_care_test');
        $second = $this->request->replicate();
        $second->forceFill(['family_user_id' => $other->id, 'family_account_id' => $account->id, 'care_arrangement_id' => null])->save();
        $second->recipient()->create(['full_name' => 'Another recipient', 'relationship_to_family' => 'Self']);
        $this->request = $second;
        $application = $this->apply($this->alice, [1]);
        $this->actingAs($other);
        $this->expectException(ValidationException::class);
        app(CarePlanService::class)->activateFromRecurringRequest($second, $application, $other);
    }

    public function test_a_regular_hire_cannot_overlap_an_accepted_extra_for_another_caregiver(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $service = app(SharedCareService::class);
        $care = CareArrangement::findOrFail($alice->care_arrangement_id);
        $offer = $service->requestVisit($care, $this->family, $alice->id, Carbon::parse('2026-09-22 08:00'), Carbon::parse('2026-09-22 14:00'));
        app(CarePlanService::class)->respondToScheduleChange($offer, $this->alice, true);
        $this->expectException(ValidationException::class);
        $this->hire($this->ben, [2, 4, 6]);
    }

    public function test_legacy_extra_entry_point_uses_shared_need_and_removal_withdraws_offer(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $service = app(CarePlanService::class);
        $offer = $service->requestExtraVisit($plan, $this->family, Carbon::parse('2026-09-27 10:00'), Carbon::parse('2026-09-27 12:00'));
        $this->assertNotNull($offer->care_arrangement_need_id);
        app(SharedCareService::class)->endAssignment($plan, $this->family, '2026-09-26');
        $service->respondToScheduleChange($offer, $this->alice, true);
        $this->assertSame('withdrawn', $offer->fresh()->status);
        $this->assertSame(0, CareBooking::where('care_arrangement_need_id', $offer->care_arrangement_need_id)->count());
    }

    public function test_payments_keep_their_original_caregiver_after_replacement(): void
    {
        $alice = $this->hire($this->alice, [1, 3, 5]);
        $ben = $this->hire($this->ben, [2, 4, 6]);
        $visit = $alice->generatedBookings()->orderBy('scheduled_start_at')->first();
        $payment = \App\Models\CareBookingPayment::updateOrCreate(['care_booking_id' => $visit->id], [
            'family_user_id' => $this->family->id, 'caregiver_user_id' => $this->alice->id,
            'status' => 'captured', 'currency' => 'usd', 'amount_captured_cents' => 18600,
        ]);
        $need = \App\Models\CareArrangementNeed::findOrFail($visit->care_arrangement_need_id);
        $service = app(SharedCareService::class);
        $service->cancelNeed($need, $this->family, true);
        $offer = $service->requestVisit($need->arrangement, $this->family, $ben->id, $need->starts_at, $need->ends_at, $need->id);
        app(CarePlanService::class)->respondToScheduleChange($offer, $this->ben, true);
        $this->assertSame('captured', $payment->fresh()->status);
        $this->assertSame($this->alice->id, $payment->fresh()->caregiver_user_id);
        Livewire::actingAs($this->family)->test(SharedCareShow::class, ['arrangement' => $alice->care_arrangement_id])
            ->set('tab', 'payments')->assertSee('Cancellation payment needs review')->assertSee('186.00');
    }

    public function test_day_selection_is_required_and_application_stores_only_selected_days(): void
    {
        config()->set('marketplace.caregiver_prelaunch_mode', false);
        $profile = $this->alice->caregiverProfile;
        $profile->skills()->attach(\App\Models\Skill::firstOrCreate(['name' => 'Companionship']));
        $profile->languages()->attach(\App\Models\Language::firstOrCreate(['name' => 'English']));
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '14:00']);
        Livewire::actingAs($this->alice)->test(\App\Livewire\Caregiver\ApplyToCareRequest::class, ['careRequest' => $this->request->id])
            ->set('selectedDays', [])->call('submit')->assertHasErrors('selectedDays')
            ->set('selectedDays', [1, 3])->call('submit')->assertHasNoErrors();
        $application = $this->request->applications()->sole();
        $this->assertSame([1, 3], $application->selected_days);
        $this->assertSame([1, 3], array_column($application->offered_schedule['slots'], 'day'));
    }

    public function test_old_pause_controls_cannot_reactivate_shared_cancelled_visits(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $this->expectException(ValidationException::class);
        app(CarePlanService::class)->pausePlan($plan, $this->family, Carbon::parse('2026-09-21'));
    }

    public function test_skip_beyond_the_generated_window_stays_skipped_and_restore_requires_acceptance(): void
    {
        $plan = $this->hire($this->alice, [1, 3, 5]);
        $care = CareArrangement::findOrFail($plan->care_arrangement_id);
        $service = app(SharedCareService::class);
        $need = $service->syncNeeds($care, Carbon::parse('2027-01-04'), Carbon::parse('2027-01-04'))->sole();
        $this->assertSame(0, $need->bookings()->count());
        $service->cancelNeed($need, $this->family, false);
        $service->restoreNeed($need->fresh(), $this->family);
        app(CarePlanOccurrenceService::class)->materialize($plan, through: Carbon::parse('2027-01-04 23:59'));
        $this->assertSame(0, $need->bookings()->count());
        $offer = $service->requestVisit($care, $this->family, $plan->id, $need->starts_at, $need->ends_at, $need->id);
        app(CarePlanService::class)->respondToScheduleChange($offer, $this->alice, true);
        $this->assertSame(1, $need->bookings()->where('status', 'scheduled')->count());
    }
}
