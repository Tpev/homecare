<?php

namespace App\Services\ContinuousCoverage;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageReplacementCase;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftOffer;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContinuousCoverageOperationsService
{
    public function __construct(
        private readonly ContinuousCoverageAccess $access,
        private readonly ContinuousCoverageScheduleService $schedule,
        private readonly ContinuousCoverageBookingAdapter $bookings,
        private readonly BookingPaymentService $payments,
        private readonly ContinuousCoverageNotificationService $notifications,
        private readonly ContinuousCoverageEventRecorder $events,
    ) {}

    /** @return array{plans:int,shifts_created:int,bookings_linked:int,payments_prepared:int,failures:int} */
    public function process(): array
    {
        $counts = ['plans' => 0, 'shifts_created' => 0, 'bookings_linked' => 0, 'payments_prepared' => 0, 'failures' => 0];
        if (! $this->access->enabled()) {
            return $counts;
        }

        $through = now()->addWeeks((int) config('marketplace.continuous_coverage.generation_weeks', 6));
        ContinuousCoveragePlan::query()
            ->where('status', ContinuousCoveragePlan::STATUS_ACTIVE)
            ->where('starts_on', '<=', $through->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))
            ->chunkById(50, function ($plans) use (&$counts, $through): void {
                foreach ($plans as $plan) {
                    $plan->loadMissing('family');
                    if (! $this->access->allows($plan->family)) {
                        continue;
                    }
                    $counts['plans']++;
                    $counts['shifts_created'] += $this->schedule->generate($plan, $through);
                }
            });

        $horizon = now()->addHours((int) config('marketplace.continuous_coverage.booking_horizon_hours', 48));
        ContinuousCoverageShift::query()
            ->with(['plan.family', 'assignedCaregiver', 'booking.payment'])
            ->whereHas('plan', fn ($query) => $query->where('status', ContinuousCoveragePlan::STATUS_ACTIVE))
            ->whereIn('status', [ContinuousCoverageShift::STATUS_CONFIRMED, ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION])
            ->where('scheduled_start_at', '>', now()->subHours(2))
            ->where('scheduled_start_at', '<=', $horizon)
            ->chunkById(100, function ($shifts) use (&$counts): void {
                foreach ($shifts as $shift) {
                    if (! $this->access->allows($shift->plan->family)) {
                        continue;
                    }
                    try {
                        $booking = $shift->care_booking_id
                            ? $shift->booking
                            : $this->bookings->linkConfirmedShift($shift);
                        if (! $shift->care_booking_id) {
                            $counts['bookings_linked']++;
                            $shift->refresh();
                        }

                        $paymentStatus = $booking?->payment?->status;
                        if (! in_array($paymentStatus, [
                            CareBookingPayment::STATUS_AUTHORIZED,
                            CareBookingPayment::STATUS_CAPTURED,
                            CareBookingPayment::STATUS_TRANSFERRED,
                            CareBookingPayment::STATUS_TRANSFER_FAILED,
                            CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
                            CareBookingPayment::STATUS_REFUNDED,
                        ], true)) {
                            $this->payments->authorizeForBooking($booking);
                            $counts['payments_prepared']++;
                            $booking->refresh()->load('payment');
                            $this->events->record($shift->plan, 'payment_prepared', shift: $shift, payload: [
                                'care_booking_id' => $booking->id,
                                'payment_status' => $booking->payment?->status,
                            ]);
                        }
                        if ($shift->status === ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION) {
                            $shift->forceFill(['status' => ContinuousCoverageShift::STATUS_CONFIRMED])->save();
                        }
                    } catch (Throwable $exception) {
                        $counts['failures']++;
                        $shift->forceFill([
                            'status' => ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
                            'metadata' => array_merge((array) $shift->metadata, [
                                'last_operational_error' => class_basename($exception),
                                'last_operational_error_at' => now()->toIso8601String(),
                            ]),
                        ])->save();
                        $this->events->record($shift->plan, 'booking_or_payment_failed', shift: $shift, payload: [
                            'exception' => class_basename($exception),
                        ]);
                        $this->notifications->paymentAttention($shift);
                        Log::warning('Continuous Coverage booking preparation failed.', [
                            'continuous_coverage_plan_id' => $shift->continuous_coverage_plan_id,
                            'continuous_coverage_shift_id' => $shift->id,
                            'exception' => class_basename($exception),
                        ]);
                    }
                }
            });

        $this->expireOffers();
        $this->expireLaneOffers();
        $this->syncBookingStates();
        $this->sendFinalizedEarnings();
        $this->sendReminders();

        return $counts;
    }

    private function expireOffers(): void
    {
        $offerIds = ContinuousCoverageShiftOffer::query()
            ->where('status', ContinuousCoverageShiftOffer::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->pluck('id');
        foreach ($offerIds as $offerId) {
            $offer = ContinuousCoverageShiftOffer::query()
                ->with('replacementCase.shift.plan.family')
                ->find($offerId);
            if (! $offer) {
                continue;
            }
            if (! $this->access->allows($offer->replacementCase->shift->plan->family)) {
                continue;
            }
            $unresolvedShift = DB::transaction(function () use ($offerId): ?ContinuousCoverageShift {
                $lockedOffer = ContinuousCoverageShiftOffer::query()->lockForUpdate()->find($offerId);
                if (! $lockedOffer
                    || $lockedOffer->status !== ContinuousCoverageShiftOffer::STATUS_PENDING
                    || ! $lockedOffer->expires_at
                    || $lockedOffer->expires_at->isFuture()) {
                    return null;
                }
                $case = ContinuousCoverageReplacementCase::query()
                    ->lockForUpdate()
                    ->with('shift.plan')
                    ->findOrFail($lockedOffer->replacement_case_id);
                $lockedOffer->forceFill([
                    'status' => ContinuousCoverageShiftOffer::STATUS_EXPIRED,
                    'responded_at' => now(),
                ])->save();
                $this->events->record($case->shift->plan, 'replacement_offer_expired', shift: $case->shift, payload: [
                    'replacement_case_id' => $case->id,
                    'offer_id' => $lockedOffer->id,
                ]);
                if ($case->status !== ContinuousCoverageReplacementCase::STATUS_OPEN
                    || $case->offers()->where('status', ContinuousCoverageShiftOffer::STATUS_PENDING)->exists()) {
                    return null;
                }
                $case->forceFill(['status' => ContinuousCoverageReplacementCase::STATUS_UNRESOLVED])->save();

                return $case->shift;
            });
            if ($unresolvedShift) {
                $this->notifications->gapUnresolved($unresolvedShift);
            }
        }
    }

    private function expireLaneOffers(): void
    {
        ContinuousCoverageShiftTemplate::query()
            ->with('plan.family')
            ->where('status', ContinuousCoverageShiftTemplate::STATUS_OFFERED)
            ->where('offer_expires_at', '<=', now())
            ->chunkById(100, function ($templates): void {
                foreach ($templates as $template) {
                    if (! $this->access->allows($template->plan->family)) {
                        continue;
                    }
                    DB::transaction(function () use ($template): void {
                        $locked = ContinuousCoverageShiftTemplate::query()
                            ->lockForUpdate()
                            ->with('plan')
                            ->findOrFail($template->id);
                        if ($locked->status !== ContinuousCoverageShiftTemplate::STATUS_OFFERED
                            || ! $locked->offer_expires_at
                            || $locked->offer_expires_at->isFuture()) {
                            return;
                        }
                        $locked->forceFill([
                            'status' => ContinuousCoverageShiftTemplate::STATUS_EXPIRED,
                            'offer_expires_at' => null,
                        ])->save();
                        $locked->shifts()
                            ->where('scheduled_start_at', '>=', now())
                            ->whereNull('care_booking_id')
                            ->where('status', ContinuousCoverageShift::STATUS_OFFER_PENDING)
                            ->update(['status' => ContinuousCoverageShift::STATUS_UNCOVERED, 'updated_at' => now()]);
                        $this->events->record($locked->plan, 'recurring_lane_offer_expired', payload: [
                            'shift_template_id' => $locked->id,
                            'roster_member_id' => $locked->roster_member_id,
                        ]);
                    });
                }
            });
    }

    private function syncBookingStates(): void
    {
        ContinuousCoverageShift::query()
            ->with(['booking', 'plan.family', 'assignedCaregiver'])
            ->whereNotNull('care_booking_id')
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
                ContinuousCoverageShift::STATUS_IN_PROGRESS,
            ])
            ->chunkById(100, function ($shifts): void {
                foreach ($shifts as $shift) {
                    if (! $this->access->allows($shift->plan->family)) {
                        continue;
                    }
                    $newStatus = match ($shift->booking?->status) {
                        CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED => ContinuousCoverageShift::STATUS_IN_PROGRESS,
                        CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED => ContinuousCoverageShift::STATUS_COMPLETED,
                        CareBooking::STATUS_CANCELLED => ContinuousCoverageShift::STATUS_CANCELLED,
                        default => null,
                    };
                    if (! $newStatus || $newStatus === $shift->status) {
                        continue;
                    }
                    $shift->forceFill([
                        'status' => $newStatus,
                        'completed_at' => $newStatus === ContinuousCoverageShift::STATUS_COMPLETED ? ($shift->booking->completed_at ?: now()) : $shift->completed_at,
                        'cancelled_at' => $newStatus === ContinuousCoverageShift::STATUS_CANCELLED ? ($shift->booking->cancelled_at ?: now()) : $shift->cancelled_at,
                    ])->save();
                    if ($newStatus === ContinuousCoverageShift::STATUS_COMPLETED) {
                        $this->notifications->shiftCompleted($shift);
                    }
                }
            });
    }

    private function sendReminders(): void
    {
        ContinuousCoverageShift::query()
            ->with(['plan', 'assignedCaregiver', 'booking'])
            ->whereIn('status', [ContinuousCoverageShift::STATUS_CONFIRMED, ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION])
            ->whereBetween('scheduled_start_at', [now()->addHours(23), now()->addHours(25)])
            ->whereNotNull('assigned_caregiver_user_id')
            ->each(function (ContinuousCoverageShift $shift): void {
                if ($this->access->allows($shift->plan->family)) {
                    $this->notifications->shiftReminder($shift);
                }
            });
    }

    private function sendFinalizedEarnings(): void
    {
        ContinuousCoverageShift::query()
            ->with(['plan.family', 'assignedCaregiver', 'booking.payment'])
            ->where('status', ContinuousCoverageShift::STATUS_COMPLETED)
            ->whereHas('booking.payment', fn ($query) => $query->whereIn('status', [
                CareBookingPayment::STATUS_CAPTURED,
                CareBookingPayment::STATUS_TRANSFERRED,
                CareBookingPayment::STATUS_TRANSFER_FAILED,
            ]))
            ->where('completed_at', '>=', now()->subDays(14))
            ->chunkById(100, function ($shifts): void {
                foreach ($shifts as $shift) {
                    if ($this->access->allows($shift->plan->family)) {
                        $this->notifications->earningsFinalized($shift);
                    }
                }
            });
    }
}
