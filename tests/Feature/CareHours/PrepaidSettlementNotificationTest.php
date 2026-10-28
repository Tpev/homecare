<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\NotificationsCenter;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareHourPurchase;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PrepaidSettlementNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Real commit callbacks, with no persistent application database or provider calls.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh')->assertSuccessful();
        config(['care_hours.enabled' => true, 'services.stripe.bypass' => true]);
        Notification::fake();
    }

    public function test_settlement_and_confirmed_earnings_notify_once_when_reconciled_repeatedly(): void
    {
        [$booking, $owner, $caregiver] = $this->scenario();
        $service = app(PrepaidBookingSettlementService::class);
        $settlement = $service->settle($booking);
        $service->reconcile($settlement);
        $service->settle($booking->fresh());

        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame(2, $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_VISIT_SETTLED)->count());
        $notice = $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_VISIT_SETTLED)->where('channel', 'email')->sole();
        $this->assertSame('Care hours used for your visit', data_get($notice->payload, 'email.title'));
        $this->assertStringContainsString('No additional visit payment was needed.', data_get($notice->payload, 'email.body'));
        $this->assertSame(2, $this->deliveries($caregiver, MarketplaceEvent::PAYOUT_TRANSFERRED)->count());
        $earnings = $this->deliveries($caregiver, MarketplaceEvent::PAYOUT_TRANSFERRED)->where('channel', 'email')->sole();
        $this->assertStringContainsString('$54.00 USD', data_get($earnings->payload, 'email.body'));
        $this->assertStringContainsString('Bank arrival follows your Stripe payout schedule.', data_get($earnings->payload, 'email.body'));
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertSame(1, $settlement->operations()->where('kind', 'transfer')->count());
    }

    public function test_pending_payout_does_not_claim_earnings_are_available_and_recovery_notifies_once(): void
    {
        [$booking, $owner, $caregiver] = $this->scenario();
        $caregiver->caregiverProfile->update(['stripe_payouts_enabled' => false]);
        $service = app(PrepaidBookingSettlementService::class);
        $settlement = $service->settle($booking);
        $this->assertSame('payout_pending', $settlement->status);
        $this->assertSame(0, $this->deliveries($caregiver, MarketplaceEvent::PAYOUT_TRANSFERRED)->count());
        $this->assertSame(2, $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_VISIT_SETTLED)->count());

        $caregiver->caregiverProfile->update(['stripe_payouts_enabled' => true]);
        $service->reconcile($settlement);
        $service->reconcile($settlement->fresh());
        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame(2, $this->deliveries($caregiver, MarketplaceEvent::PAYOUT_TRANSFERRED)->count());
        $this->assertSame(2, $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_VISIT_SETTLED)->count());
    }

    public function test_unpaid_overrun_sends_attention_without_success_or_full_payout_notice(): void
    {
        [$booking, $owner, $caregiver] = $this->scenario(60);
        $settlement = app(PrepaidBookingSettlementService::class)->settle($booking);
        $this->assertSame(3100, (int) $settlement->amount_due_cents);
        $this->assertSame(0, $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_VISIT_SETTLED)->count());
        $this->assertSame(0, $this->deliveries($caregiver, MarketplaceEvent::PAYOUT_TRANSFERRED)->count());
        $this->assertGreaterThan(0, $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_PAYMENT_ATTENTION)->count());
    }

    public function test_family_can_save_prepaid_email_preferences_and_payout_respects_caregiver_opt_out(): void
    {
        [$booking, $owner, $caregiver] = $this->scenario();
        Livewire::actingAs($owner)->test(NotificationsCenter::class)
            ->set('preferences.'.MarketplaceEvent::CARE_HOURS_PAYMENT_ATTENTION.'.email', false)
            ->set('preferences.'.MarketplaceEvent::CARE_HOURS_VISIT_SETTLED.'.email', false)
            ->call('savePreferences')->assertHasNoErrors();
        \App\Models\UserNotificationPreference::create(['user_id' => $caregiver->id,
            'event_key' => MarketplaceEvent::PAYOUT_TRANSFERRED, 'email_enabled' => false, 'in_app_enabled' => true]);
        app(PrepaidBookingSettlementService::class)->settle($booking);

        foreach ([[$owner, MarketplaceEvent::CARE_HOURS_VISIT_SETTLED], [$caregiver, MarketplaceEvent::PAYOUT_TRANSFERRED]] as [$user, $event]) {
            $this->assertSame(0, $this->deliveries($user, $event)->where('channel', 'email')->count());
            $this->assertSame(1, $this->deliveries($user, $event)->where('channel', 'in_app')->count());
        }
        $funding = app(PrepaidBookingFundingService::class);
        $funding->notifyPaymentAttention($funding->forBooking($booking));
        $this->assertSame(0, $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_PAYMENT_ATTENTION)->where('channel', 'email')->count());
    }

    public function test_reducing_approved_time_does_not_announce_another_transfer(): void
    {
        [$booking, $owner, $caregiver] = $this->scenario();
        $service = app(PrepaidBookingSettlementService::class);
        $service->settle($booking);
        $booking->update(['worked_minutes' => 60]);
        $settlement = $service->settle($booking->fresh());
        $this->assertSame('paid', $settlement->status);
        $this->assertSame(2700, (int) $settlement->paid_cents);
        $this->assertSame(2, $this->deliveries($caregiver, MarketplaceEvent::PAYOUT_TRANSFERRED)->count());
        $this->assertSame(4, $this->deliveries($owner, MarketplaceEvent::CARE_HOURS_VISIT_SETTLED)->count());
    }

    public function test_notification_failure_cannot_change_confirmed_payment_state(): void
    {
        [$booking] = $this->scenario();
        $notifications = \Mockery::mock(\App\Services\Notifications\MarketplaceNotificationService::class);
        $notifications->shouldReceive('notify')->twice()->andThrow(new \RuntimeException('Notification transport unavailable.'));
        $this->app->instance(\App\Services\Notifications\MarketplaceNotificationService::class, $notifications);

        $settlement = app(PrepaidBookingSettlementService::class)->settle($booking);
        $this->assertSame('paid', $settlement->status);
        $this->assertSame(5400, (int) $settlement->paid_cents);
        $this->assertSame(1, $settlement->operations()->where('kind', 'transfer')->where('status', 'succeeded')->count());
        $this->assertNull($settlement->last_error);
    }

    private function deliveries(User $user, string $event)
    {
        return MarketplaceNotificationDelivery::where('user_id', $user->id)->where('event_key', $event);
    }

    private function scenario(int $minutes = 600): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'stripe_connect_account_id' => 'acct_notification_test',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Morning companionship',
            'status' => 'filled', 'request_type' => 'one_time', 'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(2), 'address_line1' => '100 Fictional St',
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = $request->recipient()->create(['full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $owner);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $owner->id, 'buyer_email' => $owner->email, 'buyer_name' => $owner->name,
            'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes, 'amount_cents' => $minutes * 3100 / 60,
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'stripe_charge_id' => 'ch_notification_test',
            'terms_version' => 'test', 'quote' => [], 'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $application = CareRequestApplication::create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'family_account_id' => $account->family_account_id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'scheduled', 'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 120, 'caregiver_gross_rate_cents' => 2700, 'family_care_rate_cents' => 3000, 'family_processing_fee_rate_cents' => 100]);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $booking->update(['status' => 'completed', 'worked_minutes' => 120, 'completed_at' => now(), 'family_confirmed_at' => now()]);

        return [$booking->fresh(), $owner, $caregiver];
    }
}
