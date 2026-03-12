<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        $defaultRate = (float) ($profile?->resolvePlatformHourlyRate() ?? 0);

        $items = collect();

        $pendingInvitations = CareRequestInvitation::query()
            ->with([
                'family:id,name',
                'careRequest:id,title,request_type,requested_start_at,requested_end_at,recurring_days,recurring_start_time,recurring_end_time,city,state,status',
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
                'compensation_line' => $this->compensationLine($invitationMinutes, $defaultRate),
                'status_label' => 'Invited',
                'status_tone' => 'info',
                'fit_reason' => 'Family invited you directly.',
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

        $applications = CareRequestApplication::query()
            ->with([
                'careRequest:id,title,request_type,requested_start_at,requested_end_at,recurring_days,recurring_start_time,recurring_end_time,city,state,status,created_at',
                'conversation:id,care_request_application_id',
                'booking:id,care_request_application_id,status,scheduled_start_at,scheduled_end_at,started_at,completed_at',
            ])
            ->where('caregiver_user_id', $caregiverId)
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

        $excludedRequestIds = collect()
            ->merge($pendingInvitations->pluck('care_request_id'))
            ->merge($applications->pluck('care_request_id'))
            ->unique()
            ->values();

        $recommendedRequests = CareRequest::query()
            ->with(['tasks:id,name'])
            ->where('status', CareRequest::STATUS_OPEN)
            ->when($excludedRequestIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $excludedRequestIds))
            ->latest()
            ->limit(30)
            ->get();

        foreach ($recommendedRequests as $request) {
            $score = $this->recommendationScore($request, $user, $skillIds);
            if ($score <= 0) {
                continue;
            }

            $recommendedMinutes = $this->estimatedShiftMinutes($request);

            $items->push([
                'id' => 'recommended-'.$request->id,
                'scope' => 'recommended',
                'state' => 'open_match',
                'priority' => 300 + $score,
                'created_at' => $request->created_at,
                'start_at' => $request->requested_start_at,
                'title' => $request->title,
                'location' => $request->city.', '.$request->state,
                'schedule' => $this->scheduleLabel($request),
                'compensation_line' => $this->compensationLine($recommendedMinutes, $defaultRate),
                'status_label' => 'Open',
                'status_tone' => 'neutral',
                'fit_reason' => $this->recommendationReason($request, $user, $skillIds),
                'note' => null,
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
                'meta' => 'Recommended for you',
            ]);
        }

        $items = $items->filter(function (array $item) use ($scope) {
            return $scope === 'all' ? true : $item['scope'] === $scope;
        });

        $items = match ($sort) {
            'newest' => $items->sortByDesc(fn (array $item) => optional($item['created_at'])->getTimestamp() ?? 0),
            'start_soon' => $items->sortBy(fn (array $item) => optional($item['start_at'])->getTimestamp() ?? PHP_INT_MAX),
            'best_fit' => $items->sortByDesc(fn (array $item) => (int) ($item['priority'] ?? 0)),
            default => $items->sortByDesc(function (array $item) {
                $scopeWeight = match ((string) ($item['scope'] ?? 'all')) {
                    'needs_response' => 5000,
                    'hired' => 4000,
                    'recommended' => 3000,
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

        return [
            'all' => $items->count(),
            'needs_response' => $items->where('scope', 'needs_response')->count(),
            'recommended' => $items->where('scope', 'recommended')->count(),
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

        if ($status === CareRequestApplication::STATUS_APPLIED) {
            $minutes = $this->estimatedShiftMinutes($request, $booking);
            $rate = (float) ($application->proposed_rate ?: $defaultRate);

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
                'compensation_line' => $this->compensationLine($minutes, $rate),
                'status_label' => 'Applied',
                'status_tone' => 'neutral',
                'fit_reason' => 'Waiting for family review.',
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
            $rate = (float) ($application->proposed_rate ?: $defaultRate);

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
                'compensation_line' => $this->compensationLine($minutes, $rate),
                'status_label' => 'Shortlisted',
                'status_tone' => 'success',
                'fit_reason' => 'Family shortlisted you. Chat now to increase hire odds.',
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
                $rate = (float) ($application->proposed_rate ?: $defaultRate);

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
                    'compensation_line' => $this->compensationLine($minutes, $rate),
                    'status_label' => 'Hired',
                    'status_tone' => 'success',
                    'fit_reason' => 'You were hired. Open shift details and confirm agreement.',
                    'note' => null,
                    'primary_action' => [
                        'kind' => 'link',
                        'label' => 'Open shift setup',
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
                    'meta' => 'Ready for shift setup',
                ];
            }

            $bookingStatus = (string) $booking->status;
            [$scope, $priority, $label, $tone, $fitReason, $primaryLabel] = match ($bookingStatus) {
                CareBooking::STATUS_SCHEDULED => ['hired', 420, 'Scheduled', 'info', 'Shift scheduled. Start when you arrive.', 'Start shift'],
                CareBooking::STATUS_IN_PROGRESS => ['hired', 500, 'In progress', 'success', 'Shift is live right now.', 'Continue shift'],
                CareBooking::STATUS_PAUSED => ['hired', 490, 'Paused', 'warning', 'Shift paused. Resume or end when ready.', 'Resume shift'],
                CareBooking::STATUS_COMPLETED => ['completed', 140, 'Completed', 'warning', 'Timesheet submitted and waiting family confirmation.', 'View recap'],
                CareBooking::STATUS_REVIEWED => ['completed', 120, 'Reviewed', 'success', 'Shift closed. Great job.', 'View shift'],
                CareBooking::STATUS_DISPUTED => ['completed', 110, 'Disputed', 'danger', 'Dispute in review with support.', 'Open shift'],
                CareBooking::STATUS_CANCELLED => ['completed', 100, 'Cancelled', 'neutral', 'Shift was cancelled.', 'View shift'],
                default => ['hired', 300, 'Hired', 'success', 'Shift ready.', 'Open shift'],
            };
            $minutes = $this->estimatedShiftMinutes($request, $booking);
            $rate = (float) ($application->proposed_rate ?: $defaultRate);

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
                'compensation_line' => $this->compensationLine($minutes, $rate),
                'status_label' => $label,
                'status_tone' => $tone,
                'fit_reason' => $fitReason,
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

    private function compensationLine(?int $minutes, float $hourlyRate): ?string
    {
        if (! $minutes || $minutes <= 0 || $hourlyRate <= 0) {
            return null;
        }

        $hours = $minutes / 60;
        $total = round($hours * $hourlyRate, 2);
        $hoursLabel = abs($hours - round($hours)) < 0.01
            ? (string) (int) round($hours)
            : number_format($hours, 1);

        return sprintf(
            '%sh @ $%s/hr • $%s total shift',
            $hoursLabel,
            number_format($hourlyRate, 2),
            number_format($total, 2)
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
            $startMinutes = $this->timeStringToMinutes($request->recurring_start_time);
            $endMinutes = $this->timeStringToMinutes($request->recurring_end_time);

            if ($startMinutes === null || $endMinutes === null) {
                return null;
            }

            $diff = $endMinutes - $startMinutes;
            if ($diff <= 0) {
                $diff += 24 * 60;
            }

            return $diff > 0 ? $diff : null;
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

    private function scheduleLabel(CareRequest $request, ?CareBooking $booking = null): string
    {
        if ($booking && $booking->scheduled_start_at && $booking->scheduled_end_at) {
            return 'Shift: '.$booking->scheduled_start_at->format('M d, H:i').' - '.$booking->scheduled_end_at->format('M d, H:i');
        }

        if ($request->request_type === CareRequest::TYPE_RECURRING) {
            $dayMap = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $days = collect($request->recurring_days ?? [])
                ->map(fn ($day) => $dayMap[(int) $day] ?? null)
                ->filter()
                ->implode(', ');

            return 'Recurring: '.$days.' '.$request->recurring_start_time.'-'.$request->recurring_end_time;
        }

        if ($request->requested_start_at && $request->requested_end_at) {
            return 'One-time: '.$request->requested_start_at->format('M d, H:i').' - '.$request->requested_end_at->format('M d, H:i');
        }

        return 'Schedule pending';
    }
}
