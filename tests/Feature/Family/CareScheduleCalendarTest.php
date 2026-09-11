<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\CareSchedule;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class CareScheduleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $family;

    private User $caregiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00'));
        $this->family = User::factory()->create(['role' => 'family']);
        $this->caregiver = User::factory()->create(['role' => 'caregiver']);
    }

    public function test_calendar_loads_every_booking_in_its_grid_without_changing_list_pagination(): void
    {
        $bookings = collect(range(2, 13))->map(fn (int $day) => $this->booking(sprintf('2026-09-%02d 09:00:00', $day)));
        $before = $bookings->map(fn (CareBooking $booking) => $booking->fresh()->getRawOriginal())->all();

        $component = Livewire::actingAs($this->family)->test(CareSchedule::class)
            ->assertSet('scheduleView', 'list')
            ->assertViewHas('visits', fn (Collection $visits) => $visits->count() === 8)
            ->assertViewHas('totalVisitCount', 12)
            ->assertViewHas('hasMoreVisits', true)
            ->call('setView', 'calendar')
            ->assertSet('scheduleView', 'calendar')
            ->assertViewHas('calendarVisits', fn (Collection $visits) => $this->ids($visits) === $bookings->pluck('id')->all())
            ->call('selectDate', '2026-09-13')
            ->assertViewHas('selectedDay', fn (Carbon $date) => $date->toDateString() === '2026-09-13')
            ->assertViewHas('selectedVisits', fn (Collection $visits) => $this->ids($visits) === [$bookings->last()->id]);

        $component->call('setView', 'list')
            ->assertViewHas('visits', fn (Collection $visits) => $visits->count() === 8)
            ->call('loadMoreVisits')
            ->assertViewHas('visits', fn (Collection $visits) => $visits->count() === 12);

        $this->assertSame($before, $bookings->map(fn (CareBooking $booking) => $booking->fresh()->getRawOriginal())->all());
    }

    public function test_calendar_respects_shared_account_access_recipient_and_existing_upcoming_status_rules(): void
    {
        $scheduled = $this->booking('2026-09-03 09:00:00');
        $live = $this->booking('2026-08-31 23:00:00', '2026-09-01 13:00:00', status: CareBooking::STATUS_IN_PROGRESS);
        $paused = $this->booking('2026-09-01 09:00:00', '2026-09-01 14:00:00', status: CareBooking::STATUS_PAUSED);
        $otherPerson = $this->booking('2026-09-04 09:00:00', recipient: 'Linda Harris');
        foreach ([CareBooking::STATUS_CANCELLED, CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED, CareBooking::STATUS_DISPUTED] as $status) {
            $this->booking('2026-09-05 09:00:00', status: $status);
        }
        $this->booking(now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes() + 1)->toDateTimeString());
        $this->booking('2026-08-30 08:00:00', '2026-08-30 12:00:00', status: CareBooking::STATUS_IN_PROGRESS);
        $this->booking('2026-08-30 08:00:00', '2026-08-30 12:00:00', status: CareBooking::STATUS_PAUSED);
        $outsider = User::factory()->create(['role' => 'family']);
        $foreign = $this->booking('2026-09-03 08:00:00', family: $outsider);

        $account = app(FamilyAccountContext::class)->account($this->family);
        $member = User::factory()->create(['role' => 'family']);
        FamilyAccountMember::query()->where('user_id', $member->id)->delete();
        FamilyAccountMember::query()->create([
            'family_account_id' => $account->id,
            'user_id' => $member->id,
            'access_level' => FamilyAccountMember::ACCESS_MEMBER,
            'status' => FamilyAccountMember::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        $expected = [$scheduled->id, $live->id, $paused->id, $otherPerson->id];
        sort($expected);

        Livewire::actingAs($member)->test(CareSchedule::class)
            ->call('setView', 'calendar')
            ->assertViewHas('calendarVisits', fn (Collection $visits) => $this->ids($visits) === $expected)
            ->assertViewHas('visits', fn (Collection $visits) => $this->ids($visits) === $expected)
            ->set('recipient', 'Mary Villano')
            ->assertViewHas('calendarVisits', fn (Collection $visits) => $this->ids($visits) === [$scheduled->id, $live->id, $paused->id])
            ->assertViewHas('recipientOptions', fn (array $options) => collect($options)->pluck('value')->contains('Linda Harris'));

        $this->assertNotSame($account->id, $foreign->family_account_id);
        Livewire::actingAs($this->caregiver)->test(CareSchedule::class)->assertForbidden();
    }

    public function test_calendar_filters_actual_regular_extra_and_continuous_care_without_generating_recurrence(): void
    {
        $plan = $this->plan();
        $oneTime = $this->booking('2026-09-07 09:00:00');
        $regular = $this->booking('2026-09-07 12:00:00', plan: $plan);
        $extra = $this->booking('2026-09-08 09:00:00', plan: $plan, kind: 'extra');
        $completedExtra = $this->booking('2026-09-08 12:00:00', plan: $plan, kind: 'completed_extra');
        $coverage = $this->booking('2026-09-09 00:00:00', '2026-09-10 00:00:00', plan: $plan, kind: 'coverage');
        $this->plan('Unbooked person');
        $this->request($this->family, 'Unbooked person', CareRequest::TYPE_RECURRING);
        $before = CareBooking::query()->get()->map->getRawOriginal()->all();

        $component = Livewire::actingAs($this->family)->test(CareSchedule::class)->call('setView', 'calendar')
            ->assertViewHas('calendarVisits', fn (Collection $visits) => $visits->count() === 5);
        foreach (['one_time' => [$oneTime->id], 'regular' => [$regular->id], 'extra' => [$extra->id, $completedExtra->id], 'coverage' => [$coverage->id]] as $type => $ids) {
            $component->set('careType', $type)
                ->assertViewHas('calendarVisits', fn (Collection $visits) => $this->ids($visits) === $ids)
                ->assertViewHas('visits', fn (Collection $visits) => $this->ids($visits) === $ids);
        }
        $component->set('recipient', 'Unbooked person')
            ->assertViewHas('calendarVisits', fn (Collection $visits) => $visits->isEmpty());
        $this->assertDatabaseCount('care_bookings', 5);
        $this->assertSame($before, CareBooking::query()->get()->map->getRawOriginal()->all());
    }

    public function test_sunday_to_saturday_grid_includes_adjacent_months_and_uses_half_open_overlap(): void
    {
        $this->travelTo(Carbon::parse('2026-08-29 12:00:00'));
        $endsAtGridStart = $this->booking('2026-08-29 08:00:00', '2026-08-30 00:00:00', status: CareBooking::STATUS_IN_PROGRESS);
        $crossesGridStart = $this->booking('2026-08-29 23:00:00', '2026-08-30 01:00:00');
        $adjacentAugust = $this->booking('2026-08-31 09:00:00');
        $adjacentOctober = $this->booking('2026-10-03 09:00:00');
        $startsAfterGrid = $this->booking('2026-10-04 00:00:00');

        Livewire::withQueryParams(['view' => 'calendar', 'month' => '2026-09'])->actingAs($this->family)->test(CareSchedule::class)
            ->assertViewHas('calendarMonth', fn (Carbon $month) => $month->toDateString() === '2026-09-01')
            ->assertViewHas('calendarDays', function (Collection $days): bool {
                $this->assertCount(35, $days);
                $this->assertSame('2026-08-30', $days->first()['date']->toDateString());
                $this->assertSame('2026-10-03', $days->last()['date']->toDateString());
                $this->assertSame(Carbon::SUNDAY, $days->first()['date']->dayOfWeek);
                $this->assertSame(Carbon::SATURDAY, $days->last()['date']->dayOfWeek);
                $this->assertCount(30, $days->where('in_month', true));

                return true;
            })
            ->assertViewHas('calendarVisits', function (Collection $visits) use ($crossesGridStart, $adjacentAugust, $adjacentOctober, $endsAtGridStart, $startsAfterGrid): bool {
                $this->assertSame([$crossesGridStart->id, $adjacentAugust->id, $adjacentOctober->id], $this->ids($visits));
                $this->assertNotContains($endsAtGridStart->id, $this->ids($visits));
                $this->assertNotContains($startsAfterGrid->id, $this->ids($visits));

                return true;
            });
    }

    public function test_overnight_visit_appears_on_both_days_but_midnight_end_and_missing_end_do_not_spill(): void
    {
        $overnight = $this->booking('2026-09-12 22:00:00', '2026-09-13 02:00:00');
        $midnightEnd = $this->booking('2026-09-12 19:00:00', '2026-09-13 00:00:00');
        $noEnd = $this->booking('2026-09-12 18:00:00');
        $noEnd->update(['scheduled_end_at' => null]);
        $midnightStart = $this->booking('2026-09-13 00:00:00', '2026-09-13 03:00:00');

        Livewire::actingAs($this->family)->test(CareSchedule::class)->call('setView', 'calendar')
            ->assertViewHas('calendarDays', function (Collection $days) use ($overnight, $midnightEnd, $noEnd, $midnightStart): bool {
                $visitsOn = fn (string $date) => $this->ids($days->first(fn (array $day) => $day['date']->toDateString() === $date)['visits']);
                $this->assertSame([$overnight->id, $midnightEnd->id, $noEnd->id], $visitsOn('2026-09-12'));
                $this->assertSame([$overnight->id, $midnightStart->id], $visitsOn('2026-09-13'));
                $this->assertSame([], $visitsOn('2026-09-14'));

                return true;
            })
            ->call('selectDate', '2026-09-13')
            ->assertViewHas('selectedVisits', fn (Collection $visits) => $this->ids($visits) === [$overnight->id, $midnightStart->id]);
    }

    public function test_month_navigation_and_list_toggle_keep_existing_filters_and_today_restores_date(): void
    {
        Livewire::withQueryParams(['view' => 'calendar', 'month' => '2027-01', 'person' => 'Mary Villano', 'type' => 'regular'])
            ->actingAs($this->family)->test(CareSchedule::class)
            ->assertSet('scheduleView', 'calendar')
            ->call('previousMonth')->assertSet('month', '2026-12')
            ->call('nextMonth')->assertSet('month', '2027-01')
            ->call('nextMonth')->assertSet('month', '2027-02')
            ->call('selectDate', '2027-02-28')
            ->assertViewHas('selectedDay', fn (Carbon $day) => $day->toDateString() === '2027-02-28')
            ->call('setView', 'list')
            ->assertSet('recipient', 'Mary Villano')->assertSet('careType', 'regular')
            ->assertSet('month', '2027-02')->assertSet('selectedDate', '2027-02-28')
            ->call('setView', 'calendar')
            ->call('goToToday')
            ->assertSet('month', '2026-09')->assertSet('selectedDate', '2026-09-01')
            ->assertSet('recipient', 'Mary Villano')->assertSet('careType', 'regular')
            ->assertViewHas('selectedDay', fn (Carbon $date) => $date->isToday());
    }

    public function test_empty_month_opens_the_nearest_future_booking_without_inventing_current_month_visits(): void
    {
        $booking = $this->booking('2026-11-12 09:00:00');

        Livewire::actingAs($this->family)->test(CareSchedule::class)
            ->assertSet('month', '')
            ->call('setView', 'calendar')
            ->assertSet('month', '2026-11')
            ->assertSet('selectedDate', '2026-11-12')
            ->assertViewHas('selectedVisits', fn (Collection $visits) => $this->ids($visits) === [$booking->id])
            ->call('goToToday')
            ->assertViewHas('calendarVisits', fn (Collection $visits) => $visits->isEmpty());
        $this->assertDatabaseCount('care_bookings', 1);
    }

    public function test_invalid_url_and_action_values_normalize_without_parse_errors_or_calendar_rollover(): void
    {
        Livewire::withQueryParams(['view' => 'unexpected', 'month' => '2026-13', 'day' => 'not-a-date'])
            ->actingAs($this->family)->test(CareSchedule::class)
            ->assertSet('scheduleView', 'list')
            ->call('setView', 'calendar')
            ->assertViewHas('calendarMonth', fn (Carbon $date) => $date->format('Y-m') === '2026-09')
            ->set('month', '2026-02')
            ->call('selectDate', '2026-02-30')
            ->assertViewHas('calendarMonth', fn (Carbon $date) => $date->format('Y-m') === '2026-02')
            ->assertViewHas('selectedDay', fn (Carbon $date) => $date->format('Y-m') === '2026-02')
            ->set('month', 'not-a-month')
            ->assertViewHas('calendarMonth', fn (Carbon $date) => $date->format('Y-m') === '2026-09')
            ->call('selectDate', 'not-a-date')
            ->assertViewHas('selectedDay', fn (Carbon $date) => $date->format('Y-m') === '2026-09')
            ->call('setView', 'unexpected')->assertSet('scheduleView', 'list');
    }

    public function test_day_only_calendar_url_opens_that_month_instead_of_the_nearest_booking_month(): void
    {
        $this->booking('2026-09-10 09:00:00');
        $selected = $this->booking('2027-02-15 09:00:00');

        Livewire::withQueryParams(['view' => 'calendar', 'day' => '2027-02-15'])
            ->actingAs($this->family)->test(CareSchedule::class)
            ->assertSet('month', '2027-02')
            ->assertSet('selectedDate', '2027-02-15')
            ->assertViewHas('calendarMonth', fn (Carbon $date) => $date->toDateString() === '2027-02-01')
            ->assertViewHas('selectedDay', fn (Carbon $date) => $date->toDateString() === '2027-02-15')
            ->assertViewHas('selectedVisits', fn (Collection $visits) => $this->ids($visits) === [$selected->id]);
    }

    public function test_recurring_calendar_visit_link_opens_the_selected_occurrence_request(): void
    {
        $plan = $this->plan();
        $first = $this->booking('2026-09-12 09:00:00', plan: $plan);
        $selected = $this->booking('2026-09-13 09:00:00', plan: $plan);
        $first->careRequest->update(['care_plan_id' => $plan->id]);
        $selected->careRequest->update(['care_plan_id' => $plan->id]);
        $visitUrl = route('family.requests.show', ['careRequest' => $selected->care_request_id, 'tab' => 'shift']);
        $otherVisitUrl = route('family.requests.show', ['careRequest' => $first->care_request_id, 'tab' => 'shift']);
        $planStoryUrl = route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id]);

        $component = Livewire::withQueryParams(['view' => 'calendar', 'day' => '2026-09-13'])
            ->actingAs($this->family)->test(CareSchedule::class)
            ->assertViewHas('selectedVisits', fn (Collection $visits) => $this->ids($visits) === [$selected->id])
            ->assertSee('View visit')
            ->assertDontSee('href="'.e($otherVisitUrl).'"', false)
            ->assertDontSee('href="'.e($planStoryUrl).'"', false);

        $this->assertMatchesRegularExpression(
            '~<a\s[^>]*href="'.preg_quote(e($visitUrl), '~').'"[^>]*>\s*View visit\s*</a>~',
            $component->html(),
        );
    }

    private function ids(Collection $visits): array
    {
        return $visits->pluck('id')->sort()->values()->all();
    }

    private function request(User $family, string $recipient, string $type = CareRequest::TYPE_ONE_TIME): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Calendar test request',
            'request_type' => $type,
            'status' => CareRequest::STATUS_FILLED,
            'city' => 'Apex', 'state' => 'NC', 'zip' => '27502', 'address_line1' => '100 Main Street',
        ]);
        $request->recipient()->create(['full_name' => $recipient, 'relationship_to_family' => 'Mother']);

        return $request;
    }

    private function plan(string $recipient = 'Mary Villano'): CarePlan
    {
        return CarePlan::query()->create([
            'family_user_id' => $this->family->id, 'caregiver_user_id' => $this->caregiver->id,
            'status' => CarePlan::STATUS_ACTIVE, 'title' => 'Calendar recurring care',
            'recipient_snapshot' => ['full_name' => $recipient], 'schedule_days' => [1, 3, 5],
            'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => '2026-09-01', 'hourly_rate' => 30,
        ]);
    }

    private function booking(string $start, ?string $end = null, string $recipient = 'Mary Villano', string $status = CareBooking::STATUS_SCHEDULED, ?User $family = null, ?CarePlan $plan = null, ?string $kind = null): CareBooking
    {
        $family ??= $this->family;
        $request = $this->request($family, $recipient, $plan ? CareRequest::TYPE_RECURRING : CareRequest::TYPE_ONE_TIME);

        return CareBooking::query()->create([
            'care_request_id' => $request->id, 'care_plan_id' => $plan?->id, 'plan_visit_kind' => $kind,
            'family_user_id' => $family->id, 'caregiver_user_id' => $this->caregiver->id,
            'status' => $status, 'scheduled_start_at' => $start,
            'scheduled_end_at' => $end ?? Carbon::parse($start)->addHours(2),
        ]);
    }
}
