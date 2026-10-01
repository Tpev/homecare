<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\UsageAnalytics;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\FunnelEvent;
use App\Models\PageViewEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsageAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_platform_usage_metrics_by_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00'));

        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
            'created_at' => Carbon::parse('2026-05-01 09:00:00'),
        ]);
        $familyOne = User::factory()->create([
            'role' => 'family',
            'name' => 'Don Johnson',
            'created_at' => Carbon::parse('2026-06-02 09:00:00'),
        ]);
        $familyTwo = User::factory()->create([
            'role' => 'family',
            'name' => 'Caroline Family',
            'created_at' => Carbon::parse('2026-06-03 09:00:00'),
        ]);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Caroline Caregiver',
            'created_at' => Carbon::parse('2026-06-04 09:00:00'),
        ]);
        User::factory()->create([
            'role' => 'family',
            'created_at' => Carbon::parse('2026-05-04 09:00:00'),
        ]);

        $openRequest = $this->careRequest($familyOne, CareRequest::STATUS_OPEN, '2026-06-05 10:00:00');
        $filledRequestOne = $this->careRequest($familyOne, CareRequest::STATUS_FILLED, '2026-06-06 10:00:00', '2026-06-07 11:00:00');
        $filledRequestTwo = $this->careRequest($familyTwo, CareRequest::STATUS_FILLED, '2026-06-15 10:00:00', '2026-06-16 11:00:00');
        $this->careRequest($familyOne, CareRequest::STATUS_DRAFT, '2026-06-18 10:00:00');
        $this->careRequest($familyOne, CareRequest::STATUS_OPEN, '2026-07-05 10:00:00');

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $openRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        $application->forceFill([
            'created_at' => Carbon::parse('2026-06-10 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-10 09:00:00'),
        ])->save();

        $conversation = CareRequestConversation::query()->create([
            'care_request_id' => $openRequest->id,
            'family_user_id' => $familyOne->id,
            'caregiver_user_id' => $caregiver->id,
            'care_request_application_id' => $application->id,
            'started_by_user_id' => $familyOne->id,
        ]);

        $message = CareRequestMessage::query()->create([
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $familyTwo->id,
            'body' => 'Can we talk tomorrow?',
        ]);
        $message->forceFill([
            'created_at' => Carbon::parse('2026-06-11 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-11 09:00:00'),
        ])->save();

        FunnelEvent::query()->create([
            'event' => 'care_request_published',
            'user_id' => $familyOne->id,
            'role' => 'family',
            'occurred_at' => Carbon::parse('2026-06-10 12:00:00'),
        ]);

        $pageView = PageViewEvent::query()->create([
            'event_name' => 'dashboard_view',
            'user_id' => $familyTwo->id,
            'url' => 'https://carelolo.com/dashboard',
        ]);
        $pageView->forceFill([
            'created_at' => Carbon::parse('2026-06-11 10:00:00'),
            'updated_at' => Carbon::parse('2026-06-11 10:00:00'),
        ])->save();

        $bookingOne = $this->booking(
            $filledRequestOne,
            $familyOne,
            $caregiver,
            workedMinutes: 150,
            completedAt: '2026-06-09 12:00:00',
            confirmedAt: '2026-06-09 13:00:00',
        );
        $bookingTwo = $this->booking(
            $filledRequestTwo,
            $familyTwo,
            $caregiver,
            workedMinutes: 90,
            completedAt: '2026-06-20 12:00:00',
            confirmedAt: '2026-06-20 13:00:00',
        );

        $this->payment($bookingOne, $familyOne, $caregiver, 7500, '2026-06-10 14:00:00', overageCents: 500, refundedCents: 1000);
        $this->payment($bookingTwo, $familyTwo, $caregiver, 4500, '2026-06-21 14:00:00');

        $this->actingAs($admin)
            ->get(route('admin.analytics.usage', [
                'startDate' => '2026-06-01',
                'endDate' => '2026-06-30',
                'grouping' => 'week',
            ]))
            ->assertOk()
            ->assertSee('Platform Usage Analytics')
            ->assertSee('Family signups')
            ->assertSee('Caregiver signups')
            ->assertSee('1.50 requests per posting family')
            ->assertSee('$115.00')
            ->assertSee('4.0');

        Livewire::actingAs($admin)
            ->test(UsageAnalytics::class)
            ->set('startDate', '2026-06-01')
            ->set('endDate', '2026-06-30')
            ->set('grouping', 'month')
            ->assertSee('Monthly usage breakdown')
            ->assertSee('1.50 requests per posting family')
            ->assertSee('$115.00');

        Carbon::setTestNow();
    }

    public function test_sales_and_family_users_cannot_open_usage_analytics(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($sales)->get(route('admin.analytics.usage'))->assertForbidden();
        $this->actingAs($family)->get(route('admin.analytics.usage'))->assertForbidden();
    }

    public function test_excluded_family_history_does_not_change_any_usage_metric_or_customer_average(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-06-30 12:00:00'));

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'test@test.com', 'created_at' => '2026-05-01']);
        $family = User::factory()->create(['role' => 'family', 'name' => 'Charles Petrini', 'created_at' => '2026-06-02']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'email' => 'charlespetrinipoli@gmail.com', 'created_at' => '2026-06-03']);
        $inactiveCaregiver = User::factory()->create(['role' => 'caregiver', 'created_at' => '2026-05-01']);
        $request = $this->careRequest($family, CareRequest::STATUS_FILLED, '2026-06-06 10:00:00', '2026-06-07 11:00:00');
        $booking = $this->booking($request, $family, $caregiver, 90, '2026-06-09 12:00:00', '2026-06-09 13:00:00');
        $this->payment($booking, $family, $caregiver, 4500, '2026-06-10 14:00:00');

        $analytics = Livewire::actingAs($admin)->test(UsageAnalytics::class)
            ->set('startDate', '2026-06-01')->set('endDate', '2026-06-30');
        $snapshot = fn (): array => collect(['summary', 'bucketRows', 'dailyActiveUsers', 'customerHours'])
            ->mapWithKeys(fn (string $key): array => [$key => $analytics->viewData($key)])->all();
        $baseline = [];
        foreach (['week', 'month'] as $grouping) {
            $analytics->set('grouping', $grouping);
            $baseline[$grouping] = $snapshot();
        }
        $this->assertSame(1, $baseline['month']['summary']['family_signups']);
        $this->assertSame(1, $baseline['month']['summary']['caregiver_signups']);
        $this->assertSame(2, $baseline['month']['summary']['active_users']);
        $this->assertSame(4500, $baseline['month']['summary']['money_spent_cents']);
        $this->assertEquals(1.5, $baseline['month']['customerHours']['total_hours']);

        $excluded = User::factory()->create([
            'role' => 'family', 'name' => 'Charles Petrini',
            'email' => 'BARLSEY42@GMAIL.COM', 'created_at' => '2026-06-04',
        ]);
        FunnelEvent::create(['event' => 'dashboard_view', 'user_id' => $excluded->id, 'role' => 'family', 'occurred_at' => '2026-06-05']);
        PageViewEvent::create(['event_name' => 'dashboard_view', 'user_id' => $excluded->id, 'url' => 'https://carelolo.com/dashboard'])
            ->forceFill(['created_at' => Carbon::parse('2026-06-06')])->saveQuietly();
        $open = $this->careRequest($excluded, CareRequest::STATUS_OPEN, '2026-06-12 10:00:00');
        $filled = $this->careRequest($excluded, CareRequest::STATUS_FILLED, '2026-06-13 10:00:00', '2026-06-14 11:00:00');
        $application = CareRequestApplication::create([
            'care_request_id' => $open->id, 'caregiver_user_id' => $inactiveCaregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        $application->forceFill(['created_at' => Carbon::parse('2026-06-15')])->saveQuietly();
        $conversation = CareRequestConversation::create([
            'care_request_id' => $open->id, 'family_user_id' => $excluded->id,
            'caregiver_user_id' => $inactiveCaregiver->id, 'care_request_application_id' => $application->id,
            'started_by_user_id' => $excluded->id,
        ]);
        foreach ([$excluded, $inactiveCaregiver] as $sender) {
            CareRequestMessage::create([
                'care_request_conversation_id' => $conversation->id, 'sender_user_id' => $sender->id, 'body' => 'Excluded activity',
            ])->forceFill(['created_at' => Carbon::parse('2026-06-16')])->saveQuietly();
        }
        $excludedBooking = $this->booking($filled, $excluded, $inactiveCaregiver, 600, '2026-06-18 12:00:00', '2026-06-19 13:00:00');
        $this->payment($excludedBooking, $excluded, $inactiveCaregiver, 30000, '2026-06-21 14:00:00', 2000, 1000)
            ->forceFill(['authorized_at' => Carbon::parse('2026-06-20'), 'transferred_at' => Carbon::parse('2026-06-22')])->saveQuietly();
        $recurring = $this->careRequest($excluded, CareRequest::STATUS_FILLED, '2026-06-23 10:00:00', '2026-06-24 11:00:00');
        $recurring->forceFill(['is_system_generated' => true, 'request_type' => 'recurring'])->saveQuietly();
        $this->booking($recurring, $excluded, $inactiveCaregiver, 240, '2026-06-25 12:00:00', '2026-06-25 13:00:00');

        foreach (['week', 'month'] as $grouping) {
            $analytics->set('grouping', $grouping)->assertDontSee('BARLSEY42@GMAIL.COM');
            $this->assertEquals($baseline[$grouping], $snapshot(), 'Excluded history changed '.$grouping.' usage metrics.');
        }
        $this->assertDatabaseHas('users', ['id' => $excluded->id]);
        $this->assertDatabaseHas('care_bookings', ['id' => $excludedBooking->id, 'worked_minutes' => 600]);
        $this->assertDatabaseHas('care_booking_payments', ['care_booking_id' => $excludedBooking->id, 'amount_captured_cents' => 30000]);
    }

    public function test_excluded_account_cannot_reenter_usage_as_a_caregiver_or_message_sender(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-06-30 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'test@test.com', 'created_at' => '2026-05-01']);
        $family = User::factory()->create(['role' => 'family', 'created_at' => '2026-06-02']);
        $excluded = User::factory()->create(['role' => 'caregiver', 'email' => 'barlsey42@gmail.com', 'created_at' => '2026-06-03']);
        $request = $this->careRequest($family, CareRequest::STATUS_FILLED, '2026-06-04 10:00:00', '2026-06-04 11:00:00');
        $application = CareRequestApplication::create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $excluded->id, 'status' => CareRequestApplication::STATUS_APPLIED,
        ]);
        $application->forceFill(['created_at' => Carbon::parse('2026-06-05')])->saveQuietly();
        $conversation = CareRequestConversation::create([
            'care_request_id' => $request->id, 'family_user_id' => $family->id,
            'caregiver_user_id' => $excluded->id, 'care_request_application_id' => $application->id,
            'started_by_user_id' => $excluded->id,
        ]);
        CareRequestMessage::create([
            'care_request_conversation_id' => $conversation->id, 'sender_user_id' => $excluded->id, 'body' => 'Excluded activity',
        ])->forceFill(['created_at' => Carbon::parse('2026-06-06')])->saveQuietly();
        $booking = $this->booking($request, $family, $excluded, 180, '2026-06-07 12:00:00', '2026-06-07 13:00:00');
        $this->payment($booking, $family, $excluded, 9000, '2026-06-08 14:00:00');

        Livewire::actingAs($admin)->test(UsageAnalytics::class)
            ->set('startDate', '2026-06-01')->set('endDate', '2026-06-30')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['family_signups'] === 1
                && $summary['caregiver_signups'] === 0 && $summary['active_users'] === 1
                && $summary['tracked_minutes'] === 0 && $summary['money_spent_cents'] === 0)
            ->assertViewHas('dailyActiveUsers', fn (array $days): bool => collect($days)
                ->filter(fn (array $day): bool => $day['date'] >= '2026-06-05')->sum('count') === 0)
            ->assertViewHas('customerHours', fn (array $report): bool => $report['customer_count'] === 0 && $report['visit_count'] === 0);
    }

    private function careRequest(User $family, string $status, string $createdAt, ?string $filledAt = null): CareRequest
    {
        $created = Carbon::parse($createdAt);
        $filled = $filledAt ? Carbon::parse($filledAt) : null;

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Care support',
            'status' => $status,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $created->copy()->addDay(),
            'requested_end_at' => $created->copy()->addDay()->addHours(2),
            'address_line1' => '123 Main St',
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
        ]);

        $request->forceFill([
            'first_hire_at' => $filled,
            'created_at' => $created,
            'updated_at' => $filled ?: $created,
        ])->save();

        return $request->fresh();
    }

    private function booking(
        CareRequest $request,
        User $family,
        User $caregiver,
        int $workedMinutes,
        string $completedAt,
        string $confirmedAt,
    ): CareBooking {
        return CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => Carbon::parse($completedAt)->subHours(2),
            'scheduled_end_at' => Carbon::parse($completedAt),
            'started_at' => Carbon::parse($completedAt)->subHours(2),
            'completed_at' => Carbon::parse($completedAt),
            'timesheet_submitted_at' => Carbon::parse($confirmedAt)->subMinutes(20),
            'family_confirmed_at' => Carbon::parse($confirmedAt),
            'worked_minutes' => $workedMinutes,
        ]);
    }

    private function payment(
        CareBooking $booking,
        User $family,
        User $caregiver,
        int $capturedCents,
        string $capturedAt,
        int $overageCents = 0,
        int $refundedCents = 0,
    ): CareBookingPayment {
        return CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_CAPTURED,
            'currency' => 'usd',
            'amount_captured_cents' => $capturedCents,
            'amount_overage_cents' => $overageCents,
            'amount_refunded_cents' => $refundedCents,
            'captured_at' => Carbon::parse($capturedAt),
        ]);
    }
}
