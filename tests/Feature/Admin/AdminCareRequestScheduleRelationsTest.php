<?php

namespace Tests\Feature\Admin;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\User;
use App\Support\AdminCareRequestSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminCareRequestScheduleRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_eager_loaded_source_plan_finds_first_valid_future_booking_without_presenter_queries(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(8, 0));
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $source = CareRequest::query()->create([
            'family_user_id' => $family->id, 'title' => 'Weekly care',
            'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => CareRequest::TYPE_RECURRING, 'status' => CareRequest::STATUS_FILLED,
            'recurring_starts_on' => '2026-10-12', 'recurring_days' => [1, 3, 5],
            'recurring_start_time' => '09:00', 'recurring_end_time' => '12:00',
        ]);
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $source->id, 'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Weekly care agreement',
            'schedule_days' => [1, 3, 5], 'schedule_start_time' => '09:00', 'schedule_end_time' => '12:00',
            'starts_on' => '2026-10-12', 'timezone' => 'America/New_York', 'hourly_rate' => 30,
        ]);

        // Insert the later valid visit first: selecting a default has-one/id is incorrect.
        $this->booking($plan, '2026-10-22 09:00:00');
        $expected = $this->booking($plan, '2026-10-19 11:00:00');
        $this->booking($plan, '2026-10-19 15:00:00');
        $this->booking($plan, '2026-10-13 09:00:00');
        $this->booking($plan, '2026-10-16 09:00:00', ['status' => CareBooking::STATUS_CANCELLED]);
        $this->booking($plan, '2026-10-16 10:00:00', ['status' => CareBooking::STATUS_COMPLETED]);
        $this->booking($plan, '2026-10-17 09:00:00', ['replacement_released_at' => now()]);
        $this->booking($plan, '2026-10-18 09:00:00', ['scheduled_end_at' => '2026-10-18 08:00:00']);

        $loaded = CareRequest::query()->with(AdminCareRequestSchedule::relations())->findOrFail($source->id);
        $this->assertNull($loaded->care_plan_id);
        $this->assertSame($expected->id, $loaded->sourceCarePlans->first()->nextUpcomingBooking->id);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $schedule = app(AdminCareRequestSchedule::class)->present($loaded);

        $this->assertSame('2026-10-19 11:00:00', $schedule['next_at']->format('Y-m-d H:i:s'));
        $this->assertSame('Next confirmed visit', $schedule['confirmed']['label']);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->travelBack();
    }

    private function booking(CarePlan $plan, string $start, array $attributes = []): CareBooking
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $plan->family_user_id, 'care_plan_id' => $plan->id,
            'is_system_generated' => true, 'title' => 'Confirmed visit',
            'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => CareRequest::TYPE_ONE_TIME, 'status' => CareRequest::STATUS_FILLED,
        ]);

        return CareBooking::query()->create(array_merge([
            'care_request_id' => $request->id, 'care_plan_id' => $plan->id,
            'family_user_id' => $plan->family_user_id, 'caregiver_user_id' => $plan->caregiver_user_id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => \Carbon\Carbon::parse($start)->addHours(2),
        ], $attributes));
    }
}
