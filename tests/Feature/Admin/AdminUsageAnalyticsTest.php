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
