<?php

namespace App\Services\CareHours;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only persisted backlog inspection; it never resolves payments or sends notices. */
class CareHourHealthService
{
    private const REQUIRED_SCHEMA = [
        'care_hour_accounts' => ['id'],
        'care_hour_recipient_links' => ['id'],
        'care_hour_allocations' => ['id'],
        'care_hour_ledger_entries' => ['id'],
        'care_hour_contribution_links' => ['id'],
        'care_hour_contribution_verifications' => ['id'],
        'care_hour_welcome_claims' => ['care_hour_account_id'],
        'care_hour_plan_preferences' => ['id'],
        'care_hour_request_preferences' => ['id', 'care_request_id', 'care_hour_account_id', 'enabled', 'selected_by_user_id'],
        'care_hour_gift_verifications' => ['id'],
        'care_hour_gift_admin_checks' => ['id'],
        'care_hour_purchases' => ['id', 'status', 'paid_at', 'issued_at', 'created_at', 'checkout_requested_at', 'stripe_checkout_session_id', 'processing_fee_cents', 'correction_state', 'correction_requested_at'],
        'care_hour_gifts' => ['care_hour_purchase_id', 'ready_at', 'claimed_at', 'code_hash', 'encrypted_code'],
        'care_hour_purchase_notices' => ['sent_at', 'created_at'],
        'care_hour_lots' => ['status', 'issued_at', 'created_at'],
        'care_hour_booking_fundings' => ['id', 'status'],
        'care_hour_settlements' => ['status', 'approved_at', 'created_at', 'paid_cents', 'caregiver_net_cents'],
        'care_hour_payment_operations' => ['care_hour_booking_funding_id', 'kind', 'status', 'created_at', 'captured_cents', 'refunded_cents', 'metadata'],
        'stripe_webhook_events' => ['status', 'created_at'],
        'marketplace_notification_deliveries' => ['event_key', 'channel', 'status', 'created_at', 'payload'],
    ];

    public function inspect(int $maxAgeMinutes = 30, int $checkoutAgeMinutes = 1500): array
    {
        $result = [
            'status' => 'unknown',
            'checked_at' => now()->toIso8601String(),
            'read_only' => true,
            'flags' => [
                'enabled' => (bool) config('care_hours.enabled', false),
                'sales_enabled' => (bool) config('care_hours.sales_enabled', false),
                'gifts_enabled' => (bool) config('care_hours.gifts_enabled', false),
            ],
            'schema' => ['status' => 'complete', 'missing' => []],
            'checks' => [],
        ];
        foreach (self::REQUIRED_SCHEMA as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $result['schema']['missing'][] = $table;

                continue;
            }
            foreach (array_diff($columns, Schema::getColumnListing($table)) as $column) {
                $result['schema']['missing'][] = $table.'.'.$column;
            }
        }
        if ($result['schema']['missing'] !== []) {
            $result['schema']['status'] = 'incomplete';

            return $result;
        }

        // Ready, unclaimed gifts are valid liabilities with no expiry, not stuck fulfillment.
        $readyGift = fn (Builder $query) => $query->selectRaw('1')->from('care_hour_gifts as g')
            ->whereColumn('g.care_hour_purchase_id', 'p.id')->whereNotNull('g.ready_at')
            ->whereNotNull('g.code_hash')->whereNotNull('g.encrypted_code')->whereNull('g.claimed_at');
        $purchases = fn () => DB::table('care_hour_purchases as p');
        $checks = [];
        $checks['purchase_checkout'] = $this->check(
            $purchases()->where('p.status', 'pending')->whereNull('p.paid_at'),
            'COALESCE(p.checkout_requested_at, p.created_at)', $checkoutAgeMinutes,
            'Checkout attempt or purchase creation; ordinary open checkouts get 25 hours by default.'
        );
        $checks['purchase_fulfillment'] = $this->check(
            $purchases()->where(fn (Builder $query) => $query->where('p.status', 'recovery')
                ->orWhere(fn (Builder $paid) => $paid->whereNotNull('p.paid_at')->whereNull('p.issued_at')
                    ->whereNotIn('p.status', ['held', 'corrected'])->whereNull('p.correction_state')->whereNotExists($readyGift))),
            'COALESCE(p.paid_at, p.checkout_requested_at, p.created_at)', $maxAgeMinutes,
            'Payment, checkout attempt or purchase creation.'
        );
        $checks['purchase_fees'] = $this->check(
            $purchases()->whereNotNull('p.paid_at')->whereNull('p.processing_fee_cents')->whereIn('p.status', ['paid', 'issued', 'held']),
            'COALESCE(p.paid_at, p.created_at)', $maxAgeMinutes, 'Payment or purchase creation.'
        );
        $checks['purchase_corrections'] = $this->check(
            $purchases()->whereIn('p.correction_state', ['pending', 'failed']),
            'COALESCE(p.correction_requested_at, p.paid_at, p.created_at)', $maxAgeMinutes, 'Correction request, payment or purchase creation.'
        );
        $checks['settlements'] = $this->check(
            DB::table('care_hour_settlements')->where(fn (Builder $query) => $query->where('status', '!=', 'paid')
                ->orWhereColumn('paid_cents', '<', 'caregiver_net_cents')),
            'COALESCE(approved_at, created_at)', $maxAgeMinutes,
            'Initial approval or settlement creation; includes fee, funding, refund and caregiver payout attention.'
        );
        $unreleased = fn (Builder $query) => $query->whereIn('o.status', ['pending', 'checkout', 'authorized', 'expired'])
            ->orWhere(fn (Builder $captured) => $captured->where('o.status', 'captured')->whereColumn('o.captured_cents', '>', 'o.refunded_cents'));
        $checks['payment_releases'] = $this->check(
            DB::table('care_hour_payment_operations as o')->join('care_hour_booking_fundings as f', 'f.id', '=', 'o.care_hour_booking_funding_id')
                ->where('o.kind', 'shortfall')->where($unreleased)
                ->where(fn (Builder $query) => $query->where('f.status', 'released')->orWhere('o.metadata->release_requested', true)),
            'o.created_at', $maxAgeMinutes,
            'Operation creation; release-request time is not persisted, so this is a conservative record-age alert.'
        );
        $checks['payment_operations'] = $this->check(
            DB::table('care_hour_payment_operations')->where(fn (Builder $query) => $query
                ->where(fn (Builder $pending) => $pending->whereIn('kind', ['transfer', 'reversal', 'refund'])->whereIn('status', ['pending', 'requested', 'unknown']))
                ->orWhere('metadata->provider_reversal_attention', true)),
            'created_at', $maxAgeMinutes, 'Operation creation; completed or superseded failed attempts are excluded.'
        );
        $checks['booking_checkouts'] = $this->check(
            DB::table('care_hour_payment_operations')->where('kind', 'shortfall')->whereIn('status', ['pending', 'checkout', 'expired']),
            'created_at', $checkoutAgeMinutes, 'Shortfall checkout creation; future care with a valid authorization is excluded.'
        );
        $checks['notices'] = $this->check(
            DB::table('care_hour_purchase_notices')->whereNull('sent_at'),
            'created_at', $maxAgeMinutes, 'Notice creation, regardless of delivery attempts or processing locks.'
        );
        $checks['visit_notices'] = $this->check(
            DB::table('marketplace_notification_deliveries')->where(function ($query) {
                $query->whereIn('event_key', ['care_hours_payment_attention', 'care_hours_visit_settled'])
                    ->orWhere(fn ($payout) => $payout->where('event_key', 'payout_transferred')->whereNotNull('payload->care_hour_settlement_id'));
            })
                ->whereIn('channel', ['email', 'in_app'])->whereIn('status', ['pending', 'queued', 'processing', 'failed']),
            'created_at', $maxAgeMinutes,
            'Prepaid coverage, settlement and earnings notice creation; includes failed attempts even when sent_at is set. Ordinary card payouts, suppressed notices and placeholder channels are excluded.'
        );
        $checks['shared_stripe_webhook_inbox'] = $this->check(
            DB::table('stripe_webhook_events')->whereIn('status', ['received', 'processing', 'failed']),
            'created_at', $maxAgeMinutes,
            'Entire shared Stripe inbox (card and prepaid): event receipt time, regardless of retries. Payloads are never read or decrypted.'
        );
        $checks['purchase_holds'] = $this->check(
            $purchases()->where('p.status', 'held'),
            'COALESCE(p.paid_at, p.created_at)', $maxAgeMinutes,
            'Payment or purchase creation; hold-entry time is not persisted for every gift, so this is a conservative record-age alert.'
        );
        $checks['lot_holds'] = $this->check(
            DB::table('care_hour_lots')->where('status', 'held'),
            'COALESCE(issued_at, created_at)', $maxAgeMinutes, 'Lot issuance or creation; review holds even if no visit is currently booked.'
        );
        $result['checks'] = $checks;
        $result['status'] = collect($checks)->contains(fn (array $check) => $check['overdue_count'] > 0) ? 'attention' : 'healthy';
        $result['notes'] = [
            'Counts can overlap; do not add them as unique incidents.',
            'Ages use stable business or creation timestamps, not updated_at or recovery attempts; they are not exact time in the current state.',
            'Healthy means no aged persisted backlog at this instant. It does not verify Stripe, email delivery, scheduler liveness, shared locks, deployment readiness or bank delivery.',
            'Existing liabilities are inspected even when new sales or the feature are paused.',
            'shared_stripe_webhook_inbox covers all Stripe domains, including ordinary card payments; it does not attribute failures to prepaid hours.',
        ];

        return $result;
    }

    private function check(Builder $query, string $timestamp, int $minutes, string $basis): array
    {
        // Expressions are fixed internally, never taken from CLI input. Aggregate only; no PII is loaded.
        $total = (clone $query)->selectRaw('COUNT(*) as total, MIN('.$timestamp.') as oldest')->first();
        $overdue = (clone $query)->whereRaw('('.$timestamp.' IS NULL OR '.$timestamp.' <= ?)', [now()->subMinutes($minutes)])->count();

        return [
            'count' => (int) $total->total,
            'overdue_count' => $overdue,
            'oldest_record_at' => $total->oldest,
            'threshold_minutes' => $minutes,
            'age_basis' => $basis,
        ];
    }
}
