<?php

namespace App\Console\Commands;

use App\Models\CareBookingTimeCorrection;
use App\Services\Booking\CareBookingTimeCorrectionService;
use Illuminate\Console\Command;

class ProcessTimeCorrections extends Command
{
    protected $signature = 'homecare:process-time-corrections {--dry-run : List actions without sending reminders or escalating}';

    protected $description = 'Send time-correction reminders and escalate corrections that can no longer wait safely.';

    public function handle(CareBookingTimeCorrectionService $corrections): int
    {
        if (! $corrections->enabled()) {
            $this->info('Visit time corrections are disabled.');

            return self::SUCCESS;
        }

        $firstHours = max(1, (int) config('marketplace.time_corrections.first_reminder_hours', 12));
        $secondHours = max($firstHours, (int) config('marketplace.time_corrections.second_reminder_hours', 24));
        $escalationHours = max($secondHours, (int) config('marketplace.time_corrections.escalation_hours', 48));
        $processed = 0;

        CareBookingTimeCorrection::query()
            ->where('status', CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING)
            ->where('processing_started_at', '<=', now()->subMinutes(10))
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($corrections, &$processed): void {
                foreach ($items as $correction) {
                    $action = (int) $correction->processing_attempts >= 3
                        ? 'escalate stalled finalization'
                        : 'retry approved finalization';

                    $this->line('Correction #'.$correction->id.': '.$action.'.');
                    $processed++;
                    if ($this->option('dry-run')) {
                        continue;
                    }

                    if ((int) $correction->processing_attempts >= 3) {
                        $corrections->escalate(
                            $correction,
                            'LoLo automation',
                            'The approved time correction could not be finalized automatically after three safe attempts.'
                        );

                        continue;
                    }

                    $retried = $corrections->retryApprovedProcessing($correction);
                    if ($retried->status === CareBookingTimeCorrection::STATUS_APPROVED_PROCESSING
                        && (int) $retried->processing_attempts >= 3) {
                        $corrections->escalate(
                            $retried,
                            'LoLo automation',
                            'The approved time correction could not be finalized automatically after three safe attempts.'
                        );
                    }
                }
            });

        CareBookingTimeCorrection::query()
            ->with(['booking.payment'])
            ->whereIn('status', [
                CareBookingTimeCorrection::STATUS_PENDING_FAMILY,
                CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED,
            ])
            ->orderBy('submitted_at')
            ->chunkById(100, function ($items) use ($corrections, $firstHours, $secondHours, $escalationHours, &$processed): void {
                foreach ($items as $correction) {
                    $ageHours = $correction->submitted_at?->diffInHours(now()) ?? 0;
                    $authorizationUrgent = $correction->booking?->payment?->authorization_expires_at
                        && $correction->booking->payment->authorization_expires_at->lte(now()->addHours(6));

                    $action = match (true) {
                        $ageHours >= $escalationHours || $authorizationUrgent => 'escalate',
                        $ageHours >= $secondHours && ! $correction->second_reminded_at => 'second reminder',
                        $ageHours >= $firstHours && ! $correction->first_reminded_at => 'first reminder',
                        default => null,
                    };

                    if (! $action) {
                        continue;
                    }

                    $this->line('Correction #'.$correction->id.': '.$action.'.');
                    $processed++;
                    if ($this->option('dry-run')) {
                        continue;
                    }

                    if ($action === 'escalate') {
                        $corrections->escalate(
                            $correction,
                            'LoLo automation',
                            $authorizationUrgent
                                ? 'The payment authorization is nearing expiration before the time correction was resolved.'
                                : 'The time correction was not resolved within the response window.'
                        );
                    } else {
                        $corrections->sendReminder($correction, $action === 'second reminder');
                    }
                }
            });

        $this->info($processed.' time-correction action(s) processed.');

        return self::SUCCESS;
    }
}
