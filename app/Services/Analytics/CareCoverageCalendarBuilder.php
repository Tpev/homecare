<?php

namespace App\Services\Analytics;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CareCoverageCalendarBuilder
{
    /**
     * @param  array{view:string,family_account:string,caregiver:string,status:string,q:string}  $filters
     * @return array{
     *     days: array<int, array<string, mixed>>,
     *     events: Collection<int, array<string, mixed>>,
     *     openRequests: Collection<int, array<string, mixed>>,
     *     summary: array{shifts:int,open_slots:int,open_requests:int,families:int}
     * }
     */
    public function build(Carbon $month, Carbon $gridStart, Carbon $gridEnd, array $filters): array
    {
        $events = collect();
        $openRequests = collect();

        if ($this->includesShifts($filters)) {
            $events = $events->concat(
                $this->bookingEvents($gridStart, $gridEnd, $filters)
            );
        }

        if ($this->includesOpenRequests($filters)) {
            $requests = $this->openRequestQuery($filters)->get();
            $openRequests = $requests->map(fn (CareRequest $request): array => $this->openRequestRow($request));

            $events = $events->concat(
                $requests->flatMap(
                    fn (CareRequest $request): array => $this->requestEvents($request, $gridStart, $gridEnd)
                )
            );
        }

        $events = $events
            ->sortBy([
                ['start_at', 'asc'],
                ['kind', 'asc'],
                ['customer', 'asc'],
            ])
            ->values();

        $eventsByDate = $events->groupBy(
            fn (array $event): string => $event['start_at']->toDateString()
        );

        $days = [];
        $cursor = $gridStart->copy()->startOfDay();
        while ($cursor->lte($gridEnd)) {
            $dateKey = $cursor->toDateString();
            $days[] = [
                'date' => $cursor->copy(),
                'date_key' => $dateKey,
                'is_current_month' => $cursor->month === $month->month && $cursor->year === $month->year,
                'is_today' => $cursor->isToday(),
                'events' => $eventsByDate->get($dateKey, collect())->values(),
            ];
            $cursor->addDay();
        }

        return [
            'days' => $days,
            'events' => $events,
            'openRequests' => $openRequests,
            'summary' => [
                'shifts' => $events->where('kind', 'shift')->count(),
                'open_slots' => $events->where('kind', 'open_request')->count(),
                'open_requests' => $openRequests->count(),
                'families' => $events->pluck('family_account_id')->filter()->unique()->count(),
            ],
        ];
    }

    /** @return Collection<int, array{value:string,label:string}> */
    public function familyOptions(): Collection
    {
        return FamilyAccount::query()
            ->with('owner:id,name,email')
            ->where(function (Builder $query): void {
                $query->whereHas('owner.familyBookings')
                    ->orWhereHas('owner.careRequests');
            })
            ->get(['id', 'owner_user_id'])
            ->map(fn (FamilyAccount $account): array => [
                'value' => (string) $account->id,
                'label' => ($account->owner?->name ?: 'Unknown family').' · Account #'.$account->id,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return Collection<int, array{value:string,label:string}> */
    public function caregiverOptions(): Collection
    {
        return User::query()
            ->where('role', 'caregiver')
            ->whereHas('caregiverBookings')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $caregiver): array => [
                'value' => (string) $caregiver->id,
                'label' => $caregiver->name ?: $caregiver->email,
            ]);
    }

    /**
     * @param  array{view:string,family_account:string,caregiver:string,status:string,q:string}  $filters
     */
    private function includesShifts(array $filters): bool
    {
        return $filters['view'] !== 'open_requests'
            && $filters['status'] !== CareRequest::STATUS_OPEN;
    }

    /**
     * @param  array{view:string,family_account:string,caregiver:string,status:string,q:string}  $filters
     */
    private function includesOpenRequests(array $filters): bool
    {
        return $filters['view'] !== 'shifts'
            && $filters['caregiver'] === 'all'
            && in_array($filters['status'], ['all', CareRequest::STATUS_OPEN], true);
    }

    /**
     * @param  array{view:string,family_account:string,caregiver:string,status:string,q:string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function bookingEvents(Carbon $gridStart, Carbon $gridEnd, array $filters): Collection
    {
        $query = CareBooking::query()
            ->whereNotNull('scheduled_start_at')
            ->whereBetween('scheduled_start_at', [
                $gridStart->copy()->startOfDay(),
                $gridEnd->copy()->endOfDay(),
            ])
            ->with([
                'careRequest:id,family_account_id,family_user_id,care_plan_id,title,is_private,city,state',
                'careRequest.recipient:id,care_request_id,full_name',
                'carePlan:id,recipient_snapshot',
                'familyAccount:id,owner_user_id',
                'familyAccount.owner:id,name,email',
                'family:id,name,email',
                'caregiver:id,name,email',
            ]);

        if ($filters['family_account'] !== 'all') {
            $query->where('family_account_id', (int) $filters['family_account']);
        }

        if ($filters['caregiver'] !== 'all') {
            $query->where('caregiver_user_id', (int) $filters['caregiver']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $this->applyBookingSearch($query, $filters['q']);

        return $query->get()->map(fn (CareBooking $booking): array => $this->bookingEvent($booking));
    }

    /**
     * @param  array{view:string,family_account:string,caregiver:string,status:string,q:string}  $filters
     */
    private function openRequestQuery(array $filters): Builder
    {
        $query = CareRequest::query()
            ->where('is_system_generated', false)
            ->where('status', CareRequest::STATUS_OPEN)
            ->whereDoesntHave('booking')
            ->with([
                'recipient:id,care_request_id,full_name',
                'familyAccount:id,owner_user_id',
                'familyAccount.owner:id,name,email',
                'family:id,name,email',
            ])
            ->withCount(['applications', 'invitations'])
            ->orderByRaw('CASE WHEN requested_start_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('requested_start_at')
            ->orderBy('created_at');

        if ($filters['family_account'] !== 'all') {
            $query->where('family_account_id', (int) $filters['family_account']);
        }

        $this->applyRequestSearch($query, $filters['q']);

        return $query;
    }

    private function bookingEvent(CareBooking $booking): array
    {
        $request = $booking->careRequest;
        $customer = trim((string) ($request?->recipient?->full_name ?? ''));
        if ($customer === '') {
            $customer = trim((string) ($booking->carePlan?->recipientName() ?? '')) ?: 'Care recipient';
        }

        $family = $booking->familyAccount?->owner?->name
            ?: $booking->family?->name
            ?: 'Unknown family';

        return [
            'key' => 'shift-'.$booking->id,
            'kind' => 'shift',
            'booking_id' => $booking->id,
            'request_id' => $booking->care_request_id,
            'family_account_id' => $booking->family_account_id,
            'start_at' => $booking->scheduled_start_at->copy(),
            'end_at' => $booking->scheduled_end_at?->copy(),
            'customer' => $customer,
            'family' => $family,
            'caregiver' => $booking->caregiver?->name ?: 'Unknown caregiver',
            'status' => (string) $booking->status,
            'status_label' => Str::headline((string) $booking->status),
            'type_label' => $this->bookingTypeLabel($booking),
            'title' => $request?->title ?: 'Care shift',
            'location' => $this->location($request?->city, $request?->state),
            'is_private' => (bool) $request?->is_private,
            'applications' => null,
            'invitations' => null,
            'url' => $booking->care_request_id
                ? route('admin.requests.show', $booking->care_request_id)
                : route('admin.care-plans.show', $booking->care_plan_id),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function requestEvents(CareRequest $request, Carbon $gridStart, Carbon $gridEnd): array
    {
        if (! $request->isRecurring()) {
            if (! $request->requested_start_at
                || $request->requested_start_at->lt($gridStart->copy()->startOfDay())
                || $request->requested_start_at->gt($gridEnd->copy()->endOfDay())) {
                return [];
            }

            return [$this->requestEvent(
                $request,
                $request->requested_start_at->copy(),
                $request->requested_end_at?->copy(),
                'Open request'
            )];
        }

        $slots = $request->recurringScheduleSlots();
        if ($slots === []) {
            return [];
        }

        $requestStart = $request->recurring_starts_on?->copy()->startOfDay()
            ?? $request->requested_start_at?->copy()->startOfDay()
            ?? $request->created_at?->copy()->startOfDay()
            ?? $gridStart->copy()->startOfDay();
        $requestEnd = $request->recurring_ends_on?->copy()->endOfDay()
            ?? $gridEnd->copy()->endOfDay();
        $startsOn = $requestStart->max($gridStart->copy()->startOfDay());
        $endsOn = $requestEnd->min($gridEnd->copy()->endOfDay());

        if ($startsOn->gt($endsOn)) {
            return [];
        }

        $events = [];
        $cursor = $startsOn->copy()->startOfDay();
        while ($cursor->lte($endsOn)) {
            foreach ($slots as $slot) {
                if ($cursor->dayOfWeek !== (int) $slot['day']) {
                    continue;
                }

                $start = $cursor->copy()->setTimeFromTimeString($slot['start_time']);
                $end = $cursor->copy()->setTimeFromTimeString($slot['end_time']);
                $events[] = $this->requestEvent($request, $start, $end, 'Recurring request');
            }
            $cursor->addDay();
        }

        return $events;
    }

    private function requestEvent(CareRequest $request, Carbon $start, ?Carbon $end, string $typeLabel): array
    {
        return [
            'key' => 'request-'.$request->id.'-'.$start->format('YmdHi'),
            'kind' => 'open_request',
            'booking_id' => null,
            'request_id' => $request->id,
            'family_account_id' => $request->family_account_id,
            'start_at' => $start,
            'end_at' => $end,
            'customer' => trim((string) ($request->recipient?->full_name ?? '')) ?: 'Care recipient',
            'family' => $request->familyAccount?->owner?->name ?: $request->family?->name ?: 'Unknown family',
            'caregiver' => null,
            'status' => CareRequest::STATUS_OPEN,
            'status_label' => 'Open · unassigned',
            'type_label' => $typeLabel,
            'title' => $request->title ?: 'Open care request',
            'location' => $this->location($request->city, $request->state),
            'is_private' => (bool) $request->is_private,
            'applications' => (int) $request->applications_count,
            'invitations' => (int) $request->invitations_count,
            'url' => route('admin.requests.show', $request),
        ];
    }

    private function openRequestRow(CareRequest $request): array
    {
        if ($request->isRecurring()) {
            $schedule = $request->recurringScheduleLabel();
            $range = collect([
                $request->recurring_starts_on?->format('M j, Y'),
                $request->recurring_ends_on?->format('M j, Y'),
            ])->filter()->implode(' – ');
            $schedule = trim($schedule.($range !== '' ? ' · '.$range : ''));
        } elseif ($request->requested_start_at) {
            $schedule = $request->requested_start_at->format('M j, Y · g:i A');
            if ($request->requested_end_at) {
                $schedule .= '–'.$request->requested_end_at->format('g:i A');
            }
        } else {
            $schedule = 'Unscheduled';
        }

        return [
            'id' => $request->id,
            'title' => $request->title ?: 'Open care request',
            'customer' => trim((string) ($request->recipient?->full_name ?? '')) ?: 'Care recipient',
            'family' => $request->familyAccount?->owner?->name ?: $request->family?->name ?: 'Unknown family',
            'family_account_id' => $request->family_account_id,
            'schedule' => $schedule !== '' ? $schedule : 'Unscheduled',
            'location' => $this->location($request->city, $request->state),
            'applications' => (int) $request->applications_count,
            'invitations' => (int) $request->invitations_count,
            'is_private' => (bool) $request->is_private,
            'url' => route('admin.requests.show', $request),
        ];
    }

    private function bookingTypeLabel(CareBooking $booking): string
    {
        return match ($booking->plan_visit_kind) {
            'regular' => 'Recurring shift',
            'extra' => 'Extra visit',
            'coverage' => 'Coverage shift',
            default => $booking->care_plan_id ? 'Recurring shift' : 'One-time shift',
        };
    }

    private function location(mixed $city, mixed $state): string
    {
        return collect([trim((string) $city), trim((string) $state)])->filter()->implode(', ');
    }

    private function applyBookingSearch(Builder $query, string $search): void
    {
        $term = trim($search);
        if ($term === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($term): void {
            $searchQuery
                ->whereHas('careRequest', fn (Builder $request) => $request
                    ->where('title', 'like', '%'.$term.'%')
                    ->orWhereHas('recipient', fn (Builder $recipient) => $recipient
                        ->where('full_name', 'like', '%'.$term.'%')))
                ->orWhereHas('family', fn (Builder $family) => $family
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%'))
                ->orWhereHas('caregiver', fn (Builder $caregiver) => $caregiver
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%'));
        });
    }

    private function applyRequestSearch(Builder $query, string $search): void
    {
        $term = trim($search);
        if ($term === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($term): void {
            $searchQuery
                ->where('title', 'like', '%'.$term.'%')
                ->orWhere('city', 'like', '%'.$term.'%')
                ->orWhere('state', 'like', '%'.$term.'%')
                ->orWhereHas('recipient', fn (Builder $recipient) => $recipient
                    ->where('full_name', 'like', '%'.$term.'%'))
                ->orWhereHas('family', fn (Builder $family) => $family
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%'));
        });
    }
}
