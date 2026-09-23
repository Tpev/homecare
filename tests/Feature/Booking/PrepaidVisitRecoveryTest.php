<?php

namespace Tests\Feature\Booking;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPaymentOperation;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Booking\PrepaidVisitRecoveryService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\StripeClient;
use App\Services\RegularCare\CareBookingCheckInPolicy;
use App\Support\MarketplacePricing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PrepaidVisitRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.bypass', true);
        config()->set('services.stripe.bypass_processing_fee_percent', 2.9);
        config()->set('services.stripe.bypass_processing_fee_fixed_cents', 30);
        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/New_York'));
        Notification::fake();
    }

    public function test_preview_and_reset_preserve_money_schedule_and_audit_without_stripe_or_messages(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $original = $booking->getAttributes();
        $paymentBefore = $booking->payment->getAttributes();
        $ledgerBefore = $booking->payment->operations()->orderBy('id')->get()->toArray();
        $this->forbidStripe();
        Notification::fake();
        $recovery = app(PrepaidVisitRecoveryService::class);
        $preview = $recovery->preview($booking->id, $ticket->id, $admin);
        $this->assertSame(37200, $preview['captured_cents_retained']);
        $this->assertStringContainsString('19:00:00 EDT', $preview['scheduled_start_eastern']);
        $this->assertDatabaseCount('care_booking_corrections', 0);
        $this->assertFalse($booking->payment->fresh()->hasPrepaidVisitHold());

        $requestId = (string) Str::uuid();
        $receipt = $recovery->apply($booking->id, $ticket->id, $admin, 'Caregiver confirmed no work occurred for this future visit.',
            $requestId, $preview['expected_state'], true, true);
        $booking->refresh();
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking->status);
        foreach (['started_at', 'completed_at', 'timesheet_submitted_at', 'worked_minutes', 'family_confirmed_at', 'family_confirmed_by_user_id', 'check_in_source', 'check_out_source'] as $field) {
            $this->assertNull($booking->$field, $field);
        }
        foreach (['scheduled_start_at', 'scheduled_end_at', 'caregiver_user_id', 'family_user_id', 'expected_minutes', 'care_request_id', 'care_request_application_id', 'agreement_snapshot', 'family_terms_accepted_at', 'caregiver_terms_accepted_at'] as $field) {
            $this->assertSame($original[$field], $booking->getRawOriginal($field), $field);
        }
        $paymentAfter = $booking->payment->getAttributes();
        unset($paymentBefore['metadata'], $paymentBefore['updated_at'], $paymentAfter['metadata'], $paymentAfter['updated_at']);
        $this->assertSame($paymentBefore, $paymentAfter);
        $this->assertSame($ledgerBefore, $booking->payment->operations()->orderBy('id')->get()->toArray());
        $this->assertTrue($booking->payment->hasPrepaidVisitHold());
        $this->assertSame($original, $receipt->before_snapshot['booking']);
        $this->assertSame($booking->id, $ticket->fresh()->care_booking_id);
        $this->assertSame(SupportTicket::STATUS_IN_PROGRESS, $ticket->fresh()->status);
        $this->assertDatabaseCount('support_ticket_messages', 0);
        Notification::assertNothingSent();
        $this->assertSame($receipt->id, $recovery->apply($booking->id, $ticket->id, $admin,
            'Caregiver confirmed no work occurred for this future visit.', $requestId, $preview['expected_state'], true, true)->id);
        $this->assertDatabaseCount('care_booking_corrections', 1);
        $this->assertSame(1, CareBookingEvent::where('event_type', 'prepaid_visit_reopened_on_hold')->count());
    }

    public function test_stale_preview_does_not_apply(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $preview = app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin);
        $booking->update(['check_out_note' => 'New evidence arrived after preview.']);
        $this->expectException(ValidationException::class);
        try {
            app(PrepaidVisitRecoveryService::class)->apply($booking->id, $ticket->id, $admin,
                'No actual care was delivered.', (string) Str::uuid(), $preview['expected_state'], true, true);
        } finally {
            $this->assertDatabaseCount('care_booking_corrections', 0);
            $this->assertFalse($booking->payment->fresh()->hasPrepaidVisitHold());
            $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->fresh()->status);
        }
    }

    public function test_paid_or_pending_transfer_and_refund_states_are_rejected(): void
    {
        foreach ([CareBookingPaymentOperation::TYPE_TRANSFER, CareBookingPaymentOperation::TYPE_REFUND, CareBookingPaymentOperation::TYPE_DISPUTE] as $type) {
            [$admin, $booking, $ticket] = $this->scenario();
            $booking->payment->operations()->create([
                'care_booking_id' => $booking->id, 'financial_reference' => $booking->financial_reference,
                'type' => $type, 'status' => CareBookingPaymentOperation::STATUS_PENDING,
                'amount_cents' => 100, 'currency' => 'usd', 'idempotency_key' => (string) Str::uuid(),
            ]);
            try {
                app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin);
                $this->fail('Unsafe financial state must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('operation', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('care_booking_corrections', 0);
    }

    public function test_wrong_ticket_or_non_admin_cannot_reset(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        try {
            app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $booking->caregiver);
            $this->fail('Caregiver cannot perform admin recovery.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('care_booking_corrections', 0);
        }
        $ticket->update(['opener_user_id' => $admin->id]);
        $this->expectException(ValidationException::class);
        app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin);
    }

    public function test_confirmations_are_required_even_with_a_valid_preview(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $preview = app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin);
        foreach ([[false, true], [true, false]] as [$noCare, $provider]) {
            try {
                app(PrepaidVisitRecoveryService::class)->apply($booking->id, $ticket->id, $admin,
                    'No actual care was delivered.', (string) Str::uuid(), $preview['expected_state'], $noCare, $provider);
                $this->fail('Missing confirmation must fail.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('care_booking_corrections', 0);
            }
        }
    }

    public function test_held_payment_blocks_stale_worker_manual_and_scheduled_transfer_paths(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $stalePayment = $booking->payment;
        $this->reopen($admin, $booking, $ticket);
        $booking->caregiver->caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_now_ready', 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now(),
        ]);
        $this->forbidStripe();
        $this->assertTrue(app(BookingPaymentV2Service::class)->finalizeFeesAndTransfers($stalePayment)->hasPrepaidVisitHold());
        $this->assertTrue(app(BookingPaymentService::class)->retryTransfer($stalePayment)->hasPrepaidVisitHold());
        $this->artisan('homecare:retry-payout-transfers')->assertSuccessful();
        $this->artisan('homecare:reconcile-payment-ledger-v2')->assertSuccessful();
        $this->assertSame(0, $stalePayment->operations()->where('type', 'transfer')->count());
        $this->assertSame(37200, (int) $stalePayment->fresh()->amount_captured_cents);
    }

    public function test_hold_blocks_capture_refund_adjustment_and_ordinary_admin_correction(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $this->reopen($admin, $booking, $ticket);
        $this->forbidStripe();
        $booking->refresh();
        $calls = [
            fn () => app(BookingPaymentService::class)->captureForBooking($booking),
            fn () => app(BookingPaymentV2Service::class)->capture($booking, $booking->payment),
            fn () => app(BookingPaymentV2Service::class)->refund($booking, $booking->payment, 100, 'requested_by_customer'),
            fn () => app(BookingPaymentV2Service::class)->chargeAdjustment($booking, $booking->payment, 100, 'unexpected'),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('Held money must not move.');
            } catch (PaymentException) {
                $this->assertSame(37200, (int) $booking->payment->fresh()->amount_captured_cents);
                $this->assertSame(0, (int) $booking->payment->fresh()->amount_refunded_cents);
            }
        }
        $this->expectException(ValidationException::class);
        app(BookingCorrectionService::class)->preview($booking->fresh(), ['action' => 'complete_and_bill']);
    }

    public function test_actual_visit_can_start_finish_and_receive_family_approval_without_moving_money(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $this->reopen($admin, $booking, $ticket);
        $this->assertSame('prepaid_visit_too_early', app(CareBookingCheckInPolicy::class)->evaluate($booking->fresh())['code']);
        $this->forbidStripe();
        $this->travelTo($booking->scheduled_start_at);
        Livewire::actingAs($booking->caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $booking->care_request_id])->call('startBooking')->assertHasNoErrors();
        $this->assertSame(CareBooking::STATUS_IN_PROGRESS, $booking->fresh()->status);
        $this->travelTo($booking->scheduled_end_at);
        Livewire::actingAs($booking->caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $booking->care_request_id])->call('completeBooking')->assertHasNoErrors();
        $this->assertSame(720, (int) $booking->fresh()->worked_minutes);
        Livewire::actingAs($booking->family)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])->call('completeBooking')->assertHasNoErrors();
        $this->assertSame($booking->family_user_id, $booking->fresh()->family_confirmed_by_user_id);
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        $this->assertSame(1, $booking->payment->operations()->where('type', 'charge')->count());
    }

    public function test_auto_approval_does_not_approve_or_release_a_held_visit(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $this->reopen($admin, $booking, $ticket);
        $this->completeActualVisit($booking, false);
        $this->travel(25)->hours();
        $this->forbidStripe();
        $this->artisan('homecare:auto-approve-timesheets')->assertSuccessful();
        $this->assertNull($booking->fresh()->family_confirmed_at);
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
    }

    public function test_release_requires_explicit_actual_family_approval_and_an_unchanged_bill(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $this->reopen($admin, $booking, $ticket);
        $this->completeActualVisit($booking, false);
        try {
            app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin, true);
            $this->fail('Family approval must be recorded first.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('family', $exception->getMessage());
        }
        $booking->update(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => $booking->family_user_id,
            'worked_minutes' => 660, 'total_paused_seconds' => 3600]);
        $this->expectException(ValidationException::class);
        try {
            app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin, true);
        } finally {
            $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        }
    }

    public function test_reviewed_release_makes_one_transfer_eligible_without_a_second_charge(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $this->reopen($admin, $booking, $ticket);
        $this->completeActualVisit($booking, true);
        $service = app(PrepaidVisitRecoveryService::class);
        $preview = $service->preview($booking->id, $ticket->id, $admin, true);
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        $requestId = (string) Str::uuid();
        $receipt = $service->apply($booking->id, $ticket->id, $admin, 'Family approved actual hours; the retained payment matches exactly.',
            $requestId, $preview['expected_state'], false, true, true);
        $this->assertFalse($booking->payment->fresh()->hasPrepaidVisitHold());
        $this->assertSame(0, $booking->payment->operations()->where('type', 'transfer')->count());
        $booking->caregiver->caregiverProfile->update([
            'stripe_connect_account_id' => 'acct_ready_after_real_visit', 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now(),
        ]);
        app(BookingPaymentService::class)->retryTransfer($booking->payment->fresh());
        app(BookingPaymentV2Service::class)->finalizeFeesAndTransfers($booking->payment->fresh());
        $this->assertSame(1, $booking->payment->operations()->where('type', 'charge')->count());
        $this->assertSame(1, $booking->payment->operations()->where('type', 'transfer')->count());
        $this->assertSame(37200, (int) $booking->payment->fresh()->amount_captured_cents);
        $this->assertSame($receipt->id, $service->apply($booking->id, $ticket->id, $admin,
            'Family approved actual hours; the retained payment matches exactly.', $requestId, $preview['expected_state'], false, true, true)->id);
    }

    public function test_command_defaults_to_read_only_and_needs_all_apply_arguments(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $args = ['booking' => $booking->id, '--ticket' => $ticket->id, '--admin' => $admin->id];
        $this->artisan('homecare:recover-prepaid-visit', $args)->expectsOutputToContain('READ-ONLY PREVIEW')->assertSuccessful();
        $this->artisan('homecare:recover-prepaid-visit', $args + ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('care_booking_corrections', 0);
        $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->fresh()->status);
    }

    public function test_changed_financial_evidence_keeps_the_hold_even_after_family_approval(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $this->reopen($admin, $booking, $ticket);
        $this->completeActualVisit($booking, true);
        $booking->payment->operations()->where('type', 'processing_fee')->update(['amount_cents' => 1110]);
        $this->expectException(ValidationException::class);
        try {
            app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin, true);
        } finally {
            $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        }
    }

    public function test_reset_refuses_a_visit_that_has_reached_its_scheduled_start(): void
    {
        [$admin, $booking, $ticket] = $this->scenario();
        $this->travelTo($booking->scheduled_start_at);
        $this->expectException(ValidationException::class);
        app(PrepaidVisitRecoveryService::class)->preview($booking->id, $ticket->id, $admin);
    }

    private function reopen(User $admin, CareBooking $booking, SupportTicket $ticket): CareBookingCorrection
    {
        $service = app(PrepaidVisitRecoveryService::class);
        $preview = $service->preview($booking->id, $ticket->id, $admin);

        return $service->apply($booking->id, $ticket->id, $admin, 'Caregiver confirmed no care occurred for the future visit.',
            (string) Str::uuid(), $preview['expected_state'], true, true);
    }

    private function completeActualVisit(CareBooking $booking, bool $approved): void
    {
        $booking->refresh();
        $this->travelTo($booking->scheduled_end_at->copy()->addMinutes(5));
        $booking->update([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => $booking->scheduled_start_at,
            'completed_at' => $booking->scheduled_end_at,
            'timesheet_submitted_at' => $booking->scheduled_end_at,
            'worked_minutes' => 720,
            'family_confirmed_at' => $approved ? now() : null,
            'family_confirmed_by_user_id' => $approved ? $booking->family_user_id : null,
        ]);
    }

    private function forbidStripe(): void
    {
        $this->app->instance(StripeClient::class, Mockery::mock(StripeClient::class));
    }

    private function scenario(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 27]);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id, 'title' => 'Overnight prepaid recovery test',
            'status' => CareRequest::STATUS_FILLED, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->setTime(19, 0), 'requested_end_at' => now()->addDay()->setTime(7, 0),
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 27,
        ]);
        $booking = CareBooking::query()->create(array_merge(app(MarketplacePricing::class)->currentSnapshotAttributes(), [
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            'started_at' => now()->subDay()->setTime(19, 0), 'completed_at' => now()->setTime(7, 0),
            'timesheet_submitted_at' => now()->setTime(7, 0), 'family_confirmed_at' => now()->setTime(8, 0),
            'family_confirmed_by_user_id' => $family->id, 'expected_minutes' => 720, 'worked_minutes' => 720,
            'caregiver_terms_accepted_at' => now()->subDays(2), 'family_terms_accepted_at' => now()->subDays(2),
            'agreement_snapshot' => ['preserve' => 'agreed terms'], 'check_in_source' => 'manual', 'check_out_source' => 'manual',
        ]));
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        app(BookingPaymentService::class)->captureForBooking($booking->fresh());
        $ticket = SupportTicket::query()->create([
            'opener_user_id' => $caregiver->id, 'origin_path' => '/care-requests/'.$request->id.'/apply',
            'subject' => 'Accidental start', 'description' => 'No care was delivered for this visit.',
            'category' => 'general', 'priority' => 'normal', 'status' => SupportTicket::STATUS_IN_PROGRESS,
        ]);

        return [$admin, $booking->fresh(['payment', 'caregiver', 'family']), $ticket];
    }
}
