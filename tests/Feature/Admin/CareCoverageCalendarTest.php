<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CareCoverageCalendar;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\Analytics\CareCoverageCalendarBuilder;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CareCoverageCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_sees_assigned_shifts_and_unfulfilled_requests_with_people_and_account_context(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family', 'name' => 'The Rivera Family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Carla Caregiver']);

        $shiftRequest = $this->request($family, [
            'title' => 'Morning companionship',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => Carbon::parse('2026-06-18 09:00:00'),
            'requested_end_at' => Carbon::parse('2026-06-18 12:00:00'),
        ], 'Alice Customer');
        $this->booking($shiftRequest, $family, $caregiver, '2026-06-18 09:00:00', '2026-06-18 12:00:00');

        $openRequest = $this->request($family, [
            'title' => 'Private evening support',
            'status' => CareRequest::STATUS_OPEN,
            'is_private' => true,
            'requested_start_at' => Carbon::parse('2026-06-19 17:00:00'),
            'requested_end_at' => Carbon::parse('2026-06-19 20:00:00'),
        ], 'Bob Customer');

        $fulfilledWithoutShift = $this->request($family, [
            'title' => 'Do not show as prospective',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => Carbon::parse('2026-06-20 09:00:00'),
            'requested_end_at' => Carbon::parse('2026-06-20 10:00:00'),
        ], 'Hidden Customer');

        $response = $this->actingAs($admin)->get(route('admin.analytics.care-coverage-calendar', ['month' => '2026-06']));

        $response
            ->assertOk()
            ->assertSee('Care Coverage Calendar')
            ->assertSee('Alice Customer')
            ->assertSee('The Rivera Family')
            ->assertSee('Carla Caregiver')
            ->assertSee('Bob Customer')
            ->assertSee('Unassigned')
            ->assertSee('Private')
            ->assertSee('Open request queue')
            ->assertSee(route('admin.analytics.care-coverage-calendar'), false)
            ->assertSee(route('admin.requests.show', $openRequest), false)
            ->assertDontSee('Do not show as prospective');

        $this->assertNotNull($fulfilledWithoutShift->id);
    }

    public function test_recurring_open_request_is_expanded_only_on_requested_days_and_range(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $family = User::factory()->create(['role' => 'family', 'name' => 'Morgan Family']);
        $request = $this->request($family, [
            'title' => 'Weekday recurring help',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_RECURRING,
            'recurring_schedule' => [
                ['day' => 1, 'start_time' => '08:30', 'end_time' => '10:30'],
                ['day' => 3, 'start_time' => '14:00', 'end_time' => '17:00'],
            ],
            'recurring_starts_on' => '2026-06-08',
            'recurring_ends_on' => '2026-06-17',
            'requested_start_at' => null,
            'requested_end_at' => null,
        ], 'Riley Recipient');

        $month = Carbon::parse('2026-06-01');
        $start = $month->copy()->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY);
        $calendar = app(CareCoverageCalendarBuilder::class)->build($month, $start, $end, $this->filters());

        $occurrences = $calendar['events']
            ->where('kind', 'open_request')
            ->where('request_id', $request->id)
            ->pluck('start_at')
            ->map(fn (Carbon $date): string => $date->format('Y-m-d H:i'))
            ->values()
            ->all();

        $this->assertSame([
            '2026-06-08 08:30',
            '2026-06-10 14:00',
            '2026-06-15 08:30',
            '2026-06-17 14:00',
        ], $occurrences);
        $this->assertSame(1, $calendar['summary']['open_requests']);
        $this->assertSame(4, $calendar['summary']['open_slots']);
    }

    public function test_calendar_filters_by_caregiver_and_hides_unassigned_demand_while_filter_is_active(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family', 'name' => 'Nguyen Family']);
        $caregiverOne = User::factory()->create(['role' => 'caregiver', 'name' => 'Selected Caregiver']);
        $caregiverTwo = User::factory()->create(['role' => 'caregiver', 'name' => 'Other Caregiver']);

        $firstRequest = $this->request($family, [
            'title' => 'Selected shift',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => Carbon::parse('2026-06-12 08:00:00'),
            'requested_end_at' => Carbon::parse('2026-06-12 10:00:00'),
        ], 'Selected Customer');
        $this->booking($firstRequest, $family, $caregiverOne, '2026-06-12 08:00:00', '2026-06-12 10:00:00');

        $secondRequest = $this->request($family, [
            'title' => 'Other shift',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => Carbon::parse('2026-06-13 08:00:00'),
            'requested_end_at' => Carbon::parse('2026-06-13 10:00:00'),
        ], 'Other Customer');
        $this->booking($secondRequest, $family, $caregiverTwo, '2026-06-13 08:00:00', '2026-06-13 10:00:00');

        $this->request($family, [
            'title' => 'Unassigned request',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => Carbon::parse('2026-06-14 08:00:00'),
            'requested_end_at' => Carbon::parse('2026-06-14 10:00:00'),
        ], 'Unassigned Customer');

        Livewire::actingAs($admin)
            ->test(CareCoverageCalendar::class)
            ->set('month', '2026-06')
            ->set('caregiver', (string) $caregiverOne->id)
            ->assertSee('Selected Customer')
            ->assertDontSee('Other Customer')
            ->assertDontSee('Unassigned Customer')
            ->assertSee('Open requests are unassigned');
    }

    public function test_non_admin_cannot_open_care_coverage_calendar(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)
            ->get(route('admin.analytics.care-coverage-calendar'))
            ->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function request(User $family, array $overrides, string $recipient): CareRequest
    {
        $request = CareRequest::query()->create(array_merge([
            'family_user_id' => $family->id,
            'title' => 'Care support',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => Carbon::parse('2026-06-20 09:00:00'),
            'requested_end_at' => Carbon::parse('2026-06-20 11:00:00'),
            'address_line1' => '123 Main St',
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
        ], $overrides));

        $request->recipient()->create([
            'full_name' => $recipient,
            'relationship_to_family' => 'Parent',
        ]);

        return $request->fresh(['recipient', 'familyAccount.owner']);
    }

    private function booking(
        CareRequest $request,
        User $family,
        User $caregiver,
        string $startsAt,
        string $endsAt,
    ): CareBooking {
        return CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => Carbon::parse($startsAt),
            'scheduled_end_at' => Carbon::parse($endsAt),
        ]);
    }

    /** @return array{view:string,family_account:string,caregiver:string,status:string,q:string} */
    private function filters(): array
    {
        return [
            'view' => 'all',
            'family_account' => 'all',
            'caregiver' => 'all',
            'status' => 'all',
            'q' => '',
        ];
    }
}
