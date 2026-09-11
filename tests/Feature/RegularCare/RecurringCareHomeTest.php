<?php

namespace Tests\Feature\RegularCare;

use App\Livewire\Family\RegularCareShow;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\CarePlanScheduleChange;
use App\Models\CareRequest;
use App\Models\CompletedExtraVisitRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecurringCareHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_plan_state_has_truthful_status_and_no_unbooked_visit_claim(): void
    {
        [$family, , $plan] = $this->fixture();
        foreach ([
            CarePlan::STATUS_DRAFT => 'Draft recurring care',
            CarePlan::STATUS_PENDING_CAREGIVER => 'Waiting for Amy Caregiver',
            CarePlan::STATUS_COUNTERED => 'New schedule proposed',
            CarePlan::STATUS_ACTIVE => 'Recurring care is active',
            CarePlan::STATUS_PAYMENT_ATTENTION => 'Payment needs attention',
            CarePlan::STATUS_PAUSED => 'Care is paused',
            CarePlan::STATUS_ENDED => 'Recurring care ended',
            CarePlan::STATUS_CANCELLED => 'Recurring care cancelled',
            CarePlan::STATUS_DECLINED => 'Offer declined',
            CarePlan::STATUS_EXPIRED => 'Offer expired',
        ] as $status => $label) {
            $plan->update(['status' => $status]);
            $view = Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
                ->assertSee($label)
                ->assertSeeInOrder(['Overview', 'Caregivers', 'Visits', 'Care details'])
                ->assertDontSee('Next booked visit')
                ->assertDontSee('Payment confirmed');
            if ($status !== CarePlan::STATUS_ACTIVE) {
                $view->assertDontSee('Recurring care is active');
            }
            $view->call('setActiveTab', 'visits');
            if ($plan->isLive()) {
                $view->assertSee('Manage recurring care');
            } else {
                $view->assertDontSee('Manage recurring care');
            }
            if ($status === CarePlan::STATUS_COUNTERED) {
                $view->call('setActiveTab', 'overview')->assertSee('Proposed dates')->assertSee('Accept proposed schedule');
            }
        }
    }

    public function test_ended_plan_keeps_exact_remaining_visit_and_uses_its_assigned_caregiver_and_location(): void
    {
        [$family, , $plan] = $this->fixture(['status' => CarePlan::STATUS_ENDED]);
        $replacement = User::factory()->create(['role' => 'caregiver', 'name' => 'Rosa Visit Caregiver']);
        $visit = $this->booking($plan, ['caregiver_user_id' => $replacement->id]);
        Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee('Recurring care ended')
            ->assertSee('Your remaining booked visit')
            ->assertSee('Rosa Visit Caregiver')
            ->assertSee('200 Visit Lane')
            ->assertSee('Unit B')
            ->assertSee('return_tab=overview', false)
            ->assertDontSee('Skip this visit')
            ->call('setActiveTab', 'visits')
            ->assertSee('care-visit-'.$visit->id, false)
            ->assertSee('return_tab=visits', false)
            ->assertDontSee('Manage recurring care');
    }

    public function test_later_payment_attention_is_visible_before_opening_the_visit_tab(): void
    {
        [$family, , $plan] = $this->fixture();
        $this->booking($plan);
        $later = $this->booking($plan, ['scheduled_start_at' => now()->addDays(5), 'scheduled_end_at' => now()->addDays(5)->addHours(2)]);
        CareBookingPayment::query()->create([
            'care_booking_id' => $later->id, 'family_user_id' => $family->id,
            'caregiver_user_id' => $plan->caregiver_user_id, 'status' => CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            'currency' => 'usd',
        ]);
        Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee('1 visit to follow up')
            ->assertSee('Visit #'.$later->id)
            ->assertSee('Payment needs attention')
            ->assertSee('Fix payment')
            ->call('setVisitFilter', 'attention')
            ->assertViewHas('visits', fn ($visits) => $visits->total() === 1 && $visits->first()->is($later))
            ->assertSee('visit_filter=attention', false);
    }

    public function test_current_visit_and_required_decisions_appear_before_older_optional_reviews(): void
    {
        [$family, , $plan] = $this->fixture();
        $review = $this->booking($plan, ['status' => CareBooking::STATUS_REVIEWED, 'family_confirmed_at' => now()->subDays(6), 'scheduled_start_at' => now()->subDays(6), 'scheduled_end_at' => now()->subDays(6)->addHours(2)]);
        $hours = $this->booking($plan, ['status' => CareBooking::STATUS_COMPLETED, 'scheduled_start_at' => now()->subDays(3), 'scheduled_end_at' => now()->subDays(3)->addHours(2)]);
        $change = $this->booking($plan, ['scheduled_start_at' => now()->addDays(4), 'scheduled_end_at' => now()->addDays(4)->addHours(2)]);
        \App\Models\CareBookingChangeRequest::query()->create([
            'care_booking_id' => $change->id, 'requester_user_id' => $plan->caregiver_user_id,
            'type' => 'reschedule', 'status' => 'pending', 'reason' => 'Please start later',
        ]);
        $payment = $this->booking($plan, ['scheduled_start_at' => now()->addDays(5), 'scheduled_end_at' => now()->addDays(5)->addHours(2)]);
        CareBookingPayment::query()->create([
            'care_booking_id' => $payment->id, 'family_user_id' => $family->id,
            'caregiver_user_id' => $plan->caregiver_user_id, 'status' => CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED, 'currency' => 'usd',
        ]);
        $current = $this->booking($plan, ['status' => CareBooking::STATUS_IN_PROGRESS, 'scheduled_start_at' => now()->subHour(), 'scheduled_end_at' => now()->addHour(), 'started_at' => now()->subHour()]);

        foreach ([CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED] as $status) {
            $current->update(['status' => $status]);
            Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
                ->assertSeeInOrder(['Current visit', '4 visits to follow up', 'Review hours', 'View visit change', 'Fix payment', 'Leave a review'])
                ->assertViewHas('attentionVisits', fn ($visits) => $visits->pluck('id')->all() === [$hours->id, $change->id, $payment->id, $review->id])
                ->assertDontSee('No upcoming visit is booked yet');
            $this->assertSame($status, $current->fresh()->status);
            $this->assertNull($hours->fresh()->family_confirmed_at);
        }
    }

    public function test_tabs_preserve_management_drafts_and_saved_care_details(): void
    {
        [$family, , $plan] = $this->fixture();
        $plan->sourceCareRequest->thirdPartyContact()->create([
            'full_name' => 'Pat Care Contact', 'relationship_to_recipient' => 'Daughter',
            'phone' => '9195550123', 'email' => 'pat@example.test',
        ]);
        Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->call('openManagePanel', 'schedule')
            ->set('scheduleNote', 'Please keep the side entrance clear.')
            ->set('scheduleSlots.1.start_time', '10:30')
            ->call('setActiveTab', 'details')
            ->assertSee('Use the blue mug')
            ->assertSee('Prepare breakfast')
            ->assertSee('Keep tea lukewarm')
            ->assertSee('Pat Care Contact')
            ->assertSee('Daughter')
            ->assertSee('9195550123')
            ->assertSee('pat@example.test')
            ->call('setActiveTab', 'caregivers')
            ->assertSee('Your recurring caregiver')
            ->assertSee('View applications & invitations', false)
            ->call('setActiveTab', 'visits')
            ->assertSet('managePanel', 'schedule')
            ->assertSet('scheduleNote', 'Please keep the side entrance clear.')
            ->assertSet('scheduleSlots.1.start_time', '10:30')
            ->assertSee('Send schedule change');
    }

    public function test_visit_filters_keep_cancelled_records_and_paginate_the_full_history(): void
    {
        [$family, , $plan] = $this->fixture();
        $this->booking($plan);
        $cancelled = $this->booking($plan, ['status' => CareBooking::STATUS_CANCELLED, 'cancellation_reason' => 'Family unavailable', 'scheduled_start_at' => now()->addDays(3), 'scheduled_end_at' => now()->addDays(3)->addHours(2)]);
        foreach (range(1, 13) as $index) {
            $this->booking($plan, ['status' => CareBooking::STATUS_REVIEWED, 'family_confirmed_at' => now()->subDays($index), 'scheduled_start_at' => now()->subDays($index)->setTime(9, 0), 'scheduled_end_at' => now()->subDays($index)->setTime(11, 0)]);
        }
        Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->call('setVisitFilter', 'cancelled')
            ->assertViewHas('visits', fn ($visits) => $visits->total() === 1 && $visits->first()->is($cancelled))
            ->assertSee('Family unavailable')
            ->call('setVisitFilter', 'past')
            ->assertViewHas('visits', fn ($visits) => $visits->total() === 13 && $visits->count() === 12)
            ->call('setPage', 2, 'visitsPage')
            ->assertViewHas('visits', fn ($visits) => $visits->count() === 1)
            ->assertSee('return_page=2', false)
            ->call('setVisitFilter', 'all')
            ->assertViewHas('visits', fn ($visits) => $visits->total() === 15 && $visits->currentPage() === 1)
            ->assertSee('return_page=1', false);
    }

    public function test_pending_schedule_request_shows_the_actual_proposal_and_preserves_booking_state(): void
    {
        [$family, , $plan] = $this->fixture();
        $booking = $this->booking($plan);
        CarePlanScheduleChange::query()->create([
            'care_plan_id' => $plan->id, 'requested_by_user_id' => $family->id,
            'type' => 'schedule', 'status' => 'pending', 'effective_on' => now()->addWeek(),
            'proposed_schedule' => ['days' => [2], 'start_time' => '13:00', 'end_time' => '15:30', 'slots' => [['day' => 2, 'start_time' => '13:00', 'end_time' => '15:30']]],
            'note' => 'After the appointment',
        ]);
        Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee('Waiting for Amy Caregiver')
            ->call('setActiveTab', 'visits')
            ->assertSee('Tue')
            ->assertSee('1:00 PM')
            ->assertSee('3:30 PM')
            ->assertSee('After the appointment')
            ->assertSee('Existing bookings stay unchanged');
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->fresh()->status);
    }

    public function test_invalid_navigation_falls_back_and_other_families_cannot_open_the_plan(): void
    {
        [$family, , $plan] = $this->fixture();
        Livewire::actingAs($family)->withQueryParams(['tab' => '../../bad', 'visit_filter' => 'bad'])
            ->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSet('activeTab', 'overview')->assertSet('visitFilter', 'upcoming');
        $other = User::factory()->create(['role' => 'family']);
        $this->actingAs($other)->get(route('family.care.show', $plan->id))->assertNotFound();
    }

    public function test_older_unresolved_extra_report_keeps_its_actions_after_twelve_newer_reports(): void
    {
        [$family, , $plan] = $this->fixture();
        foreach (range(1, 14) as $version) {
            CompletedExtraVisitRequest::query()->create([
                'client_request_id' => (string) \Illuminate\Support\Str::uuid(),
                'care_plan_id' => $plan->id, 'family_user_id' => $family->id, 'caregiver_user_id' => $plan->caregiver_user_id,
                'version' => $version, 'status' => $version === 1 ? CompletedExtraVisitRequest::STATUS_PENDING_FAMILY : CompletedExtraVisitRequest::STATUS_WITHDRAWN,
                'reason_code' => CompletedExtraVisitRequest::REASON_FAMILY_REQUESTED,
                'explanation' => $version === 1 ? 'Older report still needs a decision' : 'Withdrawn report '.$version,
                'timezone' => 'America/New_York', 'proposed_started_at' => now()->subDays($version)->setTime(9, 0),
                'proposed_completed_at' => now()->subDays($version)->setTime(11, 0), 'proposed_break_minutes' => 0,
                'proposed_worked_minutes' => 120, 'financial_preview' => ['total_charge_cents' => 6200], 'submitted_at' => now()->subDays($version),
            ]);
        }
        Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee('Older report still needs a decision')
            ->assertSeeInOrder(['Older report still needs a decision', 'Withdrawn report 14'])
            ->assertSee('1 reported extra visit to follow up')
            ->assertSee('Approve visit and payment')
            ->assertSee('Request changes')
            ->call('setActiveTab', 'details')
            ->assertSee('1 item to follow up across this care.')
            ->call('setActiveTab', 'visits')
            ->assertSee('Older report still needs a decision');
        $this->assertDatabaseCount('completed_extra_visit_requests', 14);
        $this->assertSame(CompletedExtraVisitRequest::STATUS_PENDING_FAMILY, CompletedExtraVisitRequest::query()->where('version', 1)->value('status'));
    }

    private function fixture(array $attributes = []): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Amy Caregiver']);
        $this->actingAs($family);
        $source = CareRequest::query()->create([
            'family_user_id' => $family->id, 'title' => 'Recurring breakfast care',
            'request_type' => CareRequest::TYPE_RECURRING, 'status' => CareRequest::STATUS_FILLED,
            'address_line1' => '100 Plan Road', 'city' => 'Apex', 'state' => 'NC', 'zip' => '27502',
        ]);
        $plan = CarePlan::query()->create(array_merge([
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $source->id, 'title' => 'Breakfast with Mary',
            'status' => CarePlan::STATUS_ACTIVE, 'recipient_snapshot' => ['full_name' => 'Mary'],
            'address_snapshot' => ['address_line1' => '100 Plan Road', 'city' => 'Apex', 'state' => 'NC', 'zip' => '27502'],
            'task_snapshot' => [['name' => 'Prepare breakfast', 'task_note' => 'Keep tea lukewarm']],
            'care_notes' => 'Use the blue mug', 'hourly_rate' => 30,
            'schedule_days' => [1, 3], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => now()->addDay()->toDateString(), 'timezone' => 'America/New_York',
            'counter_schedule_days' => [2, 4], 'counter_schedule_start_time' => '10:00', 'counter_schedule_end_time' => '12:00',
            'counter_starts_on' => now()->addWeek()->toDateString(),
        ], $attributes));

        return [$family, $caregiver, $plan];
    }

    private function booking(CarePlan $plan, array $attributes = []): CareBooking
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $plan->family_user_id, 'care_plan_id' => $plan->id,
            'is_system_generated' => true, 'title' => 'Booked visit',
            'request_type' => CareRequest::TYPE_ONE_TIME, 'status' => CareRequest::STATUS_FILLED,
            'address_line1' => '200 Visit Lane', 'address_line2' => 'Unit B', 'city' => 'Apex', 'state' => 'NC', 'zip' => '27502',
        ]);

        return CareBooking::query()->create(array_merge([
            'care_request_id' => $request->id, 'care_plan_id' => $plan->id,
            'family_user_id' => $plan->family_user_id, 'caregiver_user_id' => $plan->caregiver_user_id,
            'status' => CareBooking::STATUS_SCHEDULED, 'plan_visit_kind' => 'regular',
            'scheduled_start_at' => now()->addDay()->setTime(9, 0), 'scheduled_end_at' => now()->addDay()->setTime(11, 0),
        ], $attributes));
    }
}
