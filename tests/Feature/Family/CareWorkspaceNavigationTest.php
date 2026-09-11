<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Family\FamilyCarePresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CareWorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_request_has_a_continuing_home_and_an_honest_unconfirmed_schedule(): void
    {
        [$family, $request] = $this->requestFixture(CareRequest::TYPE_RECURRING);

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('activeTab', 'home')
            ->assertSee('Care for Ellie')->assertSee('Recurring care')
            ->assertSee('Care request')->assertSee('Find caregivers')->assertSee('Review applicants')->assertSee('Get started')
            ->assertSee('View requested weekly visits')->assertSee('Full care details')
            ->call('setActiveTab', 'visits')
            ->assertSee('These are requested times.')
            ->assertSee('Monday')->assertSee('09:00');

        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_filtering_to_one_applicant_keeps_the_filters_and_all_decisions(): void
    {
        [$family, $request] = $this->requestFixture();
        foreach (range(1, 4) as $number) {
            $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Carer '.$number]);
            $request->applications()->create([
                'caregiver_user_id' => $caregiver->id,
                'status' => $number === 1 ? CareRequestApplication::STATUS_SHORTLISTED : CareRequestApplication::STATUS_APPLIED,
                'proposed_rate' => 30,
            ]);
        }

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')
            ->set('applicationStatus', CareRequestApplication::STATUS_SHORTLISTED)
            ->assertSee('Status')->assertSee('All caregivers')->assertSee('Hire Carer')
            ->assertSee('Not this caregiver')
            ->set('applicationStatus', CareRequestApplication::STATUS_WITHDRAWN)
            ->assertSee('Status')->assertSee('All caregivers');
    }

    public function test_an_overdue_unbooked_request_opens_its_resolution_action(): void
    {
        [$family, $request] = $this->requestFixture();
        $request->update(['requested_start_at' => now()->subDays(2), 'requested_end_at' => now()->subDays(2)->addHours(2)]);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('activeTab', 'home')->assertSee('Requested date passed')->assertSee('Choose another date');
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
    }

    public function test_hire_review_does_not_hire_or_create_a_booking(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $application = $request->applications()->create([
            'caregiver_user_id' => $caregiver->id, 'status' => CareRequestApplication::STATUS_APPLIED, 'proposed_rate' => 30,
        ]);

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')->call('reviewHire', $application->id)
            ->assertSee('Confirm hire')->assertSee('Agreed care')
            ->call('closeDecisionReview')->assertSet('reviewingApplicationId', null)
            ->assertSet('activeTab', 'applicants');

        $this->assertSame(CareRequestApplication::STATUS_APPLIED, $application->fresh()->status);
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_reviewing_hours_preserves_the_booking_and_draft_when_changing_tabs(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id, 'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED, 'scheduled_start_at' => now()->subHours(3),
            'scheduled_end_at' => now()->subHour(), 'started_at' => now()->subHours(3), 'completed_at' => now()->subHour(),
            'timesheet_submitted_at' => now()->subHour(), 'worked_minutes' => 120, 'expected_minutes' => 120,
        ]);

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewCompletion')->assertSee('Estimated total')
            ->set('confirmationNote', 'Please keep this draft')
            ->call('setActiveTab', 'support')->assertSet('reviewingCompletion', false)
            ->assertDontSee('Approve hours and pay')
            ->call('setActiveTab', 'shift')
            ->assertSet('confirmationNote', 'Please keep this draft')
            ->call('closeDecisionReview');

        $this->assertNull($booking->fresh()->family_confirmed_at);
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->fresh()->status);
    }

    public function test_a_generated_recurring_visit_links_to_its_exact_record_and_parent(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Actual visit caregiver']);
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'title' => 'Weekly care', 'status' => CarePlan::STATUS_ENDED,
            'recipient_snapshot' => ['full_name' => 'Ellie'], 'hourly_rate' => 30,
            'schedule_days' => [1], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => now()->toDateString(), 'timezone' => 'America/New_York',
        ]);
        $request->update(['care_plan_id' => $plan->id, 'is_system_generated' => true]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id, 'care_plan_id' => $plan->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED, 'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
        ]);
        $presented = app(FamilyCarePresentationService::class)->visit($booking->load(['careRequest.recipient', 'carePlan', 'caregiver', 'payment']));
        $this->assertSame(route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'shift']), $presented['action_url']);

        Livewire::actingAs($family)->withQueryParams(['tab' => 'shift'])
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Back to recurring care')->assertSee('One visit in your recurring care')
            ->assertSee('Actual visit caregiver')->assertDontSee('One-time care');
    }

    public function test_legacy_links_and_access_boundaries_still_work(): void
    {
        [$family, $request] = $this->requestFixture();
        foreach (['overview', 'applicants', 'support'] as $tab) {
            Livewire::actingAs($family)->withQueryParams(['tab' => $tab])
                ->test(ManageCareRequest::class, ['careRequest' => $request->id])->assertSet('activeTab', $tab)->assertOk();
        }
        $other = User::factory()->create(['role' => 'family']);
        $this->actingAs($other)->get(route('family.requests.show', $request->id))->assertNotFound();
    }

    public function test_get_started_is_read_only_for_unbooked_one_time_and_recurring_requests(): void
    {
        foreach ([CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING] as $type) {
            auth()->logout();
            [$family, $request] = $this->requestFixture($type);
            foreach ([CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED] as $status) {
                $caregiver = User::factory()->create(['role' => 'caregiver']);
                $request->applications()->create([
                    'caregiver_user_id' => $caregiver->id,
                    'status' => $status,
                    'proposed_rate' => 30,
                ]);
            }
            $requestBefore = $request->refresh()->getRawOriginal();
            $applicationsBefore = $request->applications()->orderBy('id')->get()
                ->map(fn (CareRequestApplication $application) => $application->getRawOriginal())->all();

            Livewire::actingAs($family)->withQueryParams(['tab' => 'start'])
                ->test(ManageCareRequest::class, ['careRequest' => $request->id])
                ->assertSet('activeTab', 'start')
                ->assertSee('Choose a caregiver to get started')
                ->assertSet('reviewingApplicationId', null)
                ->call('setActiveTab', 'home')
                ->assertSet('activeTab', 'home')
                ->call('setActiveTab', 'start')
                ->assertSet('activeTab', 'start')
                ->assertSee('Choose a caregiver to get started')
                ->assertSet('reviewingApplicationId', null);

            $this->assertSame($requestBefore, $request->fresh()->getRawOriginal());
            $this->assertSame($applicationsBefore, $request->applications()->orderBy('id')->get()
                ->map(fn (CareRequestApplication $application) => $application->getRawOriginal())->all());
        }

        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('care_request_applications', 4);

        $other = User::factory()->create(['role' => 'family']);
        $this->actingAs($other)->get(route('family.requests.show', [
            'careRequest' => $request->id, 'tab' => 'start',
        ]))->assertNotFound();
    }

    public function test_booked_request_cannot_enter_the_pre_hire_get_started_tab(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request->update(['status' => CareRequest::STATUS_FILLED]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDays(2)->setTime(9, 0),
            'scheduled_end_at' => now()->addDays(2)->setTime(11, 0),
        ]);
        $bookingBefore = $booking->refresh()->getRawOriginal();

        Livewire::actingAs($family)->withQueryParams(['tab' => 'start'])
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('activeTab', 'shift')
            ->assertSet('reviewingApplicationId', null)
            ->call('setActiveTab', 'overview')
            ->call('setActiveTab', 'start')
            ->assertSet('activeTab', 'overview')
            ->assertSet('reviewingApplicationId', null);

        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_request_applications', 0);
    }

    private function requestFixture(string $type = CareRequest::TYPE_ONE_TIME): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id, 'title' => 'Care for Ellie', 'request_type' => $type,
            'status' => CareRequest::STATUS_OPEN, 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'address_line1' => '123 Local Demo Lane', 'scope_of_work' => 'Companionship and meals',
            'requested_start_at' => now()->addDays(2)->setTime(9, 0), 'requested_end_at' => now()->addDays(2)->setTime(11, 0),
            'recurring_schedule' => [['day' => 1, 'start_time' => '09:00', 'end_time' => '11:00']],
            'recurring_starts_on' => now()->addDays(2)->toDateString(),
        ]);
        $request->recipient()->create(['full_name' => 'Ellie', 'relationship_to_family' => 'Mother', 'recipient_is_requester' => false]);

        return [$family, $request];
    }
}
