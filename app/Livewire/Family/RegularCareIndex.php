<?php

namespace App\Livewire\Family;

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

        $rebookableRequests = app(FamilyRebookingOptions::class)->forUser($user, 6);

        return view('livewire.family.regular-care-index', [
            'plans' => $carePlans,
            'rebookableRequests' => $rebookableRequests,
            'scheduleService' => $plans,
        ]);
    }
}
