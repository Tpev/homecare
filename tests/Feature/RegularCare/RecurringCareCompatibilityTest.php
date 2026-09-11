<?php

namespace Tests\Feature\RegularCare;

use App\Livewire\Family\CareJourney;
use App\Livewire\Family\RegularCareShow;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RecurringCareCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_continuous_and_extra_care_keep_their_type_and_exact_generated_visit_link(): void
    {
        [$family, $plan] = $this->legacyPlan();
        $booking = $this->booking($plan, ['plan_visit_kind' => 'coverage']);
        foreach (['coverage' => 'Continuous care', 'extra' => 'Extra visit', 'completed_extra' => 'Extra visit', 'regular' => 'Recurring visit'] as $kind => $label) {
            $booking->update(['plan_visit_kind' => $kind]);
            $before = $booking->fresh()->getRawOriginal();
            Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
                ->assertSee($label.' · Visit #'.$booking->id)
                ->assertSee('href="'.e(route('family.requests.show', [
                    'careRequest' => $booking->care_request_id, 'tab' => 'shift',
                    'return_tab' => 'overview', 'visit_filter' => 'upcoming', 'return_page' => 1,
                ])).'"', false)
                ->call('setActiveTab', 'visits')
                ->assertSee($label.' · Visit #'.$booking->id);
            $this->assertSame($before, $booking->fresh()->getRawOriginal());
        }
    }

    public function test_legacy_plan_without_source_profile_snapshots_or_next_booking_renders_every_tab_and_journey(): void
    {
        [$family, $plan] = $this->legacyPlan();
        $before = $plan->fresh()->getRawOriginal();
        $component = Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
            ->assertSee('No upcoming visit is booked yet');
        foreach (['caregivers', 'visits', 'details', 'overview'] as $tab) {
            $component->call('setActiveTab', $tab)->assertSet('activeTab', $tab)->assertStatus(200);
        }
        Livewire::actingAs($family)->test(CareJourney::class, ['resourceType' => 'regular', 'resourceId' => $plan->id])
            ->assertSee('No confirmed visits yet.')
            ->assertViewHas('journey', fn (array $journey) => $journey['visits']->isEmpty());
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_closed_plans_keep_remaining_and_completed_visits_in_home_and_journey(): void
    {
        [$family, $plan] = $this->legacyPlan();
        $remaining = $this->booking($plan);
        $past = $this->booking($plan, [
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subDays(3), 'scheduled_end_at' => now()->subDays(3)->addHours(2),
            'family_confirmed_at' => now()->subDays(2), 'completed_at' => now()->subDays(3)->addHours(2),
        ]);
        $before = CareBooking::query()->orderBy('id')->get()->map->getRawOriginal()->all();
        foreach ([CarePlan::STATUS_ENDED, CarePlan::STATUS_CANCELLED] as $status) {
            $plan->update(['status' => $status]);
            Livewire::actingAs($family)->test(RegularCareShow::class, ['carePlan' => $plan->id])
                ->assertSee('Your remaining booked visit')
                ->assertViewHas('nextVisit', fn (CareBooking $next) => $next->is($remaining))
                ->call('setVisitFilter', 'past')
                ->assertViewHas('visits', fn ($visits) => $visits->pluck('id')->all() === [$past->id])
                ->assertDontSee('Manage recurring care');
            Livewire::actingAs($family)->test(CareJourney::class, ['resourceType' => 'regular', 'resourceId' => $plan->id])
                ->assertViewHas('journey', fn (array $journey) => $journey['visits']->pluck('id')->all() === [$remaining->id, $past->id]);
        }
        $this->assertSame($before, CareBooking::query()->orderBy('id')->get()->map->getRawOriginal()->all());
    }

    public function test_journey_keeps_current_live_and_paused_visits_after_scheduled_grace_but_excludes_stale_visits(): void
    {
        [$family, $plan] = $this->legacyPlan();
        $current = $this->booking($plan, [
            'scheduled_start_at' => now()->subHours(4), 'scheduled_end_at' => now()->addHours(2),
            'started_at' => now()->subHours(4),
        ]);
        $recentWithoutEnd = $this->booking($plan, ['scheduled_start_at' => now()->subHours(3), 'scheduled_end_at' => null]);
        $stale = $this->booking($plan, ['scheduled_start_at' => now()->subDays(3), 'scheduled_end_at' => now()->subDay()->subSecond()]);
        $staleWithoutEnd = $this->booking($plan, ['scheduled_start_at' => now()->subDay()->subSecond(), 'scheduled_end_at' => null]);
        $this->booking($plan, [
            'scheduled_start_at' => now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes() + 1),
            'scheduled_end_at' => now()->addHours(2),
        ]);
        $future = $this->booking($plan);

        foreach ([CareBooking::STATUS_IN_PROGRESS => 'In progress', CareBooking::STATUS_PAUSED => 'Paused'] as $status => $label) {
            foreach ([$current, $recentWithoutEnd, $stale, $staleWithoutEnd] as $booking) {
                $booking->update(['status' => $status]);
            }
            $before = CareBooking::query()->orderBy('id')->get()->map->getRawOriginal()->all();
            Livewire::actingAs($family)->test(CareJourney::class, ['resourceType' => 'regular', 'resourceId' => $plan->id])
                ->assertViewHas('journey', function (array $journey) use ($current, $recentWithoutEnd, $future, $label): bool {
                    $this->assertSame([$current->id, $recentWithoutEnd->id, $future->id], $journey['visits']->pluck('id')->all());
                    $currentCard = $journey['visits']->firstWhere('id', $current->id);
                    $this->assertSame($label, $currentCard['status']);
                    $this->assertSame(route('family.requests.show', $current->care_request_id), $currentCard['url']);
                    $nextStage = $journey['timeline']->firstWhere('label', 'Next visit');
                    $this->assertSame('current', $nextStage['state']);
                    $this->assertTrue($nextStage['at']->equalTo($current->scheduled_start_at));

                    return true;
                });
            $this->assertSame($before, CareBooking::query()->orderBy('id')->get()->map->getRawOriginal()->all());
        }
    }

    private function legacyPlan(): array
    {
        $this->travelTo(Carbon::parse('2026-09-11 12:00:00'));
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Legacy Caregiver']);
        $this->actingAs($family);
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'title' => 'Legacy recurring care', 'status' => CarePlan::STATUS_ACTIVE,
            'source_care_request_id' => null, 'source_care_booking_id' => null, 'next_booking_id' => null,
            'recipient_snapshot' => null, 'address_snapshot' => null, 'task_snapshot' => null,
            'schedule_days' => [1, 3], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => '2026-09-12', 'hourly_rate' => 30,
        ]);

        return [$family, $plan];
    }

    private function booking(CarePlan $plan, array $attributes = []): CareBooking
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $plan->family_user_id, 'care_plan_id' => $plan->id,
            'is_system_generated' => true, 'title' => 'Generated legacy plan visit',
            'request_type' => CareRequest::TYPE_ONE_TIME, 'status' => CareRequest::STATUS_FILLED,
            'address_line1' => '100 Visit Lane', 'city' => 'Apex', 'state' => 'NC', 'zip' => '27502',
        ]);

        return CareBooking::query()->create(array_merge([
            'care_request_id' => $request->id, 'care_plan_id' => $plan->id,
            'family_user_id' => $plan->family_user_id, 'caregiver_user_id' => $plan->caregiver_user_id,
            'status' => CareBooking::STATUS_SCHEDULED, 'plan_visit_kind' => 'regular',
            'scheduled_start_at' => now()->addDay()->setTime(9, 0), 'scheduled_end_at' => now()->addDay()->setTime(11, 0),
        ], $attributes));
    }
}
