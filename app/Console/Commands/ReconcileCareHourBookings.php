<?php

namespace App\Console\Commands;

use App\Models\CareHourBookingFunding;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourSettlement;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use Illuminate\Console\Command;

class ReconcileCareHourBookings extends Command
{
    protected $signature = 'care-hours:reconcile-bookings {--limit=100}';

    protected $description = 'Recover committed care-hours booking payments and payouts without changing card bookings.';

    public function handle(PrepaidBookingFundingService $funding, PrepaidBookingSettlementService $settlements, CareHourPaymentGateway $gateway): int
    {
        if (! $funding->available()) {
            return self::SUCCESS;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        foreach (CareHourPaymentOperation::where('kind', 'shortfall')->whereIn('status', ['pending', 'checkout', 'expired'])
            ->whereNotNull('stripe_checkout_session_id')->orderBy('updated_at')->limit($limit)->get() as $operation) {
            // Rotate unresolved checkouts so an old open session cannot starve newer payment results.
            $operation->touch();
            try {
                $session = $gateway->retrieveCheckout($operation->stripe_checkout_session_id);
                if (($session['status'] ?? '') === 'expired') {
                    $funding->failShortfall($operation->public_id);

                    continue;
                }
                $intent = $session['payment_intent'] ?? null;
                if (is_array($intent)) {
                    $intent = $intent['id'] ?? null;
                }
                if ($intent) {
                    $result = $gateway->financials($intent);
                    if (in_array($result['status'] ?? '', ['requires_capture', 'succeeded'], true)) {
                        $funding->completeShortfall($operation->public_id, $result);
                    }
                }
            } catch (\Throwable $error) {
                $operation->update(['last_error' => $error->getMessage()]);
                report($error);
            }
        }
        foreach (CareHourBookingFunding::where('status', 'released')->whereHas('operations', fn ($query) => $query
            ->where('kind', 'shortfall')->where(fn ($pending) => $pending->whereIn('status', ['pending', 'checkout', 'authorized', 'expired'])
            ->orWhere(fn ($captured) => $captured->where('status', 'captured')->whereColumn('captured_cents', '>', 'refunded_cents'))))
            ->orderBy('updated_at')->limit($limit)->get() as $record) {
            try {
                $record->touch();
                $funding->reconcileReleased($record->id);
            } catch (\Throwable $error) {
                $record->update(['last_error' => $error->getMessage()]);
                report($error);
            }
        }
        foreach (CareHourPaymentOperation::where('kind', 'shortfall')->where('metadata->release_requested', true)
            ->where(fn ($pending) => $pending->where('status', 'authorized')
                ->orWhere(fn ($captured) => $captured->where('status', 'captured')->whereColumn('captured_cents', '>', 'refunded_cents')))
            ->orderBy('updated_at')->limit($limit)->get() as $operation) {
            try {
                $operation->touch();
                app(\App\Services\CareHours\CareHourScheduleChangeFundingService::class)->releaseOperation($operation);
            } catch (\Throwable $error) {
                $operation->update(['last_error' => $error->getMessage()]);
                report($error);
            }
        }
        $unaccepted = \App\Models\CareBookingChangeRequest::where('status', 'pending')->whereHas('booking', fn ($q) => $q->where('status', '!=', 'scheduled'))->select('id');
        foreach (CareHourPaymentOperation::where('kind', 'shortfall')->whereIn('metadata->schedule_change_id', $unaccepted)
            ->where(fn ($pending) => $pending->where('status', 'authorized')
                ->orWhere(fn ($captured) => $captured->where('status', 'captured')->whereColumn('captured_cents', '>', 'refunded_cents')))
            ->orderBy('updated_at')->limit($limit)->get() as $operation) {
            try {
                $operation->touch();
                app(\App\Services\CareHours\CareHourScheduleChangeFundingService::class)->afterProviderConfirmation($operation);
            } catch (\Throwable $error) {
                $operation->update(['last_error' => $error->getMessage()]);
                report($error);
            }
        }
        foreach (CareHourSettlement::whereNotIn('status', ['paid'])->orderBy('updated_at')->limit($limit)->get() as $settlement) {
            $settlements->reconcileSafely($settlement->id);
        }

        return self::SUCCESS;
    }
}
