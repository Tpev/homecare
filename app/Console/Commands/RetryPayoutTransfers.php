<?php

namespace App\Console\Commands;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBookingPayment;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\StripeClient;
use Illuminate\Console\Command;

class RetryPayoutTransfers extends Command
{
    protected $signature = 'homecare:retry-payout-transfers
        {--dry-run : List eligible payments without creating Stripe transfers}
        {--limit=100 : Maximum number of payments to inspect}';

    protected $description = 'Retry captured caregiver payout transfers once Stripe Connect accounts are ready.';

    public function handle(BookingPaymentService $payments, StripeClient $stripe): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');

        $candidates = CareBookingPayment::query()
            ->with(['booking.caregiver.caregiverProfile', 'booking.caregiver'])
            ->whereIn('status', [
                CareBookingPayment::STATUS_CAPTURED,
                CareBookingPayment::STATUS_TRANSFER_FAILED,
            ])
            ->whereNull('stripe_transfer_id')
            ->where('caregiver_amount_cents', '>', 0)
            ->orderBy('captured_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No captured caregiver payout transfers are waiting.');

            return self::SUCCESS;
        }

        $ready = 0;
        $transferred = 0;
        $skipped = 0;
        $waitingOnBalance = 0;
        $failed = 0;
        $availableBalances = [];
        $balanceCheckFailed = [];

        foreach ($candidates as $payment) {
            $booking = $payment->booking;
            $profile = $booking?->caregiver?->caregiverProfile;

            if (! $booking || ! $profile?->stripeConnectIsReady() || ! $profile->stripe_connect_account_id) {
                $skipped++;
                $this->line('Skipped payment #'.$payment->id.': caregiver payout account is not ready.');

                continue;
            }

            $currency = strtolower((string) ($payment->currency ?: $stripe->currency()));
            $amountCents = (int) $payment->caregiver_amount_cents;
            if (! array_key_exists($currency, $availableBalances) && ! ($balanceCheckFailed[$currency] ?? false)) {
                try {
                    $availableBalances[$currency] = $stripe->availableBalanceCents($currency);
                } catch (PaymentException $exception) {
                    $balanceCheckFailed[$currency] = true;
                    $this->warn('Could not read Stripe '.$currency.' available balance: '.$this->exceptionDetail($exception));
                }
            }

            if (! ($balanceCheckFailed[$currency] ?? false)
                && ($availableBalances[$currency] ?? 0) < $amountCents) {
                $waitingOnBalance++;
                $this->line(
                    'Waiting payment #'.$payment->id.': platform available balance is '
                    .$this->formatCents((int) ($availableBalances[$currency] ?? 0), $currency)
                    .'; needs '.$this->formatCents($amountCents, $currency)
                    .'. Stripe funds may still be pending or delayed.'
                );

                continue;
            }

            $ready++;
            $this->line('Ready payment #'.$payment->id.' for booking #'.$booking->id.' ('.$this->formatCents($amountCents, $currency).').');

            if ($dryRun) {
                continue;
            }

            try {
                $updated = $payments->retryTransfer($payment);
            } catch (PaymentException $exception) {
                $failed++;
                $this->warn('Payment #'.$payment->id.' transfer retry failed: '.$exception->userMessage);
                $detail = $this->exceptionDetail($exception);
                if ($detail !== $exception->userMessage) {
                    $this->line('Stripe detail: '.$detail);
                }

                continue;
            }

            if ($updated->status === CareBookingPayment::STATUS_TRANSFERRED) {
                $transferred++;
                if (! ($balanceCheckFailed[$currency] ?? false)) {
                    $availableBalances[$currency] = max(0, (int) ($availableBalances[$currency] ?? 0) - $amountCents);
                }
                $this->info('Transferred payment #'.$updated->id.' using '.$updated->stripe_transfer_id.'.');
            }
        }

        if ($dryRun) {
            $this->info($ready.' ready transfer(s), '.$waitingOnBalance.' waiting on Stripe balance, '.$skipped.' skipped because Connect is not ready.');

            return self::SUCCESS;
        }

        $this->info('Transferred '.$transferred.' payout(s); waiting on balance '.$waitingOnBalance.'; skipped '.$skipped.'; failed '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function formatCents(int $cents, string $currency): string
    {
        return '$'.number_format($cents / 100, 2).' '.strtolower($currency);
    }

    private function exceptionDetail(PaymentException $exception): string
    {
        $detail = trim($exception->getMessage());

        return $detail !== '' ? $detail : $exception->userMessage;
    }
}
