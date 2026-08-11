<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CaregiverWorkInboxBuilder
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function buildForUser(User $user, string $scope = 'all', string $sort = 'priority', int $limit = 30): Collection
    {
        if ($user->role !== 'caregiver') {
            return collect();
        }

        $caregiverId = (int) $user->id;
        $now = now();
        $profile = $user->caregiverProfile()->with('skills:id')->first();
        $skillIds = $profile?->skills?->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
        $defaultRate = (float) config('marketplace.family_estimate_hourly_rate', 30.00);

        $items = collect();

        $pendingInvitations = CareRequestInvitation::query()
            ->with([
                'family:id,name',
                'careRequest:id,family_user_id,title,request_type,requested_start_at,requested_end_at,recurring_days,recurring_start_time,recurring_end_time,recurring_schedule,city,state,status',
                'careRequest.family:id,email',
                'careRequest.recipient:id,care_request_id,recipient_is_requester,full_name,relationship_to_family',
            ])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CareRequestInvitation::STATUS_PENDING)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->latest()
            ->get();

        foreach ($pendingInvitations as $invitation) {
            $request = $invitation->careRequest;
            if (! $request) {
                continue;
            }

            $invitationMinutes = $this->estimatedShiftMinutes($request);
            $invitationCompensation = $this->compensationPayload(
                $invitationMinutes,
                $this->effectiveRequestRate($request, $defaultRate)
            );

            $items->push([
                'id' => 'invite-'.$invitation->id,
                'scope' => 'needs_response',
                'state' => 'invited',
                'priority' => 1000,
                'created_at' => $invitation->created_at,
                'start_at' => $request->requested_start_at,
                'title' => $request->title,
                'location' => $request->city.', '.$request->state,
                'schedule' => $this->scheduleLabel($request),
                'compensation' => $invitationCompensation,
                'compensation_line' => $invitationCompensation['line'] ?? null,
                'status_label' => 'Invited',
                'status_tone' => 'info',
                'fit_reason' => 'Family invited you directly.',
                'recipient_context' => $this->recipientContext($request->recipient),
                'note' => $invitation->message ?: null,
                'primary_action' => [
                    'kind' => 'inline',
                    'label' => 'Accept invite',
                    'method' => 'acceptInvitation',
                    'id' => $invitation->id,
                ],
                'secondary_action' => [
                    'kind' => 'inline',
                    'label' => 'Decline',
                    'method' => 'declineInvitation',
                    'id' => $invitation->id,
                ],
                'open_href' => route('care-requests.apply', $request->id),
                'meta' => $invitation->family ? 'From '.$invitation->family->name : 'Direct invite',
            ]);
        }

        $regularCareOffers = CarePlan::query()
            ->with(['family:id,name,email,city,state'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CarePlan::STATUS_PENDING_CAREGIVER)
            ->latest()
            ->get();

        foreach ($regularCareOffers as $plan) {
            $minutes = $this->estimatedPlanMinutes($plan);
            $compensation = $this->compensationPayload($minutes, $this->effectivePlanRate($plan));

            $items->push([
                'id' => 'regular-offer-'.$plan->id,
                'scope' => 'needs_response',
                'state' => 'regular_care_offer',
                'priority' => 1250,
                'created_at' => $plan->offered_at ?: $plan->created_at,
                'start_at' => $plan->starts_on,
                'title' => $plan->title,
                'location' => $this->planLocation($plan),
                'schedule' => $this->planScheduleLabel($plan),
                'compensation' => $compensation,
                'compensation_line' => $compensation['line'] ?? null,
                'status_label' => 'Regular offer',
                'status_tone' => 'info',
                'fit_reason' => 'A family wants to rebook you on a regular schedule.',
                'recipient_context' => $this->planRecipientContext($plan),
                'note' => $plan->family_message ?: null,
                'primary_action' => [
                    'kind' => 'link',
                    'label' => 'Review offer',
                    'href' => route('caregiver.regular-clients.index'),
                ],
                'secondary_action' => [
                    'kind' => 'link',
                    'label' => 'Regular clients',
                    'href' => route('caregiver.regular-clients.index'),
                ],
                'open_href' => route('caregiver.regular-clients.index'),
                'meta' => $plan->family ? 'From '.$plan->family->name : 'Direct regular-care offer',
            ]);
        }

        $applications = CareRequestApplication::query()
            ->with([
                'careRequest:id,family_user_id,title,request_type,requested_start_at,requested_end_at,recurring_days,recurring_start_time,recurring_end_time,recurring_schedule,city,state,status,created_at',
                'careRequest.family:id,email',
                'careRequest.recipient:id,care_request_id,recipient_is_requester,full_name,relationship_to_family',
                'conversation:id,care_request_application_id',
                'booking:id,care_request_application_id,status,scheduled_start_at,scheduled_end_at,started_at,completed_at',
            ])
            ->where('caregiver_user_id', $caregiverId)
            ->whereHas('careRequest', fn ($query) => $query->where('is_system_generated', false))
            ->whereIn('status', [
                CareRequestApplication::STATUS_APPLIED,
                CareRequestApplication::STATUS_SHORTLISTED,
                CareRequestApplication::STATUS_HIRED,
            ])
            ->latest()
            ->get();

        foreach ($applications as $application) {
            $request = $application->careRequest;
            if (! $request) {
                continue;
            }

            $item = $this->buildApplicationItem($application, $defaultRate);
            if ($item) {
                $items->push($item);
            }
        }

        $activeRegularPlans = CarePlan::query()
            ->with(['family:id,name,email,city,state', 'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at'])
            ->where('caregiver_user_id', $caregiverId)
            ->whereIn('status', [
                CarePlan::STATUS_ACTIVE,
                CarePlan::STATUS_PAYMENT_ATTENTION,
                CarePlan::STATUS_PAUSED,
            ])
            ->latest('updated_at')
            ->limit(12)
            ->get();

        foreach ($activeRegularPlans as $plan) {
            $minutes = $this->estimatedPlanMinutes($plan);
            $compensation = $this->compensationPayload($minutes, $this->effectivePlanRate($plan));
            $paymentNeedsFamily = $plan->status === CarePlan::STATUS_PAYMENT_ATTENTION;

            $items->push([
                'id' => 'regular-plan-'.$plan->id,
                'scope' => 'hired',
                'state' => 'regular_care_'.$plan->status,
                'priority' => $plan->status === CarePlan::STATUS_PAYMENT_ATTENTION ? 430 : 360,
                'created_at' => $plan->updated_at,
                'start_at' => $plan->nextBooking?->scheduled_start_at ?: $plan->starts_on,
                'title' => $plan->title,
                'location' => $this->planLocation($plan),
                'schedule' => $this->planScheduleLabel($plan),
                'compensation' => $compensation,
                'compensation_line' => $compensation['line'] ?? null,
                'status_label' => $paymentNeedsFamily ? 'Family payment needed' : 'Regular client',
                'status_tone' => $paymentNeedsFamily ? 'warning' : 'success',
                'fit_reason' => $paymentNeedsFamily
                    ? 'Family needs to update payment. No action needed from you right now.'
                    : ($plan->nextBooking
                    ? 'Next regular-care visit is ready in your visit workflow.'
                    : 'Regular-care schedule accepted.'),
                'recipient_context' => $this->planRecipientContext($plan),
                'note' => null,
                'primary_action' => [
                    'kind' => 'link',
                    'label' => $plan->nextBooking ? 'Open next visit' : 'Open plan',
                    'href' => $plan->nextBooking
                        ? route('care-requests.apply', $plan->nextBooking->care_request_id)
                        : route('caregiver.regular-clients.index'),
                ],
                'secondary_action' => [
                    'kind' => 'link',
                    'label' => 'Regular clients',
                    'href' => route('caregiver.regular-clients.index'),
                ],
                'open_href' => route('caregiver.regular-clients.index'),
                'meta' => $plan->family ? 'Regular client: '.$plan->family->name : 'Regular client',
            ]);
        }

        $excludedRequestIds = collect()
            ->merge($pendingInvitations->pluck('care_request_id'))
            ->merge($applications->pluck('care_request_id'))
            ->unique()
            ->values();

        $recommendedRequests = collect();
        if (! CaregiverPrelaunch::enabled()) {
            $recommendedRequests = CareRequest::query()
                ->with([
                    'tasks:id,name',
                    'family:id,email',
                    'recipient:id,care_request_id,recipient_is_requester,full_name,relationship_to_family',
                ])
                ->withCount('applications')
                ->where('status', CareRequest::STATUS_OPEN)
                ->when($excludedRequestIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $excludedRequestIds))
                ->latest()
                ->limit(30)
                ->get();
        }

        foreach ($recommendedRequests as $request) {
            $score = $this->recommendationScore($request, $user, $skillIds);
            if ($score <= 0) {
                continue;
            }

            $recommendedMinutes = $this->estimatedShiftMinutes($request);
            $recommendedCompensation = $this->compensationPayload(
                $recommendedMinutes,
                $this->effectiveRequestRate($request, $defaultRate)
            );
            $isFresh = $request->created_at?->gte(now()->subDays(2)) ?? false;

            $items->push([
                'id' => 'new-request-'.$request->id,
                'scope' => 'new_requests',
                'state' => 'open_match',
                'priority' => ($isFresh ? 350 : 300) + $score,
                'created_at' => $request->created_at,
                'start_at' => $request->requested_start_at,
                'title' => $request->title,
                'location' => $request->city.', '.$request->state,
                'schedule' => $this->scheduleLabel($request),
                'compensation' => $recommendedCompensation,
                'compensation_line' => $recommendedCompensation['line'] ?? null,
                'status_label' => $isFresh ? 'New' : 'Open',
                'status_tone' => $isFresh ? 'info' : 'neutral',
                'fit_reason' => $this->recommendationReason($request, $user, $skillIds),
                'recipient_context' => $this->recipientContext($request->recipient),
                'note' => $this->requestPreview($request),
                'request_details' => $this->requestDetails($request),
                'primary_action' => [
                    'kind' => 'link',
                    'label' => 'Apply now',
                    'href' => route('care-requests.apply', $request->id),
                ],
                'secondary_action' => [
                    'kind' => 'link',
                    'label' => 'View details',
                    'href' => route('care-requests.apply', $request->id),
                ],
                'open_href' => route('care-requests.apply', $request->id),
                'meta' => 'Posted '.$this->postedLabel($request).' - '.$this->applicantCountLabel((int) ($request->applications_count ?? 0)),
            ]);
        }

        $items = $items->filter(function (array $item) use ($scope) {
            if ($scope === 'all') {
                return true;
            }

            if ($scope === 'recommended') {
                return $item['scope'] === 'new_requests';
            }

            return $item['scope'] === $scope;
        });

        $items = match ($sort) {
            'newest' => $items->sortByDesc(fn (array $item) => optional($item['created_at'])->getTimestamp() ?? 0),
            'start_soon' => $items->sortBy(fn (array $item) => optional($item['start_at'])->getTimestamp() ?? PHP_INT_MAX),
            'best_fit' => $items->sortByDesc(fn (array $item) => (int) ($item['priority'] ?? 0)),
            default => $items->sortByDesc(function (array $item) {
                $scopeWeight = match ((string) ($item['scope'] ?? 'all')) {
                    'needs_response' => 5000,
                    'hired' => 4000,
                    'new_requests' => 3000,
                    'applied' => 2000,
                    'completed' => 1000,
                    default => 0,
                };

                return $scopeWeight + (int) ($item['priority'] ?? 0);
            }),
        };

        return $items->values()->take(max(1, $limit));
    }

    /**
     * @return array<string, int>
     */
    public function countsForUser(User $user): array
    {
        $items = $this->buildForUser($user, 'all', 'priority', 300);
        $newRequests = $items->where('scope', 'new_requests')->count();

        return [
            'all' => $items->count(),
            'needs_response' => $items->where('scope', 'needs_response')->count(),
            'new_requests' => $newRequests,
            'recommended' => $newRequests,
            'applied' => $items->where('scope', 'applied')->count(),
            'hired' => $items->where('scope', 'hired')->count(),
            'completed' => $items->where('scope', 'completed')->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildApplicationItem(CareRequestApplication $application, float $defaultRate): ?array
    {
        $request = $application->careRequest;
        if (! $request) {
            return null;
        }

        $booking = $application->booking;
        $status = (string) $application->status;
        $recipientContext = $this->recipientContext($request->recipient);

        if ($status === CareRequestApplication::STATUS_APPLIED) {
            $minutes = $this->estimatedShiftMinutes($request, $booking);
            $rate = $this->effectiveRequestRate($request, (float) ($application->proposed_rate ?: $defaultRate));
            $compensation = $this->compensationPayload($minutes, $rate);

            return [
                'id' => 'application-'.$application->id,
                'scope' => 'applied',
                'state' => 'applied',
                'priority' => 190,
                'created_at' => $application->created_at,
                'start_at' => $request->requested_start_at,
                'title' => $request->title,
                'location' => $request->city.', '.$request->state,
                'schedule' => $this->scheduleLabel($request),
                'compensation' => $compensation,
                'compensation_line' => $compensation['line'] ?? null,
                'status_label' => 'Applied',
                'status_tone' => 'neutral',
                'fit_reason' => 'Waiting for family review.',
                'recipient_context' => $recipientContext,
                'note' => null,
                'primary_action' => [
                    'kind' => 'link',
                    'label' => 'Open application',
                    'href' => route('care-requests.apply', $request->id),
                ],
                'secondary_action' => null,
                'open_href' => route('care-requests.apply', $request->id),
                'meta' => 'Application sent',
            ];
        }

        if ($status === CareRequestApplication::STATUS_SHORTLISTED) {
            $chatHref = $application->conversation
                ? route('messages.show', $application->conversation->id)
                : route('care-requests.apply', $request->id);
            $minutes = $this->estimatedShiftMinutes($request, $booking);
            $rate = $this->effectiveRequestRate($request, (float) ($application->proposed_rate ?: $defaultRate));
            $compensation = $this->compensationPayload($minutes, $rate);

            return [
                'id' => 'application-'.$application->id,
                'scope' => 'applied',
                'state' => 'shortlisted',
                'priority' => 260,
                'created_at' => $application->updated_at,
                'start_at' => $request->requested_start_at,
                'title' => $request->title,
                'location' => $request->city.', '.$request->state,
                'schedule' => $this->scheduleLabel($request),
                'compensation' => $compensation,
                'compensation_line' => $compensation['line'] ?? null,
                'status_label' => 'Shortlisted',
                'status_tone' => 'success',
                'fit_reason' => 'Family shortlisted you. Chat now to increase hire odds.',
                'recipient_context' => $recipientContext,
                'note' => null,
                'primary_action' => [
                    'kind' => 'link',
                    'label' => 'Open chat',
                    'href' => $chatHref,
                ],
                'secondary_action' => [
                    'kind' => 'link',
                    'label' => 'Open application',
                    'href' => route('care-requests.apply', $request->id),
                ],
                'open_href' => route('care-requests.apply', $request->id),
                'meta' => 'Candidate review in progress',
            ];
        }

        if ($status === CareRequestApplication::STATUS_HIRED) {
            if (! $booking) {
                $minutes = $this->estimatedShiftMinutes($request);
                $rate = $this->effectiveRequestRate($request, (float) ($application->proposed_rate ?: $defaultRate));
                $compensation = $this->compensationPayload($minutes, $rate);

                return [
                    'id' => 'application-'.$application->id,
                    'scope' => 'hired',
                    'state' => 'hired_pending_setup',
                    'priority' => 380,
                    'created_at' => $application->updated_at,
                    'start_at' => $request->requested_start_at,
                    'title' => $request->title,
                    'location' => $request->city.', '.$request->state,
                    'schedule' => $this->scheduleLabel($request),
                    'compensation' => $compensation,
                    'compensation_line' => $compensation['line'] ?? null,
                    'status_label' => 'Hired',
                    'status_tone' => 'success',
                    'fit_reason' => 'You were hired. Open visit details and confirm agreement.',
                    'recipient_context' => $recipientContext,
                    'note' => null,
                    'primary_action' => [
                        'kind' => 'link',
                        'label' => 'Open visit setup',
                        'href' => route('care-requests.apply', $request->id),
                    ],
                    'secondary_action' => $application->conversation
                        ? [
                            'kind' => 'link',
                            'label' => 'Open chat',
                            'href' => route('messages.show', $application->conversation->id),
                        ]
                        : null,
                    'open_href' => route('care-requests.apply', $request->id),
                    'meta' => 'Ready for visit setup',
                ];
            }

            $bookingStatus = (string) $booking->status;
            [$scope, $priority, $label, $tone, $fitReason, $primaryLabel] = match ($bookingStatus) {
                CareBooking::STATUS_SCHEDULED => ['hired', 420, 'Scheduled', 'info', 'Visit scheduled. Start when you arrive.', 'Start visit'],
                CareBooking::STATUS_IN_PROGRESS => ['hired', 500, 'In progress', 'success', 'Visit is live right now.', 'Continue visit'],
                CareBooking::STATUS_PAUSED => ['hired', 490, 'Paused', 'warning', 'Visit paused. Resume or end when ready.', 'Resume visit'],
                CareBooking::STATUS_COMPLETED => ['completed', 140, 'Completed', 'warning', 'Timesheet submitted and waiting family confirmation.', 'View recap'],
                CareBooking::STATUS_REVIEWED => ['completed', 120, 'Reviewed', 'success', 'Visit closed. Great job.', 'View visit'],
                CareBooking::STATUS_DISPUTED => ['completed', 110, 'Disputed', 'danger', 'Dispute in review with support.', 'Open visit'],
                CareBooking::STATUS_CANCELLED => ['completed', 100, 'Cancelled', 'neutral', 'Visit was cancelled.', 'View visit'],
                default => ['hired', 300, 'Hired', 'success', 'Visit ready.', 'Open visit'],
            };
            $minutes = $this->estimatedShiftMinutes($request, $booking);
            $rate = $this->effectiveRequestRate($request, (float) ($application->proposed_rate ?: $defaultRate));
            $compensation = $this->compensationPayload($minutes, $rate);

            return [
                'id' => 'booking-'.$booking->id,
                'scope' => $scope,
                'state' => 'booking_'.$bookingStatus,
                'priority' => $priority,
                'created_at' => $booking->updated_at,
                'start_at' => $booking->scheduled_start_at ?: $request->requested_start_at,
                'title' => $request->title,
                'location' => $request->city.', '.$request->state,
                'schedule' => $this->scheduleLabel($request, $booking),
                'compensation' => $compensation,
                'compensation_line' => $compensation['line'] ?? null,
                'status_label' => $label,
                'status_tone' => $tone,
                'fit_reason' => $fitReason,
                'recipient_context' => $recipientContext,
                'note' => null,
                'primary_action' => [
                    'kind' => 'link',
                    'label' => $primaryLabel,
                    'href' => route('care-requests.apply', $request->id),
                ],
                'secondary_action' => $application->conversation
                    ? [
                        'kind' => 'link',
                        'label' => 'Open chat',
                        'href' => route('messages.show', $application->conversation->id),
                    ]
                    : null,
                'open_href' => route('care-requests.apply', $request->id),
                'meta' => 'Hired request',
            ];
        }

        return null;
    }

    private function recommendationScore(CareRequest $request, User $user, array $skillIds): int
    {
        $score = 0;

        if ($user->state && strtoupper((string) $user->state) === strtoupper((string) $request->state)) {
            $score += 25;
        }

        if ($user->city && strcasecmp((string) $user->city, (string) $request->city) === 0) {
            $score += 20;
        }

        if ($request->requested_start_at && $request->requested_start_at->lte(now()->addHours(24))) {
            $score += 18;
        }

        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            $score += 12;
        }

        $requestTaskIds = $request->tasks->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($requestTaskIds !== [] && $skillIds !== []) {
            $overlapCount = count(array_intersect($requestTaskIds, $skillIds));
            $score += min(25, $overlapCount * 8);
        }

        if (($request->preferred_response_hours ?? 12) <= 12) {
            $score += 8;
        }

        return $score;
    }

    private function requestPreview(CareRequest $request): ?string
    {
        $preview = trim((string) ($request->scope_of_work ?: $request->additional_info ?: $request->time_expectations));

        return $preview !== '' ? Str::limit($preview, 170) : null;
    }

    /**
     * @return list<string>
     */
    private function requestDetails(CareRequest $request): array
    {
        return array_values(array_filter([
            ($request->preferred_response_hours ?: 12).'h response target',
            $this->applicantCountLabel((int) ($request->applications_count ?? 0)),
            $request->tasks->isNotEmpty()
                ? $request->tasks->pluck('name')->take(3)->implode(', ')
                : null,
        ], fn ($value) => is_string($value) && trim($value) !== ''));
    }

    private function postedLabel(CareRequest $request): string
    {
        return $request->created_at?->diffForHumans() ?? 'recently';
    }

    private function applicantCountLabel(int $count): string
    {
        return $count.' applicant'.($count === 1 ? '' : 's');
    }

    private function recommendationReason(CareRequest $request, User $user, array $skillIds): string
    {
        if ($user->city && strcasecmp((string) $user->city, (string) $request->city) === 0) {
            return 'Same city match.';
        }

        $requestTaskIds = $request->tasks->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($requestTaskIds !== [] && $skillIds !== [] && count(array_intersect($requestTaskIds, $skillIds)) > 0) {
            return 'Matches your selected comfort tasks.';
        }

        if ($request->requested_start_at && $request->requested_start_at->lte(now()->addHours(24))) {
            return 'Starts soon and needs fast response.';
        }

        return 'Good profile fit based on your service settings.';
    }

    /**
     * @return array<string, mixed>
     */
    private function recipientContext(?CareRecipient $recipient = null, ?array $snapshot = null): array
    {
        $name = $recipient?->full_name ?: data_get($snapshot, 'full_name');
        $relationship = $recipient?->relationship_to_family ?: data_get($snapshot, 'relationship_to_family');
        $isRequester = (bool) ($recipient?->recipient_is_requester ?? data_get($snapshot, 'recipient_is_requester', false))
            || strcasecmp((string) $relationship, 'self') === 0;

        if (! $recipient && ! $snapshot && ! $isRequester) {
            return [];
        }

        return [
            'name' => trim((string) $name),
            'relationship' => trim((string) $relationship),
            'recipient_is_requester' => $isRequester,
            'label' => $isRequester ? 'Requester receives care' : 'Family member receives care',
            'description' => $isRequester
                ? 'The person posting is also receiving care.'
                : ($relationship ? 'A family contact is coordinating care for their '.strtolower((string) $relationship).'.' : 'A family contact is coordinating care for someone else.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planRecipientContext(CarePlan $plan): array
    {
        return $this->recipientContext(snapshot: $plan->recipient_snapshot ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function compensationPayload(?int $minutes, float $hourlyRate): ?array
    {
        if (! $minutes || $minutes <= 0 || $hourlyRate <= 0) {
            return null;
        }

        $hours = $minutes / 60;
        $total = round($hours * $hourlyRate, 2);
        $hoursLabel = abs($hours - round($hours)) < 0.01
            ? (string) (int) round($hours)
            : number_format($hours, 1);

        return [
            'minutes' => $minutes,
            'hours' => $hours,
            'hours_label' => $hoursLabel,
            'hourly_rate' => round($hourlyRate, 2),
            'total' => $total,
            'line' => sprintf(
                '%sh @ $%s/hr • $%s total visit',
                $hoursLabel,
                number_format($hourlyRate, 2),
                number_format($total, 2)
            ),
        ];
    }

    private function compensationLine(?int $minutes, float $hourlyRate): ?string
    {
        return $this->compensationPayload($minutes, $hourlyRate)['line'] ?? null;
    }

    private function effectiveRequestRate(CareRequest $request, float $fallback): float
    {
        return app(MarketplacePricing::class)->hourlyRateForRequest($request, $fallback);
    }

    private function effectivePlanRate(CarePlan $plan): float
    {
        return app(MarketplacePricing::class)->hourlyRateForFamily(
            $plan->family,
            (float) $plan->hourly_rate
        );
    }

    private function estimatedShiftMinutes(CareRequest $request, ?CareBooking $booking = null): ?int
    {
        if ($booking?->scheduled_start_at && $booking->scheduled_end_at) {
            $minutes = (int) $booking->scheduled_start_at->diffInMinutes($booking->scheduled_end_at, false);

            return $minutes > 0 ? $minutes : null;
        }

        if ($request->request_type === CareRequest::TYPE_ONE_TIME && $request->requested_start_at && $request->requested_end_at) {
            $minutes = (int) $request->requested_start_at->diffInMinutes($request->requested_end_at, false);

            return $minutes > 0 ? $minutes : null;
        }

        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            $slot = WeeklySchedule::first($request->recurringScheduleSlots());
            if (! $slot) {
                return null;
            }

            return WeeklySchedule::durationMinutes($slot);
        }

        return null;
    }

    private function timeStringToMinutes(?string $time): ?int
    {
        if (! $time) {
            return null;
        }

        try {
            $parsed = Carbon::parse($time);
        } catch (\Throwable) {
            return null;
        }

        return ((int) $parsed->format('H') * 60) + (int) $parsed->format('i');
    }

    private function estimatedPlanMinutes(CarePlan $plan): ?int
    {
        $slot = WeeklySchedule::first($plan->weeklyScheduleSlots());
        if (! $slot) {
            return null;
        }

        return WeeklySchedule::durationMinutes($slot);
    }

    private function planScheduleLabel(CarePlan $plan): string
    {
        return 'Regular: '.WeeklySchedule::label($plan->weeklyScheduleSlots());
    }

    private function planLocation(CarePlan $plan): string
    {
        $city = (string) data_get($plan->address_snapshot, 'city', $plan->family?->city);
        $state = (string) data_get($plan->address_snapshot, 'state', $plan->family?->state);
        $location = trim($city.', '.$state, ', ');

        return $location !== '' ? $location : 'Family address';
    }

    private function scheduleLabel(CareRequest $request, ?CareBooking $booking = null): string
    {
        if ($booking && $booking->scheduled_start_at && $booking->scheduled_end_at) {
            return 'Shift: '.$booking->scheduled_start_at->format('M d, H:i').' - '.$booking->scheduled_end_at->format('M d, H:i');
        }

        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            return 'Recurring: '.$request->recurringScheduleLabel();
        }

        if ($request->requested_start_at && $request->requested_end_at) {
            return 'One-time: '.$request->requested_start_at->format('M d, H:i').' - '.$request->requested_end_at->format('M d, H:i');
        }

        return 'Schedule pending';
    }
}
