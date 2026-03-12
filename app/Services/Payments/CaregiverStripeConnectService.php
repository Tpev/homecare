<?php

namespace App\Services\Payments;

use App\Models\CaregiverProfile;
use App\Models\User;

class CaregiverStripeConnectService
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    public function profileFor(User $caregiver): CaregiverProfile
    {
        return CaregiverProfile::query()->firstOrCreate(
            ['user_id' => $caregiver->id],
            ['status' => 'draft']
        );
    }

    public function createOnboardingUrl(User $caregiver, string $refreshUrl, string $returnUrl): string
    {
        $profile = $this->profileFor($caregiver);
        $accountId = $this->stripe->ensureCaregiverConnectAccount($profile);

        return $this->stripe->createConnectOnboardingLink($accountId, $refreshUrl, $returnUrl);
    }

    /**
     * @return array{charges_enabled:bool,payouts_enabled:bool,details_submitted:bool}
     */
    public function syncStatus(User $caregiver): array
    {
        $profile = $this->profileFor($caregiver);

        return $this->stripe->syncCaregiverConnectAccount($profile);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleAccountUpdatedWebhook(array $payload): void
    {
        $accountId = (string) ($payload['id'] ?? '');
        if ($accountId === '') {
            return;
        }

        $profile = CaregiverProfile::query()
            ->where('stripe_connect_account_id', $accountId)
            ->first();

        if (! $profile) {
            return;
        }

        $chargesEnabled = (bool) ($payload['charges_enabled'] ?? false);
        $payoutsEnabled = (bool) ($payload['payouts_enabled'] ?? false);

        $profile->forceFill([
            'stripe_charges_enabled' => $chargesEnabled,
            'stripe_payouts_enabled' => $payoutsEnabled,
            'stripe_connect_onboarding_completed_at' => ($chargesEnabled && $payoutsEnabled)
                ? ($profile->stripe_connect_onboarding_completed_at ?: now())
                : null,
            'stripe_connect_last_synced_at' => now(),
        ])->save();
    }
}
