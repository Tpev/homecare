<?php

namespace App\Services\Caregiver;

use App\Models\CaregiverCertification;
use App\Models\CaregiverModerationLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaregiverCertificationReviewService
{
    public function verify(User $admin, CaregiverCertification $certification): void
    {
        $this->authorize($admin);

        if (! $certification->document_path) {
            throw ValidationException::withMessages([
                'certification_'.$certification->id => 'Supporting evidence is required before this credential can be verified.',
            ]);
        }

        if ($certification->isExpired()) {
            throw ValidationException::withMessages([
                'certification_'.$certification->id => 'An expired credential cannot be marked as currently verified.',
            ]);
        }

        DB::transaction(function () use ($admin, $certification): void {
            $certification->forceFill([
                'verification_status' => CaregiverCertification::STATUS_VERIFIED,
                'verified_by_user_id' => $admin->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ])->save();

            CaregiverModerationLog::query()->create([
                'caregiver_profile_id' => $certification->caregiver_profile_id,
                'actor_user_id' => $admin->id,
                'action' => 'credential_verified',
                'note' => 'Credential evidence verified by an administrator.',
                'meta' => [
                    'certification_id' => $certification->id,
                    'certification_type_id' => $certification->caregiver_certification_type_id,
                    'status' => CaregiverCertification::STATUS_VERIFIED,
                ],
            ]);
        });
    }

    public function reject(User $admin, CaregiverCertification $certification, string $reason): void
    {
        $this->authorize($admin);
        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'certification_'.$certification->id.'_reason' => 'Enter a reason between 5 and 1,000 characters.',
            ]);
        }

        DB::transaction(function () use ($admin, $certification, $reason): void {
            $certification->forceFill([
                'verification_status' => CaregiverCertification::STATUS_REJECTED,
                'verified_by_user_id' => null,
                'verified_at' => null,
                'rejection_reason' => $reason,
            ])->save();

            CaregiverModerationLog::query()->create([
                'caregiver_profile_id' => $certification->caregiver_profile_id,
                'actor_user_id' => $admin->id,
                'action' => 'credential_rejected',
                'note' => $reason,
                'meta' => [
                    'certification_id' => $certification->id,
                    'certification_type_id' => $certification->caregiver_certification_type_id,
                    'status' => CaregiverCertification::STATUS_REJECTED,
                ],
            ]);
        });
    }

    private function authorize(User $admin): void
    {
        if (! $admin->isAdministrator()) {
            throw new AuthorizationException('Only administrators can review caregiver credentials.');
        }
    }
}
