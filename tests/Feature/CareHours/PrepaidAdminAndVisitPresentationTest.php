<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Admin\CareRequestsIndex;
use App\Livewire\Admin\CareRequestShow;
use App\Livewire\Family\RegularCareShow;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareHourAccount;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\CarePlan;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\Payments\StripeClient;
use App\Services\RegularCare\CarePlanPaymentWindowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrepaidAdminAndVisitPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Mail::fake();
        Notification::fake();
        Http::preventStrayRequests();
        $this->app->instance(CareHourPaymentGateway::class, Mockery::mock(CareHourPaymentGateway::class));
        $this->app->instance(StripeClient::class, Mockery::mock(StripeClient::class));
        config(['care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true,
            'marketplace.caregiver_prelaunch_mode' => false, 'marketplace.pricing_v2_enabled' => true,
            'marketplace.regular_care.authorization_window_hours' => 48,
            'marketplace.ops_alert_recipients' => []]);
    }

    public static function cancellationCases(): array
    {
        return ['index prepaid' => ['index', 300], 'detail prepaid' => ['detail', 300],
            'index mixed' => ['index', 60], 'detail mixed' => ['detail', 60]];
    }

    #[DataProvider('cancellationCases')]
    public function test_admin_cancellation_returns_hours_and_reconciles_card_topups_on_retries(string $screen, int $minutes): void
    {
        [$booking, $account, $owner] = $this->fixture($minutes);
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        $funding = $service->prepare($booking);
        $operation = null;
        if ($minutes === 60) {
            $operation = CareHourPaymentOperation::create([
                'public_id' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $funding->id,
                'kind' => 'shortfall', 'operation_key' => 'admin-cancel:'.$booking->id,
                'status' => 'authorized', 'amount_cents' => 3100, 'currency' => 'usd',
                'stripe_payment_intent_id' => 'pi_admin_cancel_test', 'authorization_expires_at' => now()->addDays(5),
                'metadata' => ['funding_revision' => $funding->revision],
            ]);
            app(StripeClient::class)->shouldReceive('cancelPaymentIntent')->once()->with('pi_admin_cancel_test')->andReturn([]);
        }
        config(['care_hours.enabled' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->cancelOnScreen($screen, $booking, $admin);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $this->assertSame($minutes, $account->balance()['available_minutes']);
        $this->assertSame('released', $funding->fresh()->status);

        // The test transaction defers after-commit work; the scheduler uses this same retry path.
        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->cancelOnScreen($screen, $booking, $admin);
        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->assertSame(0, $account->balance()['reserved_minutes']);
        $this->assertSame($minutes, $account->balance()['available_minutes']);
        if ($operation) {
            $this->assertSame('cancelled', $operation->fresh()->status);
        }
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public static function adminScreens(): array
    {
        return ['index' => ['index'], 'detail' => ['detail']];
    }

    #[DataProvider('adminScreens')]
    public function test_prepaid_release_failure_rolls_back_admin_cancellation(string $screen): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $service = app(PrepaidBookingFundingService::class);
        $service->selectForBooking($booking, $account, $owner);
        $service->prepare($booking);
        $this->partialMock(CareHourLedgerService::class)->shouldReceive('release')->once()
            ->andThrow(new \RuntimeException('Release interrupted'));
        $this->withoutExceptionHandling();

        try {
            $this->cancelOnScreen($screen, $booking, User::factory()->create(['role' => 'admin']));
            $this->fail('Cancellation should not succeed if releasing hours fails.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Release interrupted', $exception->getMessage());
        }

        $this->assertSame('scheduled', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->cancelled_at);
        $this->assertSame('ready', $service->forBooking($booking)->status);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertSame(180, $account->balance()['available_minutes']);
    }

    #[DataProvider('adminScreens')]
    public function test_ordinary_card_admin_override_keeps_existing_payment_behavior(string $screen): void
    {
        [$booking, $account] = $this->fixture();
        $payment = CareBookingPayment::create(['care_booking_id' => $booking->id,
            'family_user_id' => $booking->family_user_id, 'caregiver_user_id' => $booking->caregiver_user_id,
            'status' => 'authorized', 'currency' => 'usd', 'stripe_payment_intent_id' => 'pi_ordinary_card_test',
            'amount_authorized_cents' => 6200]);
        $original = $payment->fresh()->getRawOriginal();
        $this->cancelOnScreen($screen, $booking, User::factory()->create(['role' => 'admin']));

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame($original, $payment->fresh()->getRawOriginal());
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
    }

    public function test_future_selected_visit_shows_when_hours_will_be_reserved_without_payment_warning(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $this->selectRecurringVisit($booking, $account, $owner, 168);
        $this->assertSame('selected', app(PrepaidBookingFundingService::class)->forBooking($booking)->status);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $screen = new RegularCareShow;
        $this->assertSame('Care hours will be reserved closer to this visit', $screen->paymentLabel($booking));
        $this->assertSame([], $screen->visitAttention($booking));
    }

    public function test_future_visit_that_has_a_real_shortfall_still_shows_payment_action(): void
    {
        [$booking, $account, $owner] = $this->fixture(60);
        $this->selectRecurringVisit($booking, $account, $owner, 168);
        app(PrepaidBookingFundingService::class)->prepare($booking);
        $screen = new RegularCareShow;
        $this->assertSame('Care hours · payment needs attention', $screen->paymentLabel($booking));
        $this->assertContains('Fix payment', array_column($screen->visitAttention($booking), 'action'));
    }

    public static function heldPaymentCases(): array
    {
        return [
            'open dispute before settlement' => ['needs_response', false, null],
            'lost dispute after settlement' => ['lost', false, 0],
            'lost dispute with amount due' => ['lost', false, 1550],
            'late win awaiting recovery' => ['won', true, 0],
        ];
    }

    #[DataProvider('heldPaymentCases')]
    public function test_disputed_shortfall_shows_support_instead_of_payment_actions(string $state, bool $recovery, ?int $due): void
    {
        [$booking, $account, $owner] = $this->fixture(60);
        $this->selectRecurringVisit($booking, $account, $owner, 24);
        $service = app(PrepaidBookingFundingService::class);
        $funding = $service->forBooking($booking);
        CareHourPaymentOperation::create([
            'public_id' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $funding->id,
            'kind' => 'shortfall', 'operation_key' => 'held-payment:'.$booking->id,
            'status' => 'captured', 'amount_cents' => 3100, 'captured_cents' => 3100, 'currency' => 'usd',
            'metadata' => ['disputes' => ['dp_test' => $state], 'dispute_recovery_required' => $recovery],
        ]);
        if ($due !== null) {
            $booking->update(['status' => 'reviewed', 'worked_minutes' => 120, 'family_confirmed_at' => now()]);
            CareHourSettlement::create(['care_booking_id' => $booking->id, 'care_hour_booking_funding_id' => $funding->id,
                'status' => 'funding_on_hold', 'worked_minutes' => 120, 'prepaid_minutes' => 60,
                'card_minutes' => 60, 'amount_due_cents' => $due]);
        }
        config(['care_hours.sales_enabled' => true]);
        $summary = $service->summary($booking);
        $this->assertTrue($summary['payment_under_review']);
        $screen = new RegularCareShow;
        $this->assertSame('Payment under review', $screen->paymentLabel($booking));
        $this->assertContains(['label' => 'Payment under review', 'action' => 'Contact support', 'tab' => 'support'], $screen->visitAttention($booking));
        $this->assertNotContains('Fix payment', array_column($screen->visitAttention($booking), 'action'));
        $html = view('care-hours.partials.booking-summary', ['booking' => $booking, 'prepaidBookingSummary' => $summary])->render();
        $this->assertStringContainsString('Payment under review', $html);
        $this->assertStringContainsString(route('support.index'), $html);
        $this->assertStringNotContainsString('Paid with care hours', $html);
        $this->assertStringNotContainsString('Pay $', $html);
        $this->assertStringNotContainsString('Buy more hours', $html);
        $this->assertStringNotContainsString(route('family.care-hours.shortfall', $booking), $html);
    }

    public function test_selected_visit_within_reservation_window_is_not_mislabelled_as_future(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $this->selectRecurringVisit($booking, $account, $owner, 168);
        $booking->update(['scheduled_start_at' => now()->addHours(48), 'scheduled_end_at' => now()->addHours(50)]);
        $screen = new RegularCareShow;
        $this->assertSame('Care hours · payment needs attention', $screen->paymentLabel($booking));
        $this->assertContains('Fix payment', array_column($screen->visitAttention($booking), 'action'));
    }

    public function test_prepared_visit_payment_label_is_unchanged(): void
    {
        [$booking, $account, $owner] = $this->fixture();
        $this->selectRecurringVisit($booking, $account, $owner, 24);
        $screen = new RegularCareShow;
        $this->assertSame('Care hours · scheduled care covered', $screen->paymentLabel($booking));
        $this->assertSame([], $screen->visitAttention($booking));
    }

    public function test_ordinary_card_payment_label_and_action_are_unchanged(): void
    {
        [$cardBooking] = $this->fixture();
        CareBookingPayment::create(['care_booking_id' => $cardBooking->id,
            'family_user_id' => $cardBooking->family_user_id, 'caregiver_user_id' => $cardBooking->caregiver_user_id,
            'status' => 'authorization_required', 'currency' => 'usd']);
        $cardScreen = new RegularCareShow;
        $this->assertSame('Payment needs attention', $cardScreen->paymentLabel($cardBooking));
        $this->assertContains('Fix payment', array_column($cardScreen->visitAttention($cardBooking), 'action'));
    }

    private function cancelOnScreen(string $screen, CareBooking $booking, User $admin): void
    {
        if ($screen === 'index') {
            Livewire::actingAs($admin)->test(CareRequestsIndex::class)
                ->call('forceBookingStatus', $booking->care_request_id, 'cancelled')->assertHasNoErrors();
        } else {
            Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $booking->careRequest])
                ->call('forceBookingStatus', 'cancelled')->assertHasNoErrors();
        }
    }

    private function selectRecurringVisit(CareBooking $booking, CareHourAccount $account, User $owner, int $hours): void
    {
        $start = now()->addHours($hours);
        $plan = CarePlan::create(['family_user_id' => $owner->id, 'caregiver_user_id' => $booking->caregiver_user_id,
            'source_care_request_id' => $booking->care_request_id, 'status' => 'active', 'title' => 'Weekly care',
            'schedule_days' => [$start->dayOfWeek], 'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00',
            'starts_on' => $start->toDateString(), 'timezone' => 'America/New_York', 'hourly_rate' => 30]);
        $booking->update(['care_plan_id' => $plan->id, 'scheduled_start_at' => $start, 'scheduled_end_at' => $start->copy()->addHours(2)]);
        app(PrepaidBookingFundingService::class)->selectForPlan($plan, $account, $owner);
        app(CarePlanPaymentWindowService::class)->preparePlan($plan);
        $this->actingAs($owner);
    }

    private function fixture(int $minutes = 300): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care visit', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        $recipient = CareRecipient::create(['care_request_id' => $request->id, 'full_name' => 'Jane Smith', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $owner);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $owner->id, 'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Family',
            'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes, 'amount_cents' => (int) round($minutes / 60 * 3100),
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);

        return [$booking, $account, $owner];
    }
}
