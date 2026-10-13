<?php

namespace App\Services\Family;

use App\Models\CareArrangement;
use App\Models\CareArrangementNeed;
use App\Models\FamilyAccount;
use App\Services\RegularCare\SharedCareService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SharedCarePresentationService
{
    /** Care requirements without a booking, alongside the existing booking calendar. */
    public function calendarNeeds(FamilyAccount $account, Carbon $from, Carbon $until, ?string $careType = null, ?string $recipient = null): Collection
    {
        if ($careType && ! in_array($careType, ['regular', 'extra'])) {
            return collect();
        }

        return CareArrangement::forFamilyAccount($account)->where('status', 'active')
            ->with(['sourceRequest.recipient', 'plans.caregiver'])->get()
            ->filter(fn ($care) => ! $recipient || $care->sourceRequest->recipient?->full_name === $recipient)
            ->flatMap(function ($care) use ($from, $until, $careType) {
                return $this->rows($care, $from, $until->copy()->subSecond())
                    ->filter(fn ($row) => ! $row['booking'] && $row['need']->status === 'needed'
                        && $row['need']->starts_at->lt($until) && $row['need']->ends_at->gt($from) && $row['need']->ends_at->isFuture()
                        && (! $careType || $row['need']->kind === ($careType === 'extra' ? 'extra' : 'regular')))
                    ->map(fn ($row) => [
                        'id' => 'shared-need-'.$row['need']->id,
                        'is_care_need' => true,
                        'starts_at' => $row['need']->starts_at,
                        'ends_at' => $row['need']->ends_at,
                        'recipient' => $care->sourceRequest->recipient?->full_name ?: $care->title,
                        'headline' => $care->sourceRequest->recipient?->full_name ?: $care->title,
                        'caregiver' => $row['person']?->name ?: 'Caregiver needed',
                        'caregiver_user' => $row['person'],
                        'type_label' => $row['need']->kind === 'extra' ? 'Extra care requested' : 'Recurring care requested',
                        'status' => ['label' => $row['state'], 'tone' => $row['tone'] === 'pending' ? 'blue' : 'amber'],
                        'payment_needs_action' => false,
                        'location' => $care->sourceRequest->city,
                        'reference' => 'These requested hours are not yet booked.',
                        'action_url' => route('family.shared-care.show', ['arrangement' => $care->id,
                            'week' => $row['need']->starts_at->copy()->setTimezone($care->timezone)->startOfWeek()->toDateString(),
                            'visit' => $row['need']->id]),
                    ]);
            })->values();
    }

    public function rows(CareArrangement $care, Carbon $from, Carbon $through): Collection
    {
        $care->loadMissing(['plans.caregiver.caregiverProfile']);

        return app(SharedCareService::class)->syncNeeds($care, $from, $through)->map(function (CareArrangementNeed $need) use ($care) {
            $booking = $need->bookings->where('status', '!=', 'cancelled')->sortByDesc('id')->first();
            $offer = $need->offers->where('status', 'pending')->sortByDesc('id')->first();
            $expiredOffer = $offer && $need->starts_at->isPast();
            $localDate = $need->starts_at->copy()->setTimezone($care->timezone);
            $assigned = $care->plans->first(fn ($plan) => $plan->isLive()
                && in_array($localDate->dayOfWeek, $plan->schedule_days ?? [])
                && ! in_array($plan->id, $need->suppressed_plan_ids ?? [])
                && $localDate->toDateString() >= $plan->starts_on->toDateString()
                && (! $plan->ends_on || $localDate->toDateString() <= $plan->ends_on->toDateString()));
            $cancelled = $need->bookings->where('status', 'cancelled')->isNotEmpty();
            $beyondWindow = $need->starts_at->gt(now($care->timezone)->addWeeks(max(1, min(12, (int) config('marketplace.regular_care.visit_window_weeks', 6))))->endOfDay());
            $state = $need->status === 'waived' ? 'Care not needed' : ($booking ? match ($booking->status) {
                'in_progress', 'paused' => 'In progress',
                'completed', 'family_confirmed', 'reviewed' => 'Completed',
                'disputed' => 'Under review',
                default => $need->starts_at->isPast() ? 'Check arrival' : 'Booked',
            } : ($offer && ! $expiredOffer ? 'Waiting for reply' : ($assigned && ! $cancelled && $need->kind === 'regular'
                ? ($beyondWindow ? 'Planned' : 'Visit setup needs attention')
                : ($need->ends_at->isPast() ? 'Missed care' : 'Needs caregiver'))));
            $payment = $booking?->payment;
            $paymentLabel = match ($payment?->status) {
                'authorized' => $payment->authorization_expires_at?->isPast() ? 'Payment action needed' : 'Card authorized',
                'captured', 'transferred' => 'Paid',
                'transfer_failed' => 'Paid · earnings processing',
                'authorization_required', 'reauth_required', 'failed' => 'Payment action needed',
                'partially_refunded' => 'Partially refunded', 'refunded' => 'Refunded',
                default => $booking ? 'Card check before visit' : 'Not booked',
            };
            $tone = match ($state) {
                'Needs caregiver', 'Visit setup needs attention' => 'gap',
                'Check arrival', 'Missed care', 'Under review' => 'urgent',
                'Waiting for reply' => 'pending',
                'Care not needed' => 'quiet',
                default => 'booked',
            };
            $person = $booking?->caregiver ?: ($offer && ! $expiredOffer ? $offer->carePlan?->caregiver : ($state === 'Planned' ? $assigned?->caregiver : null));

            return compact('need', 'booking', 'offer', 'state', 'paymentLabel', 'expiredOffer', 'assigned', 'tone', 'person');
        });
    }
}
