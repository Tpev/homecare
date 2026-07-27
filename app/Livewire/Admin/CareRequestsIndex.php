<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\CareRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CareRequestsIndex extends Component
{
    use WithPagination;

    public string $q = '';

    public string $status = 'all';

    public string $requestType = 'all';

    public string $sort = 'latest';

    public int $perPage = 25;

    protected $queryString = [
        'q' => ['except' => ''],
        'status' => ['except' => 'all'],
        'requestType' => ['except' => 'all'],
        'sort' => ['except' => 'latest'],
        'perPage' => ['except' => 25],
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingRequestType(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function forceRequestStatus(int $requestId, string $status): void
    {
        if (! in_array($status, [
            CareRequest::STATUS_DRAFT,
            CareRequest::STATUS_OPEN,
            CareRequest::STATUS_FILLED,
            CareRequest::STATUS_CANCELLED,
            CareRequest::STATUS_EXPIRED,
        ], true)) {
            return;
        }

        $request = CareRequest::query()->findOrFail($requestId);
        $request->update(['status' => $status]);

        session()->flash('status', 'Request #'.$request->id.' status updated to '.strtoupper($status).'.');
    }

    public function forceBookingStatus(int $requestId, string $status): void
    {
        if (! in_array($status, [
            CareBooking::STATUS_SCHEDULED,
            CareBooking::STATUS_IN_PROGRESS,
            CareBooking::STATUS_PAUSED,
            CareBooking::STATUS_COMPLETED,
            CareBooking::STATUS_DISPUTED,
            CareBooking::STATUS_REVIEWED,
            CareBooking::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $request = CareRequest::query()->with('booking')->findOrFail($requestId);
        if (! $request->booking) {
            $this->addError('booking', 'No booking exists for this request yet.');

            return;
        }

        $payload = ['status' => $status];
        if ($status === CareBooking::STATUS_COMPLETED && ! $request->booking->completed_at) {
            $payload['completed_at'] = now();
        }
        if ($status === CareBooking::STATUS_CANCELLED && ! $request->booking->cancelled_at) {
            $payload['cancelled_at'] = now();
            $payload['cancelled_by_user_id'] = auth()->id();
            $payload['cancellation_reason'] = 'Admin override from request operations panel.';
        }

        $request->booking->update($payload);
        session()->flash('status', 'Booking for request #'.$request->id.' moved to '.strtoupper($status).'.');
    }

    public function deleteRequest(int $requestId): void
    {
        $request = CareRequest::query()->findOrFail($requestId);

        try {
            $request->delete();
        } catch (QueryException) {
            $this->addError('delete', 'Could not delete this request because related records are protected.');

            return;
        }

        session()->flash('status', 'Request #'.$requestId.' deleted.');
    }

    public function render(): View
    {
        $baseQuery = CareRequest::query()->where('is_system_generated', false);

        $query = CareRequest::query()
            ->where('is_system_generated', false)
            ->with([
                'family:id,name,email',
                'recipient:id,care_request_id,full_name,relationship_to_family',
                'booking:id,care_request_id,caregiver_user_id,status,scheduled_start_at,scheduled_end_at,completed_at',
                'booking.caregiver:id,name',
            ])
            ->withCount(['applications', 'invitations', 'conversations', 'tasks']);

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->requestType !== 'all') {
            $query->where('request_type', $this->requestType);
        }

        if (trim($this->q) !== '') {
            $term = trim($this->q);
            $query->where(function ($subQuery) use ($term) {
                $subQuery
                    ->where('title', 'like', '%'.$term.'%')
                    ->orWhere('city', 'like', '%'.$term.'%')
                    ->orWhere('state', 'like', '%'.$term.'%')
                    ->orWhere('zip', 'like', '%'.$term.'%')
                    ->orWhereHas('family', function ($familyQuery) use ($term) {
                        $familyQuery
                            ->where('name', 'like', '%'.$term.'%')
                            ->orWhere('email', 'like', '%'.$term.'%');
                    });
            });
        }

        match ($this->sort) {
            'oldest' => $query->oldest('created_at'),
            'updated' => $query->latest('updated_at'),
            'start_soon' => $query->orderByRaw('COALESCE(requested_start_at, created_at) asc'),
            default => $query->latest('created_at'),
        };

        $requests = $query->paginate($this->perPage);

        return view('livewire.admin.care-requests-index', [
            'requests' => $requests,
            'summary' => [
                'all' => (clone $baseQuery)->count(),
                'open' => (clone $baseQuery)->where('status', CareRequest::STATUS_OPEN)->count(),
                'filled' => (clone $baseQuery)->where('status', CareRequest::STATUS_FILLED)->count(),
                'cancelled' => (clone $baseQuery)->where('status', CareRequest::STATUS_CANCELLED)->count(),
                'with_booking' => (clone $baseQuery)->whereHas('booking')->count(),
            ],
            'statusOptions' => [
                ['label' => 'All statuses', 'value' => 'all'],
                ['label' => 'Draft', 'value' => CareRequest::STATUS_DRAFT],
                ['label' => 'Open', 'value' => CareRequest::STATUS_OPEN],
                ['label' => 'Filled', 'value' => CareRequest::STATUS_FILLED],
                ['label' => 'Cancelled', 'value' => CareRequest::STATUS_CANCELLED],
                ['label' => 'Expired', 'value' => CareRequest::STATUS_EXPIRED],
            ],
            'typeOptions' => [
                ['label' => 'All request types', 'value' => 'all'],
                ['label' => 'One-time', 'value' => CareRequest::TYPE_ONE_TIME],
                ['label' => 'Recurring', 'value' => CareRequest::TYPE_RECURRING],
            ],
            'sortOptions' => [
                ['label' => 'Newest first', 'value' => 'latest'],
                ['label' => 'Oldest first', 'value' => 'oldest'],
                ['label' => 'Recently updated', 'value' => 'updated'],
                ['label' => 'Starting soon', 'value' => 'start_soon'],
            ],
        ]);
    }
}
