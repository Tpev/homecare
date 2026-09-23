<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Booking\PrepaidVisitRecoveryService;
use Illuminate\Console\Command;
use Throwable;

class RecoverPrepaidVisit extends Command
{
    protected $signature = 'homecare:recover-prepaid-visit {booking}
        {--ticket= : Exact caregiver support ticket ID}
        {--admin= : Administrator authorizing this operation}
        {--release : Preview release after the actual visit and explicit family approval}
        {--apply : Apply the reviewed preview; otherwise this command is read-only}
        {--expected-state= : Exact fingerprint from the latest preview}
        {--request-id= : Stable UUID; reuse it if the apply result is uncertain}
        {--reason= : Operational reason and evidence for this exact visit}
        {--confirm-no-care : The caregiver confirmed no care was delivered}
        {--provider-verified : Stripe was checked for the charge, refunds, disputes, and transfers}';

    protected $description = 'Preview or explicitly apply an audited prepaid visit reset or hold release, without Stripe writes or messages';

    public function handle(PrepaidVisitRecoveryService $recovery): int
    {
        try {
            $admin = User::query()->findOrFail((int) $this->option('admin'));
            $bookingId = (int) $this->argument('booking');
            $ticketId = (int) $this->option('ticket');
            $release = (bool) $this->option('release');
            if (! $this->option('apply')) {
                $this->line(json_encode($recovery->preview($bookingId, $ticketId, $admin, $release), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('READ-ONLY PREVIEW. No records, payments, or messages changed.');

                return self::SUCCESS;
            }
            $receipt = $recovery->apply($bookingId, $ticketId, $admin, (string) $this->option('reason'),
                (string) $this->option('request-id'), (string) $this->option('expected-state'),
                (bool) $this->option('confirm-no-care'), (bool) $this->option('provider-verified'), $release);
            $this->info('Correction #'.$receipt->id.' '.$receipt->status.'. No Stripe writes or messages sent.');
            $this->warn($release ? 'Hold released: normal transfer retries can now move funds.' : 'Payment remains held. A separate reviewed release is required after the actual visit.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
