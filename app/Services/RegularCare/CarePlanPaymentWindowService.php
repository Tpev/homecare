<?php

namespace App\Services\RegularCare;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CarePlanPaymentWindowService
{
    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly CarePlanHealthService $health,
    ) {}

    public function authorizationWindowHours(): int
    {
        return max(1, (int) config('marketplace.regular_care.authorization_window_hours', 48));
    }

    public function dueBookingsQuery(?int $hours = null): Builder
    {
        $hours = max(1, $hours ?? $this->authorizationWindowHours());

        return CareBooking::query()
            ->with(['carePlan.family', 'family', 'caregiver.caregiverProfile', 'application', 'payment'])
            ->whereNotNull('care_plan_id')
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->whereBetween('scheduled_start_at', [
                now()->subMinutes((int) config('marketplace.regular_care.check_in_closes_minutes_after', 120)),
                now()->addHours($hours),
            ])
            ->whereHas('carePlan', fn (Builder $query) => $query->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION]))
            ->where(function (Builder $query) {
                $query->whereDoesntHave('payment')
                    ->orWhereHas('payment', function (Builder $paymentQuery) {
                        $paymentQuery->where(function (Builder $statusQuery) {
                            $statusQuery->whereIn('status', [
                                CareBookingPayment::STATUS_DRAFT,
                                CareBookingPayment::STATUS_REAUTH_REQUIRED,
                                CareBookingPayment::STATUS_CANCELLED,
                            ])->orWhere(function (Builder $expiredQuery) {
                                $expiredQuery->where('status', CareBookingPayment::STATUS_AUTHORIZED)
                                    ->whereNotNull('authorization_expires_at')
                                    ->where('authorization_expires_at', '<=', now());
                            });
                        });
                    });
            });
    }

    /**
     * @return array{ready:Collection<int,CareBooking>,needs_action:Collection<int,CareBooking>}
     */
    public function preparePlan(CarePlan $plan, bool $dryRun = false, ?int $hours = null): array
    {
        $bookings = $this->dueBookingsQuery($hours)->where('care_plan_id', $plan->id)->get();

        return $this->prepareBookings($bookings, $dryRun);
    }

    /**
     * @param  Collection<int,CareBooking>  $bookings
     * @return array{ready:Collection<int,CareBooking>,needs_action:Collection<int,CareBooking>}
     */
    public function prepareBookings(Collection $bookings, bool $dryRun = false): array
    {
        $ready = collect();
        $needsAction = collect();

        foreach ($bookings as $booking) {
            if ($dryRun) {
                $ready->push($booking);

                continue;
            }

            try {
                $forceReauthorize = $booking->payment?->status === CareBookingPayment::STATUS_AUTHORIZED
                    && $booking->payment?->authorization_expires_at?->isPast();
                $this->payments->authorizeForBooking($booking, $forceReauthorize);
                $ready->push($booking->fresh('payment'));
            } catch (PaymentException $exception) {
                $needsAction->push($booking->fresh('payment'));
            } finally {
                $this->health->reconcileForBooking($booking);
            }
        }

        return ['ready' => $ready, 'needs_action' => $needsAction];
    }
}
