<?php

namespace App\Http\Controllers;

use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverModerationLog;
use App\Support\CaregiverOnboardingState;
use App\Support\FunnelTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiditWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        if (! $this->hasValidSignature($rawBody, (string) $request->header('X-Signature-V2'))) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $sessionId = (string) ($payload['session_id'] ?? '');
        if ($sessionId === '') {
            return response()->json(['message' => 'Missing session_id'], 422);
        }

        $verification = CaregiverIdentityVerification::query()
            ->where('didit_session_id', $sessionId)
            ->with('caregiverProfile')
            ->first();

        if (! $verification) {
            return response()->json(['message' => 'Session not found'], 202);
        }

        $normalizedStatus = CaregiverIdentityVerification::normalizeDiditStatus((string) ($payload['status'] ?? ''));
        $decision = is_array($payload['decision'] ?? null) ? $payload['decision'] : null;

        DB::transaction(function () use ($verification, $normalizedStatus, $payload, $decision, $sessionId): void {
            $verification->forceFill([
                'status' => $normalizedStatus,
                'decision_payload' => $decision,
                'webhook_payload' => $payload,
                'last_webhook_at' => now(),
                'completed_at' => in_array($normalizedStatus, [
                    CaregiverIdentityVerification::STATUS_APPROVED,
                    CaregiverIdentityVerification::STATUS_DECLINED,
                    CaregiverIdentityVerification::STATUS_ABANDONED,
                    CaregiverIdentityVerification::STATUS_EXPIRED,
                ], true) ? now() : $verification->completed_at,
                'approved_at' => $normalizedStatus === CaregiverIdentityVerification::STATUS_APPROVED
                    ? now()
                    : $verification->approved_at,
                'declined_at' => $normalizedStatus === CaregiverIdentityVerification::STATUS_DECLINED
                    ? now()
                    : $verification->declined_at,
            ])->save();

            $profile = $verification->caregiverProfile;
            $profile->forceFill([
                'identity_verification_status' => $normalizedStatus,
                'identity_verification_session_id' => $sessionId,
                'identity_verification_checked_at' => now(),
                'identity_verified_at' => $normalizedStatus === CaregiverIdentityVerification::STATUS_APPROVED
                    ? ($profile->identity_verified_at ?: now())
                    : $profile->identity_verified_at,
            ])->save();

            $logAction = match ($normalizedStatus) {
                CaregiverIdentityVerification::STATUS_APPROVED => 'identity_auto_verified',
                CaregiverIdentityVerification::STATUS_DECLINED => 'identity_auto_declined',
                CaregiverIdentityVerification::STATUS_IN_REVIEW => 'identity_auto_in_review',
                CaregiverIdentityVerification::STATUS_ABANDONED => 'identity_auto_abandoned',
                CaregiverIdentityVerification::STATUS_EXPIRED => 'identity_auto_expired',
                default => null,
            };

            if ($logAction) {
                CaregiverModerationLog::query()->create([
                    'caregiver_profile_id' => $profile->id,
                    'actor_user_id' => null,
                    'action' => $logAction,
                    'note' => 'Didit webhook status: '.$normalizedStatus,
                    'meta' => [
                        'didit_session_id' => $sessionId,
                        'status' => $normalizedStatus,
                    ],
                ]);
            }

            if ($normalizedStatus === CaregiverIdentityVerification::STATUS_APPROVED) {
                FunnelTracker::track(
                    'caregiver_onboarding_step_completed',
                    $profile->user,
                    $profile,
                    ['step' => CaregiverOnboardingState::STEP_IDENTITY]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    private function hasValidSignature(string $rawBody, string $providedSignature): bool
    {
        $secret = (string) config('services.didit.webhook_secret');
        if ($secret === '') {
            return false;
        }

        if ($providedSignature === '') {
            return false;
        }

        $providedSignature = str_starts_with($providedSignature, 'sha256=')
            ? substr($providedSignature, 7)
            : $providedSignature;

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $providedSignature);
    }
}
