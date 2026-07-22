<?php

namespace App\Livewire\Family;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Services\RegularCare\CarePlanService;
use App\Support\FamilyRebookingOptions;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RegularCareIndex extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function render(CarePlanService $plans)
    {
        $user = auth()->user();

        $carePlans = CarePlan::query()
            ->with([
                'caregiver:id,name,email,city,state',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path,average_rating,reviews_count,platform_hourly_rate',
                'sourceCareRequest:id,title',
                'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                'nextBooking.payment:id,care_booking_id,status',
            ])
            ->where('family_user_id', $user->id)
            ->latest()
            ->get();

        $nextPlan = CarePlan::query()
            ->with(['caregiver:id,name', 'nextBooking.payment'])
            ->where('family_user_id', $user->id)
            ->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])
            ->whereHas('nextBooking', fn ($query) => $query->whereIn('status', [
                CareBooking::STATUS_SCHEDULED,
                CareBooking::STATUS_IN_PROGRESS,
                CareBooking::STATUS_PAUSED,
            ]))
            ->orderBy(CareBooking::query()
                ->select('scheduled_start_at')
                ->whereColumn('care_bookings.id', 'care_plans.next_booking_id')
                ->limit(1))
            ->first();

        $rebookableRequests = app(FamilyRebookingOptions::class)->forUser($user, 6);

        return view('livewire.family.regular-care-index', [
            'plans' => $carePlans,
            'nextPlan' => $nextPlan,
            'rebookableRequests' => $rebookableRequests,
            'scheduleService' => $plans,
        ]);
    }
}
