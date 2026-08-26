<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Admin\PaymentsQueue;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\FamilyBillingService;
use App\Services\Payments\StripeClient;
use App\Services\RegularCare\CarePlanHealthService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class StripeMarketplacePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_hire_fails_when_billing_is_not_configured(): void
    {
        config()->set('services.stripe.bypass', false);
        config()->set('services.stripe.secret', null);

        [$family, $request, $application] = $this->seedScenario();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_OPEN,
        ]);
        $this->assertDatabaseMissing('care_bookings', [
            'care_request_id' => $request->id,
        ]);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_authorization_requires_payment_method_throws_declined_card_message(): void
    {
        config()->set('services.stripe.bypass', false);

        [$family, $request, $application] = $this->seedScenario();

        $this->bindDeclinedStripeClient();

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $application->caregiver_user_id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(9, 0),
            'scheduled_end_at' => now()->addDay()->setTime(13, 0),
            'expected_minutes' => 240,
        ]);

        try {
            app(BookingPaymentService::class)->authorizeForBooking($booking);
            $this->fail('Expected a declined-card payment exception.');
        } catch (PaymentException $exception) {
            $this->assertSame(
                'Your card was declined. Confirm or replace your card for this visit, then retry authorization.',
                $exception->userMessage
            );
        }

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => CareBookingPayment::STATUS_FAILED,
            'stripe_payment_intent_id' => 'pi_declined',
            'last_error' => 'Your card was declined.',
        ]);
    }

    public function test_hire_continues_and_starts_client_authorization_when_payment_needs_confirmation(): void
    {
        config()->set('services.stripe.bypass', false);

        [$family, $request, $application] = $this->seedScenario();

        $this->bindDeclinedStripeClient();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_FILLED,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);
        $this->assertDatabaseHas('care_bookings', [
            'id' => $booking->id,
            'care_request_id' => $request->id,
            'status' => CareBooking::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            'stripe_payment_intent_id' => 'pi_client_confirmation',
            'stripe_payment_intent_client_secret' => 'pi_client_confirmation_secret_test',
            'last_error' => 'Card authorization needs confirmation.',
        ]);
    }

    public function test_family_can_finalize_client_authorization_after_3ds(): void
    {
        config()->set('services.stripe.bypass', false);

        [$family, $request, $application] = $this->seedScenario();

        $this->bindDeclinedStripeClient();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('finalizeStripeAuthorization', 'pi_client_confirmation');

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZED,
            'stripe_payment_intent_id' => 'pi_client_confirmation',
            'amount_authorized_cents' => 10890,
            'last_error' => null,
        ]);
    }

    public function test_superseded_authorization_webhook_cannot_replace_the_current_revision(): void
    {
        config()->set('services.stripe.bypass', false);
        [$family, $request, $application] = $this->seedScenario();
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $application->caregiver_user_id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(10, 0),
            'scheduled_end_at' => now()->addDay()->setTime(12, 0),
            'expected_minutes' => 120,
        ]);

        $stripe = new class extends StripeClient
        {
            public int $created = 0;

            /** @var list<string> */
            public array $canceled = [];

            public function ensureFamilyCustomer(User $family): string
            {
                return 'cus_revision_test';
            }

            public function defaultPaymentMethodForCustomer(string $customerId): ?array
            {
                return ['id' => 'pm_revision_test'];
            }

            public function createManualAuthorizationIntent(
                CareBooking $booking,
                string $customerId,
                string $paymentMethodId,
                int $amountCents,
                string $currency,
                ?string $idempotencyKey = null,
            ): array {
                $this->created++;
                $id = 'pi_revision_'.$this->created;

                return [
                    'payment_intent_id' => $id,
                    'client_secret' => $id.'_secret',
                    'status' => 'requires_action',
                    'amount' => $amountCents,
                    'authorization_expires_at' => null,
                ];
            }

            public function cancelPaymentIntent(string $paymentIntentId): void
            {
                $this->canceled[] = $paymentIntentId;
            }

            public function currency(): string
            {
                return 'usd';
            }
        };
        app()->instance(StripeClient::class, $stripe);
        $this->actingAs($family);
        $payments = app(BookingPaymentService::class);

        $payments->prepareOnSessionAuthorizationForAmount($booking, 5000, 'correction:v1');
        $payments->prepareOnSessionAuthorizationForAmount($booking->fresh(), 6000, 'correction:v2');

        $this->assertSame(2, $stripe->created);
        $this->assertSame(['pi_revision_1'], $stripe->canceled);
        $this->assertDatabaseHas('care_booking_payment_attempts', [
            'stripe_payment_intent_id' => 'pi_revision_1',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('care_booking_payment_attempts', [
            'stripe_payment_intent_id' => 'pi_revision_2',
            'is_active' => true,
        ]);

        $payments->handlePaymentIntentWebhook([
            'id' => 'pi_revision_1',
            'status' => 'requires_capture',
            'amount' => 5000,
        ]);
        $payment = $booking->fresh()->payment;
        $this->assertSame('pi_revision_2', $payment?->stripe_payment_intent_id);
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED, $payment?->status);

        $payments->handlePaymentIntentWebhook([
            'id' => 'pi_revision_2',
            'status' => 'requires_capture',
            'amount' => 6000,
            'authorization_expires_at' => now()->addDays(6),
        ]);
        $payment->refresh();
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $payment->status);
        $this->assertSame(6000, (int) $payment->amount_authorized_cents);
    }

    public function test_predeployment_authorization_is_adopted_without_restarting_an_active_users_confirmation(): void
    {
        config()->set('services.stripe.bypass', false);
        [$family, $request, $application] = $this->seedScenario();
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $application->caregiver_user_id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay()->setTime(10, 0),
            'scheduled_end_at' => now()->addDay()->setTime(12, 0),
            'expected_minutes' => 120,
        ]);
        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $application->caregiver_user_id,
            'status' => CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            'currency' => 'usd',
            'stripe_customer_id' => 'cus_legacy_active',
            'stripe_payment_method_id' => 'pm_legacy_active',
            'stripe_payment_intent_id' => 'pi_legacy_active',
            'stripe_payment_intent_client_secret' => 'pi_legacy_active_secret',
            'metadata' => [
                'requested_authorization_cents' => 5000,
            ],
        ]);

        $stripe = new class extends StripeClient
        {
            public int $created = 0;

            public function ensureFamilyCustomer(User $family): string
            {
                return 'cus_legacy_active';
            }

            public function defaultPaymentMethodForCustomer(string $customerId): ?array
            {
                return ['id' => 'pm_legacy_active'];
            }

            public function createManualAuthorizationIntent(
                CareBooking $booking,
                string $customerId,
                string $paymentMethodId,
                int $amountCents,
                string $currency,
                ?string $idempotencyKey = null,
            ): array {
                $this->created++;

                throw new PaymentException('The active legacy intent should have been reused.');
            }

            public function currency(): string
            {
                return 'usd';
            }
        };
        app()->instance(StripeClient::class, $stripe);
        $this->actingAs($family);

        $payment = app(BookingPaymentService::class)->prepareOnSessionAuthorizationForAmount(
            $booking->fresh(),
            5000,
            'time-correction:legacy-compatible:5000',
        );

        $this->assertSame(0, $stripe->created);
        $this->assertSame('pi_legacy_active', $payment->stripe_payment_intent_id);
        $this->assertSame(
            'time-correction:legacy-compatible:5000',
            data_get($payment->metadata, 'authorization_revision_key'),
        );
    }

    public function test_regular_care_plan_recovers_after_client_completes_3ds(): void
    {
        config()->set('services.stripe.bypass', false);
        [$family, $request, $application] = $this->seedScenario();
        $this->bindDeclinedStripeClient();

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])->call('hire', $application->id);
        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $application->caregiver_user_id,
            'source_care_request_id' => $request->id,
            'source_care_booking_id' => $booking->id,
            'next_booking_id' => $booking->id,
            'status' => CarePlan::STATUS_PAYMENT_ATTENTION,
            'title' => 'Regular care payment recovery',
            'schedule_days' => [$booking->scheduled_start_at->dayOfWeek],
            'schedule_start_time' => $booking->scheduled_start_at->format('H:i:s'),
            'schedule_end_time' => $booking->scheduled_end_at->format('H:i:s'),
            'starts_on' => $booking->scheduled_start_at->toDateString(),
            'timezone' => config('app.timezone'),
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_ACTION_REQUIRED,
            'last_error' => 'Card authorization needs confirmation.',
        ]);
        $booking->forceFill(['care_plan_id' => $plan->id])->save();
        app(CarePlanHealthService::class)->reconcile($plan);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('finalizeStripeAuthorization', 'pi_client_confirmation');

        $plan->refresh();
        $this->assertSame(CarePlan::STATUS_ACTIVE, $plan->status);
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->payment_status);
        $this->assertNull($plan->last_error);
        $this->assertSame(CareBookingPayment::STATUS_AUTHORIZED, $booking->fresh()->payment?->status);
    }

    public function test_hire_authorizes_payment_in_bypass_mode(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application] = $this->seedScenario();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => 'authorized',
            'family_user_id' => $family->id,
        ]);
    }

    public function test_family_confirmation_captures_and_transfers_when_connect_ready(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_test_ready',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => 'transferred',
        ]);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'status' => 'paid',
        ]);
        $this->assertNotNull($booking->fresh()?->family_confirmed_at);
    }

    public function test_failed_payout_transfer_stays_internal_and_is_retried_without_alerting_the_caregiver(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiver = $application->caregiver;
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_test_transfer_failure',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        app()->instance(StripeClient::class, new class extends StripeClient
        {
            public function createTransferForCharge(
                string $destinationAccountId,
                string $sourceChargeId,
                string $transferGroup,
                int $amountCents,
                string $currency,
                array $metadata = [],
                ?string $idempotencyKey = null,
            ): array {
                throw new PaymentException('Unable to transfer caregiver earnings right now.');
            }
        });
        Notification::fake();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => CareBookingPayment::STATUS_TRANSFER_FAILED,
        ]);
        Notification::assertNotSentTo($caregiver, MarketplaceEventNotification::class, function (MarketplaceEventNotification $notification) use ($caregiver): bool {
            return data_get($notification->toArray($caregiver), 'event_key') === MarketplaceEvent::PAYOUT_TRANSFER_FAILED;
        });
        Notification::assertNotSentTo($family, MarketplaceEventNotification::class, function (MarketplaceEventNotification $notification) use ($family): bool {
            return data_get($notification->toArray($family), 'event_key') === MarketplaceEvent::PAYOUT_TRANSFER_FAILED;
        });
    }

    public function test_new_shift_uses_v2_snapshot_instead_of_legacy_family_override(): void
    {
        config()->set('services.stripe.bypass', true);
        config()->set('marketplace.family_pricing_overrides', [
            'donrjohn22@yahoo.com' => [
                'hourly_rate' => 15.75,
                'platform_fee_percent' => 0,
            ],
        ]);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $family->forceFill(['email' => 'DonRJohn22@yahoo.com'])->save();
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_test_ready_override',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();

        $this->assertSame(14880, (int) $payment->amount_authorized_cents);
        $this->assertSame('2026-08-v2', $payment->pricing_version);
        $this->assertSame(3000, (int) $payment->family_care_rate_cents);
        $this->assertSame(100, (int) $payment->family_processing_fee_rate_cents);
        $this->assertSame(2700, (int) $payment->caregiver_gross_rate_cents);
        $this->assertSame(30.0, (float) data_get($payment->metadata, 'hourly_rate'));
        $this->assertSame(0.0, (float) data_get($payment->metadata, 'platform_fee_percent'));

        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment->refresh();

        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $payment->status);
        $this->assertSame(6200, (int) $payment->amount_captured_cents);
        $this->assertSame(800, (int) $payment->platform_fee_cents);
        $this->assertSame(5400, (int) $payment->caregiver_gross_amount_cents);
        $this->assertSame(0, (int) $payment->stripe_processing_fee_cents);
        $this->assertSame(5400, (int) $payment->caregiver_amount_cents);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'status' => 'paid',
            'amount' => 54.00,
        ]);
    }

    public function test_v2_ledger_links_charge_actual_processing_fee_earning_and_source_transfer(): void
    {
        config()->set('services.stripe.bypass', true);
        config()->set('services.stripe.bypass_processing_fee_percent', 2.9);
        config()->set('services.stripe.bypass_processing_fee_fixed_cents', 30);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_v2_ledger_ready',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $charge = $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_CHARGE)->firstOrFail();
        $processingFee = $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_PROCESSING_FEE)->firstOrFail();
        $earning = $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_EARNING)->latest('id')->firstOrFail();
        $transfer = $payment->operations()->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)->firstOrFail();

        $this->assertStringStartsWith('SHIFT-', (string) $payment->financial_reference);
        $this->assertSame($booking->financial_reference, $payment->financial_reference);
        $this->assertSame(6200, (int) $charge->amount_cents);
        $this->assertSame(210, (int) $processingFee->amount_cents);
        $this->assertSame($charge->id, $processingFee->parent_operation_id);
        $this->assertSame(5400, (int) $earning->amount_cents);
        $this->assertSame(5190, (int) data_get($earning->metadata, 'net_earnings_cents'));
        $this->assertSame(5190, (int) $transfer->amount_cents);
        $this->assertSame($charge->id, $transfer->parent_operation_id);
        $this->assertSame($charge->stripe_object_id, $transfer->stripe_parent_object_id);
        $this->assertSame(210, (int) $payment->stripe_processing_fee_cents);
        $this->assertSame(5190, (int) $payment->caregiver_amount_cents);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'financial_reference' => $booking->financial_reference,
            'gross_amount' => 54.00,
            'processing_fee_amount' => 2.10,
            'amount' => 51.90,
        ]);
        $this->actingAs($booking->caregiver)
            ->get(route('caregiver.earnings.index', ['tab' => 'shifts', 'range' => 'all']))
            ->assertOk()
            ->assertSee('Gross earnings')
            ->assertSee('Processing fees')
            ->assertSee('Net earnings')
            ->assertSee('$54.00')
            ->assertSee('$2.10')
            ->assertSee('$51.90')
            ->assertSee($booking->financial_reference);
    }

    public function test_v2_dashboard_refund_reverses_the_matching_caregiver_transfer_once(): void
    {
        [$booking, $payment, $charge, $transfer] = $this->completeV2TransferredBooking();
        $payments = app(BookingPaymentV2Service::class);
        $refund = [
            'id' => 're_dashboard_partial_'.$payment->id,
            'charge' => $charge->stripe_object_id,
            'status' => 'succeeded',
            'amount' => 3100,
        ];

        $this->assertTrue($payments->handleRefund($refund));
        $this->assertTrue($payments->handleChargeRefund([
            'id' => $charge->stripe_object_id,
            'refunds' => ['data' => [$refund]],
        ]));

        $payment->refresh();
        $reversals = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->where('parent_operation_id', $transfer->id)
            ->get();

        $this->assertSame(CareBookingPayment::STATUS_PARTIALLY_REFUNDED, $payment->status);
        $this->assertSame(3100, (int) $payment->amount_refunded_cents);
        $this->assertCount(1, $reversals);
        $this->assertSame(2595, (int) $reversals->first()->amount_cents);
        $this->assertSame(CareBookingPaymentOperation::STATUS_SUCCEEDED, $reversals->first()->status);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'amount' => 25.95,
        ]);
    }

    public function test_v2_dispute_only_reverses_caregiver_transfer_after_a_final_loss(): void
    {
        [, $payment, $charge, $transfer] = $this->completeV2TransferredBooking();
        $payments = app(BookingPaymentV2Service::class);
        $dispute = [
            'id' => 'dp_'.$payment->id,
            'charge' => $charge->stripe_object_id,
            'status' => 'needs_response',
            'amount' => $charge->amount_cents,
            'reason' => 'fraudulent',
        ];

        $payments->handleDispute($dispute);
        $this->assertFalse($payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->exists());

        $payments->handleDispute(array_merge($dispute, ['status' => 'lost']));
        $reversal = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
            ->where('parent_operation_id', $transfer->id)
            ->firstOrFail();

        $this->assertSame((int) $transfer->amount_cents, (int) $reversal->amount_cents);
        $this->assertSame(CareBookingPaymentOperation::STATUS_SUCCEEDED, $reversal->status);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $payment->care_booking_id,
            'status' => 'reversed',
            'amount' => 0.00,
        ]);
    }

    public function test_pre_cutover_booking_without_payment_adopts_v2_when_payment_is_first_created(): void
    {
        config()->set('services.stripe.bypass', true);
        [$family, $request, $application] = $this->seedScenario();
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_account_id' => $request->family_account_id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $application->caregiver_user_id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at,
            'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 240,
        ]);
        $booking->forceFill([
            'pricing_version' => null,
            'family_care_rate_cents' => null,
            'family_processing_fee_rate_cents' => null,
            'caregiver_gross_rate_cents' => null,
            'caregiver_fee_policy' => null,
            'pricing_snapshotted_at' => null,
        ])->save();

        $payment = app(BookingPaymentService::class)->authorizeForBooking($booking->fresh());

        $this->assertSame('2026-08-v2', $booking->fresh()->pricing_version);
        $this->assertSame('2026-08-v2', $payment->pricing_version);
        $this->assertSame(3000, (int) $payment->family_care_rate_cents);
        $this->assertSame(100, (int) $payment->family_processing_fee_rate_cents);
        $this->assertSame(2700, (int) $payment->caregiver_gross_rate_cents);
    }

    public function test_existing_legacy_payment_is_not_repriced_during_reauthorization(): void
    {
        config()->set('services.stripe.bypass', true);
        [$family, $request, $application] = $this->seedScenario();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->forceFill([
            'pricing_version' => null,
            'family_care_rate_cents' => null,
            'family_processing_fee_rate_cents' => null,
            'caregiver_gross_rate_cents' => null,
            'caregiver_fee_policy' => null,
            'pricing_snapshotted_at' => null,
        ])->save();
        $payment = $booking->payment()->firstOrFail();
        $payment->forceFill([
            'pricing_version' => null,
            'family_care_rate_cents' => null,
            'family_processing_fee_rate_cents' => null,
            'caregiver_gross_rate_cents' => null,
            'authorization_expires_at' => now()->subMinute(),
        ])->save();

        $reauthorized = app(BookingPaymentService::class)->authorizeForBooking($booking->fresh());

        $this->assertNull($reauthorized->pricing_version);
        $this->assertNull($reauthorized->family_processing_fee_rate_cents);
        $this->assertSame($payment->id, $reauthorized->id);
        $this->assertSame(14784, (int) $reauthorized->amount_authorized_cents);
    }

    public function test_family_billing_checkout_in_bypass_mode_sets_customer_profile(): void
    {
        config()->set('services.stripe.bypass', true);

        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)
            ->get(route('family.billing.show', ['checkout_session_id' => 'bypass-session']))
            ->assertRedirect(route('family.billing.show'));

        $this->assertNotNull($family->fresh()->stripe_customer_id);
    }

    public function test_family_billing_page_renders_checkout_button_as_submit(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $this->actingAs($family)
            ->get(route('family.billing.show'))
            ->assertOk()
            ->assertSee('Add card securely')
            ->assertSee('type="submit"', false);
    }

    public function test_family_billing_page_stays_available_when_saved_card_lookup_fails(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $billing = $this->mock(FamilyBillingService::class);
        $billing->shouldReceive('summaryFor')
            ->once()
            ->withArgs(fn (User $actor): bool => $actor->is($family))
            ->andThrow(new PaymentException('Unable to load billing method right now.', 'provider detail'));

        $this->actingAs($family)
            ->get(route('family.billing.show'))
            ->assertOk()
            ->assertSee('We could not check the saved card right now.')
            ->assertSee('Add or update card securely')
            ->assertSee('data-ai-target="family.billing.manage_payment_method"', false);
    }

    public function test_caregiver_connect_onboarding_bypass_sets_connect_ready(): void
    {
        config()->set('services.stripe.bypass', true);

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'draft',
        ]);

        $this->actingAs($caregiver)
            ->post(route('caregiver.payouts.connect.start'))
            ->assertRedirect(route('caregiver.payouts.connect.return'));

        $this->actingAs($caregiver)
            ->get(route('caregiver.payouts.connect.return'))
            ->assertRedirect(route('caregiver.payouts.connect.show'));

        $profile = CaregiverProfile::query()->where('user_id', $caregiver->id)->firstOrFail();
        $this->assertTrue($profile->stripeConnectIsReady());
    }

    public function test_stripe_webhook_updates_booking_payment_state(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application] = $this->seedScenario();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();

        $payload = [
            'id' => 'evt_test_payment_cancelled',
            'object' => 'event',
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => 'pi_bypass_booking_'.$booking->id,
                    'status' => 'canceled',
                ],
            ],
        ];

        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();
        $this->postJson(route('webhooks.stripe'), $payload)
            ->assertOk()
            ->assertJson(['ok' => true, 'duplicate' => true]);

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'stripe_event_id' => 'evt_test_payment_cancelled',
            'status' => 'processed',
            'attempts' => 1,
        ]);
    }

    public function test_capture_handles_overage_with_second_charge(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_test_ready_overage',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        CareBookingPayment::query()->where('care_booking_id', $booking->id)->update([
            'amount_authorized_cents' => 100,
        ]);

        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 300,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $this->assertGreaterThan(0, (int) $payment->amount_overage_cents);
        $this->assertNotNull($payment->stripe_overage_payment_intent_id);

        $capturedCents = (int) $payment->amount_captured_cents;
        $caregiverAmountCents = (int) $payment->caregiver_amount_cents;
        $overageCents = (int) $payment->amount_overage_cents;

        $this->postJson(route('webhooks.stripe'), [
            'id' => 'evt_test_overage_succeeded',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $payment->stripe_overage_payment_intent_id,
                    'status' => 'succeeded',
                    'amount_received' => $overageCents,
                ],
            ],
        ])->assertOk();

        $payment->refresh();
        $this->assertSame($capturedCents, (int) $payment->amount_captured_cents);
        $this->assertSame($caregiverAmountCents, (int) $payment->caregiver_amount_cents);
        $this->assertSame($overageCents, (int) $payment->amount_overage_cents);
    }

    public function test_expired_authorization_reauthorizes_before_capture(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_test_ready_reauth',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        CareBookingPayment::query()->where('care_booking_id', $booking->id)->update([
            'authorization_expires_at' => now()->subMinute(),
        ]);

        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $this->assertNotNull($payment->reauthorized_at);
    }

    public function test_refund_marks_payment_refunded_and_reverses_payout_item(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_test_ready_refund',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 180,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        app(BookingPaymentService::class)->refundForBooking($booking->fresh(['payment']));

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $this->assertSame(CareBookingPayment::STATUS_REFUNDED, $payment->status);
        $this->assertGreaterThan(0, (int) $payment->amount_refunded_cents);
        $this->assertNotNull($payment->stripe_last_refund_id);
        $this->assertNotNull($payment->stripe_last_transfer_reversal_id);

        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'status' => 'reversed',
        ]);
    }

    public function test_stripe_webhook_checkout_completed_syncs_family_billing(): void
    {
        config()->set('services.stripe.bypass', true);

        $family = User::factory()->create(['role' => 'family']);
        $payload = [
            'id' => 'evt_checkout_completed',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_billing',
                    'mode' => 'setup',
                    'status' => 'complete',
                    'metadata' => [
                        'family_user_id' => (string) $family->id,
                    ],
                ],
            ],
        ];

        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();
        $this->assertNotNull($family->fresh()->stripe_customer_id);
    }

    public function test_stripe_webhook_account_updated_syncs_caregiver_connect_state(): void
    {
        config()->set('services.stripe.bypass', true);

        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'stripe_connect_account_id' => 'acct_sync_test',
            'stripe_charges_enabled' => false,
            'stripe_payouts_enabled' => false,
        ]);

        $payload = [
            'id' => 'evt_account_updated',
            'object' => 'event',
            'type' => 'account.updated',
            'data' => [
                'object' => [
                    'id' => 'acct_sync_test',
                    'charges_enabled' => true,
                    'payouts_enabled' => true,
                ],
            ],
        ];

        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();

        $profile->refresh();
        $this->assertTrue((bool) $profile->stripe_charges_enabled);
        $this->assertTrue((bool) $profile->stripe_payouts_enabled);
    }

    public function test_admin_payments_queue_can_retry_transfer_and_refund(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_admin_retry',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 90,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $payment->update([
            'status' => CareBookingPayment::STATUS_TRANSFER_FAILED,
            'stripe_transfer_id' => null,
        ]);

        $admin = User::factory()->create([
            'email' => 'test@test.com',
            'role' => 'admin',
        ]);

        Livewire::actingAs($admin)
            ->test(PaymentsQueue::class)
            ->set('refundAmountCents.'.$payment->id, '500')
            ->set('refundReason.'.$payment->id, 'requested_by_customer')
            ->call('retryTransfer', $payment->id)
            ->call('refund', $payment->id);

        $this->assertDatabaseHas('care_booking_payments', [
            'id' => $payment->id,
            'status' => CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
        ]);
    }

    public function test_payment_succeeded_webhook_repairs_stale_transfer_failed_summary_from_ledger(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_transfer_failed_webhook',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $payment->forceFill([
            'status' => CareBookingPayment::STATUS_TRANSFER_FAILED,
            'stripe_transfer_id' => null,
            'last_error' => 'Unable to transfer caregiver payout right now.',
        ])->save();

        $this->postJson(route('webhooks.stripe'), [
            'id' => 'evt_late_payment_succeeded',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $payment->stripe_payment_intent_id,
                    'status' => 'succeeded',
                    'amount_received' => (int) $payment->amount_captured_cents,
                ],
            ],
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $payment->status);
        $this->assertNull($payment->last_error);
        $this->assertNotNull($payment->stripe_transfer_id);
    }

    public function test_retry_payout_transfers_command_moves_payment_after_connect_becomes_ready(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $this->assertSame(CareBookingPayment::STATUS_TRANSFER_FAILED, $payment->status);
        $this->assertNull($payment->stripe_transfer_id);

        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'status' => 'scheduled',
        ]);

        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_retry_command_ready',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        $this->artisan('homecare:retry-payout-transfers --limit=10')
            ->assertSuccessful();

        $payment->refresh();
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $payment->status);
        $this->assertNotNull($payment->stripe_transfer_id);

        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'status' => 'paid',
        ]);
    }

    public function test_retry_payout_transfers_waits_when_platform_balance_is_not_available(): void
    {
        config()->set('services.stripe.bypass', true);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_retry_command_waiting_balance',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        app()->instance(StripeClient::class, new class extends StripeClient
        {
            public function availableBalanceCents(?string $currency = null): int
            {
                return 0;
            }

            public function createTransfer(
                string $destinationAccountId,
                int $amountCents,
                string $currency,
                array $metadata = [],
                ?string $idempotencyKey = null,
            ): array {
                throw new PaymentException('Unexpected transfer attempt.');
            }
        });

        $this->artisan('homecare:retry-payout-transfers --limit=10')
            ->expectsOutputToContain('Waiting payment #'.$payment->id)
            ->expectsOutputToContain('waiting on balance 1')
            ->assertSuccessful();

        $payment->refresh();
        $this->assertSame(CareBookingPayment::STATUS_TRANSFER_FAILED, $payment->status);
        $this->assertNull($payment->stripe_transfer_id);

        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'status' => 'scheduled',
        ]);
    }

    private function bindDeclinedStripeClient(): void
    {
        app()->instance(StripeClient::class, new class extends StripeClient
        {
            public function ensureFamilyCustomer(User $family): string
            {
                return 'cus_declined';
            }

            public function defaultPaymentMethodForCustomer(string $customerId): ?array
            {
                return ['id' => 'pm_declined'];
            }

            public function createManualAuthorization(
                CareBooking $booking,
                string $customerId,
                string $paymentMethodId,
                int $amountCents,
                string $currency,
                ?string $idempotencyKey = null,
            ): array {
                return [
                    'payment_intent_id' => 'pi_declined',
                    'status' => 'requires_payment_method',
                    'amount' => $amountCents,
                    'authorization_expires_at' => null,
                    'failure_message' => 'Your card was declined.',
                ];
            }

            public function createManualAuthorizationIntent(
                CareBooking $booking,
                string $customerId,
                string $paymentMethodId,
                int $amountCents,
                string $currency,
                ?string $idempotencyKey = null,
            ): array {
                return [
                    'payment_intent_id' => 'pi_client_confirmation',
                    'client_secret' => 'pi_client_confirmation_secret_test',
                    'status' => 'requires_payment_method',
                    'amount' => $amountCents,
                    'authorization_expires_at' => null,
                ];
            }

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                return [
                    'payment_intent_id' => $paymentIntentId,
                    'id' => $paymentIntentId,
                    'client_secret' => $paymentIntentId.'_secret_test',
                    'status' => 'requires_capture',
                    'amount' => 10890,
                    'amount_received' => 0,
                    'authorization_expires_at' => now()->addDays(6),
                    'last_payment_error' => null,
                ];
            }

            public function currency(): string
            {
                return 'usd';
            }
        });
    }

    /**
     * @return array{User,CareRequest,CareRequestApplication,CaregiverProfile|null}
     */
    private function completeV2TransferredBooking(): array
    {
        config()->set('services.stripe.bypass', true);
        config()->set('services.stripe.bypass_processing_fee_percent', 2.9);
        config()->set('services.stripe.bypass_processing_fee_fixed_cents', 30);

        [$family, $request, $application, $caregiverProfile] = $this->seedScenario(returnProfile: true);
        $caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_v2_refund_ready_'.$caregiverProfile->id,
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $booking = CareBooking::query()->where('care_request_id', $request->id)->firstOrFail();
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'completed_at' => now(),
            'timesheet_submitted_at' => now(),
            'worked_minutes' => 120,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('completeBooking');

        $payment = CareBookingPayment::query()->where('care_booking_id', $booking->id)->firstOrFail();
        $charge = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_CHARGE)
            ->firstOrFail();
        $transfer = $payment->operations()
            ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER)
            ->firstOrFail();

        return [$booking, $payment, $charge, $transfer];
    }

    private function seedScenario(bool $returnProfile = false): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $caregiverProfile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 28.00,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning companionship',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(13, 0),
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Mary Doe',
            'relationship_to_family' => 'Mother',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 28.00,
            'cover_note' => str_repeat('Can provide safe and reliable care. ', 3),
        ]);

        return [$family, $request, $application, $returnProfile ? $caregiverProfile : null];
    }
}
