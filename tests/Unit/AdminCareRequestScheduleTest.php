<?php

namespace Tests\Unit;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Support\AdminCareRequestSchedule;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class AdminCareRequestScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/New_York']);
        Carbon::setTestNow(Carbon::parse('2026-10-15 08:00:00', 'America/New_York'));
        CarbonImmutable::setTestNow(Carbon::getTestNow());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_one_time_care_displays_actual_dates_duration_and_dated_timezone(): void
    {
        $schedule = $this->present($this->request());

        $this->assertSame('Thu, Oct 15, 2026', $schedule['primary']['date']);
        $this->assertSame('9:00 AM–12:30 PM', $schedule['primary']['time']);
        $this->assertSame('3 hours 30 minutes', $schedule['primary']['duration']);
        $this->assertSame('America/New_York · EDT (UTC-04:00)', $schedule['primary']['timezone']);
        $this->assertSame('2026-10-15 09:00:00', $schedule['next_at']->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('EDT (America/New_York)', $schedule['next']);
    }

    public function test_confirmed_booking_preserves_original_request_and_ignores_worked_duration(): void
    {
        $request = $this->request(['status' => CareRequest::STATUS_FILLED]);
        $request->setRelation('booking', $this->booking([
            'scheduled_start_at' => '2026-10-16 14:00:00',
            'scheduled_end_at' => '2026-10-16 16:00:00',
            'expected_minutes' => 900,
            'worked_minutes' => 600,
        ]));
        $schedule = $this->present($request);

        $this->assertSame('Thu, Oct 15, 2026', $schedule['requested']['date']);
        $this->assertSame('Fri, Oct 16, 2026', $schedule['confirmed']['date']);
        $this->assertSame('2 hours', $schedule['primary']['duration']);
        $this->assertSame('2026-10-16 14:00:00', $schedule['next_at']->format('Y-m-d H:i:s'));
    }

    public function test_missing_or_invalid_schedule_never_uses_posting_date_or_zero_duration(): void
    {
        $request = $this->request(['requested_start_at' => null, 'requested_end_at' => null]);
        $request->created_at = Carbon::now()->addDay();
        $schedule = $this->present($request);
        $this->assertSame('Schedule not set', $schedule['primary']['date']);
        $this->assertSame('Duration unavailable', $schedule['primary']['duration']);
        $this->assertNull($schedule['next_at']);
        $this->assertNull($schedule['next']);

        $request->requested_start_at = '2026-10-15 09:00:00';
        $schedule = $this->present($request);
        $this->assertSame('9:00 AM · End time missing', $schedule['primary']['time']);
        $this->assertSame('Duration unavailable', $schedule['primary']['duration']);

        $request->requested_end_at = '2026-10-15 08:30:00';
        $schedule = $this->present($request);
        $this->assertSame('End time must be after start time.', $schedule['primary']['notice']);
        $this->assertNull($schedule['next_at']);
    }

    public function test_overnight_and_daylight_saving_visits_use_elapsed_duration(): void
    {
        $schedule = $this->present($this->request([
            'requested_start_at' => '2026-10-15 21:00:00',
            'requested_end_at' => '2026-10-16 07:00:00',
        ]));
        $this->assertSame('Thu, Oct 15, 2026 – Fri, Oct 16, 2026', $schedule['primary']['date']);
        $this->assertSame('10 hours', $schedule['primary']['duration']);

        $schedule = $this->present($this->request([
            'requested_start_at' => '2026-11-01 00:30:00',
            'requested_end_at' => '2026-11-01 03:30:00',
        ]));
        $this->assertSame('4 hours', $schedule['primary']['duration']);
        $this->assertSame('12:30 AM EDT–3:30 AM EST', $schedule['primary']['time']);
        $this->assertStringContainsString('Clock change', $schedule['primary']['notice']);
    }

    public function test_timezone_from_plan_is_used_without_browser_timezone_conversion(): void
    {
        $request = $this->request();
        $request->setRelation('carePlan', $this->plan(['timezone' => 'America/Los_Angeles']));
        $schedule = $this->present($request);

        $this->assertSame('6:00 AM–9:30 AM', $schedule['primary']['time']);
        $this->assertSame('America/Los_Angeles · PDT (UTC-07:00)', $schedule['primary']['timezone']);
        $this->assertSame('America/Los_Angeles', $schedule['next_at']->timezoneName);
    }

    public function test_closed_requests_and_finished_bookings_cannot_be_upcoming(): void
    {
        foreach ([CareRequest::STATUS_DRAFT, CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED] as $status) {
            $this->assertNull($this->present($this->request(['status' => $status]))['next_at']);
        }
        foreach ([CareBooking::STATUS_COMPLETED, CareBooking::STATUS_CANCELLED, CareBooking::STATUS_REVIEWED, CareBooking::STATUS_DISPUTED] as $status) {
            $request = $this->request();
            $request->setRelation('booking', $this->booking(['status' => $status]));
            $schedule = $this->present($request);
            $this->assertNull($schedule['next_at']);
            $this->assertStringContainsString('Booking '.$status, $schedule['confirmed']['notice']);
        }
    }

    public function test_recurring_summary_retains_each_day_and_computes_weekly_total(): void
    {
        $schedule = $this->present($this->recurring());

        $this->assertSame('Mon, Wed, Fri', $schedule['primary']['date']);
        $this->assertSame('3 visits · 8 hours/week', $schedule['primary']['duration']);
        $this->assertSame([
            ['day' => 'Monday', 'time' => '9:00 AM–12:00 PM', 'duration' => '3 hours'],
            ['day' => 'Wednesday', 'time' => '9:00 AM–12:00 PM', 'duration' => '3 hours'],
            ['day' => 'Friday', 'time' => '2:00 PM–4:00 PM', 'duration' => '2 hours'],
        ], $schedule['primary']['rows']);
        $this->assertSame('Starts Mon, Oct 12, 2026 · No end date specified', $schedule['primary']['range']);
        $this->assertSame('2026-10-16 14:00:00', $schedule['next_at']->format('Y-m-d H:i:s'));
    }

    public function test_recurring_respects_start_end_and_local_weekday(): void
    {
        $request = $this->recurring(['recurring_starts_on' => '2027-01-01', 'recurring_ends_on' => '2027-01-01']);
        $schedule = $this->present($request);
        $this->assertSame('2027-01-01 14:00:00 EST', $schedule['next_at']->format('Y-m-d H:i:s T'));

        $request->recurring_ends_on = '2026-12-31';
        $this->assertNull($this->present($request)['next_at']);
        $request->recurring_starts_on = '2026-10-01';
        $request->recurring_ends_on = '2026-10-14';
        $this->assertNull($this->present($request)['next_at']);
        $this->assertSame('Recurring schedule has ended.', $this->present($request)['primary']['notice']);

        $request = $this->recurring([
            'recurring_schedule' => [['day' => 4, 'start_time' => '05:30', 'end_time' => '07:00']],
        ]);
        $request->setRelation('carePlan', $this->plan(['timezone' => 'America/Los_Angeles']));
        $this->assertSame('2026-10-15 05:30:00 PDT', $this->present($request)['next_at']->format('Y-m-d H:i:s T'));
    }

    public function test_missing_recurring_start_or_invalid_slots_are_explicit(): void
    {
        $request = $this->recurring(['recurring_starts_on' => null]);
        $schedule = $this->present($request);
        $this->assertNull($schedule['next_at']);
        $this->assertStringContainsString('Start date not set', $schedule['primary']['range']);

        $request->recurring_schedule = [['day' => 1, 'start_time' => '09:00', 'end_time' => null]];
        $schedule = $this->present($request);
        $this->assertSame('Schedule not set', $schedule['primary']['date']);
        $this->assertSame('Duration unavailable', $schedule['primary']['duration']);
        $this->assertNull($schedule['next_at']);
    }

    public function test_legacy_shared_recurring_times_and_partial_slots_are_supported(): void
    {
        $request = $this->recurring([
            'recurring_schedule' => null,
            'recurring_days' => [2, 4],
            'recurring_start_time' => '09:15:00',
            'recurring_end_time' => '11:00:00',
        ]);
        $schedule = $this->present($request);
        $this->assertSame('2 visits · 3 hours 30 minutes/week', $schedule['primary']['duration']);
        $this->assertSame('2026-10-15 09:15:00', $schedule['next_at']->format('Y-m-d H:i:s'));

        $request = $this->recurring([
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                ['day' => 3, 'start_time' => '09:00', 'end_time' => null],
            ],
        ]);
        $this->assertStringContainsString('totals include valid visits only', $this->present($request)['primary']['notice']);
    }

    public function test_filled_recurring_uses_next_confirmed_visit_instead_of_original_weekly_projection(): void
    {
        $request = $this->recurring(['status' => CareRequest::STATUS_FILLED]);
        $request->setRelation('booking', $this->booking(['status' => CareBooking::STATUS_COMPLETED]));
        $plan = $this->plan();
        $plan->setRelation('nextUpcomingBooking', $this->booking([
            'scheduled_start_at' => '2026-10-17 10:00:00',
            'scheduled_end_at' => '2026-10-17 12:00:00',
        ]));
        $request->setRelation('sourceCarePlans', new Collection([$plan]));
        $schedule = $this->present($request);

        $this->assertSame('Mon, Wed, Fri', $schedule['primary']['date']);
        $this->assertSame('Next confirmed visit', $schedule['confirmed']['label']);
        $this->assertSame('Sat, Oct 17, 2026', $schedule['confirmed']['date']);
        $this->assertSame('2026-10-17 10:00:00', $schedule['next_at']->format('Y-m-d H:i:s'));

        $plan->setRelation('nextUpcomingBooking', null);
        $schedule = $this->present($request);
        $this->assertNull($schedule['next_at']);
        $this->assertStringContainsString('No upcoming confirmed visit', $schedule['primary']['notice']);
    }

    public function test_multiple_source_plans_use_earliest_future_booking_and_its_timezone(): void
    {
        $request = $this->recurring(['status' => CareRequest::STATUS_FILLED]);
        $latePlan = $this->plan(['id' => 1]);
        $latePlan->setRelation('nextUpcomingBooking', $this->booking(['scheduled_start_at' => '2026-10-17 10:00:00', 'scheduled_end_at' => '2026-10-17 11:00:00']));
        $earlyPlan = $this->plan(['id' => 2, 'timezone' => 'America/Chicago']);
        $earlyPlan->setRelation('nextUpcomingBooking', $this->booking(['scheduled_start_at' => '2026-10-16 10:00:00', 'scheduled_end_at' => '2026-10-16 11:00:00']));
        $request->setRelation('sourceCarePlans', new Collection([$latePlan, $earlyPlan]));

        $schedule = $this->present($request);
        $this->assertSame('2026-10-16 09:00:00 CDT', $schedule['next_at']->format('Y-m-d H:i:s T'));
        $this->assertStringContainsString('America/Chicago', $schedule['confirmed']['timezone']);
    }

    private function present(CareRequest $request): array
    {
        return (new AdminCareRequestSchedule)->present($request);
    }

    private function request(array $attributes = []): CareRequest
    {
        return (new CareRequest(array_merge([
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => '2026-10-15 09:00:00',
            'requested_end_at' => '2026-10-15 12:30:00',
        ], $attributes)))
            ->setRelation('booking', null)
            ->setRelation('carePlan', null)
            ->setRelation('sourceCarePlans', new Collection);
    }

    private function recurring(array $attributes = []): CareRequest
    {
        return $this->request(array_merge([
            'request_type' => CareRequest::TYPE_RECURRING,
            'requested_start_at' => null,
            'requested_end_at' => null,
            'recurring_starts_on' => '2026-10-12',
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
                ['day' => 3, 'start_time' => '09:00', 'end_time' => '12:00'],
                ['day' => 5, 'start_time' => '14:00', 'end_time' => '16:00'],
            ],
        ], $attributes));
    }

    private function booking(array $attributes = []): CareBooking
    {
        return (new CareBooking(array_merge([
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => '2026-10-15 09:00:00',
            'scheduled_end_at' => '2026-10-15 12:30:00',
        ], $attributes)))->setRelation('carePlan', null);
    }

    private function plan(array $attributes = []): CarePlan
    {
        $plan = new CarePlan(array_merge(['status' => CarePlan::STATUS_ACTIVE, 'timezone' => 'America/New_York'], $attributes));
        if (isset($attributes['id'])) {
            $plan->id = $attributes['id'];
        }

        return $plan->setRelation('nextUpcomingBooking', null);
    }
}
