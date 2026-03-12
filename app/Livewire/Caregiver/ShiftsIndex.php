<?php

namespace App\Livewire\Caregiver;

use App\Models\CareBooking;
use App\Models\CareRequestApplication;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ShiftsIndex extends Component
{
    use WithPagination;

    public string $status = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'caregiver', 403);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $caregiverId = (int) auth()->id();

        $statusOrderSql = "CASE status
            WHEN 'in_progress' THEN 0
            WHEN 'paused' THEN 1
            WHEN 'scheduled' THEN 2
            WHEN 'completed' THEN 3
            WHEN 'reviewed' THEN 4
            WHEN 'disputed' THEN 5
            WHEN 'cancelled' THEN 6
            ELSE 7 END";

        $bookingsQuery = CareBooking::query()
            ->with([
                'careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at,recurring_days,recurring_start_time,recurring_end_time',
                'family:id,name',
                'application:id,care_request_id,caregiver_user_id,status,proposed_rate',
                'application.conversation:id,care_request_application_id',
            ])
            ->where('caregiver_user_id', $caregiverId);

        if ($this->status !== 'all') {
            $bookingsQuery->where('status', $this->status);
        }

        $bookings = $bookingsQuery
            ->orderByRaw($statusOrderSql)
            ->orderBy('scheduled_start_at')
            ->orderByDesc('updated_at')
            ->paginate(10);

        $hiredWithoutBooking = CareRequestApplication::query()
            ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CareRequestApplication::STATUS_HIRED)
            ->whereDoesntHave('booking')
            ->latest()
            ->limit(6)
            ->get();

        $counts = [
            'active' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->count(),
            'scheduled' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->where('status', CareBooking::STATUS_SCHEDULED)
                ->count(),
            'completed' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
                ->count(),
            'issues' => CareBooking::query()
                ->where('caregiver_user_id', $caregiverId)
                ->whereIn('status', [CareBooking::STATUS_DISPUTED, CareBooking::STATUS_CANCELLED])
                ->count(),
        ];

        $nextShift = CareBooking::query()
            ->with('careRequest:id,title,city,state')
            ->where('caregiver_user_id', $caregiverId)
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_start_at')
            ->orderBy('scheduled_start_at')
            ->first();

        return view('livewire.caregiver.shifts-index', [
            'bookings' => $bookings,
            'hiredWithoutBooking' => $hiredWithoutBooking,
            'counts' => $counts,
            'nextShift' => $nextShift,
        ]);
    }
}
