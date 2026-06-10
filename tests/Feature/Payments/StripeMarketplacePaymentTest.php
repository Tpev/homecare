<?php

namespace Tests\Feature\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Admin\PaymentsQueue;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CaregiverProfile;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'Card was declined. Update your payment method in Billing & Payments, then try hiring again.',
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

    public function test_family_pricing_override_controls_authorization_capture_fee_and_transfer(): void
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

        $this->assertSame(7560, (int) $payment->amount_authorized_cents);
        $this->assertSame(15.75, (float) data_get($payment->metadata, 'hourly_rate'));
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
        $this->assertSame(3150, (int) $payment->amount_captured_cents);
        $this->assertSame(0, (int) $payment->platform_fee_cents);
        $this->assertSame(3150, (int) $payment->caregiver_amount_cents);
        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $booking->id,
            'status' => 'paid',
            'amount' => 31.50,
        ]);
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
            ->assertSee('Add card with Stripe')
            ->assertSee('type="submit"', false);
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

        $this->assertDatabaseHas('care_booking_payments', [
            'care_booking_id' => $booking->id,
            'status' => 'cancelled',
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
            'role' => 'family',
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
