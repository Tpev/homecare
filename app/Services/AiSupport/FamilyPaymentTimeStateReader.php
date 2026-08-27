<?php

namespace App\Services\AiSupport;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;

class FamilyPaymentTimeStateReader
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    /** @return array<string,mixed>|null */
    public function latestPaymentAttention(
        User $actor,
        ?string $resourceType = null,
        ?int $resourceId = null,
    ): ?array {
        $account = $this->authorizedAccount($actor);
        if (($resourceType !== null || $resourceId !== null)
            && (! in_array($resourceType, ['care_request', 'care_plan'], true) || ! $resourceId)) {
            return null;
        }
        $booking = CareBooking::query()
            ->forFamilyAccount($account)
            ->with([
                'careRequest:id,title,is_system_generated',
                'carePlan:id,title',
                'caregiver:id,name',
                'payment',
                'latestTimeCorrection',
            ])
            ->where(function ($query): void {
                $query->whereHas('payment', function ($payment): void {
                    $payment->whereIn('status', CareBookingPayment::FAMILY_ACTION_REQUIRED_STATUSES)
                        ->orWhere('overage_pending_cents', '>', 0);
                })->orWhereHas('latestTimeCorrection', fn ($correction) => $correction
                    ->where('status', CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED));
            })
            ->when($resourceType === 'care_request', fn ($query) => $query->where('care_request_id', $resourceId))
            ->when($resourceType === 'care_plan', fn ($query) => $query->where('care_plan_id', $resourceId))
            ->latest('updated_at')
            ->first();

        if (! $booking) {
            return null;
        }

        $payment = $booking->payment;
        $correction = $booking->latestTimeCorrection;
        $reason = $this->failureReason($payment, $correction);

        return [
            'care_request_id' => $booking->care_request_id ? (int) $booking->care_request_id : null,
            'care_plan_id' => $booking->care_plan_id ? (int) $booking->care_plan_id : null,
            'subject' => trim((string) ($booking->careRequest?->title ?: $booking->carePlan?->title ?: 'Care visit')),
            'caregiver_name' => trim((string) ($booking->caregiver?->name ?: 'Caregiver')),
            'payment_status' => (string) ($payment?->status ?: 'payment_action_required'),
            'reason_code' => $reason['code'],
            'reason' => $reason['message'],
            'recovery' => $reason['recovery'],
            'amounts' => [
                'authorized_cents' => max(0, (int) ($payment?->amount_authorized_cents ?? 0)),
                'captured_cents' => max(0, (int) ($payment?->amount_captured_cents ?? 0)),
                'refunded_cents' => max(0, (int) ($payment?->amount_refunded_cents ?? 0)),
                'additional_pending_cents' => max(0, (int) ($payment?->overage_pending_cents ?? 0)),
            ],
            'target' => $this->targetFor($booking, true),
            'state_hash' => hash('sha256', json_encode([
                $booking->id,
                $payment?->id,
                $payment?->status,
                $payment?->updated_at?->getTimestamp(),
                $correction?->id,
                $correction?->status,
                $correction?->updated_at?->getTimestamp(),
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array<string,mixed>|null */
    public function latestSubmittedHours(User $actor): ?array
    {
        $account = $this->authorizedAccount($actor);
        $booking = CareBooking::query()
            ->forFamilyAccount($account)
            ->with([
                'careRequest:id,title,is_system_generated',
                'carePlan:id,title',
                'caregiver:id,name',
                'taskChecks:id,care_booking_id,label,is_completed,notes',
                'latestTimeCorrection',
                'payment',
            ])
            ->whereNotNull('timesheet_submitted_at')
            ->orderByRaw('CASE WHEN family_confirmed_at IS NULL THEN 0 ELSE 1 END')
            ->latest('timesheet_submitted_at')
            ->first();

        if (! $booking) {
            return null;
        }

        $correction = $booking->latestTimeCorrection;
        $originalMinutes = max(0, (int) $booking->worked_minutes);
        $proposedMinutes = $correction ? max(0, (int) $correction->proposed_worked_minutes) : null;

        return [
            'care_request_id' => $booking->care_request_id ? (int) $booking->care_request_id : null,
            'care_plan_id' => $booking->care_plan_id ? (int) $booking->care_plan_id : null,
            'subject' => trim((string) ($booking->careRequest?->title ?: $booking->carePlan?->title ?: 'Care visit')),
            'caregiver_name' => trim((string) ($booking->caregiver?->name ?: 'Caregiver')),
            'submitted_at' => $booking->timesheet_submitted_at?->toIso8601String(),
            'started_at' => $booking->started_at?->toIso8601String(),
            'completed_at' => $booking->completed_at?->toIso8601String(),
            'worked_minutes' => $originalMinutes,
            'expected_minutes' => max(0, (int) $booking->expected_minutes),
            'difference_minutes' => $originalMinutes - max(0, (int) $booking->expected_minutes),
            'family_confirmed' => $booking->family_confirmed_at !== null,
            'tasks' => $booking->taskChecks->map(fn ($task): array => [
                'label' => trim((string) $task->label),
                'completed' => (bool) $task->is_completed,
                'notes' => trim((string) $task->notes),
            ])->values()->all(),
            'check_in_note' => trim((string) $booking->check_in_note),
            'check_out_note' => trim((string) $booking->check_out_note),
            'correction' => $correction ? [
                'status' => (string) $correction->status,
                'status_label' => $correction->statusLabel(),
                'reason_label' => $correction->reasonLabel(),
                'proposed_started_at' => $correction->proposed_started_at?->toIso8601String(),
                'proposed_completed_at' => $correction->proposed_completed_at?->toIso8601String(),
                'proposed_break_minutes' => max(0, (int) $correction->proposed_break_minutes),
                'proposed_worked_minutes' => $proposedMinutes,
                'difference_minutes' => $proposedMinutes - $originalMinutes,
                'family_charge_cents' => max(0, (int) data_get($correction->financial_preview, 'target_charge_cents', 0)),
            ] : null,
            'payment_status' => (string) ($booking->payment?->status ?: 'not_prepared'),
            'target' => $this->targetFor(
                $booking,
                $correction?->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED,
            ),
        ];
    }

    /** @return array<string,mixed>|null */
    public function latestPaymentRecord(User $actor): ?array
    {
        $account = $this->authorizedAccount($actor);
        $booking = CareBooking::query()
            ->forFamilyAccount($account)
            ->with([
                'careRequest:id,title,is_system_generated',
                'carePlan:id,title',
                'caregiver:id,name',
                'payment',
            ])
            ->whereHas('payment')
            ->latest('updated_at')
            ->first();

        if (! $booking || ! $booking->payment) {
            return null;
        }

        $payment = $booking->payment;
        $captured = max(0, (int) $payment->amount_captured_cents);
        $refunded = max(0, (int) $payment->amount_refunded_cents);

        return [
            'subject' => trim((string) ($booking->careRequest?->title ?: $booking->carePlan?->title ?: 'Care visit')),
            'caregiver_name' => trim((string) ($booking->caregiver?->name ?: 'Caregiver')),
            'status' => (string) $payment->status,
            'authorized_cents' => max(0, (int) $payment->amount_authorized_cents),
            'captured_cents' => $captured,
            'refunded_cents' => $refunded,
            'net_paid_cents' => max(0, $captured - $refunded),
            'authorized_at' => $payment->authorized_at?->toIso8601String(),
            'captured_at' => $payment->captured_at?->toIso8601String(),
            'target' => [
                'target_id' => 'family.care_history',
                'resource_type' => null,
                'resource_id' => null,
                'label' => 'Open care history',
            ],
        ];
    }

    /** @return array{code:string,message:string,recovery:string} */
    public function failureReason(?CareBookingPayment $payment, ?CareBookingTimeCorrection $correction = null): array
    {
        $error = mb_strtolower(trim((string) ($payment?->last_error ?? '')));
        $status = (string) ($payment?->status ?? '');
        $patterns = [
            'authentication_required' => ['authentication', 'authentication_required', 'requires action', 'requires_action', 'verify your card', 'verification is required', '3d secure'],
            'insufficient_funds' => ['insufficient fund', 'insufficient_funds', 'not enough fund'],
            'expired_card' => ['expired card', 'expired_card', 'card has expired'],
            'incorrect_card_details' => ['incorrect cvc', 'incorrect_cvc', 'incorrect security code', 'incorrect card', 'invalid card number', 'incorrect number'],
            'card_declined' => ['card declined', 'card was declined', 'decline'],
            'payment_method_missing' => ['payment method is missing', 'payment_method_missing', 'add a payment method', 'no payment method', 'billing profile is missing'],
            'provider_temporarily_unavailable' => ['temporarily unavailable', 'retry in a moment', 'right now', 'timeout', 'connection'],
        ];
        $code = null;
        foreach ($patterns as $candidate => $needles) {
            if (collect($needles)->contains(fn (string $needle): bool => str_contains($error, $needle))) {
                $code = $candidate;
                break;
            }
        }
        $code ??= match (true) {
            $status === CareBookingPayment::STATUS_REAUTH_REQUIRED => 'authorization_expired',
            $status === CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED => 'authentication_required',
            max(0, (int) ($payment?->overage_pending_cents ?? 0)) > 0,
            $correction?->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED => 'additional_authorization_required',
            default => 'reason_unavailable',
        };

        return match ($code) {
            'authentication_required' => ['code' => $code, 'message' => 'Your card or bank needs a secure verification step.', 'recovery' => 'Open the payment action and complete the secure verification, then check again.'],
            'insufficient_funds' => ['code' => $code, 'message' => 'The card provider reported that there were not enough available funds.', 'recovery' => 'Use a different payment method or contact the card provider, then retry securely.'],
            'expired_card' => ['code' => $code, 'message' => 'The saved card appears to be expired.', 'recovery' => 'Update the payment method, then return to the visit and retry.'],
            'incorrect_card_details' => ['code' => $code, 'message' => 'The card provider reported that some card details were not accepted.', 'recovery' => 'Update or replace the payment method securely, then retry.'],
            'card_declined' => ['code' => $code, 'message' => 'The card provider declined this payment attempt.', 'recovery' => 'Try another payment method or contact the card provider, then retry.'],
            'payment_method_missing' => ['code' => $code, 'message' => 'LoLo could not find a usable saved payment method.', 'recovery' => 'Add or update the Family payment method, then retry the visit payment.'],
            'authorization_expired' => ['code' => $code, 'message' => 'The earlier temporary card authorization expired.', 'recovery' => 'Open the visit payment and authorize it again.'],
            'additional_authorization_required' => ['code' => $code, 'message' => 'The final or corrected hours need an additional secure authorization.', 'recovery' => 'Review the amount and complete the secure payment action for this visit.'],
            'provider_temporarily_unavailable' => ['code' => $code, 'message' => 'The payment provider could not complete the attempt at that moment.', 'recovery' => 'Wait a moment and use the existing secure retry.'],
            default => ['code' => 'reason_unavailable', 'message' => 'LoLo knows this payment needs attention, but no safe specific reason is available.', 'recovery' => 'Open the exact payment action to retry, update the card, or ask a person for help.'],
        };
    }

    private function authorizedAccount(User $actor): mixed
    {
        if ($actor->role !== 'family' || ! $this->familyAccounts->membershipFor($actor, false)) {
            throw new AuthorizationException('An active Family Account is required.');
        }

        return $this->familyAccounts->account($actor);
    }

    /** @return array{target_id:string,resource_type:string,resource_id:int,label:string} */
    private function targetFor(CareBooking $booking, bool $payment): array
    {
        if ($booking->care_request_id && ! $booking->careRequest?->is_system_generated) {
            return [
                'target_id' => $payment ? 'family.request.payment_attention' : 'family.request.timesheet',
                'resource_type' => 'care_request',
                'resource_id' => (int) $booking->care_request_id,
                'label' => $payment ? 'Fix this payment' : 'Review submitted hours',
            ];
        }

        return [
            'target_id' => 'family.regular_care.attention',
            'resource_type' => 'care_plan',
            'resource_id' => (int) $booking->care_plan_id,
            'label' => $payment ? 'Fix this recurring care payment' : 'Review recurring care hours',
        ];
    }
}
