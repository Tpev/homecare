<?php

namespace Tests\Feature\Booking;

use App\Exceptions\Payments\PaymentActionRequiredException;
use App\Livewire\Admin\SupportTicketShow;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\StripeClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class BookingCorrectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.bypass', true);
    }

    public function test_admin_can_complete_corrected_visit_and_capture_payment(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        app(BookingPaymentService::class)->authorizeForBooking($booking);

        $correction = $this->applyCorrection($ticket, $admin, 180);

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $booking->refresh();
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->status);
        $this->assertSame(180, $booking->worked_minutes);
        $this->assertNotNull($booking->timesheet_submitted_at);
        $this->assertNotNull($booking->family_confirmed_at);
        $this->assertSame('admin_correction', $booking->check_in_source);
        $this->assertSame('admin_correction', $booking->check_out_source);

        $payment = $booking->payment()->firstOrFail();
        $this->assertContains($payment->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
        ]);
        $this->assertSame($correction->target_charge_cents, $payment->amount_captured_cents - $payment->amount_refunded_cents);
        $this->assertDatabaseHas('caregiver_payout_items', ['care_booking_id' => $booking->id]);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'event_type' => 'admin_booking_correction_applied',
        ]);
    }

    public function test_post_capture_increase_charges_only_delta_and_updates_payout(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $this->captureExistingVisit($booking, 120);
        $beforePayment = $booking->fresh()->payment;
        $beforeCaptured = (int) $beforePayment->amount_captured_cents;
        $beforePayout = (float) $booking->payoutItem()->firstOrFail()->amount;

        $correction = $this->applyCorrection($ticket, $admin, 180);
        $payment = $booking->fresh()->payment;

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $this->assertGreaterThan(0, $correction->payment_delta_cents);
        $this->assertSame($correction->payment_delta_cents, (int) $payment->amount_captured_cents - $beforeCaptured);
        $this->assertSame($correction->target_charge_cents, (int) $payment->amount_captured_cents - (int) $payment->amount_refunded_cents);
        $this->assertNotNull(data_get($correction->provider_payload, 'additional_charge.payment_intent_id'));
        $this->assertGreaterThan($beforePayout, (float) $booking->payoutItem()->firstOrFail()->amount);
    }

    public function test_post_capture_decrease_refunds_delta_and_adjusts_payout(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $this->captureExistingVisit($booking, 180);
        $beforeRefunded = (int) $booking->fresh()->payment->amount_refunded_cents;

        $correction = $this->applyCorrection($ticket, $admin, 120);
        $payment = $booking->fresh()->payment;

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $this->assertLessThan(0, $correction->payment_delta_cents);
        $this->assertSame(abs($correction->payment_delta_cents), (int) $payment->amount_refunded_cents - $beforeRefunded);
        $this->assertSame($correction->target_charge_cents, (int) $payment->amount_captured_cents - (int) $payment->amount_refunded_cents);
        $this->assertNotEmpty(data_get($correction->provider_payload, 'refunds'));
        $this->assertLessThan(0, (float) $booking->payoutItem()->firstOrFail()->payout->adjustments_amount);
    }

    public function test_admin_can_reopen_uncaptured_visit_and_tracking_is_cleared(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
            'worked_minutes' => 120,
            'timesheet_submitted_at' => now(),
            'family_confirmed_at' => now(),
            'check_in_source' => 'browser',
            'check_out_source' => 'browser',
        ])->save();

        $correction = app(BookingCorrectionService::class)->apply(
            $ticket,
            $admin,
            [
                'action' => CareBookingCorrection::ACTION_REOPEN,
                'reason' => 'The visit was closed accidentally before the approved work occurred.',
                'family_approved' => false,
            ],
            (string) Str::uuid(),
        );

        $booking->refresh();
        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        $this->assertNull($booking->started_at);
        $this->assertNull($booking->completed_at);
        $this->assertNull($booking->worked_minutes);
        $this->assertNull($booking->family_confirmed_at);
        $this->assertNull($booking->check_in_source);
    }

    public function test_reopening_a_recurring_visit_reconciles_the_plan_next_booking(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $family = $booking->family;

        $futureRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Future generated recurring visit',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addWeek()->setTime(9, 0),
            'requested_end_at' => now()->addWeek()->setTime(12, 0),
            'address_line1' => '123 Test Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $futureApplication = CareRequestApplication::query()->create([
            'care_request_id' => $futureRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        $futureBooking = CareBooking::query()->create([
            'care_request_id' => $futureRequest->id,
            'care_request_application_id' => $futureApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $futureRequest->requested_start_at,
            'scheduled_end_at' => $futureRequest->requested_end_at,
            'expected_minutes' => 180,
        ]);
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $booking->care_request_id,
            'source_care_booking_id' => $booking->id,
            'next_booking_id' => $futureBooking->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Recurring correction test',
            'schedule_days' => [strtolower(now()->addWeek()->format('l'))],
            'schedule_start_time' => '09:00:00',
            'schedule_end_time' => '12:00:00',
            'starts_on' => now()->subMonth()->toDateString(),
            'timezone' => config('app.timezone'),
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
        ]);
        $booking->careRequest()->update(['care_plan_id' => $plan->id]);
        $futureRequest->update(['care_plan_id' => $plan->id]);
        $booking->update([
            'care_plan_id' => $plan->id,
            'occurrence_key' => "regular-care:{$plan->id}:regular:past:09:00",
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => 1,
        ]);
        $futureBooking->update([
            'care_plan_id' => $plan->id,
            'occurrence_key' => "regular-care:{$plan->id}:regular:future:09:00",
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => 1,
        ]);

        app(BookingPaymentService::class)->authorizeForBooking($booking->fresh());
        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => now()->subDay()->setTime(9, 0),
            'completed_at' => now()->subDay()->setTime(11, 0),
            'worked_minutes' => 120,
            'timesheet_submitted_at' => now(),
            'family_confirmed_at' => now(),
        ])->save();

        app(BookingCorrectionService::class)->apply(
            $ticket,
            $admin,
            [
                'action' => CareBookingCorrection::ACTION_REOPEN,
                'reason' => 'Operations is reopening this approved recurring visit for correction.',
                'family_approved' => false,
            ],
            (string) Str::uuid(),
        );

        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->fresh()->status);
        $this->assertSame($admin->id, $booking->fresh()->check_in_override_by_user_id);
        $this->assertNotNull($booking->fresh()->check_in_override_at);
        $this->assertSame($booking->id, $plan->fresh()->next_booking_id);
        $this->assertSame(CarePlan::PAYMENT_AUTHORIZED, $plan->fresh()->payment_status);
    }

    public function test_complete_and_bill_on_a_recurring_visit_advances_the_plan(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        [$plan, $futureBooking] = $this->attachRecurringPlan($booking, $caregiver);
        app(BookingPaymentService::class)->authorizeForBooking($booking->fresh());

        $correction = $this->applyCorrection($ticket, $admin, 180);

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->fresh()->status);
        $this->assertSame($futureBooking->id, $plan->fresh()->next_booking_id);
        $this->assertContains($booking->fresh()->payment?->status, [
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED,
        ]);
    }

    public function test_recurring_visit_refund_and_additional_charge_remain_scoped_to_that_visit(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        [$plan, $futureBooking] = $this->attachRecurringPlan($booking, $caregiver);
        $this->captureExistingVisit($booking, 180);
        $futureBooking->refresh();
        $futurePaymentBefore = $futureBooking->payment?->toArray();

        $refundCorrection = $this->applyCorrection($ticket, $admin, 120);

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $refundCorrection->status);
        $this->assertLessThan(0, $refundCorrection->payment_delta_cents);
        $this->assertSame($futurePaymentBefore, $futureBooking->fresh()->payment?->toArray());
        $this->assertSame($futureBooking->id, $plan->fresh()->next_booking_id);

        [$increaseAdmin, $increaseCaregiver, $increaseBooking, $increaseTicket] = $this->scenario();
        [$increasePlan, $increaseFutureBooking] = $this->attachRecurringPlan($increaseBooking, $increaseCaregiver);
        $this->captureExistingVisit($increaseBooking, 120);
        $increaseFuturePaymentBefore = $increaseFutureBooking->fresh()->payment?->toArray();

        $chargeCorrection = $this->applyCorrection($increaseTicket, $increaseAdmin, 180);

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $chargeCorrection->status);
        $this->assertGreaterThan(0, $chargeCorrection->payment_delta_cents);
        $this->assertSame($increaseFuturePaymentBefore, $increaseFutureBooking->fresh()->payment?->toArray());
        $this->assertSame($increaseFutureBooking->id, $increasePlan->fresh()->next_booking_id);
    }

    public function test_failed_recurring_correction_recovery_keeps_plan_health_consistent(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        [$plan, $futureBooking] = $this->attachRecurringPlan($booking, $caregiver);
        $this->captureExistingVisit($booking, 180);

        $reversalAttempts = 0;
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $stripe->shouldReceive('createTransferReversal')
            ->twice()
            ->andReturnUsing(function (string $transferId, int $amountCents) use (&$reversalAttempts): array {
                if ($reversalAttempts++ === 0) {
                    throw new \App\Exceptions\Payments\PaymentException('Payout reversal is temporarily unavailable.');
                }

                return ['id' => 'trr_recurring_retry', 'status' => 'succeeded', 'amount' => $amountCents];
            });
        $this->app->instance(StripeClient::class, $stripe);

        $correction = $this->applyCorrection($ticket, $admin, 120);
        $this->assertSame(CareBookingCorrection::STATUS_REQUIRES_ACTION, $correction->status);
        $this->assertSame($futureBooking->id, $plan->fresh()->next_booking_id);

        $correction = app(BookingCorrectionService::class)->retry($correction, $admin);

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $this->assertSame($futureBooking->id, $plan->fresh()->next_booking_id);
    }

    public function test_captured_visit_cannot_be_reopened(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $this->captureExistingVisit($booking, 120);

        $this->expectException(ValidationException::class);
        app(BookingCorrectionService::class)->apply(
            $ticket,
            $admin,
            [
                'action' => CareBookingCorrection::ACTION_REOPEN,
                'reason' => 'Attempting to reopen a financially settled visit should be blocked.',
            ],
            (string) Str::uuid(),
        );
    }

    public function test_same_client_request_is_idempotent(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $clientRequestId = (string) Str::uuid();
        $changes = $this->correctionChanges(180);

        $first = app(BookingCorrectionService::class)->apply($ticket, $admin, $changes, $clientRequestId);
        $second = app(BookingCorrectionService::class)->apply($ticket->fresh(), $admin, $changes, $clientRequestId);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('care_booking_corrections', 1);
    }

    public function test_non_admin_cannot_apply_correction(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();

        $this->expectException(AuthorizationException::class);
        app(BookingCorrectionService::class)->apply(
            $ticket,
            $caregiver,
            $this->correctionChanges(120),
            (string) Str::uuid(),
        );
    }

    public function test_complete_and_bill_requires_family_approval_confirmation(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $changes = $this->correctionChanges(120);
        $changes['family_approved'] = false;

        try {
            app(BookingCorrectionService::class)->apply(
                $ticket,
                $admin,
                $changes,
                (string) Str::uuid(),
            );
            $this->fail('The correction should require family approval confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('correctionFamilyApproved', $exception->errors());
        }

        $this->assertDatabaseCount('care_booking_corrections', 0);
    }

    public function test_correction_inputs_and_audit_snapshot_are_immutable(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $correction = $this->applyCorrection($ticket, $admin, 120);

        $this->expectException(\LogicException::class);
        $correction->forceFill(['reason' => 'Attempted audit rewrite after the correction was applied.'])->save();
    }

    public function test_failed_payout_reversal_can_resume_without_duplicate_family_refund(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $this->captureExistingVisit($booking, 180);

        $reversalAttempts = 0;
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $stripe->shouldReceive('createTransferReversal')
            ->twice()
            ->andReturnUsing(function (string $transferId, int $amountCents) use (&$reversalAttempts): array {
                if ($reversalAttempts++ === 0) {
                    throw new \App\Exceptions\Payments\PaymentException('Payout reversal is temporarily unavailable.');
                }

                return [
                    'id' => 'trr_correction_retry',
                    'status' => 'succeeded',
                    'amount' => $amountCents,
                ];
            });
        $this->app->instance(StripeClient::class, $stripe);

        $correction = $this->applyCorrection($ticket, $admin, 120);
        $this->assertSame(CareBookingCorrection::STATUS_REQUIRES_ACTION, $correction->status);
        $this->assertCount(1, (array) data_get($correction->provider_payload, 'refunds'));

        $correction = app(BookingCorrectionService::class)->retry($correction, $admin);

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $this->assertCount(1, (array) data_get($correction->provider_payload, 'refunds'));
        $this->assertSame(
            $correction->target_charge_cents,
            (int) $booking->fresh()->payment->amount_captured_cents - (int) $booking->fresh()->payment->amount_refunded_cents,
        );
    }

    public function test_correction_retries_a_failed_initial_caregiver_transfer(): void
    {
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $transferAttempts = 0;
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $stripe->shouldReceive('createTransfer')
            ->twice()
            ->andReturnUsing(function (string $accountId, int $amountCents) use (&$transferAttempts): array {
                if ($transferAttempts++ === 0) {
                    throw new \App\Exceptions\Payments\PaymentException('Caregiver transfer is temporarily unavailable.');
                }

                return ['id' => 'tr_correction_retry', 'status' => 'paid'];
            });
        $this->app->instance(StripeClient::class, $stripe);

        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $correction = $this->applyCorrection($ticket, $admin, 180);

        $this->assertSame(CareBookingCorrection::STATUS_SUCCEEDED, $correction->status);
        $this->assertSame(CareBookingPayment::STATUS_TRANSFERRED, $booking->fresh()->payment->status);
        $this->assertSame('tr_correction_retry', $booking->fresh()->payment->stripe_transfer_id);
    }

    public function test_support_panel_applies_correction_posts_messages_and_resolves_ticket(): void
    {
        Notification::fake();
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        app(BookingPaymentService::class)->authorizeForBooking($booking);

        Livewire::actingAs($admin)
            ->test(SupportTicketShow::class, ['ticket' => $ticket])
            ->assertSee('Visit correction')
            ->assertSee('Family approved this correction')
            ->set('correctionReason', 'Family and caregiver confirmed the corrected three hour visit.')
            ->set('correctionFamilyApproved', true)
            ->set('correctionImpactConfirmed', true)
            ->call('applyVisitCorrection')
            ->assertHasNoErrors()
            ->assertSee('Correction history');

        $ticket->refresh();
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame($admin->id, $ticket->assigned_admin_id);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'kind' => SupportTicketMessage::KIND_INTERNAL_NOTE,
        ]);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
        ]);
        $this->assertDatabaseHas('care_booking_corrections', [
            'care_booking_id' => $booking->id,
            'support_ticket_id' => $ticket->id,
            'status' => CareBookingCorrection::STATUS_SUCCEEDED,
        ]);
    }

    public function test_additional_charge_requiring_card_action_stays_pending_and_notifies_family(): void
    {
        Notification::fake();
        [$admin, $caregiver, $booking, $ticket] = $this->scenario();
        $this->captureExistingVisit($booking, 120);
        $beforeCaptured = (int) $booking->fresh()->payment?->amount_captured_cents;

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('defaultPaymentMethodForCustomer')->once()->andReturn(['id' => 'pm_requires_action']);
        $stripe->shouldReceive('createAndConfirmCharge')->once()->andThrow(new PaymentActionRequiredException(
            'An additional authorization is required to complete final charges.',
            paymentIntentId: 'pi_correction_action',
            clientSecret: 'pi_correction_action_secret',
        ));
        $this->app->instance(StripeClient::class, $stripe);

        $correction = $this->applyCorrection($ticket, $admin, 180);

        $this->assertSame(CareBookingCorrection::STATUS_REQUIRES_ACTION, $correction->status);
        $this->assertSame('pi_correction_action', data_get($correction->provider_payload, 'action_required.payment_intent_id'));
        $this->assertSame($beforeCaptured, (int) $booking->fresh()->payment?->amount_captured_cents);
        Notification::assertSentTo(
            $booking->family,
            fn (MarketplaceEventNotification $notification): bool => data_get($notification->toArray($booking->family), 'payload.care_booking_correction_id') === $correction->id,
        );
    }

    private function applyCorrection(SupportTicket $ticket, User $admin, int $workedMinutes): CareBookingCorrection
    {
        return app(BookingCorrectionService::class)->apply(
            $ticket,
            $admin,
            $this->correctionChanges($workedMinutes),
            (string) Str::uuid(),
        );
    }

    /** @return array<string, mixed> */
    private function correctionChanges(int $workedMinutes): array
    {
        $start = now()->subDay()->setTime(9, 0);

        return [
            'action' => CareBookingCorrection::ACTION_COMPLETE_AND_BILL,
            'started_at' => $start->toDateTimeString(),
            'completed_at' => $start->copy()->addMinutes($workedMinutes)->toDateTimeString(),
            'break_minutes' => 0,
            'reason' => 'Family and caregiver confirmed the corrected visit hours with operations.',
            'family_approved' => true,
        ];
    }

    private function captureExistingVisit(CareBooking $booking, int $workedMinutes): void
    {
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => now()->subDay()->setTime(9, 0),
            'completed_at' => now()->subDay()->setTime(9, 0)->addMinutes($workedMinutes),
            'timesheet_submitted_at' => now()->subDay(),
            'worked_minutes' => $workedMinutes,
            'family_confirmed_at' => now()->subDay(),
        ])->save();
        app(BookingPaymentService::class)->captureForBooking($booking->fresh());
    }

    /** @return array{CarePlan,CareBooking} */
    private function attachRecurringPlan(CareBooking $booking, User $caregiver): array
    {
        $family = $booking->family;
        $futureRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Future generated recurring visit',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addWeek()->setTime(9, 0),
            'requested_end_at' => now()->addWeek()->setTime(12, 0),
            'address_line1' => '123 Test Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $futureApplication = CareRequestApplication::query()->create([
            'care_request_id' => $futureRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        $futureBooking = CareBooking::query()->create([
            'care_request_id' => $futureRequest->id,
            'care_request_application_id' => $futureApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $futureRequest->requested_start_at,
            'scheduled_end_at' => $futureRequest->requested_end_at,
            'expected_minutes' => 180,
        ]);
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $booking->care_request_id,
            'source_care_booking_id' => $booking->id,
            'next_booking_id' => $booking->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Recurring correction test',
            'schedule_days' => [strtolower(now()->addWeek()->format('l'))],
            'schedule_start_time' => '09:00:00',
            'schedule_end_time' => '12:00:00',
            'starts_on' => now()->subMonth()->toDateString(),
            'timezone' => config('app.timezone'),
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
        ]);
        $booking->careRequest()->update(['care_plan_id' => $plan->id]);
        $futureRequest->update(['care_plan_id' => $plan->id]);
        $booking->update([
            'care_plan_id' => $plan->id,
            'occurrence_key' => "regular-care:{$plan->id}:regular:past:09:00",
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => 1,
        ]);
        $futureBooking->update([
            'care_plan_id' => $plan->id,
            'occurrence_key' => "regular-care:{$plan->id}:regular:future:09:00",
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => 1,
        ]);

        return [$plan, $futureBooking->fresh()];
    }

    /** @return array{User,User,CareBooking,SupportTicket} */
    private function scenario(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'platform_hourly_rate' => 30,
            'stripe_connect_account_id' => 'acct_correction_ready',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_connect_onboarding_completed_at' => now(),
        ]);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'One-time visit correction test',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->subDay()->setTime(9, 0),
            'requested_end_at' => now()->subDay()->setTime(12, 0),
            'address_line1' => '123 Test Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at,
            'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 180,
        ]);
        $ticket = SupportTicket::query()->create([
            'opener_user_id' => $caregiver->id,
            'care_request_id' => $request->id,
            'care_booking_id' => $booking->id,
            'subject' => 'Incorrect hours',
            'description' => 'Please correct and bill the approved visit hours.',
            'category' => 'billing',
            'priority' => 'normal',
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return [$admin, $caregiver, $booking, $ticket];
    }
}
