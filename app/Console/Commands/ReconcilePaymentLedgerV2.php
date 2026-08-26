<?php

namespace App\Console\Commands;

use App\Models\CareBookingPayment;
use App\Models\CareBookingPaymentOperation;
use App\Services\Payments\BookingPaymentV2Service;
use App\Support\MarketplacePricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePaymentLedgerV2 extends Command
{
    protected $signature = 'homecare:reconcile-payment-ledger-v2 {--limit=100}';

    protected $description = 'Finalize Stripe processing fees and source-linked caregiver transfers for pricing v2 payments';

    public function handle(BookingPaymentV2Service $payments, MarketplacePricing $pricing): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $candidates = CareBookingPayment::query()
            ->where('pricing_version', $pricing->currentVersion())
            ->where(function ($query): void {
                $query->where(function ($unfinishedPayment): void {
                    $unfinishedPayment->whereIn('status', [
                        CareBookingPayment::STATUS_CAPTURED,
                        CareBookingPayment::STATUS_TRANSFER_FAILED,
                    ])->where(function ($unfinishedLedger): void {
                        $unfinishedLedger->where('fee_finalization_status', '!=', 'finalized')
                            ->orWhereNull('fee_finalization_status')
                            ->orWhereNull('transferred_at');
                    });
                })->orWhereHas('operations', function ($operation): void {
                    $operation->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
                        ->whereIn('status', [
                            CareBookingPaymentOperation::STATUS_PENDING,
                            CareBookingPaymentOperation::STATUS_FAILED,
                        ]);
                });
            })
            ->oldest('updated_at')
            ->limit($limit)
            ->get();

        $completed = 0;
        $failed = 0;
        foreach ($candidates as $payment) {
            try {
                $hasPendingReversal = $payment->operations()
                    ->where('type', CareBookingPaymentOperation::TYPE_TRANSFER_REVERSAL)
                    ->whereIn('status', [
                        CareBookingPaymentOperation::STATUS_PENDING,
                        CareBookingPaymentOperation::STATUS_FAILED,
                    ])
                    ->exists();
                $updated = $hasPendingReversal
                    ? $payments->retryPendingTransferReversals($payment)
                    : $payments->finalizeFeesAndTransfers($payment);
                if ($hasPendingReversal || $updated->status === CareBookingPayment::STATUS_TRANSFERRED) {
                    $completed++;
                }
            } catch (Throwable $exception) {
                $failed++;
                Log::error('payment.v2_reconciliation_failed', [
                    'care_booking_payment_id' => $payment->id,
                    'financial_reference' => $payment->financial_reference,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Payment ledger v2 reconciled: {$completed} completed, {$failed} failed, {$candidates->count()} checked.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
