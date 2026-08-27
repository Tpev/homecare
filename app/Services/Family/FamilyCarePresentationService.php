<?php

namespace App\Services\Family;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Support\CareRequestProgress;
use App\Support\WeeklySchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FamilyCarePresentationService
{
    /**
     * Return actual dated care, regardless of whether it began as a one-time
     * request, a regular-care occurrence, an extra visit, or coverage.
     */
    public function upcomingVisits(
        FamilyAccount $account,
        int $limit = 50,
        ?string $careType = null,
        ?string $recipient = null,
    ): Collection {
        return $this->applyUpcomingFilters(
            $this->upcomingBookingsQuery($account),
            $careType,
            $recipient,
        )
            ->with([
                'careRequest:id,title,care_plan_id,request_type,city,state',
                'careRequest.recipient:id,care_request_id,full_name',
                'carePlan:id,title,recipient_snapshot,caregiver_user_id',
                'caregiver:id,name',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path',
                'payment:id,care_booking_id,status,last_error',
            ])
            ->orderBy('scheduled_start_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (CareBooking $booking): array => $this->visit($booking));
    }

    public function upcomingVisitCount(
        FamilyAccount $account,
        ?string $careType = null,
        ?string $recipient = null,
    ): int {
        return $this->applyUpcomingFilters(
            $this->upcomingBookingsQuery($account),
            $careType,
            $recipient,
        )->count();
    }

    public function upcomingRecipientNames(FamilyAccount $account): Collection
    {
        return $this->upcomingBookingsQuery($account)
            ->with([
                'careRequest:id',
                'careRequest.recipient:id,care_request_id,full_name',
                'carePlan:id,recipient_snapshot',
            ])
            ->get(['id', 'care_request_id', 'care_plan_id'])
            ->map(fn (CareBooking $booking): string => trim((string) (
                $booking->carePlan?->recipientName()
                    ?: $booking->careRequest?->recipient?->full_name
                    ?: ''
            )))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->sort()
            ->values();
    }

    public function careRecipientNames(FamilyAccount $account): Collection
    {
        $requestNames = CareRequest::query()
            ->with('recipient:id,care_request_id,full_name')
            ->forFamilyAccount($account)
            ->get(['id'])
            ->pluck('recipient.full_name');
        $planNames = CarePlan::query()
            ->forFamilyAccount($account)
            ->get(['recipient_snapshot'])
            ->map(fn (CarePlan $plan): string => $plan->recipientName());

        return $requestNames
            ->merge($planNames)
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->sort()
            ->values();
    }

    public function request(CareRequest $request): array
    {
        $recipient = trim((string) ($request->recipient?->full_name ?? '')) ?: 'Care recipient';
        $isRegular = $request->request_type === CareRequest::TYPE_RECURRING;
        $datePassed = CareRequestProgress::oneTimeDateHasPassed($request);
        $candidateCount = isset($request->pending_candidate_count)
            ? (int) $request->pending_candidate_count
            : (int) ($request->applications_count ?? 0);

        $status = match (true) {
            $datePassed => ['key' => 'date_passed', 'label' => 'Date passed · resolve', 'tone' => 'amber'],
            $request->status === CareRequest::STATUS_DRAFT => ['key' => 'draft', 'label' => 'Draft', 'tone' => 'slate'],
            $request->status === CareRequest::STATUS_OPEN && $candidateCount > 0 => ['key' => 'review', 'label' => $candidateCount.' '.($candidateCount === 1 ? 'caregiver replied' : 'caregivers replied'), 'tone' => 'blue'],
            $request->status === CareRequest::STATUS_OPEN => ['key' => 'finding', 'label' => 'Finding a caregiver', 'tone' => 'blue'],
            $request->status === CareRequest::STATUS_FILLED => ['key' => 'scheduled', 'label' => 'Caregiver selected', 'tone' => 'green'],
            $request->status === CareRequest::STATUS_CANCELLED => ['key' => 'closed', 'label' => 'Withdrawn', 'tone' => 'slate'],
            $request->status === CareRequest::STATUS_EXPIRED => ['key' => 'closed', 'label' => 'Expired', 'tone' => 'slate'],
            default => ['key' => 'status', 'label' => ucfirst(str_replace('_', ' ', (string) $request->status)), 'tone' => 'slate'],
        };

        $schedule = $isRegular
            ? ($request->recurringScheduleLabel() ?: 'Weekly schedule not set')
            : $this->dateTimeLabel($request->requested_start_at, $request->requested_end_at);

        $actionLabel = match (true) {
            $datePassed => 'Resolve request',
            $request->status === CareRequest::STATUS_OPEN && $candidateCount > 0 => 'Review caregivers',
            $request->status === CareRequest::STATUS_OPEN => 'View request',
            $request->status === CareRequest::STATUS_DRAFT => 'Continue request',
            default => 'View details',
        };

        return [
            'id' => (int) $request->id,
            'type_key' => $isRegular ? 'regular' : 'one_time',
            'type_label' => $isRegular ? 'Recurring care' : 'One-time care',
            'headline' => $isRegular ? 'Recurring care for '.$recipient : $recipient.' · '.($request->requested_start_at?->format('D, M j') ?: 'Date not set'),
            'recipient' => $recipient,
            'schedule' => $schedule,
            'location' => trim(collect([$request->city, $request->state])->filter()->implode(', ')),
            'status' => $status,
            'action_label' => $actionLabel,
            'action_url' => route('family.requests.show', $request->id),
            'details_url' => route('family.care.journey', ['resourceType' => 'request', 'resourceId' => $request->id]),
            'tasks' => $request->relationLoaded('tasks') ? $request->tasks->pluck('name')->take(4)->values() : collect(),
            'reference' => 'Request #'.$request->id,
            'date_passed' => $datePassed,
        ];
    }

    public function plan(CarePlan $plan): array
    {
        $recipient = $plan->recipientName();
        $caregiver = trim((string) ($plan->caregiver?->name ?? '')) ?: 'Caregiver pending';
        $status = match ((string) $plan->status) {
            CarePlan::STATUS_ACTIVE => ['label' => 'Active', 'tone' => 'green'],
            CarePlan::STATUS_PAYMENT_ATTENTION => ['label' => 'Payment needs attention', 'tone' => 'amber'],
            CarePlan::STATUS_PAUSED => ['label' => 'Paused', 'tone' => 'blue'],
            CarePlan::STATUS_PENDING_CAREGIVER => ['label' => 'Waiting for '.$caregiver, 'tone' => 'blue'],
            CarePlan::STATUS_COUNTERED => ['label' => 'New schedule proposed', 'tone' => 'amber'],
            CarePlan::STATUS_DECLINED => ['label' => 'Declined', 'tone' => 'rose'],
            CarePlan::STATUS_ENDED => ['label' => 'Ended', 'tone' => 'slate'],
            CarePlan::STATUS_CANCELLED => ['label' => 'Cancelled', 'tone' => 'slate'],
            default => ['label' => ucfirst(str_replace('_', ' ', (string) $plan->status)), 'tone' => 'slate'],
        };

        $nextBooking = $plan->nextBooking;
        $nextBookingIsCurrent = $nextBooking instanceof CareBooking && match ((string) $nextBooking->status) {
            CareBooking::STATUS_SCHEDULED => $nextBooking->scheduled_start_at?->gte(
                now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes())
            ) ?? false,
            CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED => (
                $nextBooking->scheduled_end_at ?: $nextBooking->scheduled_start_at
            )?->gte(now()->subDay()) ?? false,
            default => false,
        };
        $nextLabel = $nextBookingIsCurrent && $nextBooking->scheduled_start_at
            ? $this->dateTimeLabel($nextBooking->scheduled_start_at, $nextBooking->scheduled_end_at)
            : null;

        return [
            'id' => (int) $plan->id,
            'type_label' => 'Recurring care',
            'headline' => $recipient.' with '.$caregiver,
            'recipient' => $recipient,
            'caregiver' => $caregiver,
            'schedule' => WeeklySchedule::label($plan->weeklyScheduleSlots()) ?: 'Schedule not set',
            'next_label' => $nextLabel,
            'status' => $status,
            'payment_label' => match ((string) $plan->payment_status) {
                CarePlan::PAYMENT_AUTHORIZED => 'Payment authorized',
                CarePlan::PAYMENT_ACTION_REQUIRED => 'Payment action needed',
                default => 'Payment checked before each visit',
            },
            'action_label' => $plan->status === CarePlan::STATUS_PAYMENT_ATTENTION ? 'Fix payment' : 'Manage recurring care',
            'action_url' => route('family.care.show', $plan->id),
            'details_url' => route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id]),
            'reference' => 'Recurring care #'.$plan->id,
        ];
    }

    public function visit(CareBooking $booking): array
    {
        $request = $booking->careRequest;
        $plan = $booking->carePlan;
        $recipient = trim((string) ($plan?->recipientName() ?: $request?->recipient?->full_name ?? '')) ?: 'Care recipient';
        $caregiver = trim((string) ($booking->caregiver?->name ?? '')) ?: 'Caregiver pending';
        $kind = (string) ($booking->plan_visit_kind ?? '');

        [$typeKey, $typeLabel] = match (true) {
            $kind === 'coverage' => ['coverage', 'Continuous care'],
            in_array($kind, ['extra', 'completed_extra'], true) => ['extra', 'Extra visit'],
            (int) $booking->care_plan_id > 0 => ['regular', 'Regular visit'],
            default => ['one_time', 'One-time visit'],
        };

        $status = match ((string) $booking->status) {
            CareBooking::STATUS_IN_PROGRESS => ['label' => 'Happening now', 'tone' => 'green'],
            CareBooking::STATUS_PAUSED => ['label' => 'Paused', 'tone' => 'amber'],
            CareBooking::STATUS_SCHEDULED => ['label' => 'Scheduled', 'tone' => 'green'],
            default => ['label' => ucfirst(str_replace('_', ' ', (string) $booking->status)), 'tone' => 'slate'],
        };

        $paymentNeedsAction = $booking->payment?->requiresFamilyAction() ?? false;
        if ($paymentNeedsAction) {
            $status = ['label' => 'Payment needs attention', 'tone' => 'amber'];
        }

        return [
            'id' => (int) $booking->id,
            'type_key' => $typeKey,
            'type_label' => $typeLabel,
            'headline' => $recipient.' · '.($booking->scheduled_start_at?->format('D, M j') ?: 'Date not set'),
            'recipient' => $recipient,
            'caregiver' => $caregiver,
            'starts_at' => $booking->scheduled_start_at,
            'ends_at' => $booking->scheduled_end_at,
            'schedule' => $this->dateTimeLabel($booking->scheduled_start_at, $booking->scheduled_end_at),
            'location' => trim(collect([$request?->city, $request?->state])->filter()->implode(', ')),
            'status' => $status,
            'payment_needs_action' => $paymentNeedsAction,
            'action_label' => $paymentNeedsAction ? 'Fix payment' : 'Open visit',
            'action_url' => $plan?->id
                ? route('family.care.show', $plan->id)
                : ($request?->id ? route('family.requests.show', $request->id) : route('family.requests.index')),
            'details_url' => $plan?->id
                ? route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id])
                : ($request?->id
                    ? route('family.care.journey', ['resourceType' => 'request', 'resourceId' => $request->id])
                    : route('family.requests.index')),
            'reference' => 'Visit #'.$booking->id,
            'resource_type' => $plan?->id ? 'care_plan' : ($request?->id ? 'care_request' : 'care_booking'),
            'resource_id' => (int) ($plan?->id ?: $request?->id ?: $booking->id),
        ];
    }

    private function dateTimeLabel(mixed $start, mixed $end): string
    {
        if (! $start) {
            return 'Date and time not set';
        }

        $label = $start->format('l, F j').' · '.$start->format('g:i A');

        return $end ? $label.'–'.$end->format('g:i A') : $label;
    }

    private function upcomingBookingsQuery(FamilyAccount $account): Builder
    {
        return CareBooking::query()
            ->forFamilyAccount($account)
            ->where(function ($query): void {
                $query->where(function ($live): void {
                    $recentCutoff = now()->subDay();

                    $live->whereIn('status', [
                        CareBooking::STATUS_IN_PROGRESS,
                        CareBooking::STATUS_PAUSED,
                    ])->where(function ($recent) use ($recentCutoff): void {
                        $recent->where('scheduled_end_at', '>=', $recentCutoff)
                            ->orWhere(function ($withoutEnd) use ($recentCutoff): void {
                                $withoutEnd
                                    ->whereNull('scheduled_end_at')
                                    ->where('scheduled_start_at', '>=', $recentCutoff);
                            });
                    });
                })->orWhere(function ($scheduled): void {
                    $scheduled
                        ->where('status', CareBooking::STATUS_SCHEDULED)
                        ->where('scheduled_start_at', '>=', now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes()));
                });
            });
    }

    private function applyUpcomingFilters(
        Builder $query,
        ?string $careType,
        ?string $recipient,
    ): Builder {
        if ($careType && $careType !== 'all') {
            match ($careType) {
                'one_time' => $query->whereNull('care_plan_id'),
                'regular' => $query
                    ->whereNotNull('care_plan_id')
                    ->where(function ($kind): void {
                        $kind->whereNull('plan_visit_kind')
                            ->orWhereNotIn('plan_visit_kind', ['extra', 'completed_extra', 'coverage']);
                    }),
                'extra' => $query->whereIn('plan_visit_kind', ['extra', 'completed_extra']),
                'coverage' => $query->where('plan_visit_kind', 'coverage'),
                default => null,
            };
        }

        if ($recipient && $recipient !== 'all') {
            $query->where(function ($recipientQuery) use ($recipient): void {
                $recipientQuery
                    ->whereHas('carePlan', fn ($plan) => $plan->where('recipient_snapshot->full_name', $recipient))
                    ->orWhereHas('careRequest.recipient', fn ($person) => $person->where('full_name', $recipient));
            });
        }

        return $query;
    }
}
