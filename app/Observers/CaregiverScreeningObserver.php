<?php

namespace App\Observers;

use App\Models\CaregiverProfile;
use App\Services\Screening\CaregiverScreeningService;
use Illuminate\Support\Facades\DB;

class CaregiverScreeningObserver
{
    public function updated(CaregiverProfile $profile): void
    {
        if ($profile->wasChanged('status') && $profile->status === 'under_review') {
            DB::afterCommit(function () use ($profile): void {
                if ($fresh = $profile->fresh()) {
                    app(CaregiverScreeningService::class)->sync($fresh);
                }
            });
        }
    }
}
