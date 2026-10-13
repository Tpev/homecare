<?php

namespace App\Services\Screening;

use App\Models\CaregiverIdentityVerification;
use App\Models\CaregiverProfile;
use App\Services\Didit\DiditClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DiditVerifiedIdentityService
{
    public function snapshot(CaregiverProfile $profile): ?array
    {
        $verification = $this->currentVerification($profile);
        if (! $verification || $verification->status !== 'approved' || ! $this->isRealSession($verification)) {
            return null;
        }
        $payload = $verification->provider_decision_payload;
        if (! $verification->provider_confirmed_at || ! is_array($payload)) {
            // Legacy webhooks were persisted only after signature validation. Admin overrides
            // replace the webhook payload and therefore cannot satisfy this evidence check.
            $webhook = (array) $verification->webhook_payload;
            if (! $verification->last_webhook_at
                || ($webhook['session_id'] ?? null) !== $verification->didit_session_id
                || strtolower((string) ($webhook['status'] ?? '')) !== 'approved'
                || isset($webhook['source'])) {
                return null;
            }
            $payload = $webhook['decision'] ?? $verification->decision_payload;
        }
        if (! is_array($payload) || isset($payload['source'])
            || ($payload['provider'] ?? '') === 'didit_bypass'
            || strtolower((string) ($payload['status'] ?? 'Approved')) !== 'approved') {
            return null;
        }
        $decision = $payload['decision'] ?? $payload;
        $documents = $decision['id_verifications'] ?? $decision['id_verification'] ?? [];
        if (isset($documents['status'])) {
            $documents = [$documents];
        }
        if (! is_array($documents)) {
            return null;
        }
        foreach ($documents as $document) {
            if (! is_array($document) || strtolower((string) ($document['status'] ?? '')) !== 'approved') {
                continue;
            }
            $first = trim((string) ($document['first_name'] ?? ''));
            $last = trim((string) ($document['last_name'] ?? ''));
            $full = trim((string) ($document['full_name'] ?? ($first.' '.$last)));
            if ($full === '' || in_array(strtolower($full), ['unknown', 'n/a'], true)) {
                continue;
            }

            return ['verification_id' => $verification->id, 'didit_session_id' => $verification->didit_session_id,
                'full_name' => $full, 'first_name' => $first, 'last_name' => $last,
                'date_of_birth' => $document['date_of_birth'] ?? null];
        }

        return null;
    }

    public function currentVerification(CaregiverProfile $profile): ?CaregiverIdentityVerification
    {
        return $profile->identityVerifications()
            ->when($profile->identity_verification_session_id, fn ($query, $id) => $query->where('didit_session_id', $id))
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->latest('id')->first();
    }

    public function isRealSession(CaregiverIdentityVerification $verification): bool
    {
        return filled($verification->didit_session_id)
            && ! str_starts_with($verification->didit_session_id, 'bypass_')
            && ! str_starts_with($verification->didit_session_id, 'admin-override-')
            && $verification->vendor_data !== 'didit_bypass'
            && ! data_get($verification->session_payload, 'bypass', false);
    }

    public function reconcile(CaregiverProfile $profile): void
    {
        $verification = $this->currentVerification($profile);
        if (! $verification || ! $this->isRealSession($verification)) {
            return;
        }
        $response = app(DiditClient::class)->fetchDecision($verification->didit_session_id);
        if (isset($response['session_id']) && $response['session_id'] !== $verification->didit_session_id) {
            throw new RuntimeException('Didit returned a different verification session.');
        }
        $status = CaregiverIdentityVerification::normalizeDiditStatus($response['status'] ?? data_get($response, 'decision.status'));
        if ($status === 'error') {
            throw new RuntimeException('Didit returned an unrecognized decision.');
        }
        DB::transaction(function () use ($profile, $verification, $response, $status): void {
            $freshProfile = CaregiverProfile::lockForUpdate()->findOrFail($profile->id);
            $freshVerification = CaregiverIdentityVerification::lockForUpdate()->findOrFail($verification->id);
            // Do not overwrite a webhook received while the reconciliation request was in flight.
            if ($freshVerification->updated_at?->ne($verification->updated_at)
                || $freshVerification->status !== $verification->status
                || $freshVerification->webhook_payload !== $verification->webhook_payload
                || $freshVerification->provider_decision_payload !== $verification->provider_decision_payload) {
                return;
            }
            $freshVerification->update(['status' => $status, 'provider_decision_payload' => $response,
                'provider_confirmed_at' => now(), 'approved_at' => $status === 'approved' ? ($freshVerification->approved_at ?: now()) : $freshVerification->approved_at]);
            if ((! $freshProfile->identity_verification_session_id
                || $freshProfile->identity_verification_session_id === $verification->didit_session_id)
                && ! $freshProfile->identityVerifications()->where('id', '>', $verification->id)->exists()) {
                $freshProfile->forceFill(['identity_verification_status' => $status,
                    'identity_verification_session_id' => $verification->didit_session_id,
                    'identity_verification_checked_at' => now(),
                    'identity_verified_at' => $status === 'approved' ? ($freshProfile->identity_verified_at ?: now()) : $freshProfile->identity_verified_at])->save();
            }
            // Reconciliation establishes screening evidence; it never changes work access.
            app(ScreeningAuditService::class)->record(null, null, 'didit_reconciled', [
                'caregiver_profile_id' => $freshProfile->id, 'verification_id' => $verification->id, 'status' => $status,
            ]);
        });
    }
}
