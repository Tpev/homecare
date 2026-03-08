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
        $callback = (string) config('services.didit.callback_url');
        if ($callback === '') {
            $callback = route('caregiver.verification.return');
        }

        $response = $this->client->createSession([
            'workflow_id' => $workflowId,
            'callback' => $callback,
            'vendor_data' => $vendorData,
        ]);

        $sessionId = $this->extractString($response, [
            'session_id',
            'sessionId',
            'id',
            'data.session_id',
            'data.sessionId',
            'data.id',
        ]);

        $verificationUrl = $this->extractString($response, [
            'verification_url',
            'verificationUrl',
            'url',
            'session_url',
            'sessionUrl',
            'data.verification_url',
            'data.verificationUrl',
            'data.url',
            'data.session_url',
            'data.sessionUrl',
        ]);

        // Fallback if Didit returns only session_token without direct URL key.
        if ($verificationUrl === '') {
            $sessionToken = $this->extractString($response, [
                'session_token',
                'sessionToken',
                'token',
                'data.session_token',
                'data.sessionToken',
                'data.token',
            ]);

            if ($sessionToken !== '') {
                $verificationUrl = 'https://verify.didit.me/session/'.urlencode($sessionToken);
            }
        }

        $status = CaregiverIdentityVerification::normalizeDiditStatus((string) ($response['status'] ?? 'Not Started'));

        if ($sessionId === '' || $verificationUrl === '') {
            $keys = implode(', ', array_keys($response));

            throw new RuntimeException('Didit did not return a valid session response. Top-level keys: '.$keys);
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

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<int,string>  $paths
     */
    private function extractString(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
