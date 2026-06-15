<?php

namespace App\Console\Commands;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBookingPayment;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Console\Command;

class RetryPayoutTransfers extends Command
{
    protected $signature = 'homecare:retry-payout-transfers
        {--dry-run : List eligible payments without creating Stripe transfers}
        {--limit=100 : Maximum number of payments to inspect}';

    protected $description = 'Retry captured caregiver payout transfers once Stripe Connect accounts are ready.';

    public function handle(BookingPaymentService $payments): int
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
        $failed = 0;

        foreach ($candidates as $payment) {
            $booking = $payment->booking;
            $profile = $booking?->caregiver?->caregiverProfile;

            if (! $booking || ! $profile?->stripeConnectIsReady() || ! $profile->stripe_connect_account_id) {
                $skipped++;
                $this->line('Skipped payment #'.$payment->id.': caregiver payout account is not ready.');

                continue;
            }

            $ready++;
            $amount = number_format(((int) $payment->caregiver_amount_cents) / 100, 2);
            $this->line('Ready payment #'.$payment->id.' for booking #'.$booking->id.' ($'.$amount.' '.$payment->currency.').');

            if ($dryRun) {
                continue;
            }

            try {
                $updated = $payments->retryTransfer($payment);
            } catch (PaymentException $exception) {
                $failed++;
                $this->warn('Payment #'.$payment->id.' transfer retry failed: '.$exception->userMessage);

                continue;
            }

            if ($updated->status === CareBookingPayment::STATUS_TRANSFERRED) {
                $transferred++;
                $this->info('Transferred payment #'.$updated->id.' using '.$updated->stripe_transfer_id.'.');
            }
        }

        if ($dryRun) {
            $this->info($ready.' ready transfer(s), '.$skipped.' skipped because Connect is not ready.');

            return self::SUCCESS;
        }

        $this->info('Transferred '.$transferred.' payout(s); skipped '.$skipped.'; failed '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
