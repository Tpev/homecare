<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentAttempt;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Support\MarketplacePricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** One reviewed incident, deliberately not a general-purpose charging endpoint. */
class Oct3PaymentRecoveryService
{
    public const BOOKING_ID = 239;

    public const PAYMENT_ID = 120;

    public const AMOUNT = 37252;

    public const MINUTES = 721;

    public const OLD_INTENT = 'pi_3UK1bF6ewFB76RHF0L3C0G6p';

    public const CUSTOMER_ID = 'cus_UzbLWUlGKpPlYP';

    private const RECEIPT = 'oct3_payment_recovery';

    public function __construct(
        private readonly StripeClient $stripe,
        private readonly BookingPaymentV2Service $payments,
        private readonly MarketplacePricing $pricing,
    ) {}

    public function preview(): array
    {
        [$booking, $payment] = $this->context();
        $receipt = $this->receipt($payment);

        return [
            'booking_id' => self::BOOKING_ID,
            'request_id' => 227,
            'payment_id' => self::PAYMENT_ID,
            'started_at' => $booking->started_at->toIso8601String(),
            'completed_at' => $booking->completed_at->toIso8601String(),
            'worked_minutes' => self::MINUTES,
            'family_care_cents' => 36050,
            'family_processing_fee_cents' => 1202,
            'target_charge_cents' => self::AMOUNT,
            'caregiver_gross_cents' => 32445,
            'currency' => 'usd',
            'payment_status' => $payment->status,
            'expected_state' => $receipt['expected_state'] ?? $this->fingerprint($booking, $payment),
            'recovery' => $this->publicReceipt($receipt),
            'auto_approval_eligible' => $booking->timesheet_submitted_at->lte(now()->subHours(24)),
            'administrators' => User::query()->where('role', 'admin')->get(['id', 'name'])->toArray(),
            'effects_on_apply' => 'Authorize and capture exactly USD 372.52; normal fee finalization may transfer caregiver earnings. Preserve the recorded visit. Record approval basis after capture. No support replies or notifications.',
            'provider_check' => 'Preview does not contact Stripe. Apply verifies the original canceled intent; --provider-verified attests no separate/manual charge, refund, dispute, or transfer was found.',
        ];
    }

    public function apply(User $admin, string $expectedState, string $requestId, string $reason,
        string $approvalBasis = '', ?int $approvedBy = null, bool $providerVerified = false): array
    {
        $this->ensure($admin->isAdministrator(), 'A valid administrator is required.');
        $this->ensure(Str::isUuid($requestId), 'Use one stable UUID for --request-id, including retries.');
        $this->ensure(strlen(trim($reason)) >= 15, 'Record a meaningful operational reason and approval evidence.');
        $this->ensure($providerVerified, 'Verify Stripe and any separate/manual payment, then pass --provider-verified.');
        $this->ensure(in_array($approvalBasis, ['auto-approval', 'family'], true), 'Choose an explicit approval basis: auto-approval or family.');
        $this->ensure($approvalBasis === 'family' || $approvedBy === null, 'Do not attribute automatic approval to a family member.');

        $lock = Cache::lock('oct3-payment-recovery:239', 180);
        $this->ensure($lock->get(), 'Another recovery is running. Wait and retry with the same request ID.');
        try {
            return $this->execute($admin, $expectedState, $requestId, trim($reason), $approvalBasis, $approvedBy);
        } finally {
            $lock->release();
        }
    }

    private function execute(User $admin, string $expectedState, string $requestId, string $reason,
        string $approvalBasis, ?int $approvedBy): array
    {
        $receipt = DB::transaction(function () use ($admin, $expectedState, $requestId, $reason, $approvalBasis, $approvedBy): array {
            [$booking, $payment] = $this->context(true);
            $this->assertApproval($booking, $approvalBasis, $approvedBy);
            $receipt = $this->receipt($payment);
            if ($receipt !== []) {
                foreach (['request_id' => $requestId, 'expected_state' => $expectedState,
                    'admin_user_id' => $admin->id, 'reason' => $reason,
                    'approval_basis' => $approvalBasis, 'approved_by_user_id' => $approvedBy] as $key => $value) {
                    $this->ensure(($receipt[$key] ?? null) === $value, 'An existing recovery must be resumed with exactly its original request, preview, administrator and approval inputs.');
                }

                return $receipt;
            }
            $this->ensure(hash_equals($this->fingerprint($booking, $payment), $expectedState), 'The preview is stale. Run a fresh read-only preview before applying.');
            $receipt = [
                'request_id' => $requestId, 'expected_state' => $expectedState,
                'admin_user_id' => $admin->id, 'reason' => $reason,
                'approval_basis' => $approvalBasis, 'approved_by_user_id' => $approvedBy,
                'target_charge_cents' => self::AMOUNT, 'worked_minutes' => self::MINUTES,
                'old_payment_intent_id' => self::OLD_INTENT, 'new_payment_intent_id' => null,
                'state' => 'reserved', 'reserved_at' => now()->toIso8601String(),
                'authorization_key' => 'oct3-recovery:239:'.$requestId.':authorize:37252',
                'customer_id' => $payment->stripe_customer_id,
                'payment_method_id' => $payment->stripe_payment_method_id,
            ];
            $this->storeReceipt($payment, $receipt);
            $this->event($admin->id, 'oct3_payment_recovery_reserved', $receipt);

            return $receipt;
        });

        if ($receipt['state'] === 'complete') {
            return $this->result();
        }

        // A GET failure stops recovery. The operator attestation covers other/manual payments.
        $original = $this->stripe->retrievePaymentIntent(self::OLD_INTENT);
        $this->assertStripeProfile($original);
        $this->ensure(($original['id'] ?? $original['payment_intent_id'] ?? null) === self::OLD_INTENT
            && ($original['status'] ?? null) === 'canceled'
            && array_key_exists('amount_received', $original) && (int) $original['amount_received'] === 0,
            'The original Stripe intent is not confirmed canceled with zero received. Stop and reconcile Stripe.');

        if (! $receipt['new_payment_intent_id']) {
            $this->ensure(Carbon::parse($receipt['reserved_at'])->gt(now()->subHours(23)),
                'Authorization outcome may be unknown and its retry window has elapsed. Inspect Stripe; do not create another recovery or authorization.');
            [$booking, $payment] = $this->context();
            try {
                $authorization = $this->stripe->createManualAuthorization($booking,
                    $receipt['customer_id'], $receipt['payment_method_id'], self::AMOUNT, 'usd', $receipt['authorization_key']);
            } catch (Throwable $exception) {
                $this->updateReceipt(['state' => 'authorization_unknown', 'last_error' => $exception->getMessage()]);
                throw $exception;
            }
            $this->ensure(! empty($authorization['payment_intent_id'])
                && $authorization['payment_intent_id'] !== self::OLD_INTENT
                && (int) ($authorization['amount'] ?? 0) === self::AMOUNT,
                'Unexpected authorization response. Do not create another request; reconcile Stripe with this request ID.');
            $this->persistAuthorization($authorization, $receipt);
        }

        [$booking, $payment] = $this->context();
        $receipt = $this->receipt($payment);
        $live = $this->stripe->retrievePaymentIntent($receipt['new_payment_intent_id']);
        $this->assertStripeProfile($live);
        $this->ensure(($live['id'] ?? $live['payment_intent_id'] ?? null) === $receipt['new_payment_intent_id']
            && (int) ($live['amount'] ?? 0) === self::AMOUNT,
            'Recovery authorization identity or amount changed. Reconcile Stripe before continuing.');
        if (! in_array($live['status'] ?? null, ['requires_capture', 'succeeded'], true)) {
            $this->updateReceipt(['state' => 'requires_action', 'stripe_status' => $live['status'] ?? 'unknown']);
            throw new PaymentException('Recovery payment requires family billing action or Stripe review. No new authorization was created on retry.');
        }

        // Other capture/settlement workers must wait until this provider profile is verified.
        $this->updateReceipt(['state' => 'authorized', 'provider_verified_at' => now()->toIso8601String()]);

        try {
            // Uses the existing charge/fee/transfer ledger, with the recovery-specific capture key.
            $this->payments->capture($booking, $payment);
        } catch (Throwable $exception) {
            $this->updateReceipt(['state' => 'capture_unknown', 'last_error' => $exception->getMessage()]);
            throw $exception;
        }

        DB::transaction(function () use ($admin, $approvalBasis, $approvedBy): void {
            [$booking, $payment] = $this->context(true);
            $this->ensure((int) $payment->amount_captured_cents === self::AMOUNT
                && (int) $payment->overage_pending_cents === 0, 'Capture has not reconciled to exactly USD 372.52. Retry this same recovery after reviewing Stripe.');
            $receipt = $this->receipt($payment);
            if (! $booking->family_confirmed_at) {
                $booking->forceFill([
                    'family_confirmed_at' => now(),
                    'family_confirmed_by_user_id' => $approvalBasis === 'family' ? $approvedBy : null,
                ])->save();
                $this->event($admin->id, 'oct3_timesheet_recovery_approved', $receipt);
            }
            $this->storeReceipt($payment, array_merge($receipt, [
                'state' => 'complete', 'completed_at' => now()->toIso8601String(), 'last_error' => null,
            ]));
            $this->event($admin->id, 'oct3_payment_recovery_completed', $receipt);
        });

        return $this->result();
    }

    private function persistAuthorization(array $authorization, array $receipt): void
    {
        DB::transaction(function () use ($authorization, $receipt): void {
            [$booking, $payment] = $this->context(true);
            $current = $this->receipt($payment);
            $intentId = (string) $authorization['payment_intent_id'];
            $this->ensure(! $current['new_payment_intent_id'] || $current['new_payment_intent_id'] === $intentId,
                'Recovery already references a different authorization. Stop.');
            $authorized = $authorization['status'] === 'requires_capture';
            $status = CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED;
            $metadata = (array) $payment->metadata;
            $metadata[self::RECEIPT] = array_merge($current, [
                'new_payment_intent_id' => $intentId, 'state' => 'authorization_received',
                'stripe_status' => $authorization['status'], 'last_error' => $authorization['failure_message'] ?? null,
            ]);
            $payment->forceFill([
                'status' => $status, 'stripe_payment_intent_id' => $intentId,
                // A succeeded webhook can arrive before capture() returns. Its settlement
                // must use actual work rather than the scheduled 720-minute fallback.
                'worked_minutes' => self::MINUTES, 'family_care_rate_cents' => 3000,
                'family_processing_fee_rate_cents' => 100, 'caregiver_gross_rate_cents' => 2700,
                'caregiver_fee_policy' => $booking->caregiver_fee_policy,
                'family_care_amount_cents' => 36050, 'family_processing_fee_cents' => 1202,
                'caregiver_gross_amount_cents' => 32445,
                'stripe_payment_intent_client_secret' => $authorization['client_secret'] ?? null,
                'amount_authorized_cents' => self::AMOUNT,
                'authorization_expires_at' => $authorization['authorization_expires_at'] ?? null,
                'authorized_at' => $authorized ? now() : null, 'reauthorized_at' => $authorized ? now() : null,
                'failed_at' => null, 'last_error' => $authorization['failure_message'] ?? null, 'metadata' => $metadata,
            ])->save();
            CareBookingPaymentAttempt::query()->where('care_booking_payment_id', self::PAYMENT_ID)
                ->where('stripe_payment_intent_id', '!=', $intentId)->update(['is_active' => false]);
            CareBookingPaymentAttempt::query()->firstOrCreate(['stripe_payment_intent_id' => $intentId], [
                'care_booking_payment_id' => self::PAYMENT_ID, 'care_booking_id' => self::BOOKING_ID,
                'family_account_id' => 3, 'purpose' => 'oct3_payment_recovery',
                'revision_key' => 'oct3-recovery:'.$receipt['request_id'],
                'authorization_key' => hash('sha256', $receipt['authorization_key']),
                'stripe_payment_method_id' => $receipt['payment_method_id'],
                'client_secret' => $authorization['client_secret'] ?? null,
                'amount_cents' => self::AMOUNT, 'currency' => 'usd', 'status' => $status,
                'is_active' => true, 'authorized_at' => $authorized ? now() : null,
                'metadata' => ['recovery_request_id' => $receipt['request_id']],
            ]);
            $this->payments->recordAuthorization($payment);
        });
    }

    /** Revalidate immutable visit evidence before every monetary step, including resumes. */
    private function context(bool $lock = false): array
    {
        $booking = CareBooking::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail(self::BOOKING_ID);
        $payment = CareBookingPayment::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail(self::PAYMENT_ID);
        $this->ensure((int) $booking->care_request_id === 227 && (int) $booking->family_account_id === 3
            && (int) $booking->family_user_id === 335 && (int) $booking->caregiver_user_id === 349
            && (int) $payment->care_booking_id === self::BOOKING_ID && (int) $payment->family_account_id === 3
            && (int) $payment->family_user_id === 335 && (int) $payment->caregiver_user_id === 349
            && CareBookingPayment::where('care_booking_id', self::BOOKING_ID)->count() === 1,
            'Booking/payment identity changed. This command only handles the audited October 3 visit.');
        $this->ensure($booking->status === CareBooking::STATUS_COMPLETED && ! $booking->care_plan_id
            && (int) $booking->worked_minutes === self::MINUTES && (int) $booking->total_paused_seconds === 0
            && ! $booking->paused_at && ! $booking->cancelled_at && ! $booking->dispute_opened_at
            && $booking->dispute_status === 'none' && ! $booking->no_show_flag && ! $booking->late_cancel_flag,
            'Visit status, 721 minutes, breaks, cancellation or dispute evidence changed.');
        foreach (['started_at' => '2026-10-03 19:09:52', 'completed_at' => '2026-10-04 07:11:42',
            'timesheet_submitted_at' => '2026-10-04 07:11:42', 'scheduled_start_at' => '2026-10-03 19:00:00',
            'scheduled_end_at' => '2026-10-04 07:00:00'] as $field => $expected) {
            $this->ensure($booking->$field?->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s') === $expected,
                'The recorded visit timestamps changed. This recovery never rewrites them.');
        }
        $this->ensure($booking->check_in_source === 'manual' && $booking->check_out_source === 'manual'
            && $booking->family_terms_accepted_at && $booking->caregiver_terms_accepted_at,
            'Original tracking evidence or accepted terms are missing.');
        foreach (['checked_in_by_caregiver', 'checked_out_by_caregiver'] as $type) {
            $this->ensure($booking->events()->where('event_type', $type)->where('actor_user_id', 349)->exists(), 'Caregiver check-in/out audit evidence is missing.');
        }
        $this->ensure(! $booking->timeCorrections()->exists() && ! $booking->corrections()->exists()
            && ! $booking->changeRequests()->exists() && ! $payment->hasPrepaidVisitHold(), 'A correction, change request or prepaid hold needs separate review.');
        $this->ensure($booking->pricing_version === '2026-08-v2' && ! $booking->pricing_agreement_id
            && (int) $booking->family_care_rate_cents === 3000 && (int) $booking->family_processing_fee_rate_cents === 100
            && (int) $booking->caregiver_gross_rate_cents === 2700
            && $booking->caregiver_fee_policy === 'successful_charge_balance_transaction'
            && $payment->pricing_version === $booking->pricing_version && $payment->currency === 'usd'
            && ! $this->pricing->hasUnappliedAgreement($booking), 'Saved pricing changed; do not charge from this incident command.');
        $quote = $this->pricing->quoteForCurrentBooking($booking, self::MINUTES);
        $this->ensure((int) $quote['total_charge_cents'] === self::AMOUNT && (int) $quote['caregiver_gross_amount_cents'] === 32445, 'The exact USD 372.52 quote no longer matches.');
        $account = FamilyAccount::findOrFail(3);
        $this->ensure((int) $account->owner_user_id === 335 && $account->status === FamilyAccount::STATUS_ACTIVE
            && $payment->stripe_customer_id === self::CUSTOMER_ID && $payment->stripe_payment_method_id
            && (! $account->stripe_customer_id || $account->stripe_customer_id === $payment->stripe_customer_id), 'Family billing identity is missing or changed.');
        $this->ensure((int) $payment->amount_refunded_cents === 0 && ! $payment->stripe_last_refund_id
            && ! $payment->stripe_last_transfer_reversal_id && ! $payment->stripe_overage_payment_intent_id
            && ! $payment->operations()->whereIn('type', ['refund', 'transfer_reversal', 'dispute'])->exists(),
            'Refund, dispute, reversal or additional payment evidence requires manual reconciliation.');
        $receipt = $this->receipt($payment);
        $newIntent = $receipt['new_payment_intent_id'] ?? null;
        if ($receipt !== []) {
            $this->ensure(($receipt['customer_id'] ?? null) === $payment->stripe_customer_id
                && ($receipt['payment_method_id'] ?? null) === $payment->stripe_payment_method_id,
                'The recovery billing profile changed.');
        }
        $this->ensure(! $newIntent || ($receipt['target_charge_cents'] ?? null) === self::AMOUNT, 'Recovery receipt amount is invalid.');
        $this->ensure($payment->stripe_payment_intent_id === ($newIntent ?: self::OLD_INTENT), 'Payment intent changed outside this recovery.');
        $this->ensure(! $payment->attempts()->whereNotIn('stripe_payment_intent_id', array_filter([self::OLD_INTENT, $newIntent]))->exists(), 'An additional authorization attempt needs review.');
        if (! $newIntent) {
            $this->ensure($payment->status === CareBookingPayment::STATUS_CANCELLED && (int) $payment->amount_captured_cents === 0
                && ! $payment->captured_at && ! $payment->transferred_at && ! $payment->stripe_transfer_id
                && ! $payment->stripe_primary_charge_id && ! $booking->payoutItem()->exists()
                && ! $payment->operations()->where('type', '!=', 'authorization')->exists(), 'Existing money movement prevents a fresh authorization.');
        } else {
            $this->ensure((int) $payment->amount_authorized_cents === self::AMOUNT
                && in_array((int) $payment->amount_captured_cents, [0, self::AMOUNT], true)
                && ! $payment->operations()->where('type', 'charge')->where(fn ($q) => $q
                    ->whereNull('stripe_parent_object_id')->orWhere('stripe_parent_object_id', '!=', $newIntent))->exists(),
                'Recovery money does not match the one authorized USD 372.52 payment.');
            if (($receipt['state'] ?? null) === 'complete') {
                $this->ensure((int) $payment->amount_captured_cents === self::AMOUNT
                    && $booking->family_confirmed_at !== null,
                    'The completed recovery no longer matches its captured payment and approval.');
            }
        }

        return [$booking, $payment];
    }

    private function assertApproval(CareBooking $booking, string $basis, ?int $approvedBy): void
    {
        if ($basis === 'auto-approval') {
            $this->ensure($booking->timesheet_submitted_at->lte(now()->subHours(24)), 'The existing 24-hour automatic approval window has not elapsed.');
        } else {
            $this->ensure($approvedBy && User::whereKey($approvedBy)->where('role', 'family')->exists()
                && ((int) $approvedBy === 335 || FamilyAccountMember::where('family_account_id', 3)
                    ->where('user_id', $approvedBy)->where('status', 'active')->exists()),
                'Family approval must identify the owner or an active member of this family account; document actual approval in --reason.');
        }
    }

    private function assertStripeProfile(array $intent): void
    {
        $this->ensure(($intent['customer_id'] ?? null) === self::CUSTOMER_ID
            && ($intent['currency'] ?? null) === 'usd' && ($intent['livemode'] ?? null) === true,
            'Stripe customer, USD currency or live mode does not match the audited payment. Stop and reconcile Stripe.');
    }

    private function fingerprint(CareBooking $booking, CareBookingPayment $payment): string
    {
        return hash('sha256', json_encode([
            'booking' => $booking->getRawOriginal(), 'payment' => $payment->getRawOriginal(),
            'attempts' => $payment->attempts()->orderBy('id')->get()->map->getRawOriginal()->all(),
            'operations' => $payment->operations()->orderBy('id')->get()->map->getRawOriginal()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    private function receipt(CareBookingPayment $payment): array
    {
        return (array) data_get($payment->metadata, self::RECEIPT, []);
    }

    private function storeReceipt(CareBookingPayment $payment, array $receipt): void
    {
        $metadata = (array) $payment->metadata;
        $metadata[self::RECEIPT] = $receipt;
        $payment->forceFill(['metadata' => $metadata])->save();
    }

    private function updateReceipt(array $changes): void
    {
        DB::transaction(function () use ($changes): void {
            CareBooking::whereKey(self::BOOKING_ID)->lockForUpdate()->firstOrFail();
            $payment = CareBookingPayment::whereKey(self::PAYMENT_ID)->lockForUpdate()->firstOrFail();
            $receipt = $this->receipt($payment);
            if (($receipt['state'] ?? null) !== 'complete') {
                $this->storeReceipt($payment, array_merge($receipt, $changes));
            }
        });
    }

    private function event(int $adminId, string $type, array $receipt): void
    {
        CareBookingEvent::firstOrCreate(['care_booking_id' => self::BOOKING_ID, 'event_type' => $type], [
            'actor_user_id' => $adminId, 'actor_role' => 'admin', 'happened_at' => now(),
            'payload' => $this->publicReceipt($receipt),
        ]);
    }

    private function publicReceipt(array $receipt): array
    {
        return array_intersect_key($receipt, array_flip(['request_id', 'admin_user_id', 'approval_basis',
            'approved_by_user_id', 'reason', 'state', 'target_charge_cents', 'worked_minutes',
            'old_payment_intent_id', 'new_payment_intent_id', 'reserved_at', 'completed_at', 'last_error']));
    }

    private function result(): array
    {
        $payment = CareBookingPayment::findOrFail(self::PAYMENT_ID);

        return ['recovery' => $this->publicReceipt($this->receipt($payment)),
            'payment' => $payment->only(['id', 'status', 'amount_captured_cents', 'caregiver_gross_amount_cents',
                'stripe_processing_fee_cents', 'caregiver_amount_cents', 'fee_finalization_status',
                'stripe_payment_intent_id', 'stripe_primary_charge_id', 'stripe_transfer_id']),
            'worked_minutes' => CareBooking::findOrFail(self::BOOKING_ID)->worked_minutes];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['recovery' => $message]);
        }
    }
}
