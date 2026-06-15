<?php

namespace App\Console\Commands;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Services\Booking\BookingTrustService;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Console\Command;

class AutoApproveTimesheets extends Command
{
    protected $signature = 'homecare:auto-approve-timesheets {--dry-run : List eligible bookings without approving payment}';

    protected $description = 'Auto-approve completed caregiver timesheets after 24 hours when the family has not disputed them.';

    public function handle(BookingPaymentService $payments, BookingTrustService $trust): int
    {
        $cutoff = now()->subHours(24);

        $bookings = CareBooking::query()
            ->with(['family', 'caregiver', 'caregiver.caregiverProfile', 'application', 'payment'])
            ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
            ->whereNull('family_confirmed_at')
            ->whereNull('dispute_opened_at')
            ->whereNotNull('timesheet_submitted_at')
            ->where('timesheet_submitted_at', '<=', $cutoff)
            ->orderBy('timesheet_submitted_at')
            ->get();

        if ($this->option('dry-run')) {
            $this->info($bookings->count().' timesheet(s) eligible for auto-approval.');
            $bookings->each(function (CareBooking $booking): void {
                $this->line('Booking #'.$booking->id.' submitted '.$booking->timesheet_submitted_at?->toDateTimeString());
            });

            return self::SUCCESS;
        }

        $approved = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            $freshBooking = CareBooking::query()
                ->with(['family', 'caregiver', 'caregiver.caregiverProfile', 'application', 'payment'])
                ->whereKey($booking->id)
                ->whereNull('family_confirmed_at')
                ->whereNull('dispute_opened_at')
                ->first();

            if (! $freshBooking) {
                continue;
            }

            try {
                $payments->captureForBooking($freshBooking);
            } catch (PaymentException $exception) {
                $failed++;
                $this->warn('Booking #'.$booking->id.' was not auto-approved: '.$exception->userMessage);

                continue;
            }

            $freshBooking->forceFill([
                'family_confirmed_at' => now(),
            ])->save();

            $trust->recordEvent(
                $freshBooking,
                null,
                'system',
                'timesheet_auto_confirmed_after_24h',
                [
                    'timesheet_submitted_at' => $freshBooking->timesheet_submitted_at?->toIso8601String(),
                    'auto_approved_after_hours' => 24,
                ]
            );
            $freshBooking->refresh();
            $trust->recomputeReliabilityForBooking($freshBooking);

            $approved++;
            $this->line('Auto-approved booking #'.$booking->id.'.');
        }

        $this->info('Auto-approved '.$approved.' timesheet(s).');

        if ($failed > 0) {
            $this->warn($failed.' timesheet(s) still need payment/support attention.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
