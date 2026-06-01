<?php

namespace App\Livewire\Family;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Services\RegularCare\CarePlanService;
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

        $rebookableRequests = CareRequest::query()
            ->with([
                'recipient',
                'booking:id,care_request_id,status,scheduled_start_at,scheduled_end_at,completed_at,caregiver_user_id',
                'applications' => fn ($query) => $query
                    ->where('status', CareRequestApplication::STATUS_HIRED)
                    ->with('caregiver:id,name'),
            ])
            ->where('family_user_id', $user->id)
            ->whereNull('care_plan_id')
            ->whereHas('booking', function ($query) {
                $query->whereIn('status', [
                    CareBooking::STATUS_COMPLETED,
                    CareBooking::STATUS_REVIEWED,
                    CareBooking::STATUS_SCHEDULED,
                ]);
            })
            ->latest()
            ->limit(6)
            ->get();

        return view('livewire.family.regular-care-index', [
            'plans' => $carePlans,
            'rebookableRequests' => $rebookableRequests,
            'scheduleService' => $plans,
        ]);
    }
}
