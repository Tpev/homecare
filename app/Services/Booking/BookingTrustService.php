<?php

namespace App\Services\Booking;

use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingTaskCheck;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CaregiverProfile;
use App\Models\User;
use App\Support\MarketplacePricing;
use Illuminate\Support\Carbon;

class BookingTrustService
{
    /**
     * @return array<string,mixed>
     */
    public function buildAgreementSnapshot(CareRequest $request, CareRequestApplication $application): array
    {
        return [
            'care_request_id' => $request->id,
            'application_id' => $application->id,
            'request_type' => $request->request_type,
            'title' => $request->title,
            'scope_of_work' => $request->scope_of_work,
            'time_expectations' => $request->time_expectations,
            'home_access_notes' => $request->home_access_notes,
            'address' => [
                'line1' => $request->address_line1,
                'line2' => $request->address_line2,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
            ],
            'schedule' => [
                'scheduled_start_at' => optional($request->requested_start_at)?->toIso8601String(),
                'scheduled_end_at' => optional($request->requested_end_at)?->toIso8601String(),
                'recurring_days' => $request->recurring_days,
                'recurring_start_time' => $request->recurring_start_time,
                'recurring_end_time' => $request->recurring_end_time,
            ],
            'proposed_rate' => app(MarketplacePricing::class)->hourlyRateForRequest(
                $request,
                (float) $application->proposed_rate
            ),
            'services' => $request->tasks()
                ->pluck('care_tasks.name')
                ->values()
                ->all(),
            'recipient' => [
                'name' => $request->recipient?->full_name,
                'relationship_to_family' => $request->recipient?->relationship_to_family,
                'care_notes' => $request->recipient?->care_notes,
            ],
            'captured_at' => now()->toIso8601String(),
        ];
    }

    public function seedTaskChecks(CareBooking $booking, CareRequest $request): void
    {
        foreach ($request->tasks as $task) {
            CareBookingTaskCheck::query()->updateOrCreate(
                [
                    'care_booking_id' => $booking->id,
                    'care_task_id' => $task->id,
                ],
                [
                    'label' => $task->name,
                    'notes' => $task->pivot?->task_note,
                ]
            );
        }
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function recordEvent(
        CareBooking $booking,
        ?int $actorUserId,
        string $actorRole,
        string $eventType,
        array $payload = []
    ): void {
        CareBookingEvent::query()->create([
            'care_booking_id' => $booking->id,
            'actor_user_id' => $actorUserId,
            'actor_role' => $actorRole,
            'event_type' => $eventType,
            'payload' => $payload,
            'happened_at' => now(),
        ]);
    }

    public function markLateCancelFlag(CareBooking $booking): bool
    {
        if (! $booking->scheduled_start_at) {
            return false;
        }

        $minutesBefore = now()->diffInMinutes($booking->scheduled_start_at, false);

        return $minutesBefore <= 24 * 60;
    }

    public function recomputeReliabilityForBooking(CareBooking $booking): void
    {
        $this->recomputeCaregiverReliability((int) $booking->caregiver_user_id);
        $this->recomputeFamilyReliability((int) $booking->family_user_id);
    }

    private function recomputeCaregiverReliability(int $caregiverUserId): void
    {
        $profile = CaregiverProfile::query()
            ->where('user_id', $caregiverUserId)
            ->first();

        if (! $profile) {
            return;
        }

        $bookings = CareBooking::query()
            ->where('caregiver_user_id', $caregiverUserId)
            ->whereIn('status', [
                CareBooking::STATUS_COMPLETED,
                CareBooking::STATUS_REVIEWED,
                CareBooking::STATUS_CANCELLED,
                CareBooking::STATUS_DISPUTED,
            ])
            ->get();

        $completed = $bookings->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])->count();
        $caregiverCancels = $bookings
            ->where('status', CareBooking::STATUS_CANCELLED)
            ->where('cancelled_by_user_id', $caregiverUserId)
            ->count();
        $noShows = $bookings->where('no_show_flag', true)->count();
        $disputes = $bookings->whereNotNull('dispute_opened_at')->count();
        $onTime = $bookings->filter(function (CareBooking $booking): bool {
            if (! $booking->started_at || ! $booking->scheduled_start_at) {
                return false;
            }

            return $booking->started_at->lessThanOrEqualTo(
                Carbon::parse($booking->scheduled_start_at)->addMinutes(15)
            );
        })->count();
        $late = max($completed - $onTime, 0);

        $score = 100 - ($caregiverCancels * 18) - ($noShows * 25) - ($disputes * 10) - ($late * 5) + min(10, $completed * 0.5);
        $score = max(30, min(100, $score));

        $profile->forceFill([
            'reliability_score' => round($score, 2),
            'completed_bookings_count' => $completed,
            'cancellation_count' => $caregiverCancels + $noShows,
            'dispute_count' => $disputes,
            'on_time_check_in_count' => $onTime,
        ])->save();
    }

    private function recomputeFamilyReliability(int $familyUserId): void
    {
        $family = User::query()->find($familyUserId);
        if (! $family) {
            return;
        }

        $bookings = CareBooking::query()
            ->where('family_user_id', $familyUserId)
            ->whereIn('status', [
                CareBooking::STATUS_COMPLETED,
                CareBooking::STATUS_REVIEWED,
                CareBooking::STATUS_CANCELLED,
                CareBooking::STATUS_DISPUTED,
            ])
            ->get();

        $completed = $bookings->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])->count();
        $familyCancels = $bookings
            ->where('status', CareBooking::STATUS_CANCELLED)
            ->where('cancelled_by_user_id', $familyUserId)
            ->count();
        $familyDisputes = $bookings
            ->where('dispute_opened_by_user_id', $familyUserId)
            ->count();

        $score = 100 - ($familyCancels * 14) - ($familyDisputes * 8) + min(8, $completed * 0.4);
        $score = max(35, min(100, $score));

        $family->forceFill([
            'family_reliability_score' => round($score, 2),
            'family_completed_bookings_count' => $completed,
            'family_cancellation_count' => $familyCancels,
            'family_dispute_count' => $familyDisputes,
        ])->save();
    }
}
