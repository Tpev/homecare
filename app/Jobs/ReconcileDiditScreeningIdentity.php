<?php

namespace App\Jobs;

use App\Models\CaregiverProfile;
use App\Services\Screening\CaregiverScreeningService;
use App\Services\Screening\DiditVerifiedIdentityService;
use App\Services\Screening\ScreeningAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReconcileDiditScreeningIdentity implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $profileId) {}

    public function handle(DiditVerifiedIdentityService $identity, CaregiverScreeningService $screening): void
    {
        $profile = CaregiverProfile::find($this->profileId);
        if (! $profile) {
            return;
        }
        try {
            $identity->reconcile($profile);
            $screening->sync($profile->fresh());
        } catch (Throwable $error) {
            // The case remains Awaiting KYC. The reconciliation command can retry it.
            app(ScreeningAuditService::class)->record(null, null, 'identity_reconciliation_failed', [
                'caregiver_profile_id' => $profile->id, 'error_type' => $error::class,
            ]);
        }
    }
}
