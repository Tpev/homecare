<?php

namespace App\Services\Didit;

use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DiditSessionService
{
    public function __construct(
        private readonly DiditClient $client,
    ) {
    }

    public function createForCaregiver(User $user): CaregiverIdentityVerification
    {
        $workflowId = (string) config('services.didit.workflow_id');
        if ($workflowId === '') {
            throw new RuntimeException('DIDIT_WORKFLOW_ID is not configured.');
        }

        $profile = CaregiverProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'draft']
        );

        $vendorData = 'caregiver_user_'.$user->id.'_profile_'.$profile->id;
        $callback = route('caregiver.verification.return');

        $response = $this->client->createSession([
            'workflow_id' => $workflowId,
            'callback' => $callback,
            'vendor_data' => $vendorData,
        ]);

        $sessionId = (string) ($response['session_id'] ?? '');
        $verificationUrl = (string) ($response['verification_url'] ?? '');
        $status = CaregiverIdentityVerification::normalizeDiditStatus((string) ($response['status'] ?? 'Not Started'));

        if ($sessionId === '' || $verificationUrl === '') {
            throw new RuntimeException('Didit did not return a valid session response.');
        }

        return DB::transaction(function () use ($profile, $user, $sessionId, $verificationUrl, $vendorData, $status, $response) {
            $verification = CaregiverIdentityVerification::query()->create([
                'caregiver_profile_id' => $profile->id,
                'user_id' => $user->id,
                'didit_session_id' => $sessionId,
                'status' => $status,
                'verification_url' => $verificationUrl,
                'vendor_data' => $vendorData,
                'session_payload' => $response,
                'started_at' => now(),
            ]);

            $profile->forceFill([
                'identity_verification_status' => $status,
                'identity_verification_session_id' => $sessionId,
                'identity_verification_checked_at' => now(),
            ])->save();

            return $verification;
        });
    }
}

