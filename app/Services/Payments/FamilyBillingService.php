<?php

namespace App\Services\Payments;

use App\Models\User;

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
        $customerId = $family->stripe_customer_id
            ? (string) $family->stripe_customer_id
            : null;

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

        $familyId = (int) ($payload['metadata']['family_user_id'] ?? 0);
        $family = $familyId > 0
            ? User::query()->find($familyId)
            : null;

        if (! $family) {
            $customerId = (string) ($payload['customer'] ?? '');
            if ($customerId !== '') {
                $family = User::query()
                    ->where('stripe_customer_id', $customerId)
                    ->first();
            }
        }

        if (! $family || $family->role !== 'family') {
            return;
        }

        $this->syncSetupCheckoutSession($family, $sessionId);
    }
}
