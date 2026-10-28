<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Admin\CareRequestShow;
use App\Livewire\Admin\CareRequestsIndex;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use App\Services\CareHours\PrepaidTransferPlanner;
use App\Services\Payments\StripeClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrepaidSettlementVisitStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_dispute_stops_scheduled_reconciliation_and_resolution_pays_once(): void
    {
        [$booking, $account, $owner, $settlement] = $this->pendingVisit();
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
            ->set('disputeReason', 'The recorded visit did not match the care that was delivered.')
            ->call('openDispute')->assertHasNoErrors();
        $this->assertSame(CareBooking::STATUS_DISPUTED, $booking->fresh()->status);
        $this->readyCaregiver($booking);
        $balance = $account->balance();

        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->assertHeld($settlement);
        $this->assertSame($balance, $account->balance());

        $booking->update(['status' => CareBooking::STATUS_REVIEWED, 'dispute_status' => 'resolved']);
        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame(5400, $settlement->fresh()->paid_cents);
        $this->assertSame(1, $settlement->operations()->where('kind', 'transfer')->count());
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public static function adminScreens(): array
    {
        return ['detail' => ['detail'], 'index' => ['index']];
    }

    #[DataProvider('adminScreens')]
    public function test_admin_cancellation_or_reopened_visit_cannot_be_paid_by_the_scheduler(string $screen): void
    {
        foreach ([CareBooking::STATUS_CANCELLED, CareBooking::STATUS_IN_PROGRESS] as $status) {
            [$booking, $account, , $settlement] = $this->pendingVisit();
            $admin = User::factory()->create(['role' => 'admin']);
            $this->adminStatus($booking, $admin, $status, $screen);
            $this->readyCaregiver($booking);
            $balance = $account->balance();

            $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
            $this->assertHeld($settlement);
            $this->assertSame($balance, $account->balance());

            if ($status === CareBooking::STATUS_IN_PROGRESS) {
                $this->adminStatus($booking, $admin, CareBooking::STATUS_COMPLETED, $screen);
                $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
                $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
                $this->assertSame(5400, $settlement->fresh()->paid_cents);
                $this->assertSame(1, $settlement->operations()->where('kind', 'transfer')->count());
            }
        }
    }

    public function test_direct_planner_rechecks_stale_visit_and_released_funding(): void
    {
        foreach (['dispute', 'released'] as $reason) {
            [$booking, , , $settlement] = $this->pendingVisit();
            $this->readyCaregiver($booking);
            $stale = $settlement->fresh()->load('booking.caregiver.caregiverProfile', 'funding');
            if ($reason === 'dispute') {
                $booking->update(['dispute_status' => 'open']);
            } else {
                $settlement->funding->update(['status' => 'released']);
            }
            app(PrepaidTransferPlanner::class)->pay($stale);
            $this->assertHeld($settlement);
        }
    }

    public function test_held_visit_does_not_capture_or_refund_a_shortfall(): void
    {
        foreach (['authorized', 'captured'] as $status) {
            [$booking, , , $settlement] = $this->pendingVisit();
            $settlement->update(['card_minutes' => 60]);
            $payment = CareHourPaymentOperation::create([
                'public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
                'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id,
                'kind' => 'shortfall', 'status' => $status, 'amount_cents' => 6200, 'currency' => 'usd',
                'stripe_payment_intent_id' => 'pi_state_'.$booking->id, 'captured_cents' => $status === 'captured' ? 6200 : 0,
            ]);
            $booking->update(['status' => CareBooking::STATUS_DISPUTED, 'dispute_status' => 'open']);
            $this->mock(StripeClient::class); // Any provider call fails this regression.
            app(PrepaidBookingSettlementService::class)->reconcile($settlement);
            $this->assertHeld($settlement);
            $this->assertSame($status, $payment->fresh()->status);
            $this->assertSame(0, $payment->fresh()->refunded_cents);
            $this->assertSame(1, $settlement->funding->operations()->count());
        }
    }

    public function test_admin_cancellation_still_refunds_collected_unused_cash_once_while_payout_stays_held(): void
    {
        [$booking, , , $settlement] = $this->pendingVisit();
        $payment = CareHourPaymentOperation::create([
            'public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
            'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id,
            'kind' => 'shortfall', 'status' => 'captured', 'amount_cents' => 3100, 'captured_cents' => 3100,
            'currency' => 'usd', 'stripe_payment_intent_id' => 'pi_cancelled_visit', 'stripe_charge_id' => 'ch_cancelled_visit',
        ]);
        $this->mock(StripeClient::class);
        $gateway = $this->mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('createShortfallRefund')->once()->andReturnUsing(fn ($operation) => [
            'id' => 're_cancelled_visit', 'status' => 'succeeded', 'amount' => 3100, 'currency' => 'usd',
            'payment_intent' => 'pi_cancelled_visit', 'charge' => 'ch_cancelled_visit', 'livemode' => false,
            'metadata' => ['care_hour_operation_id' => $operation->public_id, 'care_hour_domain' => 'shortfall'],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->adminStatus($booking, $admin, CareBooking::STATUS_CANCELLED, 'detail');

        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();
        $this->artisan('care-hours:reconcile-bookings')->assertSuccessful();

        $this->assertHeld($settlement);
        $this->assertSame(3100, $payment->fresh()->refunded_cents);
        $this->assertSame(1, $settlement->funding->operations()->where('kind', 'refund')->count());
    }

    public function test_unknown_transfer_can_be_recorded_while_disputed_but_never_replayed(): void
    {
        foreach ([true, false] as $found) {
            [$booking, , , $settlement] = $this->pendingVisit();
            $transfer = $this->unknownTransfer($settlement);
            $booking->update(['status' => CareBooking::STATUS_DISPUTED]);
            $stripe = $this->mock(StripeClient::class);
            $stripe->shouldReceive('findPrepaidTransfer')->once()->andReturn($found ? [
                'id' => 'tr_recorded_'.$transfer->id, 'amount' => 5400, 'currency' => 'usd',
                'destination' => 'acct_test', 'source_transaction' => null,
                'metadata' => ['care_hour_operation_id' => $transfer->public_id, 'care_hour_domain' => 'transfer'],
            ] : null);

            app(PrepaidBookingSettlementService::class)->reconcile($settlement);

            $this->assertSame('funding_on_hold', $settlement->fresh()->status);
            $this->assertSame($found ? 5400 : 0, $settlement->fresh()->paid_cents);
            $this->assertSame($found ? 'succeeded' : 'unknown', $transfer->fresh()->status);
            $this->assertSame(1, $settlement->operations()->count());
            $this->assertNull($settlement->fresh()->payable_cents);
        }
    }

    public function test_status_change_during_provider_lookup_stops_unknown_transfer_replay(): void
    {
        [$booking, , , $settlement] = $this->pendingVisit();
        $this->readyCaregiver($booking);
        $transfer = $this->unknownTransfer($settlement);
        $stripe = $this->mock(StripeClient::class);
        $stripe->shouldReceive('findPrepaidTransfer')->once()->andReturnUsing(function () use ($booking) {
            // Model an out-of-band state write arriving while provider recovery was in flight.
            $booking->update(['status' => CareBooking::STATUS_IN_PROGRESS]);

            return null;
        });

        app(PrepaidTransferPlanner::class)->pay($settlement);

        $this->assertSame('unknown', $transfer->fresh()->status);
        $this->assertSame('funding_on_hold', $settlement->fresh()->status);
        $this->assertSame(0, $settlement->fresh()->paid_cents);
        $this->assertSame(1, $settlement->operations()->count());
    }

    public function test_resolved_hold_requires_a_reconciled_target_before_a_direct_planner_can_adjust_money(): void
    {
        [$booking, , , $settlement] = $this->pendingVisit();
        $transfer = $this->unknownTransfer($settlement);
        $transfer->update(['status' => 'succeeded', 'stripe_transfer_id' => 'tr_already_paid']);
        $booking->update(['status' => CareBooking::STATUS_DISPUTED]);
        $this->mock(StripeClient::class);
        $planner = app(PrepaidTransferPlanner::class);
        $planner->pay($settlement);
        $this->assertSame(5400, $settlement->fresh()->paid_cents);

        $booking->update(['status' => CareBooking::STATUS_REVIEWED, 'dispute_status' => 'resolved']);
        $planner->pay($settlement->fresh());

        $this->assertSame(5400, $settlement->fresh()->paid_cents);
        $this->assertSame(0, $settlement->operations()->where('kind', 'reversal')->count());
        $this->assertNull($settlement->fresh()->payable_cents);
        app(PrepaidBookingSettlementService::class)->reconcile($settlement);
        $this->assertSame('paid', $settlement->fresh()->status);
        $this->assertSame(5400, $settlement->fresh()->paid_cents);
        $this->assertSame(1, $settlement->operations()->count());
    }

    public function test_state_change_between_shortfalls_does_not_start_the_next_capture(): void
    {
        [$booking, , , $settlement] = $this->pendingVisit();
        $settlement->update(['card_minutes' => 120]);
        $payments = collect();
        for ($i = 0; $i < 2; $i++) {
            $payments->push(CareHourPaymentOperation::create([
                'public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
                'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id,
                'kind' => 'shortfall', 'status' => 'authorized', 'amount_cents' => 3100, 'currency' => 'usd',
                'stripe_payment_intent_id' => 'pi_capture_'.$i, 'authorization_expires_at' => now()->addDays(2),
            ]));
        }
        $stripe = $this->mock(StripeClient::class);
        $stripe->shouldReceive('capturePaymentIntent')->once()->with('pi_capture_0', 3100, \Mockery::type('string'))
            ->andReturnUsing(function () use ($booking) {
                $booking->update(['status' => CareBooking::STATUS_DISPUTED, 'dispute_status' => 'open']);

                return ['status' => 'succeeded', 'amount_received' => 3100];
            });
        $stripe->shouldReceive('retrieveCareHourPaymentFinancials')->once()->andReturn(['fee_finalized' => true, 'processing_fee_cents' => 0]);

        app(PrepaidBookingSettlementService::class)->reconcile($settlement);

        $this->assertSame('captured', $payments[0]->fresh()->status);
        $this->assertSame('authorized', $payments[1]->fresh()->status);
        $this->assertSame(1, $settlement->operations()->where('kind', 'capture')->count());
        $this->assertHeld($settlement);
    }

    public function test_dispute_admin_mutation_and_direct_planner_share_the_settlement_lock(): void
    {
        [$booking, , $owner, $settlement] = $this->pendingVisit();
        $admin = User::factory()->create(['role' => 'admin']);
        $lock = Cache::lock('care-hours:settlement:'.$booking->id, 300);
        $this->assertTrue($lock->get());
        try {
            foreach (['family_dispute', 'admin_reopen', 'direct_payout'] as $action) {
                try {
                    if ($action === 'family_dispute') {
                        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $booking->care_request_id])
                            ->set('disputeReason', 'The actual delivered care needs a support review.')->call('openDispute');
                    } elseif ($action === 'admin_reopen') {
                        Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $booking->careRequest])
                            ->call('forceBookingStatus', CareBooking::STATUS_IN_PROGRESS);
                    } else {
                        app(PrepaidTransferPlanner::class)->pay($settlement);
                    }
                    $this->fail('The in-flight settlement lock must serialize '.$action.'.');
                } catch (LockTimeoutException) {
                    $this->assertSame(CareBooking::STATUS_COMPLETED, $booking->fresh()->status);
                    $this->assertSame(0, $settlement->fresh()->paid_cents);
                }
            }
        } finally {
            $lock->release();
        }
    }

    private function unknownTransfer(CareHourSettlement $settlement): CareHourPaymentOperation
    {
        return CareHourPaymentOperation::create([
            'public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
            'care_hour_booking_funding_id' => $settlement->care_hour_booking_funding_id,
            'care_hour_settlement_id' => $settlement->id, 'kind' => 'transfer', 'status' => 'unknown',
            'amount_cents' => 5400, 'currency' => 'usd', 'destination_account_id' => 'acct_test',
            'metadata' => ['revision' => $settlement->revision],
        ]);
    }

    private function adminStatus(CareBooking $booking, User $admin, string $status, string $screen): void
    {
        if ($screen === 'index') {
            Livewire::actingAs($admin)->test(CareRequestsIndex::class)
                ->call('forceBookingStatus', $booking->care_request_id, $status)->assertHasNoErrors();
        } else {
            Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $booking->careRequest])
                ->call('forceBookingStatus', $status)->assertHasNoErrors();
        }
    }

    private function assertHeld(CareHourSettlement $settlement): void
    {
        $this->assertSame('funding_on_hold', $settlement->fresh()->status);
        $this->assertSame(0, $settlement->fresh()->paid_cents);
        $this->assertNull($settlement->fresh()->payable_cents);
        $this->assertSame(0, $settlement->operations()->where('kind', 'transfer')->count());
    }

    private function readyCaregiver(CareBooking $booking): void
    {
        CaregiverProfile::create(['user_id' => $booking->caregiver_user_id, 'stripe_connect_account_id' => 'acct_test',
            'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true]);
    }

    private function pendingVisit(): array
    {
        config(['care_hours.enabled' => true]);
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        $recipient = CareRecipient::create(['care_request_id' => $request->id, 'full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $owner);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $owner->id, 'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Family',
            'paid_minutes' => 300, 'bonus_minutes' => 0, 'total_minutes' => 300, 'amount_cents' => 15500,
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $booking->update(['status' => CareBooking::STATUS_COMPLETED, 'worked_minutes' => 120,
            'completed_at' => now(), 'family_confirmed_at' => now()]);
        $service = app(PrepaidBookingSettlementService::class);
        $settlement = $service->reconcile($service->settle($booking));
        $this->assertSame('payout_pending', $settlement->status);

        return [$booking, $account, $owner, $settlement];
    }
}
