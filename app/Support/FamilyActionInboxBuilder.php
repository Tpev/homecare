<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTimeCorrection;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CompletedExtraVisitRequest;
use App\Models\FamilyAccount;
use App\Services\Booking\CareBookingTimeCorrectionService;
use Illuminate\Support\Collection;

class FamilyActionInboxBuilder
{
    public function __construct(
        private readonly CareBookingTimeCorrectionService $timeCorrections,
    ) {}

    /**
     * Build the family-facing source of truth for unresolved care actions.
     *
     * Notifications are intentionally not consulted here: reading a notification
     * must never make an unresolved approval or payment task disappear.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildForAccount(FamilyAccount $account): Collection
    {
        $items = collect();

        $paymentBookings = CareBooking::query()
            ->with([
                'careRequest:id,title',
                'carePlan:id,title',
                'caregiver:id,name',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path',
                'payment:id,care_booking_id,status,last_error',
            ])
            ->forFamilyAccount($account)
            ->whereHas('payment', fn ($query) => $query
                ->whereIn('status', CareBookingPayment::FAMILY_ACTION_REQUIRED_STATUSES))
            ->orderByDesc('updated_at')
            ->get();

        foreach ($paymentBookings as $booking) {
            $requestId = (int) $booking->care_request_id;
            $items->push([
                'key' => 'booking-payment-'.$booking->id,
                'type' => 'payment',
                'priority' => 10,
                'eyebrow' => 'Payment action required',
                'title' => 'Payment needs attention',
                'subject' => $booking->careRequest?->title ?: $booking->carePlan?->title ?: 'Upcoming care visit',
                'body' => $booking->payment?->last_error ?: 'Confirm or replace your card for this visit.',
                'meta' => $this->bookingDateTimeLabel($booking),
                'label' => 'Fix payment',
                'href' => $requestId > 0
                    ? route('family.requests.show', $requestId)
                    : route('family.billing.show'),
                'tone' => 'amber',
                'caregiver' => $booking->caregiver,
                'occurred_at' => $booking->payment?->updated_at ?: $booking->updated_at,
            ]);
        }

        $paymentBookingIds = $paymentBookings->pluck('id')->map(fn ($id) => (int) $id);
        $paymentPlans = CarePlan::query()
            ->with([
                'caregiver:id,name',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path',
                'nextBooking:id,care_request_id,care_plan_id,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status,last_error',
            ])
            ->forFamilyAccount($account)
            ->where('status', CarePlan::STATUS_PAYMENT_ATTENTION)
            ->orderByDesc('updated_at')
            ->get();

        foreach ($paymentPlans as $plan) {
            if ($plan->next_booking_id && $paymentBookingIds->contains((int) $plan->next_booking_id)) {
                continue;
            }

            $items->push([
                'key' => 'plan-payment-'.$plan->id,
                'type' => 'payment',
                'priority' => 10,
                'eyebrow' => 'Payment action required',
                'title' => 'Payment needs attention',
                'subject' => $plan->title,
                'body' => $plan->nextBooking?->payment?->last_error ?: $plan->last_error ?: 'Confirm or replace your card for the next visit.',
                'meta' => $plan->nextBooking ? $this->bookingDateTimeLabel($plan->nextBooking) : null,
                'label' => 'Fix payment',
                'href' => $plan->nextBooking?->care_request_id
                    ? route('family.requests.show', $plan->nextBooking->care_request_id)
                    : route('family.care.show', $plan->id),
                'tone' => 'amber',
                'caregiver' => $plan->caregiver,
                'occurred_at' => $plan->updated_at,
            ]);
        }

        $corrections = CareBookingTimeCorrection::query()
            ->with([
                'requester:id,name',
                'requester.caregiverProfile:id,user_id,profile_photo_path',
                'booking:id,care_request_id,care_plan_id,scheduled_start_at,scheduled_end_at',
                'booking.carePlan:id,timezone',
            ])
            ->forFamilyAccount($account)
            ->whereIn('status', [
                CareBookingTimeCorrection::STATUS_PENDING_FAMILY,
                CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED,
            ])
            ->orderBy('submitted_at')
            ->get();

        foreach ($corrections as $correction) {
            $booking = $correction->booking;
            if (! $booking?->care_request_id) {
                continue;
            }

            $timezone = $this->timeCorrections->timezoneFor($booking);
            $start = $correction->proposed_started_at?->copy()->setTimezone($timezone);
            $end = $correction->proposed_completed_at?->copy()->setTimezone($timezone);
            $paymentAction = $correction->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED;

            $items->push([
                'key' => 'time-correction-'.$correction->id,
                'type' => 'time_correction',
                'priority' => $paymentAction ? 11 : 20,
                'eyebrow' => $paymentAction ? 'Hours approved · payment required' : 'Visit hours need your review',
                'title' => $paymentAction
                    ? 'Finish payment for reported hours'
                    : 'Review '.$this->possessiveName($correction->requester?->name).' reported visit hours',
                'subject' => $start?->format('l, F j, Y') ?: 'Reported care visit',
                'body' => $this->reportedTimeLabel($start, $end, $correction->durationLabel(), $correction->familyAmountLabel()),
                'meta' => $paymentAction
                    ? 'Your approval is saved. Confirm payment to finish.'
                    : 'Review the exact hours before any payment is made.',
                'label' => $paymentAction ? 'Confirm payment' : 'Review hours',
                'href' => route('family.requests.show', [
                    'careRequest' => $booking->care_request_id,
                    'tab' => 'shift',
                ]).'#time-correction-review-'.$correction->id,
                'tone' => 'amber',
                'caregiver' => $correction->requester,
                'occurred_at' => $correction->submitted_at ?: $correction->created_at,
            ]);
        }

        $extraVisits = CompletedExtraVisitRequest::query()
            ->with([
                'caregiver:id,name',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path',
                'plan:id,title',
            ])
            ->forFamilyAccount($account)
            ->current()
            ->whereIn('status', [
                CompletedExtraVisitRequest::STATUS_PENDING_FAMILY,
                CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED,
            ])
            ->orderBy('submitted_at')
            ->get();

        foreach ($extraVisits as $report) {
            $start = $report->proposed_started_at?->copy()->setTimezone($report->timezone);
            $end = $report->proposed_completed_at?->copy()->setTimezone($report->timezone);
            $paymentAction = $report->status === CompletedExtraVisitRequest::STATUS_PAYMENT_ACTION_REQUIRED;
            $chargeCents = (int) data_get(
                $report->final_financial_preview ?: $report->financial_preview,
                'amount_captured_cents',
                data_get($report->final_financial_preview ?: $report->financial_preview, 'total_charge_cents', 0)
            );

            $items->push([
                'key' => 'completed-extra-visit-'.$report->id,
                'type' => 'completed_extra_visit',
                'priority' => $paymentAction ? 11 : 20,
                'eyebrow' => $paymentAction ? 'Visit approved · payment required' : 'Reported care needs your approval',
                'title' => $paymentAction
                    ? 'Finish payment for the reported visit'
                    : 'Review '.$this->possessiveName($report->caregiver?->name).' reported extra visit',
                'subject' => $start?->format('l, F j, Y') ?: 'Reported extra visit',
                'body' => $this->reportedTimeLabel(
                    $start,
                    $end,
                    $report->durationLabel(),
                    '$'.number_format($chargeCents / 100, 2)
                ),
                'meta' => $paymentAction
                    ? 'Your approval is saved. Confirm payment to finish.'
                    : 'This does not change the regular schedule. Review before payment.',
                'label' => $paymentAction ? 'Confirm payment' : 'Review visit',
                'href' => route('family.care.show', [
                    'carePlan' => $report->care_plan_id,
                    'extra_visit' => $report->id,
                ]).'#completed-extra-visit-'.$report->id,
                'tone' => 'amber',
                'caregiver' => $report->caregiver,
                'occurred_at' => $report->submitted_at ?: $report->created_at,
            ]);
        }

        $bookingActions = CareBooking::query()
            ->with([
                'careRequest:id,title',
                'caregiver:id,name',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path',
            ])
            ->forFamilyAccount($account)
            ->where(function ($query) {
                $query->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                    ->orWhere(function ($reviewQuery) {
                        $reviewQuery->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
                            ->whereNull('family_confirmed_at');
                    });
            })
            ->whereDoesntHave('payment', fn ($query) => $query
                ->whereIn('status', CareBookingPayment::FAMILY_ACTION_REQUIRED_STATUSES))
            ->whereDoesntHave('timeCorrections', fn ($query) => $query
                ->whereIn('status', CareBookingTimeCorrection::activeStatuses()))
            ->orderBy('scheduled_start_at')
            ->get();

        foreach ($bookingActions as $booking) {
            if (! $booking->care_request_id) {
                continue;
            }

            $isLive = in_array($booking->status, [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true);
            $items->push([
                'key' => 'booking-'.$booking->id,
                'type' => $isLive ? 'live_visit' : 'timesheet',
                'priority' => $isLive ? 25 : 22,
                'eyebrow' => $isLive ? 'Visit in progress' : 'Completed visit needs your approval',
                'title' => $isLive
                    ? ($booking->status === CareBooking::STATUS_PAUSED ? 'Visit is paused' : 'Care is happening now')
                    : 'Approve '.$this->possessiveName($booking->caregiver?->name).' visit hours',
                'subject' => $booking->careRequest?->title ?: 'Care visit',
                'body' => $isLive ? $this->bookingDateTimeLabel($booking) : 'The caregiver submitted their timesheet.',
                'meta' => $isLive ? 'Open the visit for tracking, messages, or support.' : $this->bookingDateTimeLabel($booking),
                'label' => $isLive ? 'Open visit' : 'Review hours',
                'href' => route('family.requests.show', $booking->care_request_id),
                'tone' => 'green',
                'caregiver' => $booking->caregiver,
                'occurred_at' => $booking->completed_at ?: $booking->updated_at,
            ]);
        }

        $requests = CareRequest::query()
            ->with('recipient')
            ->withCount(['applications as pending_candidate_count' => fn ($query) => $query
                ->whereIn('status', [
                    CareRequestApplication::STATUS_APPLIED,
                    CareRequestApplication::STATUS_SHORTLISTED,
                ])])
            ->forFamilyAccount($account)
            ->where('is_system_generated', false)
            ->where('status', CareRequest::STATUS_OPEN)
            ->whereHas('applications', fn ($query) => $query
                ->whereIn('status', [
                    CareRequestApplication::STATUS_APPLIED,
                    CareRequestApplication::STATUS_SHORTLISTED,
                ]))
            ->orderByDesc('updated_at')
            ->get();

        foreach ($requests as $request) {
            $count = (int) $request->pending_candidate_count;
            $items->push([
                'key' => 'request-applicants-'.$request->id,
                'type' => 'applicants',
                'priority' => 30,
                'eyebrow' => 'Caregiver response',
                'title' => $count === 1 ? 'A caregiver is waiting for your review' : $count.' caregivers are waiting for your review',
                'subject' => $request->title,
                'body' => 'Compare profiles, message caregivers, and choose the right fit.',
                'meta' => null,
                'label' => $count === 1 ? 'Review caregiver' : 'Review caregivers',
                'href' => route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'applicants']),
                'tone' => 'blue',
                'caregiver' => null,
                'occurred_at' => $request->updated_at,
            ]);
        }

        return $items
            ->sort(function (array $left, array $right): int {
                $priority = ((int) $left['priority']) <=> ((int) $right['priority']);
                if ($priority !== 0) {
                    return $priority;
                }

                return ($right['occurred_at']?->getTimestamp() ?? 0) <=> ($left['occurred_at']?->getTimestamp() ?? 0);
            })
            ->values();
    }

    private function bookingDateTimeLabel(CareBooking $booking): ?string
    {
        if (! $booking->scheduled_start_at) {
            return null;
        }

        $label = $booking->scheduled_start_at->format('M j, g:i A');

        return $booking->scheduled_end_at
            ? $label.' to '.$booking->scheduled_end_at->format('g:i A')
            : $label;
    }

    private function reportedTimeLabel(mixed $start, mixed $end, string $duration, string $amount): string
    {
        $time = $start && $end
            ? $start->format('g:i A').' to '.$end->format('g:i A')
            : 'Reported time';

        return $time.' · '.$duration.' · Estimated '.$amount;
    }

    private function possessiveName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return "the caregiver's";
        }

        return $name.(str_ends_with(strtolower($name), 's') ? "'" : "'s");
    }
}
