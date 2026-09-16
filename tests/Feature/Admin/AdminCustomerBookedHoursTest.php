<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsageAnalytics;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Analytics\CustomerBookedHoursReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCustomerBookedHoursTest extends TestCase
{
    use RefreshDatabase;

    private User $caregiver;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::preventStrayRequests();
        $this->caregiver = User::factory()->create(['role' => 'caregiver']);
    }

    public function test_monthly_hours_and_averages_use_completed_work_and_include_zero_periods(): void
    {
        $first = User::factory()->create(['role' => 'family', 'name' => 'First Family']);
        $second = User::factory()->create(['role' => 'family', 'name' => 'Second Family']);
        $this->visit($first, '2026-06-15 12:00:00', 90, ['expected_minutes' => 240]);
        $this->visit($first, '2026-06-30 23:59:59', 30, [
            'status' => CareBooking::STATUS_REVIEWED,
            'family_confirmed_at' => Carbon::parse('2026-07-02 09:00:00'),
        ]);
        $recurringVisit = $this->visit($first, '2026-07-01 00:00:00', 180);
        $recurringVisit->careRequest->forceFill(['is_system_generated' => true, 'request_type' => 'recurring'])->saveQuietly();
        $this->visit($second, '2026-07-31 23:59:59', 60);

        $report = $this->report('2026-06-01', '2026-07-31');

        $this->assertSame(2, $report['customer_count']);
        $this->assertSame(4, $report['visit_count']);
        $this->assertEquals(6, $report['total_hours']);
        $this->assertEquals(3, $report['average_hours_per_customer']);
        $this->assertEquals(3, $report['average_hours_per_period']);
        $this->assertEquals(1.5, $report['average_hours_per_customer_period']);
        $this->assertEquals(['2026-06-01' => 2, '2026-07-01' => 4], $report['period_hours']);
        $this->assertEquals(['2026-06-01' => 1, '2026-07-01' => 2], $report['period_average_hours']);
        $this->assertSame([false, false], array_column($report['periods'], 'partial'));
        $this->assertSame(['First Family', 'Second Family'], array_column($report['customers'], 'name'));
        $this->assertEquals(5, $report['customers'][0]['total_hours']);
        $this->assertEquals(2.5, $report['customers'][0]['average_hours_per_period']);
        $this->assertEquals(['2026-06-01' => 0, '2026-07-01' => 1], $report['customers'][1]['hours_by_period']);
        $this->assertEquals(0.5, $report['customers'][1]['average_hours_per_period']);
    }

    public function test_unsuccessful_undated_and_out_of_range_visits_do_not_enter_the_customer_cohort(): void
    {
        $included = User::factory()->create(['role' => 'family']);
        $excluded = User::factory()->create(['role' => 'family']);
        // No payment or family confirmation is required for a completed visit with hours.
        $this->visit($included, '2026-06-15 12:00:00', 75);
        foreach ([CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED, CareBooking::STATUS_CANCELLED, CareBooking::STATUS_DISPUTED] as $status) {
            $this->visit($excluded, '2026-06-15 12:00:00', 120, ['status' => $status]);
        }
        foreach ([0, null, -30] as $minutes) {
            $this->visit($excluded, '2026-06-15 12:00:00', $minutes);
        }
        $this->visit($excluded, '2026-06-15 12:00:00', 120, ['no_show_flag' => true]);
        $this->visit($excluded, '2026-06-15 12:00:00', 120, ['completed_at' => null]);
        $this->visit($excluded, '2026-05-31 23:59:59', 120, ['family_confirmed_at' => Carbon::parse('2026-06-01')]);
        $this->visit($excluded, '2026-07-01 00:00:00', 120);
        $this->visit($excluded, '2026-06-15 12:00:00', 120, ['replacement_released_at' => Carbon::parse('2026-06-16')]);

        $report = $this->report('2026-06-01', '2026-06-30');

        $this->assertSame(1, $report['customer_count']);
        $this->assertSame(1, $report['visit_count']);
        $this->assertEquals(1.25, $report['total_hours']);
        $this->assertSame($included->id, $report['customers'][0]['user_id']);
    }

    public function test_weeks_start_on_monday_and_partial_periods_only_count_selected_dates(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $this->visit($family, '2026-12-31 23:59:59', 60);
        $this->visit($family, '2027-01-01 00:00:00', 30);
        $this->visit($family, '2027-01-03 23:59:59', 90);
        $this->visit($family, '2027-01-04 00:00:00', 120);
        $this->visit($family, '2027-01-05 00:00:00', 60);

        $weekly = $this->report('2027-01-01', '2027-01-04', 'week');
        $this->assertEquals(['2026-12-28' => 2, '2027-01-04' => 2], $weekly['period_hours']);
        $this->assertSame([true, true], array_column($weekly['periods'], 'partial'));
        $this->assertSame(3, $weekly['visit_count']);
        $this->assertEquals(2, $weekly['average_hours_per_customer_period']);

        $monthly = $this->report('2026-12-31', '2027-01-04');
        $this->assertEquals(['2026-12-01' => 1, '2027-01-01' => 4], $monthly['period_hours']);
        $this->assertSame(['Dec 2026', 'Jan 2027'], array_column($monthly['periods'], 'label'));
    }

    public function test_family_account_is_counted_once_after_owner_change_and_legacy_visits_have_a_fallback(): void
    {
        $formerOwner = User::factory()->create(['role' => 'family']);
        $currentOwner = User::factory()->create(['role' => 'family', 'name' => 'Current Owner']);
        $legacyFamily = User::factory()->create(['role' => 'family', 'name' => 'Legacy Family']);
        $firstVisit = $this->visit($formerOwner, '2026-06-01 12:00:00', 120);
        FamilyAccount::findOrFail($firstVisit->family_account_id)->update(['owner_user_id' => $currentOwner->id]);
        $this->visit($currentOwner, '2026-06-02 12:00:00', 90);
        $this->visit($legacyFamily, '2026-06-03 12:00:00', 60)->forceFill(['family_account_id' => null])->saveQuietly();
        $this->visit($legacyFamily, '2026-06-04 12:00:00', 30)->forceFill(['family_account_id' => null])->saveQuietly();

        $report = $this->report('2026-06-01', '2026-06-30');

        $this->assertSame(2, $report['customer_count']);
        $this->assertSame(4, $report['visit_count']);
        $this->assertSame(['Current Owner', 'Legacy Family'], array_column($report['customers'], 'name'));
        $this->assertEquals([3.5, 1.5], array_column($report['customers'], 'total_hours'));
        $this->assertSame($currentOwner->id, $report['customers'][0]['user_id']);
        $this->assertSame('user:'.$legacyFamily->id, $report['customers'][1]['key']);
    }

    public function test_admin_table_reacts_to_date_and_grouping_filters_and_handles_empty_ranges(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'test@test.com']);
        $family = User::factory()->create(['role' => 'family', 'name' => 'Reporting Family']);
        $this->visit($family, '2026-06-08 12:00:00', 150);

        Livewire::actingAs($admin)->test(UsageAnalytics::class)
            ->set('startDate', '2026-06-01')
            ->set('endDate', '2026-06-30')
            ->set('grouping', 'month')
            ->assertSee('Booked hours by customer')
            ->assertSee('Reporting Family')
            ->assertViewHas('customerHours', fn ($report) => count($report['periods']) === 1 && $report['total_hours'] == 2.5)
            ->set('grouping', 'week')
            ->assertViewHas('customerHours', fn ($report) => count($report['periods']) === 5 && $report['average_hours_per_customer_period'] == 0.5)
            ->set('startDate', '2026-06-09')
            ->assertSee('No customers have completed visits with recorded hours in this date range.')
            ->assertViewHas('customerHours', fn ($report) => $report['customer_count'] === 0 && $report['total_hours'] == 0 && $report['average_hours_per_customer_period'] == 0);
    }

    private function report(string $start, string $end, string $grouping = 'month'): array
    {
        return app(CustomerBookedHoursReport::class)->build(Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay(), $grouping);
    }

    private function visit(User $family, string $completedAt, ?int $minutes, array $attributes = []): CareBooking
    {
        $end = Carbon::parse($completedAt);
        $request = CareRequest::create([
            'family_user_id' => $family->id,
            'title' => 'Analytics test care',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $end->copy()->subHours(2),
            'requested_end_at' => $end,
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        return CareBooking::create(array_merge([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $this->caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => $end->copy()->subHours(2),
            'scheduled_end_at' => $end,
            'started_at' => $end->copy()->subMinutes(max(0, $minutes ?? 0)),
            'completed_at' => $end,
            'worked_minutes' => $minutes,
        ], $attributes));
    }
}
