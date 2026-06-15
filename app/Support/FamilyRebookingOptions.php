<?php

namespace App\Support;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use Illuminate\Support\Collection;

class FamilyRebookingOptions
{
    /**
     * @return Collection<int, CareRequest>
     */
    public function forUser(User $family, int $limit = 4): Collection
    {
        return CareRequest::query()
            ->with([
                'recipient',
                'booking:id,care_request_id,status,scheduled_start_at,scheduled_end_at,completed_at,caregiver_user_id',
                'applications' => fn ($query) => $query
                    ->where('status', CareRequestApplication::STATUS_HIRED)
                    ->with('caregiver:id,name'),
            ])
            ->where('family_user_id', $family->id)
            ->whereNull('care_plan_id')
            ->whereHas('applications', function ($query) {
                $query->where('status', CareRequestApplication::STATUS_HIRED);
            })
            ->whereHas('booking', function ($query) {
                $query->whereIn('status', [
                    CareBooking::STATUS_COMPLETED,
                    CareBooking::STATUS_REVIEWED,
                ])->whereNotNull('family_confirmed_at');
            })
            ->latest()
            ->get()
            ->unique(fn (CareRequest $request) => (int) ($request->booking?->caregiver_user_id
                ?: $request->applications->first()?->caregiver_user_id))
            ->take($limit)
            ->values();
    }
}
