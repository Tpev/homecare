<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\CareHistory;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\Family\FamilyCareHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CareHistoryTest extends TestCase
{
    use RefreshDatabase;

    private int $occurrenceSequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_history_is_family_only_and_never_leaks_foreign_bookings_or_filter_values(): void
    {
        Carbon::setTestNow('2026-07-31 12:00:00');
        $family = User::factory()->create(['role' => 'family']);
        $otherFamily = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Charles Care']);
        $otherCaregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Foreign Caregiver']);
        $own = $this->createBooking($family, $caregiver, ['scheduled_start_at' => now()->subDay()], ['title' => 'Own private visit']);
        $foreignPlan = $this->createPlan($otherFamily, $otherCaregiver, 'Foreign private plan');
        $foreign = $this->createBooking(
            $otherFamily,
            $otherCaregiver,
            ['scheduled_start_at' => now()->subDays(2)],
            ['title' => 'Foreign private visit'],
            $foreignPlan,
        );

        $this->actingAs($family)
            ->get(route('family.care.history'))
            ->assertOk()
            ->assertSee('Own private visit')
            ->assertDontSee('Foreign private visit')
            ->assertDontSee('Foreign Caregiver')
            ->assertDontSee('Foreign private plan');

        $this->actingAs($caregiver)
            ->get(route('family.care.history'))
            ->assertForbidden();

        $service = app(FamilyCareHistoryService::class);
        $this->assertSame(0, $service->query($family, ['plan' => (string) $foreignPlan->id])->count());
        $this->assertSame(0, $service->query($family, ['caregiver' => (string) $otherCaregiver->id])->count());
        $this->assertSame([$own->id], $service->query($family, [])->pluck('care_bookings.id')->all());
        $this->assertNotSame($own->id, $foreign->id);
    }

    public function test_mixed_history_is_chronological_distinguishes_worked_time_and_keeps_expired_visits_accessible(): void
    {
        Carbon::setTestNow('2026-07-31 12:00:00');
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Charles Petrini-Poli']);
        $plan = $this->createPlan($family, $caregiver, 'Barbara weekly care');

        $oneTime = $this->createBooking($family, $caregiver, [
            'scheduled_start_at' => now()->subDays(3)->setTime(9, 0),
            'scheduled_end_at' => now()->subDays(3)->setTime(11, 0),
            'worked_minutes' => 60,
        ], ['title' => 'One-time companionship', 'recipient' => 'Barbara Pearl']);
        $regular = $this->createBooking($family, $caregiver, [
            'scheduled_start_at' => now()->subDays(2)->setTime(7, 30),
            'scheduled_end_at' => now()->subDays(2)->setTime(9, 30),
            'worked_minutes' => 120,
        ], ['title' => 'Regular visit 51', 'recipient' => 'Barbara Pearl', 'is_system_generated' => true], $plan);
        $extra = $this->createBooking($family, $caregiver, [
            'scheduled_start_at' => now()->subDay()->setTime(14, 0),
            'scheduled_end_at' => now()->subDay()->setTime(15, 30),
            'worked_minutes' => 90,
            'plan_visit_kind' => 'extra',
        ], ['title' => 'Extra visit', 'recipient' => 'Barbara Pearl', 'is_system_generated' => true], $plan);
        $expired = $this->createBooking($family, $caregiver, [
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->subHours(3),
            'scheduled_end_at' => now()->subHour(),
            'worked_minutes' => null,
            'completed_at' => null,
            'timesheet_submitted_at' => null,
            'family_confirmed_at' => null,
        ], ['title' => 'Missed regular visit', 'recipient' => 'Barbara Pearl', 'is_system_generated' => true], $plan);
        $stillInWindow = $this->createBooking($family, $caregiver, [
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->subHour(),
            'scheduled_end_at' => now()->addHour(),
            'worked_minutes' => null,
            'completed_at' => null,
            'timesheet_submitted_at' => null,
            'family_confirmed_at' => null,
        ], ['title' => 'Current regular visit', 'recipient' => 'Barbara Pearl', 'is_system_generated' => true], $plan);
        $future = $this->createBooking($family, $caregiver, [
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
            'worked_minutes' => null,
            'completed_at' => null,
            'timesheet_submitted_at' => null,
            'family_confirmed_at' => null,
        ], ['title' => 'Future regular visit', 'recipient' => 'Barbara Pearl', 'is_system_generated' => true], $plan);
        $this->markAdjusted($regular);

        $service = app(FamilyCareHistoryService::class);
        $bookings = $service->query($family, [])->get();
        $this->assertSame([$expired->id, $extra->id, $regular->id, $oneTime->id], $bookings->pluck('id')->all());
        $this->assertNotContains($stillInWindow->id, $bookings->pluck('id')->all());
        $this->assertNotContains($future->id, $bookings->pluck('id')->all());

        $presented = $bookings->mapWithKeys(fn (CareBooking $booking): array => [$booking->id => $service->present($booking)]);
        $this->assertSame('One-time', $presented[$oneTime->id]['care_type_label']);
        $this->assertSame('Recurring care', $presented[$regular->id]['care_type_label']);
        $this->assertSame('Extra visit', $presented[$extra->id]['care_type_label']);
        $this->assertTrue($presented[$regular->id]['adjusted']);
        $this->assertSame('Check-in missing', $presented[$expired->id]['visit_status_label']);
        $this->assertSame('Report completed work', $presented[$expired->id]['action_label']);
        $this->assertStringContainsString('tab=support', $presented[$expired->id]['action_url']);
        $this->assertNull($presented[$expired->id]['worked_label']);
        $this->assertSame('2h 00m', $presented[$expired->id]['scheduled_duration_label']);

        $summary = $service->summary($family, []);
        $this->assertSame(3, $summary['care_provided']);
        $this->assertSame(270, $summary['worked_minutes']);

        Livewire::actingAs($family)
            ->test(CareHistory::class)
            ->assertSee('Missed regular visit')
            ->assertSee('Check-in missing')
            ->assertSee('No worked time recorded')
            ->assertDontSee('Current regular visit')
            ->assertDontSee('Future regular visit');
    }

    public function test_payment_truth_and_aggregates_include_every_filtered_page_without_double_counting_overage(): void
    {
        Carbon::setTestNow('2026-07-31 12:00:00');
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $authorized = $this->createBooking($family, $caregiver, ['scheduled_start_at' => now()->subHours(4)], ['title' => 'Authorized visit']);
        $this->createPayment($authorized, CareBookingPayment::STATUS_AUTHORIZED, 0, 0, 0, 7560);
        $captured = $this->createBooking($family, $caregiver, ['scheduled_start_at' => now()->subDays(1)], ['title' => 'Captured with overage']);
        $this->createPayment($captured, CareBookingPayment::STATUS_CAPTURED, 7920, 0, 495);
        $partial = $this->createBooking($family, $caregiver, ['scheduled_start_at' => now()->subDays(2)], ['title' => 'Partial refund']);
        $this->createPayment($partial, CareBookingPayment::STATUS_PARTIALLY_REFUNDED, 5670, 1000);
        $refunded = $this->createBooking($family, $caregiver, ['scheduled_start_at' => now()->subDays(3)], ['title' => 'Full refund']);
        $this->createPayment($refunded, CareBookingPayment::STATUS_REFUNDED, 1575, 1575);
        $missing = $this->createBooking($family, $caregiver, ['scheduled_start_at' => now()->subDays(4)], ['title' => 'No payment record']);
        $transferFailed = $this->createBooking($family, $caregiver, ['scheduled_start_at' => now()->subDays(5)], ['title' => 'Payout pending']);
        $this->createPayment($transferFailed, CareBookingPayment::STATUS_TRANSFER_FAILED, 3000);

        for ($index = 0; $index < 8; $index++) {
            $booking = $this->createBooking(
                $family,
                $caregiver,
                ['scheduled_start_at' => now()->subDays(10 + $index)],
                ['title' => 'Older captured visit '.$index],
            );
            $this->createPayment($booking, CareBookingPayment::STATUS_CAPTURED, 100);
        }
        $euroBooking = $this->createBooking(
            $family,
            $caregiver,
            ['scheduled_start_at' => now()->subDays(20)],
            ['title' => 'Separate currency visit'],
        );
        $this->createPayment($euroBooking, CareBookingPayment::STATUS_CAPTURED, 2000)->update(['currency' => 'eur']);

        $service = app(FamilyCareHistoryService::class);
        $page = $service->query($family, [])->paginate(12);
        $this->assertSame(15, $page->total());
        $this->assertCount(12, $page->items());

        $summary = $service->summary($family, []);
        $moneyByCurrency = collect($summary['money'])->keyBy('currency');
        $this->assertSame(16390, $moneyByCurrency['USD']['net_billed_cents']);
        $this->assertSame(2575, $moneyByCurrency['USD']['refunded_cents']);
        $this->assertSame(2000, $moneyByCurrency['EUR']['net_billed_cents']);
        $this->assertSame(0, $moneyByCurrency['EUR']['refunded_cents']);

        $items = collect([$authorized, $captured, $partial, $refunded, $missing, $transferFailed])
            ->mapWithKeys(function (CareBooking $booking) use ($service): array {
                $loaded = $service->query($booking->family, ['search' => (string) $booking->id])->firstOrFail();

                return [$booking->id => $service->present($loaded)];
            });

        $this->assertSame('Card authorized', $items[$authorized->id]['payment']['label']);
        $this->assertSame('Not charged', $items[$authorized->id]['payment']['amount_label']);
        $this->assertSame('Paid', $items[$captured->id]['payment']['label']);
        $this->assertSame(7920, $items[$captured->id]['payment']['net_cents']);
        $this->assertSame('$79.20', $items[$captured->id]['payment']['gross_label']);
        $this->assertSame('$4.95', $items[$captured->id]['payment']['overage_label']);
        $this->assertSame(4670, $items[$partial->id]['payment']['net_cents']);
        $this->assertSame('$56.70', $items[$partial->id]['payment']['gross_label']);
        $this->assertSame('$10.00', $items[$partial->id]['payment']['refunded_label']);
        $this->assertSame('$46.70', $items[$partial->id]['payment']['net_label']);
        $this->assertSame(0, $items[$refunded->id]['payment']['net_cents']);
        $this->assertSame('Refunded', $items[$refunded->id]['payment']['label']);
        $this->assertSame('Not charged', $items[$missing->id]['payment']['label']);
        $this->assertSame('Paid — payout processing', $items[$transferFailed->id]['payment']['label']);
        $this->assertStringNotContainsString('failed', strtolower($items[$transferFailed->id]['payment']['label']));
        $this->assertStringNotContainsString('caregiver', strtolower($items[$transferFailed->id]['payment']['help']));

        Livewire::actingAs($family)
            ->test(CareHistory::class)
            ->assertSee('Net billed')
            ->assertSee('Captured minus refunds')
            ->assertDontSee('Returned from captured payments');
    }

    public function test_all_history_filters_search_and_reset_are_applied_to_the_authorized_query(): void
    {
        Carbon::setTestNow('2026-07-31 12:00:00');
        $family = User::factory()->create(['role' => 'family']);
        $caregiverA = User::factory()->create(['role' => 'caregiver', 'name' => 'Charles Match']);
        $caregiverB = User::factory()->create(['role' => 'caregiver', 'name' => 'Taylor Other']);
        $plan = $this->createPlan($family, $caregiverA, 'Barbara regular plan');

        $recent = $this->createBooking($family, $caregiverA, [
            'scheduled_start_at' => now()->subDays(10),
            'plan_visit_kind' => 'extra',
        ], ['title' => 'Medication and tea', 'recipient' => 'Barbara Pearl', 'is_system_generated' => true], $plan);
        $this->createPayment($recent, CareBookingPayment::STATUS_CAPTURED, 5000);
        $older = $this->createBooking($family, $caregiverB, [
            'scheduled_start_at' => now()->subDays(50),
            'status' => CareBooking::STATUS_DISPUTED,
            'family_confirmed_at' => null,
        ], ['title' => 'Evening companionship', 'recipient' => 'John Pearl']);
        $this->createPayment($older, CareBookingPayment::STATUS_AUTHORIZED, 0, 0, 0, 3000);

        $component = Livewire::actingAs($family)->test(CareHistory::class);
        $this->assertComponentIds($component, [$recent->id, $older->id]);

        $this->assertComponentIds($component->set('range', '30_days'), [$recent->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('range', 'custom')->set('from', now()->subDays(55)->toDateString())->set('to', now()->subDays(45)->toDateString()), [$older->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('recipient', 'Barbara Pearl'), [$recent->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('caregiver', (string) $caregiverB->id), [$older->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('plan', (string) $plan->id), [$recent->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('careType', 'extra'), [$recent->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('visitStatus', 'disputed'), [$older->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('paymentStatus', 'paid'), [$recent->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component->set('search', 'Charles Match'), [$recent->id]);
        $this->assertComponentIds($component->set('search', '#'.$older->id), [$older->id]);
        $this->assertComponentIds($component->set('search', 'Medication and tea'), [$recent->id]);
        $component->call('clearFilters');
        $this->assertComponentIds($component, [$recent->id, $older->id]);
    }

    public function test_existing_care_plan_billing_dashboard_and_owned_visit_workflows_link_to_history(): void
    {
        Carbon::setTestNow('2026-07-31 12:00:00');
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Charles Care']);
        $plan = $this->createPlan($family, $caregiver, 'Barbara weekly plan');
        $booking = $this->createBooking($family, $caregiver, [
            'scheduled_start_at' => now()->subDay(),
            'status' => CareBooking::STATUS_REVIEWED,
        ], ['title' => 'Completed Barbara visit', 'recipient' => 'Barbara Pearl', 'is_system_generated' => true], $plan);
        $this->createPayment($booking, CareBookingPayment::STATUS_CAPTURED, 7920);
        $unrelated = $this->createBooking($family, $caregiver, [
            'scheduled_start_at' => now()->subDays(2),
        ], ['title' => 'Unrelated one-time visit', 'recipient' => 'John Pearl']);

        $historyUrl = route('family.care.history');
        $this->actingAs($family)->get(route('family.requests.index'))->assertOk()->assertSee($historyUrl, false);
        $this->actingAs($family)->get(route('family.care.show', $plan))->assertOk()
            ->assertSee(route('family.care.history', ['plan' => $plan->id]), false)
            ->assertSee('View past visits');
        $this->actingAs($family)->get(route('family.billing.show'))->assertOk()
            ->assertSee(route('family.care.history', ['payment' => 'charged']), false)
            ->assertSee('View payment history');
        $this->actingAs($family)->get(route('dashboard'))->assertOk()->assertSee($historyUrl, false);
        $this->actingAs($family)
            ->get(route('family.care.history', ['plan' => $plan->id]))
            ->assertOk()
            ->assertSee('Completed Barbara visit')
            ->assertDontSee('Unrelated one-time visit');
        $this->actingAs($family)
            ->get(route('family.care.history', ['payment' => 'charged']))
            ->assertOk()
            ->assertSee('Completed Barbara visit')
            ->assertDontSee('Unrelated one-time visit');

        $service = app(FamilyCareHistoryService::class);
        $item = $service->present($service->query($family, ['search' => (string) $booking->id])->firstOrFail());
        $this->assertSame('View receipt', $item['action_label']);
        $this->assertSame(
            route('family.requests.show', ['careRequest' => $booking->care_request_id, 'tab' => 'shift']),
            $item['action_url'],
        );
        $this->assertNotSame($booking->id, $unrelated->id);

        $this->actingAs($family)
            ->get(route('family.requests.show', ['careRequest' => $booking->care_request_id, 'tab' => 'support']))
            ->assertOk()
            ->assertSee('Get help with this completed visit');
    }

    private function createPlan(User $family, User $caregiver, string $title): CarePlan
    {
        return CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => $title,
            'recipient_snapshot' => ['full_name' => 'Barbara Pearl', 'relationship_to_family' => 'Mother'],
            'address_snapshot' => ['address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'],
            'task_snapshot' => [],
            'schedule_days' => [2],
            'schedule_start_time' => '07:30',
            'schedule_end_time' => '09:30',
            'starts_on' => now()->subMonths(2)->toDateString(),
            'timezone' => 'America/New_York',
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $bookingOverrides
     * @param  array<string, mixed>  $requestOverrides
     */
    private function createBooking(
        User $family,
        User $caregiver,
        array $bookingOverrides = [],
        array $requestOverrides = [],
        ?CarePlan $plan = null,
    ): CareBooking {
        $scheduledStart = $bookingOverrides['scheduled_start_at'] ?? now()->subDay()->setTime(9, 0);
        $scheduledEnd = $bookingOverrides['scheduled_end_at'] ?? $scheduledStart->copy()->addHours(2);
        $title = (string) ($requestOverrides['title'] ?? 'Care history visit');
        $recipient = (string) ($requestOverrides['recipient'] ?? 'Care Recipient');

        $request = CareRequest::query()->create(array_merge([
            'family_user_id' => $family->id,
            'care_plan_id' => $plan?->id,
            'is_system_generated' => $plan !== null,
            'title' => $title,
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => $plan ? CareRequest::TYPE_RECURRING : CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $scheduledStart,
            'requested_end_at' => $scheduledEnd,
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ], collect($requestOverrides)->except(['recipient'])->all()));
        $request->recipient()->create([
            'full_name' => $recipient,
            'relationship_to_family' => 'Mother',
        ]);

        $this->occurrenceSequence++;

        return CareBooking::query()->create(array_merge([
            'care_request_id' => $request->id,
            'care_plan_id' => $plan?->id,
            'occurrence_key' => $plan ? 'history-test-'.$plan->id.'-'.$this->occurrenceSequence : null,
            'plan_visit_kind' => $plan ? 'regular' : null,
            'plan_schedule_version' => $plan ? 1 : null,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'started_at' => $scheduledStart,
            'completed_at' => $scheduledEnd,
            'timesheet_submitted_at' => $scheduledEnd,
            'expected_minutes' => $scheduledStart->diffInMinutes($scheduledEnd),
            'worked_minutes' => $scheduledStart->diffInMinutes($scheduledEnd),
            'total_paused_seconds' => 0,
            'family_confirmed_at' => $scheduledEnd,
        ], $bookingOverrides));
    }

    private function createPayment(
        CareBooking $booking,
        string $status,
        int $capturedCents,
        int $refundedCents = 0,
        int $overageCents = 0,
        int $authorizedCents = 0,
    ): CareBookingPayment {
        return CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $booking->family_user_id,
            'caregiver_user_id' => $booking->caregiver_user_id,
            'status' => $status,
            'currency' => 'usd',
            'amount_authorized_cents' => $authorizedCents,
            'amount_captured_cents' => $capturedCents,
            'amount_refunded_cents' => $refundedCents,
            'amount_overage_cents' => $overageCents,
            'overage_pending_cents' => 0,
            'authorized_at' => $authorizedCents > 0 ? now()->subDay() : null,
            'captured_at' => $capturedCents > 0 ? now()->subHours(12) : null,
        ]);
    }

    private function markAdjusted(CareBooking $booking): void
    {
        CareBookingCorrection::query()->create([
            'client_request_id' => (string) Str::uuid(),
            'care_booking_id' => $booking->id,
            'action' => CareBookingCorrection::ACTION_COMPLETE_AND_BILL,
            'status' => CareBookingCorrection::STATUS_SUCCEEDED,
            'attempt_count' => 1,
            'previous_charge_cents' => 0,
            'target_charge_cents' => 0,
            'payment_delta_cents' => 0,
            'caregiver_delta_cents' => 0,
            'family_approval_confirmed_at' => now(),
            'reason' => 'Corrected visit record for test.',
            'before_snapshot' => [],
            'requested_changes' => [],
            'preview' => [],
            'internal_note_client_id' => (string) Str::uuid(),
            'public_reply_client_id' => (string) Str::uuid(),
            'applied_at' => now(),
        ]);
    }

    /** @param array<int, int> $expected */
    private function assertComponentIds($component, array $expected): void
    {
        $component->assertViewHas('historyItems', function (LengthAwarePaginator $items) use ($expected): bool {
            return collect($items->items())->pluck('booking_id')->all() === $expected;
        });
    }
}
