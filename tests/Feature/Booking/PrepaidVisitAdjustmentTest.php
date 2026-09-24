<?php

namespace Tests\Feature\Booking;

use App\Exceptions\Payments\PaymentActionRequiredException;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyAccountMember;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Booking\BookingCorrectionService;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\Booking\PrepaidVisitAdjustmentService;
use App\Services\Booking\PrepaidVisitRecoveryService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Services\Payments\StripeClient;
use App\Support\MarketplacePricing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrepaidVisitAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.bypass', true);
        config()->set('services.stripe.bypass_processing_fee_percent', 2.9);
        config()->set('services.stripe.bypass_processing_fee_fixed_cents', 30);
        config()->set('marketplace.time_corrections.enabled', true);
        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/New_York'));
        Notification::fake();
    }

    public function test_preview_is_read_only_and_uses_the_approved_eastern_times_and_exact_difference(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $before = $booking->payment->fresh()->toArray();
        $operations = $booking->payment->operations()->get()->toArray();
        $this->stripe();
        $preview = $this->preview($admin, $booking, $approved);
        $this->assertSame(1292, $preview['payment_delta_cents']);
        $this->assertSame(38492, $preview['target_charge_cents']);
        $this->assertSame(745, $preview['worked_minutes']);
        $this->assertSame('2026-09-23 19:00:00 EDT', $preview['approved_start_eastern']);
        $this->assertSame('2026-09-24 07:25:00 EDT', $preview['approved_end_eastern']);
        $this->assertNull($preview['caregiver_net_cents']);
        $this->assertFalse($preview['stripe_writes']);
        $this->assertSame($before, $booking->payment->fresh()->toArray());
        $this->assertSame($operations, $booking->payment->operations()->get()->toArray());
        $this->assertDatabaseCount('care_booking_corrections', 1);
        Notification::assertNothingSent();
    }

    public function test_adjustment_preserves_the_original_charge_evidence_and_member_approval_and_never_transfers(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $originalOperations = $booking->payment->operations()->orderBy('id')->get()->toArray();
        $originalTracking = $booking->only(['check_in_source', 'check_out_source', 'check_in_lat', 'check_out_lat', 'timesheet_submitted_at']);
        $preview = $this->preview($admin, $booking, $approved);
        $key = (string) Str::uuid();
        $mock = $this->stripe();
        $mock->shouldReceive('createAndConfirmCharge')->once()->withArgs(function ($actualBooking, $customer, $card, $amount, $currency, $metadata, $idempotency) use ($booking, $approved, $key): bool {
            $reserved = CareBookingCorrection::where('client_request_id', $key)->firstOrFail();
            $this->assertSame('processing', $reserved->status);
            $this->assertSame($reserved->id, data_get($booking->payment->fresh()->metadata, 'prepaid_visit_recovery.adjustment_correction_id'));

            return $actualBooking->id === $booking->id && $amount === 1292 && $currency === 'usd'
                && $metadata['time_correction_id'] === (string) $approved->id && $idempotency === 'prepaid-adjustment:'.$key;
        })->andReturn($this->charge());
        $receipt = $this->apply($admin, $booking, $approved, $preview, $key);
        $this->assertSame('succeeded', $receipt->status, $receipt->last_error ?? '');
        $this->assertSame($receipt->id, $this->apply($admin, $booking, $approved, $preview, $key)->id);
        $payment = $booking->payment->fresh();
        $this->assertTrue($payment->hasPrepaidVisitHold());
        $this->assertSame(38492, (int) $payment->amount_captured_cents);
        $this->assertSame(1292, (int) $payment->amount_overage_cents);
        $this->assertSame(33525, (int) $payment->caregiver_gross_amount_cents);
        $this->assertSame(1176, (int) $payment->stripe_processing_fee_cents);
        $this->assertSame(32349, (int) $payment->caregiver_amount_cents);
        $this->assertSame(32349, data_get($receipt->provider_payload, 'earnings.caregiver_net_cents'));
        $this->assertSame(1058, data_get($receipt->provider_payload, 'earnings.caregiver_delta_cents'));
        $this->assertSame(0, $payment->operations()->where('type', 'transfer')->count());
        $this->assertSame($originalOperations, $payment->operations()->whereIn('id', array_column($originalOperations, 'id'))->orderBy('id')->get()->toArray());
        $this->assertEquals($originalTracking, $booking->fresh()->only(array_keys($originalTracking)));
        $this->assertSame(745, $booking->fresh()->worked_minutes);
        $this->assertSame($approved->approved_by_user_id, $booking->fresh()->family_confirmed_by_user_id);
        $this->assertNotEquals($booking->family_user_id, $booking->fresh()->family_confirmed_by_user_id);
        $this->assertTrue($approved->approved_at->eq($booking->fresh()->family_confirmed_at));
        $this->assertSame('applied', $approved->fresh()->status);
        $this->assertSame('open', $approved->supportTicket->fresh()->status);
        $this->assertDatabaseCount('support_ticket_messages', 0);
        Notification::assertNothingSent();
        app(BookingPaymentService::class)->retryTransfer($payment);
        app(BookingPaymentV2Service::class)->finalizeFeesAndTransfers($payment);
        $this->assertSame(0, $payment->operations()->where('type', 'transfer')->count());
        $this->expectException(ValidationException::class);
        app(BookingCorrectionService::class)->preview($booking->fresh(), ['action' => 'complete_and_bill']);
    }

    public function test_explicit_separate_release_accepts_the_adjusted_bill_and_only_then_enables_transfers(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $receipt = $this->apply($admin, $booking, $approved, $this->preview($admin, $booking, $approved));
        $this->assertTrue($receipt->succeeded(), $receipt->last_error ?? '');
        $recovery = app(PrepaidVisitRecoveryService::class);
        $preview = $recovery->preview($booking->id, $approved->support_ticket_id, $admin, true);
        $this->assertSame(38492, $preview['captured_cents_retained']);
        $this->assertSame(32349, $preview['caregiver_net_cents']);
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        $this->assertSame(0, $booking->payment->operations()->where('type', 'transfer')->count());
        $recovery->apply($booking->id, $approved->support_ticket_id, $admin, 'Separately approved release after Stripe and payout-readiness review.',
            (string) Str::uuid(), $preview['expected_state'], false, true, true);
        $this->assertFalse($booking->payment->fresh()->hasPrepaidVisitHold());
        $booking->caregiver->caregiverProfile->update(['stripe_connect_account_id' => 'acct_ready', 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_connect_onboarding_completed_at' => now()]);
        app(BookingPaymentService::class)->retryTransfer($booking->payment->fresh());
        app(BookingPaymentService::class)->retryTransfer($booking->payment->fresh());
        $this->assertSame(2, $booking->payment->operations()->where('type', 'charge')->count());
        $this->assertSame(32349, (int) $booking->payment->operations()->where('type', 'transfer')->where('status', 'succeeded')->sum('amount_cents'));
    }

    public function test_unknown_charge_outcome_is_durably_reserved_and_cannot_be_recharged_with_any_uuid(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $preview = $this->preview($admin, $booking, $approved);
        $key = (string) Str::uuid();
        $this->stripe()->shouldReceive('createAndConfirmCharge')->once()->andThrow(new \RuntimeException('Network response lost after provider accepted request'));
        $receipt = $this->apply($admin, $booking, $approved, $preview, $key);
        $this->assertSame('failed', $receipt->status);
        $this->travel(3)->days(); // Stripe may have discarded its own idempotency key; our reservation still prevents dispatch.
        $this->assertSame($receipt->id, $this->apply($admin, $booking, $approved, $preview, $key)->id);
        try {
            app(PrepaidVisitAdjustmentService::class)->reconcile($booking->id, $approved->support_ticket_id, $approved->id, $admin, $key, true);
            $this->fail('Unknown provider outcome must require review.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('uncertain', $e->getMessage());
        }
        $this->assertSame(1, $booking->payment->operations()->where('type', 'charge')->count());
        $this->assertNull($booking->fresh()->family_confirmed_at);
        $this->expectException(ValidationException::class);
        $this->apply($admin, $booking, $approved, $preview);
    }

    #[DataProvider('knownPendingOutcomes')]
    public function test_known_charge_is_reconciled_without_recharging_or_releasing(bool $needsAuthentication): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $preview = $this->preview($admin, $booking, $approved);
        $key = (string) Str::uuid();
        $mock = $this->stripe();
        $create = $mock->shouldReceive('createAndConfirmCharge')->once();
        if ($needsAuthentication) {
            $create->andThrow(new PaymentActionRequiredException('Card needs confirmation.', paymentIntentId: 'pi_adjustment', clientSecret: 'secret_not_to_store'));
        } else {
            $create->andReturn(array_merge($this->charge(), ['fee_finalized' => false, 'processing_fee_cents' => null]));
        }
        $receipt = $this->apply($admin, $booking, $approved, $preview, $key);
        $this->assertFalse($receipt->succeeded());
        $this->assertStringNotContainsString('secret_not_to_store', json_encode($receipt->toArray()));
        $this->assertSame($receipt->id, $this->apply($admin, $booking, $approved, $preview, $key)->id);
        // Webhooks cannot classify this adjustment as the primary payment or bypass the operator's reconciliation.
        $before = $booking->payment->fresh()->toArray();
        app(BookingPaymentV2Service::class)->recordSucceededPaymentIntent($booking->payment->fresh(), $this->charge());
        foreach (['requires_action', 'canceled', 'succeeded'] as $status) {
            app(BookingPaymentService::class)->handlePaymentIntentWebhook([
                'id' => 'pi_adjustment', 'status' => $status, 'amount' => 1292,
                'metadata' => ['prepaid_adjustment_request_id' => $key],
            ]);
        }
        $this->assertSame($before, $booking->payment->fresh()->toArray());
        $mock->shouldReceive('retrievePaymentIntentFinancials')->once()->with('pi_adjustment', 1292)->andReturn($this->charge());
        $receipt = app(PrepaidVisitAdjustmentService::class)->reconcile($booking->id, $approved->support_ticket_id, $approved->id, $admin, $key, true);
        $this->assertTrue($receipt->succeeded(), $receipt->last_error ?? '');
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        $this->assertSame(2, $booking->payment->operations()->where('type', 'charge')->count());
        $this->assertSame(0, $booking->payment->operations()->where('type', 'transfer')->count());
        Notification::assertNothingSent();
    }

    public static function knownPendingOutcomes(): array
    {
        return ['fees pending' => [false], 'card confirmation needed' => [true]];
    }

    #[DataProvider('unsafeChanges')]
    public function test_changed_evidence_blocks_apply_before_any_provider_call(string $change): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $preview = $this->preview($admin, $booking, $approved);
        $this->stripe();
        match ($change) {
            'fee' => $booking->payment->operations()->where('type', 'processing_fee')->update(['amount_cents' => 1110]),
            'membership' => FamilyAccountMember::where('user_id', $approved->approved_by_user_id)->update(['status' => 'removed']),
            'price' => $booking->update(['family_care_rate_cents' => 3100]),
            'approval' => $approved->update(['status' => 'withdrawn']),
            'ticket' => $approved->supportTicket->update(['status' => 'closed']),
            'tracking' => $booking->update(['check_in_note' => 'New evidence after preview']),
            'transfer' => $booking->payment->update(['stripe_transfer_id' => 'tr_external']),
            'refund' => $booking->payment->update(['amount_refunded_cents' => 100]),
            'dispute' => $booking->update(['dispute_opened_at' => now()]),
        };
        try {
            $this->apply($admin, $booking, $approved, $preview);
            $this->fail('Changed evidence must block application.');
        } catch (ValidationException) {
            $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
            $this->assertDatabaseCount('care_booking_corrections', 1);
            $this->assertSame(1, $booking->payment->operations()->where('type', 'charge')->count());
        }
    }

    public static function unsafeChanges(): array
    {
        return array_map(fn ($value) => [$value], ['fee', 'membership', 'price', 'approval', 'ticket', 'tracking', 'transfer', 'refund', 'dispute']);
    }

    public function test_wrong_amount_and_missing_provider_attestation_are_rejected_without_reservation(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $preview = $this->preview($admin, $booking, $approved);
        $this->stripe();
        foreach ([[1293, true], [1292, false]] as [$amount, $verified]) {
            try {
                app(PrepaidVisitAdjustmentService::class)->apply($booking->id, $approved->support_ticket_id, $approved->id, $admin,
                    (string) Str::uuid(), $preview['expected_state'], $amount, 'Family approved the exact additional hours.', $verified);
                $this->fail('Explicit amount and attestation are required.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('care_booking_corrections', 1);
            }
        }
    }

    public function test_unexpected_provider_amount_is_not_applied_or_recharged(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $preview = $this->preview($admin, $booking, $approved);
        $key = (string) Str::uuid();
        $this->stripe()->shouldReceive('createAndConfirmCharge')->once()->andReturn(array_merge($this->charge(), ['amount_received' => 1293]));
        $receipt = $this->apply($admin, $booking, $approved, $preview, $key);
        $this->assertSame('failed', $receipt->status);
        $this->assertSame('pi_adjustment', data_get($receipt->provider_payload, 'charge.id'));
        $this->assertSame(744, $booking->fresh()->worked_minutes);
        $this->assertSame(37200, (int) $booking->payment->fresh()->amount_captured_cents);
        $this->assertSame($receipt->id, $this->apply($admin, $booking, $approved, $preview, $key)->id);
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
    }

    public function test_drift_after_a_known_charge_blocks_reconciliation_and_release(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $preview = $this->preview($admin, $booking, $approved);
        $key = (string) Str::uuid();
        $this->stripe()->shouldReceive('createAndConfirmCharge')->once()->andReturn(array_merge($this->charge(), ['fee_finalized' => false]));
        $this->apply($admin, $booking, $approved, $preview, $key);
        $booking->payment->update(['amount_refunded_cents' => 100]);
        try {
            app(PrepaidVisitAdjustmentService::class)->reconcile($booking->id, $approved->support_ticket_id, $approved->id, $admin, $key, true);
            $this->fail('Unexpected financial changes cannot be folded into the approved adjustment.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Records changed', $e->getMessage());
        }
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        $this->expectException(ValidationException::class);
        app(PrepaidVisitRecoveryService::class)->preview($booking->id, $approved->support_ticket_id, $admin, true);
    }

    public function test_release_preview_drift_after_adjustment_keeps_the_hold(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $receipt = $this->apply($admin, $booking, $approved, $this->preview($admin, $booking, $approved));
        $this->assertTrue($receipt->succeeded());
        $recovery = app(PrepaidVisitRecoveryService::class);
        $preview = $recovery->preview($booking->id, $approved->support_ticket_id, $admin, true);
        $approved->fresh()->update(['family_response_note' => 'Additional evidence after the release preview.']);
        $this->stripe();
        try {
            $recovery->apply($booking->id, $approved->support_ticket_id, $admin, 'Approved separately after provider review.',
                (string) Str::uuid(), $preview['expected_state'], false, true, true);
            $this->fail('A release requires the current preview.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Records changed after preview', $e->getMessage());
            $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        }
    }

    public function test_same_uuid_cannot_be_claimed_by_another_admin_or_preview(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $preview = $this->preview($admin, $booking, $approved);
        $key = (string) Str::uuid();
        $this->apply($admin, $booking, $approved, $preview, $key);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->stripe();
        foreach ([[$otherAdmin, $preview], [$admin, array_merge($preview, ['expected_state' => str_repeat('0', 64)])]] as [$actor, $otherPreview]) {
            try {
                $this->apply($actor, $booking, $approved, $otherPreview, $key);
                $this->fail('Receipts cannot be reused for a different approval.');
            } catch (ValidationException) {
                $this->assertSame(2, $booking->payment->operations()->where('type', 'charge')->count());
            }
        }
    }

    public function test_non_admin_cannot_preview_or_apply(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $this->stripe();
        $this->expectException(AuthorizationException::class);
        $this->preview($booking->family, $booking, $approved);
    }

    public function test_ordinary_retry_and_replay_cannot_take_over_a_prepaid_receipt(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $receipt = $this->apply($admin, $booking, $approved, $this->preview($admin, $booking, $approved));
        $this->assertTrue($receipt->succeeded());
        $this->stripe();
        $ordinary = app(BookingCorrectionService::class);
        foreach (['succeeded', 'failed', 'requires_action'] as $status) {
            $receipt->update(['status' => $status]);
            foreach ([
                fn () => $ordinary->retry($receipt->fresh(), $admin),
                fn () => $ordinary->apply($approved->supportTicket, $admin, [], $receipt->client_request_id),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('A dedicated recovery receipt must not enter the ordinary correction flow.');
                } catch (ValidationException $e) {
                    $this->assertStringContainsString('dedicated review command', $e->getMessage());
                    $this->assertSame($status, $receipt->fresh()->status);
                }
            }
        }
        $this->assertTrue($booking->payment->fresh()->hasPrepaidVisitHold());
        $this->assertDatabaseCount('support_ticket_messages', 0);
        Notification::assertNothingSent();
    }

    public function test_command_defaults_to_read_only_and_requires_explicit_apply_arguments(): void
    {
        [$admin, $booking, $approved] = $this->scenario();
        $this->stripe();
        $args = ['booking' => $booking->id, '--ticket' => $approved->support_ticket_id, '--time-correction' => $approved->id, '--admin' => $admin->id];
        $this->artisan('homecare:adjust-prepaid-visit', $args)->expectsOutputToContain('READ-ONLY PREVIEW')->assertSuccessful();
        $this->artisan('homecare:adjust-prepaid-visit', $args + ['--apply' => true])->assertFailed();
        $this->artisan('homecare:adjust-prepaid-visit', $args + ['--reconcile' => true])->assertFailed();
        $this->assertDatabaseCount('care_booking_corrections', 1);
    }

    private function preview(User $admin, CareBooking $booking, CareBookingTimeCorrection $approved): array
    {
        return app(PrepaidVisitAdjustmentService::class)->preview($booking->id, $approved->support_ticket_id, $approved->id, $admin);
    }

    private function apply(User $admin, CareBooking $booking, CareBookingTimeCorrection $approved, array $preview, ?string $key = null): CareBookingCorrection
    {
        return app(PrepaidVisitAdjustmentService::class)->apply($booking->id, $approved->support_ticket_id, $approved->id, $admin,
            $key ?: (string) Str::uuid(), $preview['expected_state'], 1292, 'Apply the family-approved extra hours while retaining the payout hold.', true);
    }

    private function stripe(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(StripeClient::class);
        $this->app->instance(StripeClient::class, $mock);

        return $mock;
    }

    private function charge(): array
    {
        return ['id' => 'pi_adjustment', 'status' => 'succeeded', 'amount_received' => 1292,
            'latest_charge_id' => 'ch_adjustment', 'balance_transaction_id' => 'txn_adjustment',
            'processing_fee_cents' => 67, 'fee_finalized' => true];
    }

    private function scenario(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active', 'platform_hourly_rate' => 27]);
        $request = CareRequest::create([
            'family_user_id' => $family->id, 'title' => 'Overnight held adjustment', 'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME, 'requested_start_at' => now()->setTime(19, 0),
            'requested_end_at' => now()->addDay()->setTime(7, 0), 'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => 27]);
        $booking = CareBooking::create(array_merge(app(MarketplacePricing::class)->currentSnapshotAttributes(), [
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            'started_at' => now()->subDay()->setTime(19, 0), 'completed_at' => now()->setTime(7, 0),
            'timesheet_submitted_at' => now()->setTime(7, 0), 'expected_minutes' => 720, 'worked_minutes' => 720,
        ]));
        app(BookingPaymentService::class)->authorizeForBooking($booking);
        app(BookingPaymentService::class)->captureForBooking($booking->fresh());
        $payment = $booking->payment()->firstOrFail();
        $payment->operations()->where('type', 'authorization')->update(['status' => 'pending', 'amount_cents' => 0, 'processed_at' => null]);
        $ticket = SupportTicket::create(['opener_user_id' => $caregiver->id, 'origin_path' => '/care-requests/'.$request->id.'/apply',
            'subject' => 'Accidental completion', 'description' => 'No care yet.', 'category' => 'general', 'priority' => 'normal', 'status' => 'open']);
        $recovery = app(PrepaidVisitRecoveryService::class);
        $preview = $recovery->preview($booking->id, $ticket->id, $admin);
        $recovery->apply($booking->id, $ticket->id, $admin, 'No care occurred yet; retain original money and hold it.', (string) Str::uuid(), $preview['expected_state'], true, true);
        $this->travelTo(Carbon::parse('2026-09-24 10:49:04', 'America/New_York'));
        $booking->refresh()->update([
            'status' => CareBooking::STATUS_COMPLETED, 'started_at' => $booking->scheduled_start_at->copy()->addSeconds(54),
            'completed_at' => $booking->scheduled_end_at->copy()->addMinutes(25)->addSeconds(33),
            'timesheet_submitted_at' => $booking->scheduled_end_at->copy()->addMinutes(25)->addSeconds(33),
            'worked_minutes' => 744, 'check_in_source' => 'manual', 'check_out_source' => 'manual',
        ]);
        $member = User::factory()->create(['role' => 'family']);
        FamilyAccountMember::create(['family_account_id' => $booking->family_account_id, 'user_id' => $member->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now()->subDays(2)]);
        $corrections = app(CareBookingTimeCorrectionService::class);
        $approved = $corrections->submit($booking->fresh(), $caregiver, [
            'started_at' => '2026-09-23T19:00', 'completed_at' => '2026-09-24T07:25', 'break_minutes' => 0,
            'reason_code' => 'other', 'explanation' => 'My coverage did not come on time.', 'confirmed' => true,
        ], (string) Str::uuid());
        $approved = $corrections->approve($approved, $member);
        Notification::fake();

        return [$admin, $booking->fresh(['payment', 'family', 'caregiver']), $approved->fresh()];
    }
}
