<?php

namespace App\Jobs;

use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InjectPrelaunchStaffApplication implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $careRequestId,
        public string $caregiverEmail,
        public int $delayMinutes = 0
    ) {}

    public function handle(): void
    {
        if (! config('marketplace.family_prelaunch_auto_applicants.enabled', false)) {
            return;
        }

        $careRequest = CareRequest::query()->find($this->careRequestId);
        if (! $careRequest
            || $careRequest->status !== CareRequest::STATUS_OPEN
            || ! $careRequest->isAcceptingApplications()) {
            return;
        }

        $caregiver = User::query()
            ->where('email', $this->caregiverEmail)
            ->where('role', 'caregiver')
            ->with('caregiverProfile')
            ->first();

        if (! $caregiver || ! $caregiver->caregiverProfile) {
            return;
        }

        $profile = $caregiver->caregiverProfile;
        if ($profile->status !== 'active') {
            return;
        }

        CareRequestApplication::query()->firstOrCreate(
            [
                'care_request_id' => $careRequest->id,
                'caregiver_user_id' => $caregiver->id,
            ],
            [
                'status' => CareRequestApplication::STATUS_APPLIED,
                'proposed_rate' => $this->resolveRate($profile),
                'cover_note' => (string) config('marketplace.family_prelaunch_auto_applicants.cover_note'),
            ]
        );
    }

    private function resolveRate(CaregiverProfile $profile): float
    {
        $profileRate = (float) ($profile->platform_hourly_rate ?? 0);
        if ($profileRate > 0) {
            return round($profileRate, 2);
        }

        $tier = (string) config('marketplace.default_pricing_tier', 'standard');
        $tierRate = (float) data_get(config('marketplace.pricing_tiers', []), $tier.'.rate', 27);

        return round($tierRate > 0 ? $tierRate : 27, 2);
    }
}
