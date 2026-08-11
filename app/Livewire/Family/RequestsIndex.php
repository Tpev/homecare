<?php

namespace App\Livewire\Family;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\CareRequestProgress;
use App\Support\FamilyRebookingOptions;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class RequestsIndex extends Component
{
    use WithPagination;

    public string $status = 'all';

    public string $requestType = 'all';

    public string $sort = 'latest';

    public array $statusOptions = [
        ['label' => 'All statuses', 'value' => 'all'],
        ['label' => 'Open', 'value' => CareRequest::STATUS_OPEN],
        ['label' => 'Visit scheduled', 'value' => CareRequest::STATUS_FILLED],
        ['label' => 'Draft', 'value' => CareRequest::STATUS_DRAFT],
        ['label' => 'Withdrawn', 'value' => CareRequest::STATUS_CANCELLED],
        ['label' => 'Expired', 'value' => CareRequest::STATUS_EXPIRED],
    ];

    public array $sortOptions = [
        ['label' => 'Latest first', 'value' => 'latest'],
        ['label' => 'Oldest first', 'value' => 'oldest'],
        ['label' => 'Start time (soonest)', 'value' => 'start_soon'],
    ];

    public array $requestTypeOptions = [
        ['label' => 'All types', 'value' => 'all'],
        ['label' => 'One visit', 'value' => CareRequest::TYPE_ONE_TIME],
        ['label' => 'Repeats weekly', 'value' => CareRequest::TYPE_RECURRING],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingRequestType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $account = app(FamilyAccountContext::class)->account(auth()->user());
        $requestRelations = [
            'recipient',
            'booking.caregiver:id,name',
            'booking.caregiver.caregiverProfile:id,user_id,profile_photo_path',
            'booking.payment:id,care_booking_id,status,last_error',
        ];
        $requests = CareRequest::query()
            ->with($requestRelations)
            ->withCount(['applications'])
            ->forFamilyAccount($account)
            ->where('is_system_generated', false)
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->requestType !== 'all', fn ($q) => $q->where('request_type', $this->requestType));

        match ($this->sort) {
            'oldest' => $requests->orderBy('created_at'),
            'start_soon' => $requests->orderBy('requested_start_at'),
            default => $requests->orderByDesc('created_at'),
        };

        $paginated = $requests->paginate(10);

        $carePlans = CarePlan::query()
            ->with([
                'caregiver:id,name,email,city,state',
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status,last_error',
            ])
            ->forFamilyAccount($account)
            ->orderByRaw("CASE WHEN status = '".CarePlan::STATUS_PAYMENT_ATTENTION."' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(4)
            ->get();

        $paymentAttentionPlans = CarePlan::query()
            ->with([
                'caregiver:id,name,email,city,state',
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status,last_error',
            ])
            ->forFamilyAccount($account)
            ->where('status', CarePlan::STATUS_PAYMENT_ATTENTION)
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        $rebookableRequests = app(FamilyRebookingOptions::class)->forUser(auth()->user(), 4);

        $attentionQuery = CareRequest::query()
            ->forFamilyAccount($account)
            ->where('is_system_generated', false)
            ->where(function ($query) {
                $query->where(function ($requestQuery) {
                    $requestQuery->where('status', CareRequest::STATUS_OPEN)
                        ->whereHas('applications', function ($applicationQuery) {
                            $applicationQuery->whereIn('status', [
                                CareRequestApplication::STATUS_APPLIED,
                                CareRequestApplication::STATUS_SHORTLISTED,
                            ]);
                        });
                })->orWhereHas('booking', function ($bookingQuery) {
                    $bookingQuery->where(function ($query) {
                        $query->whereIn('status', [
                            CareBooking::STATUS_IN_PROGRESS,
                            CareBooking::STATUS_PAUSED,
                        ])->orWhere(function ($reviewQuery) {
                            $reviewQuery->whereIn('status', [
                                CareBooking::STATUS_COMPLETED,
                                CareBooking::STATUS_REVIEWED,
                            ])->whereNull('family_confirmed_at');
                        })->orWhereHas('payment', fn ($paymentQuery) => $paymentQuery
                            ->whereIn('status', CareBookingPayment::FAMILY_ACTION_REQUIRED_STATUSES));
                    });
                });
            });

        $attentionCount = (clone $attentionQuery)->count()
            + CarePlan::query()
                ->forFamilyAccount($account)
                ->where('status', CarePlan::STATUS_PAYMENT_ATTENTION)
                ->count();

        $attentionRequests = $attentionQuery
            ->with($requestRelations)
            ->withCount('applications')
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        $avgFirstResponseMinutes = CareRequest::query()
            ->forFamilyAccount($account)
            ->where('is_system_generated', false)
            ->whereNotNull('first_applicant_at')
            ->get(['created_at', 'first_applicant_at'])
            ->map(fn (CareRequest $request) => CareRequestProgress::elapsedMinutes($request->created_at, $request->first_applicant_at))
            ->filter(fn (?int $minutes) => $minutes !== null)
            ->avg();

        return view('livewire.family.requests-index', [
            'requests' => $paginated,
            'carePlans' => $carePlans,
            'paymentAttentionPlans' => $paymentAttentionPlans,
            'attentionRequests' => $attentionRequests,
            'rebookableRequests' => $rebookableRequests,
            'attentionCount' => $attentionCount,
            'avgFirstResponseLabel' => CareRequestProgress::minutesLabel(
                $avgFirstResponseMinutes !== null ? (int) round($avgFirstResponseMinutes) : null
            ),
        ]);
    }
}
