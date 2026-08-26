<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\CareActionsIndex;
use App\Livewire\Family\CareSchedule;
use App\Livewire\Family\RequestsIndex;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyCareExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_care_is_one_workspace_with_a_shared_schedule(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Amy Ten Broek']);
        $startsAt = now()->addDays(2)->setTime(7, 0);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Internal request title',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => $startsAt,
            'requested_end_at' => $startsAt->copy()->addHours(12),
            'city' => 'Apex',
            'state' => 'NC',
            'zip' => '27502',
            'address_line1' => '100 Main Street',
        ]);
        $request->recipient()->create([
            'full_name' => 'Mary Villano',
            'relationship_to_family' => 'Mother',
            'recipient_is_requester' => false,
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $startsAt,
            'scheduled_end_at' => $startsAt->copy()->addHours(12),
        ]);

        $this->actingAs($family)
            ->get(route('family.care.schedule'))
            ->assertOk()
            ->assertSee('Your visits, organized by when they happen.')
            ->assertSee('This week')
            ->assertSee('Mary Villano')
            ->assertSee('Amy Ten Broek')
            ->assertSee('One-time visit')
            ->assertSee('Visit #')
            ->assertSee('View care')
            ->assertSee('Overview')
            ->assertSee('Arrangements')
            ->assertSee('History')
            ->assertDontSee('24/7 Coverage');
    }

    public function test_past_one_time_request_has_one_clear_resolution_action(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $startsAt = now()->subDays(2)->setTime(9, 0);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Old internal request title',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => $startsAt,
            'requested_end_at' => $startsAt->copy()->addHours(2),
            'city' => 'Apex',
            'state' => 'NC',
            'zip' => '27502',
            'address_line1' => '100 Main Street',
        ]);
        $request->recipient()->create([
            'full_name' => 'Mary Villano',
            'relationship_to_family' => 'Mother',
            'recipient_is_requester' => false,
        ]);

        CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        $html = $this->actingAs($family)
            ->get(route('family.requests.index'))
            ->assertOk()
            ->assertSee('One-time care · date passed')
            ->assertSee('Resolve the old request for Mary Villano')
            ->assertDontSee('A caregiver is waiting for your review')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Resolve request'));
    }

    public function test_family_can_follow_a_request_from_request_to_payment_on_one_care_story(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Amy Ten Broek']);
        $startsAt = now()->addDay()->setTime(10, 0);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Internal title',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => $startsAt,
            'requested_end_at' => $startsAt->copy()->addHours(3),
            'city' => 'Apex',
            'state' => 'NC',
            'zip' => '27502',
            'address_line1' => '100 Main Street',
            'first_hire_at' => now(),
        ]);
        $request->recipient()->create([
            'full_name' => 'Mary Villano',
            'relationship_to_family' => 'Mother',
            'recipient_is_requester' => false,
        ]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $startsAt,
            'scheduled_end_at' => $startsAt->copy()->addHours(3),
        ]);

        $this->actingAs($family)
            ->get(route('family.care.journey', ['resourceType' => 'request', 'resourceId' => $request->id]))
            ->assertOk()
            ->assertSee('The complete story')
            ->assertSee('Mary Villano with Amy Ten Broek')
            ->assertSeeInOrder([
                'Care requested',
                'Caregiver selected',
                'Visit scheduled',
                'Care delivered',
                'Payment',
            ])
            ->assertSee('Open visit details');
    }

    public function test_overview_stays_account_wide_while_arrangements_can_focus_one_recipient(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        foreach (['Mary Villano', 'Linda Harris'] as $index => $recipientName) {
            $startsAt = now()->addDays($index + 2)->setTime(9, 0);
            $request = CareRequest::query()->create([
                'family_user_id' => $family->id,
                'title' => 'Internal title '.$index,
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'status' => CareRequest::STATUS_OPEN,
                'requested_start_at' => $startsAt,
                'requested_end_at' => $startsAt->copy()->addHours(2),
                'city' => 'Apex',
                'state' => 'NC',
                'zip' => '27502',
                'address_line1' => '100 Main Street',
            ]);
            $request->recipient()->create([
                'full_name' => $recipientName,
                'relationship_to_family' => 'Mother',
                'recipient_is_requester' => false,
            ]);
        }

        $this->actingAs($family)
            ->get(route('family.requests.index', ['person' => 'Mary Villano']))
            ->assertOk()
            ->assertDontSee('Whose care?')
            ->assertDontSee('Care recipient')
            ->assertSee('View all (2)');

        $this->actingAs($family)
            ->get(route('family.care.index', ['person' => 'Mary Villano']))
            ->assertOk()
            ->assertSee('Request #1')
            ->assertDontSee('Request #2');
    }

    public function test_dense_schedule_loads_the_nearest_visits_progressively(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($family);

        foreach (range(1, 25) as $index) {
            $startsAt = now()->addDays($index)->setTime(9, 0);
            $request = CareRequest::query()->create([
                'family_user_id' => $family->id,
                'title' => 'Dense schedule visit '.$index,
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'status' => CareRequest::STATUS_FILLED,
                'requested_start_at' => $startsAt,
                'requested_end_at' => $startsAt->copy()->addHours(2),
                'city' => 'Apex',
                'state' => 'NC',
                'zip' => '27502',
                'address_line1' => '100 Main Street',
            ]);
            $request->recipient()->create([
                'full_name' => 'Mary Villano',
                'relationship_to_family' => 'Mother',
            ]);
            CareBooking::query()->create([
                'care_request_id' => $request->id,
                'family_user_id' => $family->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $startsAt,
                'scheduled_end_at' => $startsAt->copy()->addHours(2),
            ]);
        }

        Livewire::actingAs($family)
            ->test(CareSchedule::class)
            ->assertViewHas('totalVisitCount', 25)
            ->assertViewHas('visits', fn ($visits): bool => $visits->count() === 8)
            ->assertSee('Showing the next 8 visits.')
            ->call('loadMoreVisits')
            ->assertViewHas('visits', fn ($visits): bool => $visits->count() === 16)
            ->assertSee('Show the next 8')
            ->call('loadMoreVisits')
            ->assertViewHas('visits', fn ($visits): bool => $visits->count() === 24)
            ->assertSee('Show the next 1')
            ->call('loadMoreVisits')
            ->assertViewHas('visits', fn ($visits): bool => $visits->count() === 25)
            ->assertDontSee('Show the next');
    }

    public function test_dense_action_inbox_keeps_the_overview_short_and_loads_more_on_demand(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        foreach (range(1, 15) as $index) {
            $startsAt = now()->addDays($index)->setTime(9, 0);
            $request = CareRequest::query()->create([
                'family_user_id' => $family->id,
                'title' => 'Action request '.$index,
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'status' => CareRequest::STATUS_OPEN,
                'requested_start_at' => $startsAt,
                'requested_end_at' => $startsAt->copy()->addHours(2),
                'city' => 'Apex',
                'state' => 'NC',
                'zip' => '27502',
                'address_line1' => '100 Main Street',
            ]);
            $request->recipient()->create([
                'full_name' => 'Mary Villano',
                'relationship_to_family' => 'Mother',
            ]);
            CareRequestApplication::query()->create([
                'care_request_id' => $request->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => CareRequestApplication::STATUS_APPLIED,
                'proposed_rate' => 30,
            ]);
        }

        Livewire::actingAs($family)
            ->test(RequestsIndex::class)
            ->assertViewHas('attentionCount', 15)
            ->assertViewHas('familyActions', fn ($actions): bool => $actions->count() === 3)
            ->assertSee('View all 15')
            ->assertDontSee('Review 12 more actions');

        Livewire::actingAs($family)
            ->test(CareActionsIndex::class)
            ->assertViewHas('totalActionCount', 15)
            ->assertViewHas('actions', fn ($actions): bool => $actions->count() === 8)
            ->assertSee('Showing 8 of 15 actions.')
            ->call('loadMoreActions')
            ->assertViewHas('actions', fn ($actions): bool => $actions->count() === 15)
            ->assertDontSee('Show more actions');
    }

    public function test_regular_care_story_uses_the_earliest_confirmed_visit(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Amy Ten Broek']);
        $this->actingAs($family);
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Regular care test',
            'recipient_snapshot' => ['full_name' => 'Mary Villano'],
            'schedule_days' => [1, 3, 5],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '11:00',
            'starts_on' => now()->toDateString(),
            'hourly_rate' => 30,
            'accepted_at' => now()->subWeek(),
            'activated_at' => now()->subWeek(),
        ]);

        $firstStartsAt = now()->addDay()->setTime(9, 0);
        foreach (range(0, 12) as $index) {
            $startsAt = $firstStartsAt->copy()->addDays($index);
            $request = CareRequest::query()->create([
                'family_user_id' => $family->id,
                'care_plan_id' => $plan->id,
                'is_system_generated' => true,
                'title' => 'Generated regular visit '.$index,
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'status' => CareRequest::STATUS_FILLED,
                'requested_start_at' => $startsAt,
                'requested_end_at' => $startsAt->copy()->addHours(2),
                'city' => 'Apex',
                'state' => 'NC',
                'zip' => '27502',
                'address_line1' => '100 Main Street',
            ]);
            CareBooking::query()->create([
                'care_request_id' => $request->id,
                'care_plan_id' => $plan->id,
                'family_user_id' => $family->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $startsAt,
                'scheduled_end_at' => $startsAt->copy()->addHours(2),
            ]);
        }

        $this->get(route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id]))
            ->assertOk()
            ->assertSee('Next visit')
            ->assertSee($firstStartsAt->format('l, F j').' · 9:00 AM–11:00 AM');
    }

    public function test_regular_care_separates_current_and_past_arrangements(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($family);
        $makePlan = fn (string $status, string $recipient): CarePlan => CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => $status,
            'title' => $recipient.' care',
            'recipient_snapshot' => ['full_name' => $recipient],
            'schedule_days' => [1],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '11:00',
            'starts_on' => now()->toDateString(),
            'hourly_rate' => 30,
        ]);
        $makePlan(CarePlan::STATUS_ACTIVE, 'Mary Current');
        $makePlan(CarePlan::STATUS_ENDED, 'Linda Past');

        $this->get(route('family.care.index'))
            ->assertOk()
            ->assertSee('Mary Current with')
            ->assertDontSee('Linda Past with');

        $this->get(route('family.care.index', ['view' => 'past']))
            ->assertOk()
            ->assertSee('Linda Past with')
            ->assertSee('This arrangement is closed.')
            ->assertDontSee('Mary Current with');
    }

    public function test_arrangements_filters_cover_one_time_regular_and_every_plan_lifecycle_group(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Care Helper']);
        $this->actingAs($family);

        foreach ([
            [CareRequest::TYPE_ONE_TIME, 'One Time Person'],
            [CareRequest::TYPE_RECURRING, 'Repeating Request Person'],
        ] as [$type, $recipient]) {
            $request = CareRequest::query()->create([
                'family_user_id' => $family->id,
                'title' => $recipient.' request',
                'request_type' => $type,
                'status' => CareRequest::STATUS_OPEN,
                'requested_start_at' => now()->addWeek()->setTime(9, 0),
                'requested_end_at' => now()->addWeek()->setTime(11, 0),
                'city' => 'Apex',
                'state' => 'NC',
                'zip' => '27502',
                'address_line1' => '100 Main Street',
            ]);
            $request->recipient()->create([
                'full_name' => $recipient,
                'relationship_to_family' => 'Parent',
            ]);
        }

        foreach ([
            CarePlan::STATUS_PENDING_CAREGIVER => 'Pending Plan Person',
            CarePlan::STATUS_COUNTERED => 'Countered Plan Person',
            CarePlan::STATUS_ACTIVE => 'Active Plan Person',
            CarePlan::STATUS_PAYMENT_ATTENTION => 'Payment Plan Person',
            CarePlan::STATUS_PAUSED => 'Paused Plan Person',
            CarePlan::STATUS_ENDED => 'Past Plan Person',
        ] as $status => $recipient) {
            CarePlan::query()->create([
                'family_user_id' => $family->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => $status,
                'title' => $recipient.' care',
                'recipient_snapshot' => ['full_name' => $recipient],
                'schedule_days' => [1],
                'schedule_start_time' => '09:00',
                'schedule_end_time' => '11:00',
                'starts_on' => now()->toDateString(),
                'hourly_rate' => 30,
            ]);
        }

        $this->get(route('family.care.index', ['view' => 'arranging']))
            ->assertOk()
            ->assertSee('One Time Person ·')
            ->assertSee('Regular care for Repeating Request Person')
            ->assertSee('Pending Plan Person with Care Helper')
            ->assertSee('Countered Plan Person with Care Helper')
            ->assertDontSee('Active Plan Person with Care Helper')
            ->assertDontSee('Past Plan Person with Care Helper');

        $this->get(route('family.care.index', ['view' => 'ongoing', 'type' => 'regular']))
            ->assertOk()
            ->assertSee('Active Plan Person with Care Helper')
            ->assertSee('Payment Plan Person with Care Helper')
            ->assertSee('Paused Plan Person with Care Helper')
            ->assertDontSee('Pending Plan Person with Care Helper')
            ->assertDontSee('One Time Person ·');

        $this->get(route('family.care.index', ['view' => 'active']))
            ->assertOk()
            ->assertSee('Active Plan Person with Care Helper')
            ->assertSee('Payment Plan Person with Care Helper')
            ->assertDontSee('Paused Plan Person with Care Helper');

        $this->get(route('family.care.index', ['view' => 'past']))
            ->assertOk()
            ->assertSee('Past Plan Person with Care Helper')
            ->assertDontSee('Active Plan Person with Care Helper');

        $this->get(route('family.care.index', ['view' => 'current', 'type' => 'one_time']))
            ->assertOk()
            ->assertSee('One Time Person ·')
            ->assertDontSee('Regular care for Repeating Request Person')
            ->assertDontSee('Active Plan Person with Care Helper');
    }
}
