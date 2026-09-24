<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Booking\PrepaidVisitAdjustmentService;
use Illuminate\Console\Command;
use Throwable;

class AdjustPrepaidVisit extends Command
{
    protected $signature = 'homecare:adjust-prepaid-visit {booking}
        {--ticket= : Exact support ticket for the approved correction}
        {--time-correction= : Latest family-approved time correction ID}
        {--admin= : Administrator authorizing this operation}
        {--apply : Apply the approved additional charge; otherwise preview only}
        {--expected-state= : Exact fingerprint from the preview}
        {--expected-additional-cents= : Exact approved additional charge, in cents}
        {--request-id= : Stable UUID; never replace it after an uncertain outcome}
        {--reason= : Reason for the approved adjustment}
        {--provider-verified : Stripe charge, refunds, disputes and transfers were reviewed}
        {--reconcile : With --apply, read the existing intent and finish its receipt; never charge again}';

    protected $description = 'Preview or explicitly apply approved extra hours to a held prepaid visit; never transfer, notify, or resolve';

    public function handle(PrepaidVisitAdjustmentService $adjustment): int
    {
        try {
            $admin = User::query()->findOrFail((int) $this->option('admin'));
            $booking = (int) $this->argument('booking');
            $ticket = (int) $this->option('ticket');
            $correction = (int) $this->option('time-correction');
            if (! $this->option('apply')) {
                if ($this->option('reconcile')) {
                    $this->error('Reconciliation writes local records and requires --apply. No Stripe charge will be created.');

                    return self::FAILURE;
                }
                $this->line(json_encode($adjustment->preview($booking, $ticket, $correction, $admin), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('READ-ONLY PREVIEW. No records, payments, or messages changed.');

                return self::SUCCESS;
            }
            $receipt = $this->option('reconcile')
                ? $adjustment->reconcile($booking, $ticket, $correction, $admin, (string) $this->option('request-id'), (bool) $this->option('provider-verified'))
                : $adjustment->apply($booking, $ticket, $correction, $admin, (string) $this->option('request-id'),
                    (string) $this->option('expected-state'), (int) $this->option('expected-additional-cents'),
                    (string) $this->option('reason'), (bool) $this->option('provider-verified'));
            $this->line(json_encode($receipt->only(['id', 'client_request_id', 'status', 'care_booking_id', 'support_ticket_id',
                'time_correction_request_id', 'previous_charge_cents', 'target_charge_cents', 'payment_delta_cents', 'last_error']), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->warn('Transfers remain held. No messages sent; ticket remains open. Release requires separate review and approval.');
            if (! $receipt->succeeded()) {
                $this->error('Keep this receipt UUID. Do not create another charge. Review the outcome before explicit reconciliation.');
            }

            return $receipt->succeeded() ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
