<?php

namespace App\Services\Payments;

use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

class FamilyBillingService
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    /**
     * @return array{
     *   ready: bool,
     *   customer_id: string|null,
     *   card: array{id:string,brand:string,last4:string,exp_month:int,exp_year:int}|null
     * }
     */
    public function summaryFor(User $family): array
    {
        $account = app(FamilyAccountContext::class)->account($family);
        $customerId = $account->stripe_customer_id
            ? (string) $account->stripe_customer_id
            : null;

        if (! $customerId && $account->owner?->stripe_customer_id) {
            $customerId = (string) $account->owner->stripe_customer_id;
            $account->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        $card = null;
        if ($customerId) {
            $card = $this->stripe->defaultPaymentMethodForCustomer($customerId);
        }

        return [
            'ready' => $card !== null,
            'customer_id' => $customerId,
            'card' => $card,
        ];
    }

    public function createSetupCheckoutUrl(User $family, string $successUrl, string $cancelUrl): string
    {
        return $this->stripe->createFamilySetupCheckoutSession($family, $successUrl, $cancelUrl);
    }

    public function syncSetupCheckoutSession(User $family, string $sessionId): void
    {
        $this->stripe->syncFamilySetupCheckoutSession($family, $sessionId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCheckoutSessionCompletedWebhook(array $payload): void
    {
        if ((string) ($payload['mode'] ?? '') !== 'setup') {
            return;
        }

        $sessionId = (string) ($payload['id'] ?? '');
        if ($sessionId === '') {
            return;
        }

        $accountId = (int) ($payload['metadata']['family_account_id'] ?? 0);
        $familyId = (int) ($payload['metadata']['family_user_id'] ?? 0);
        $account = $accountId > 0 ? FamilyAccount::query()->with('owner')->find($accountId) : null;
        $family = $account?->owner ?: ($familyId > 0 ? User::query()->find($familyId) : null);

        if (! $family) {
            $customerId = (string) ($payload['customer'] ?? '');
            if ($customerId !== '') {
                $account = FamilyAccount::query()->with('owner')->where('stripe_customer_id', $customerId)->first();
                $family = $account?->owner ?: User::query()->where('stripe_customer_id', $customerId)->first();
            }
        }

        if (! $family || $family->role !== 'family') {
            return;
        }

        $this->syncSetupCheckoutSession($family, $sessionId);
    }
}
