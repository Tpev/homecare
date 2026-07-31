<?php

namespace App\Console\Commands;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Services\Booking\BookingTrustService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Support\MarketplaceEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoApproveTimesheets extends Command
{
    protected $signature = 'homecare:auto-approve-timesheets {--dry-run : List eligible bookings without approving payment}';

    protected $description = 'Auto-approve completed caregiver timesheets after 24 hours when the family has not disputed them.';

    public function handle(BookingPaymentService $payments, BookingTrustService $trust, MarketplaceNotificationService $notifications): int
    {
        $cutoff = now()->subHours(24);

        $bookings = CareBooking::query()
            ->with(['family', 'caregiver', 'caregiver.caregiverProfile', 'application', 'payment'])
            ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
            ->whereNull('family_confirmed_at')
            ->whereNull('dispute_opened_at')
            ->whereDoesntHave('timeCorrections', fn ($query) => $query->whereIn('status', CareBookingTimeCorrection::activeStatuses()))
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
            try {
                $freshBooking = DB::transaction(function () use ($booking, $payments, $trust): ?CareBooking {
                    $lockedBooking = CareBooking::query()
                        ->with(['family', 'caregiver', 'caregiver.caregiverProfile', 'application', 'payment'])
                        ->whereKey($booking->id)
                        ->whereNull('family_confirmed_at')
                        ->whereNull('dispute_opened_at')
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedBooking || CareBookingTimeCorrection::query()
                        ->where('care_booking_id', $lockedBooking->id)
                        ->whereIn('status', CareBookingTimeCorrection::activeStatuses())
                        ->exists()) {
                        return null;
                    }

                    $payments->captureForBooking($lockedBooking);
                    $lockedBooking->forceFill(['family_confirmed_at' => now()])->save();
                    $trust->recordEvent(
                        $lockedBooking,
                        null,
                        'system',
                        'timesheet_auto_confirmed_after_24h',
                        [
                            'timesheet_submitted_at' => $lockedBooking->timesheet_submitted_at?->toIso8601String(),
                            'auto_approved_after_hours' => 24,
                        ]
                    );

                    return $lockedBooking->fresh();
                });
            } catch (PaymentException $exception) {
                $failed++;
                $this->warn('Booking #'.$booking->id.' was not auto-approved: '.$exception->userMessage);

                continue;
            }

            if (! $freshBooking) {
                continue;
            }

            $freshBooking->refresh();
            $trust->recomputeReliabilityForBooking($freshBooking);

            foreach ([
                [$freshBooking->family, route('family.requests.show', $freshBooking->care_request_id), 'Your visit timesheet was approved automatically after 24 hours.'],
                [$freshBooking->caregiver, route('care-requests.apply', $freshBooking->care_request_id), 'Your timesheet was approved automatically after 24 hours.'],
            ] as [$recipient, $url, $body]) {
                if (! $recipient) {
                    continue;
                }
                $notifications->notify(
                    recipients: $recipient,
                    eventKey: MarketplaceEvent::TIMESHEET_AUTO_APPROVED,
                    title: 'Timesheet approved',
                    body: $body,
                    url: $url,
                    payload: ['care_booking_id' => $freshBooking->id],
                    subject: $freshBooking,
                    dedupeKey: 'timesheet-auto-approved:booking-'.$freshBooking->id.'-user-'.$recipient->id
                );
            }

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
