<?php

namespace App\Services\RegularCare;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarePlanOperationsService
{
    public function __construct(
        private readonly CarePlanOccurrenceService $occurrences,
        private readonly CarePlanPaymentWindowService $paymentWindow,
        private readonly BookingPaymentService $payments,
        private readonly BookingTrustService $trust,
        private readonly MarketplaceNotificationService $notifications,
        private readonly CarePlanHealthService $health,
    ) {}

    public function pause(CarePlan $plan, User $admin, string $reason): CarePlan
    {
        $reason = $this->validatedReason($admin, $reason);
        [$plan, $changed] = DB::transaction(function () use ($plan, $admin, $reason): array {
            $lockedPlan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status === CarePlan::STATUS_PAUSED) {
                return [$lockedPlan, false];
            }
            if (! $lockedPlan->isLive()) {
                throw ValidationException::withMessages(['operationsReason' => 'Only a live regular-care plan can be paused.']);
            }

            $previousStatus = $lockedPlan->status;
            $lockedPlan->forceFill([
                'status' => CarePlan::STATUS_PAUSED,
                'pause_starts_on' => now()->toDateString(),
                'resumes_on' => null,
                'paused_at' => now(),
                'last_error' => null,
            ])->save();
            $this->cancelFuture($lockedPlan, $admin, 'Operations paused regular care: '.$reason);
            $this->recordOperation($lockedPlan, $admin, 'admin_paused', $reason, $previousStatus);

            return [$lockedPlan, true];
        });
        if (! $changed) {
            return $this->health->reconcile($plan->fresh());
        }
        $this->notifyBoth($plan, MarketplaceEvent::REGULAR_CARE_PAUSED, 'Regular care paused by LoLo', 'LoLo operations paused this regular-care schedule.');

        return $this->health->reconcile($plan->fresh());
    }

    public function resume(CarePlan $plan, User $admin, string $reason): CarePlan
    {
        $reason = $this->validatedReason($admin, $reason);
        [$plan, $changed] = DB::transaction(function () use ($plan, $admin, $reason): array {
            $lockedPlan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status === CarePlan::STATUS_ACTIVE) {
                return [$lockedPlan, false];
            }
            if ($lockedPlan->status !== CarePlan::STATUS_PAUSED) {
                throw ValidationException::withMessages(['operationsReason' => 'Only a paused regular-care plan can be resumed.']);
            }

            $previousStatus = $lockedPlan->status;
            $lockedPlan->generatedBookings()
                ->where('status', CareBooking::STATUS_CANCELLED)
                ->where('scheduled_start_at', '>', now())
                ->where('cancellation_reason', 'like', 'Operations paused regular care:%')
                ->get()
                ->each(fn (CareBooking $booking) => $booking->forceFill([
                    'status' => CareBooking::STATUS_SCHEDULED,
                    'cancelled_at' => null,
                    'cancelled_by_user_id' => null,
                    'cancellation_reason' => null,
                ])->save());
            $lockedPlan->forceFill([
                'status' => CarePlan::STATUS_ACTIVE,
                'pause_starts_on' => null,
                'resumes_on' => null,
                'paused_at' => null,
                'last_error' => null,
            ])->save();
            $this->recordOperation($lockedPlan, $admin, 'admin_resumed', $reason, $previousStatus);

            return [$lockedPlan, true];
        });
        if (! $changed) {
            return $this->health->reconcile($plan->fresh());
        }
        $this->occurrences->materialize($plan->fresh());
        $this->paymentWindow->preparePlan($plan->fresh());
        $this->notifyBoth($plan, MarketplaceEvent::REGULAR_CARE_RESUMED, 'Regular care resumed by LoLo', 'LoLo operations resumed this regular-care schedule.');

        return $this->health->reconcile($plan->fresh());
    }

    public function end(CarePlan $plan, User $admin, string $reason): CarePlan
    {
        $reason = $this->validatedReason($admin, $reason);
        [$plan, $changed] = DB::transaction(function () use ($plan, $admin, $reason): array {
            $lockedPlan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status === CarePlan::STATUS_ENDED) {
                return [$lockedPlan, false];
            }
            if (! $lockedPlan->isLive()) {
                throw ValidationException::withMessages(['operationsReason' => 'Only a live regular-care plan can be ended.']);
            }

            $previousStatus = $lockedPlan->status;
            $lockedPlan->forceFill([
                'status' => CarePlan::STATUS_ENDED,
                'ended_at' => now(),
                'last_error' => null,
            ])->save();
            $this->cancelFuture($lockedPlan, $admin, 'Operations ended regular care: '.$reason);
            $this->recordOperation($lockedPlan, $admin, 'admin_ended', $reason, $previousStatus);

            return [$lockedPlan, true];
        });
        if (! $changed) {
            return $this->health->reconcile($plan->fresh());
        }
        $this->notifyBoth($plan, MarketplaceEvent::REGULAR_CARE_ENDED, 'Regular care ended by LoLo', 'LoLo operations ended this regular-care schedule.');

        return $this->health->reconcile($plan->fresh());
    }

    private function cancelFuture(CarePlan $plan, User $admin, string $reason): void
    {
        $plan->generatedBookings()
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->where('scheduled_start_at', '>', now())
            ->orderBy('scheduled_start_at')
            ->get()
            ->each(function (CareBooking $booking) use ($admin, $reason): void {
                $this->payments->cancelForBooking($booking);
                $booking->forceFill([
                    'status' => CareBooking::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $admin->id,
                    'cancellation_reason' => $reason,
                ])->save();
                $this->trust->recordEvent($booking, $admin->id, 'admin', 'regular_care_operations_cancelled_visit', ['reason' => $reason]);
            });
    }

    private function notifyBoth(CarePlan $plan, string $event, string $title, string $body): void
    {
        $plan->loadMissing(['family', 'caregiver']);
        foreach ([
            [$plan->family, route('family.care.show', $plan->id)],
            [$plan->caregiver, route('caregiver.regular-clients.index')],
        ] as [$recipient, $url]) {
            if (! $recipient) {
                continue;
            }
            $this->notifications->notify(
                recipients: $recipient,
                eventKey: $event,
                title: $title,
                body: $body,
                url: $url,
                payload: ['care_plan_id' => $plan->id],
                subject: $plan,
                dedupeKey: $event.':operations-plan-'.$plan->id.'-user-'.$recipient->id.'-'.now()->format('YmdHi')
            );
        }
    }

    private function recordOperation(CarePlan $plan, User $admin, string $eventType, string $reason, string $previousStatus): void
    {
        CarePlanEvent::query()->create([
            'care_plan_id' => $plan->id,
            'actor_user_id' => $admin->id,
            'event_type' => $eventType,
            'reason' => $reason,
            'payload' => [
                'previous_status' => $previousStatus,
                'new_status' => $plan->status,
            ],
        ]);
    }

    private function validatedReason(User $admin, string $reason): string
    {
        abort_unless($admin->isAdministrator(), 403);
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'operationsReason' => 'Enter an operational reason between 8 and 1,000 characters.',
            ]);
        }

        return $reason;
    }
}
