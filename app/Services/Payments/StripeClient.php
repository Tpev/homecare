<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentActionRequiredException;
use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use RuntimeException;
use Stripe\ErrorObject;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient as StripeSdk;
use Stripe\Webhook;
use Throwable;

class StripeClient
{
    private ?StripeSdk $client = null;

    public function isBypass(): bool
    {
        return (bool) config('services.stripe.bypass', false);
    }

    public function currency(): string
    {
        return strtolower((string) config('services.stripe.currency', 'usd'));
    }

    public function ensureFamilyCustomer(User $family): string
    {
        $account = $this->familyAccount($family);
        $owner = $account->owner;

        if (! $account->stripe_customer_id && $owner?->stripe_customer_id) {
            $account->forceFill(['stripe_customer_id' => $owner->stripe_customer_id])->save();
        }

        if ($this->isBypass()) {
            if (! $account->stripe_customer_id) {
                $account->forceFill([
                    'stripe_customer_id' => 'cus_bypass_account_'.$account->id,
                ])->save();
            }

            if ($owner && ! $owner->stripe_customer_id) {
                $owner->forceFill(['stripe_customer_id' => $account->stripe_customer_id])->save();
            }

            return (string) $account->stripe_customer_id;
        }

        if ($account->stripe_customer_id) {
            return (string) $account->stripe_customer_id;
        }

        try {
            $customer = $this->client()->customers->create([
                'email' => $owner?->email ?? $family->email,
                'name' => $owner?->name ?? $family->name,
                'metadata' => [
                    'family_account_id' => (string) $account->id,
                    'owner_user_id' => (string) $account->owner_user_id,
                    'created_by_user_id' => (string) $family->id,
                ],
            ], $this->requestOptions('family-customer:account-'.$account->id));
        } catch (Throwable $e) {
            throw new PaymentException(
                'Unable to initialize billing profile right now. Please try again.',
                $e->getMessage()
            );
        }

        $account->forceFill([
            'stripe_customer_id' => (string) $customer->id,
        ])->save();

        if ($owner && ! $owner->stripe_customer_id) {
            $owner->forceFill(['stripe_customer_id' => (string) $customer->id])->save();
        }

        return (string) $customer->id;
    }

    /**
     * @return array{id:string, brand:string, last4:string, exp_month:int, exp_year:int}|null
     */
    public function defaultPaymentMethodForCustomer(string $customerId): ?array
    {
        if ($customerId === '') {
            return null;
        }

        if ($this->isBypass()) {
            return [
                'id' => 'pm_bypass_'.$customerId,
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => (int) now()->addYears(3)->format('Y'),
            ];
        }

        try {
            $customer = $this->client()->customers->retrieve(
                $customerId,
                ['expand' => ['invoice_settings.default_payment_method']]
            );
        } catch (Throwable $e) {
            throw new PaymentException(
                'Unable to load billing method right now.',
                $e->getMessage()
            );
        }

        $defaultPm = $customer->invoice_settings?->default_payment_method ?? null;
        if (! $defaultPm) {
            return null;
        }

        if (is_string($defaultPm)) {
            $defaultPm = $this->client()->paymentMethods->retrieve($defaultPm);
        }

        if (! $defaultPm || ($defaultPm->type ?? null) !== 'card' || ! $defaultPm->card) {
            return null;
        }

        return [
            'id' => (string) $defaultPm->id,
            'brand' => (string) $defaultPm->card->brand,
            'last4' => (string) $defaultPm->card->last4,
            'exp_month' => (int) $defaultPm->card->exp_month,
            'exp_year' => (int) $defaultPm->card->exp_year,
        ];
    }

    public function createFamilySetupCheckoutSession(User $family, string $successUrl, string $cancelUrl): string
    {
        $account = $this->familyAccount($family);
        $customerId = $this->ensureFamilyCustomer($family);

        if ($this->isBypass()) {
            return str_replace('{CHECKOUT_SESSION_ID}', 'bypass-session-'.$account->id, $successUrl);
        }

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'setup',
                'customer' => $customerId,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'family_account_id' => (string) $account->id,
                    'family_user_id' => (string) $account->owner_user_id,
                    'acting_user_id' => (string) $family->id,
                ],
                'setup_intent_data' => [
                    'metadata' => [
                        'family_account_id' => (string) $account->id,
                        'family_user_id' => (string) $account->owner_user_id,
                        'acting_user_id' => (string) $family->id,
                    ],
                ],
            ], $this->requestOptions('billing-setup:account-'.$account->id.':actor-'.$family->id.':'.(string) \Illuminate\Support\Str::uuid()));
        } catch (Throwable $e) {
            throw new PaymentException(
                'Unable to open card setup right now. Please try again.',
                $e->getMessage()
            );
        }

        $url = (string) ($session->url ?? '');
        if ($url === '') {
            throw new PaymentException('Unable to start billing setup right now.');
        }

        return $url;
    }

    public function syncFamilySetupCheckoutSession(User $family, string $sessionId): void
    {
        $account = $this->familyAccount($family);

        if ($sessionId === '') {
            return;
        }

        if ($this->isBypass()) {
            $this->ensureFamilyCustomer($family);

            return;
        }

        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId, [
                'expand' => ['setup_intent'],
            ]);
        } catch (Throwable $e) {
            throw new PaymentException('Unable to verify billing setup session.', $e->getMessage());
        }

        if ((string) ($session->mode ?? '') !== 'setup') {
            throw new PaymentException('Invalid billing setup session.');
        }

        if ((string) ($session->status ?? '') !== 'complete') {
            throw new PaymentException('Card setup is not completed yet.');
        }

        $metadataAccountId = (int) ($session->metadata['family_account_id'] ?? 0);
        $metadataUserId = (int) ($session->metadata['family_user_id'] ?? 0);
        if (($metadataAccountId > 0 && $metadataAccountId !== (int) $account->id)
            || ($metadataAccountId < 1 && $metadataUserId > 0 && $metadataUserId !== (int) $account->owner_user_id)) {
            throw new PaymentException('This billing session does not belong to your account.');
        }

        $customerId = (string) ($session->customer ?? '');
        if ($customerId === '') {
            throw new PaymentException('Billing session did not return a customer.');
        }
        $accountCustomerId = trim((string) $account->stripe_customer_id);
        if ($accountCustomerId !== '' && ! hash_equals($accountCustomerId, $customerId)) {
            throw new PaymentException('This billing session does not belong to your account.');
        }

        if (! $account->stripe_customer_id) {
            $account->forceFill(['stripe_customer_id' => $customerId])->save();
        }
        if (! $account->owner?->stripe_customer_id) {
            $account->owner?->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        $setupIntent = $session->setup_intent;
        if (is_string($setupIntent) && $setupIntent !== '') {
            $setupIntent = $this->client()->setupIntents->retrieve($setupIntent);
        }
        if ((string) ($setupIntent->status ?? '') !== 'succeeded') {
            throw new PaymentException('Card setup is not completed yet.');
        }

        $paymentMethodId = (string) ($setupIntent->payment_method ?? '');
        if ($paymentMethodId === '') {
            throw new PaymentException('No card method was attached during setup.');
        }

        try {
            $this->client()->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ], $this->requestOptions('billing-default-payment:account-'.$account->id.'-session-'.$sessionId));
        } catch (Throwable $e) {
            throw new PaymentException('Unable to finalize card setup.', $e->getMessage());
        }
    }

    public function updateFamilyCustomerOwner(FamilyAccount $account, User $owner): void
    {
        $customerId = trim((string) $account->stripe_customer_id);
        if ($customerId === '' || $this->isBypass()) {
            return;
        }

        try {
            $this->client()->customers->update($customerId, [
                'email' => $owner->email,
                'name' => $owner->name,
                'metadata' => [
                    'family_account_id' => (string) $account->id,
                    'owner_user_id' => (string) $owner->id,
                ],
            ]);
        } catch (Throwable $exception) {
            throw new PaymentException(
                'Unable to transfer billing ownership right now. No account changes were made.',
                $exception->getMessage(),
            );
        }
    }

    private function familyAccount(User $family): FamilyAccount
    {
        if ($family->role !== 'family') {
            throw new PaymentException('A family account is required for billing.');
        }

        return app(FamilyAccountContext::class)->account($family);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        if ($sessionId === '') {
            throw new PaymentException('Missing Stripe checkout session id.');
        }

        if ($this->isBypass()) {
            return [
                'id' => $sessionId,
                'mode' => 'setup',
                'status' => 'complete',
                'customer' => null,
                'metadata' => [],
            ];
        }

        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);
        } catch (Throwable $e) {
            throw new PaymentException('Unable to retrieve checkout session.', $e->getMessage());
        }

        return json_decode(json_encode($session), true) ?: [];
    }

    public function ensureCaregiverConnectAccount(CaregiverProfile $profile): string
    {
        if ($this->isBypass()) {
            if (! $profile->stripe_connect_account_id) {
                $profile->forceFill([
                    'stripe_connect_account_id' => 'acct_bypass_'.$profile->id,
                ])->save();
            }

            return (string) $profile->stripe_connect_account_id;
        }

        if ($profile->stripe_connect_account_id) {
            return (string) $profile->stripe_connect_account_id;
        }

        try {
            $account = $this->client()->accounts->create([
                'type' => 'express',
                'country' => 'US',
                'email' => (string) $profile->user->email,
                'business_type' => 'individual',
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'metadata' => [
                    'caregiver_profile_id' => (string) $profile->id,
                    'user_id' => (string) $profile->user_id,
                ],
            ]);
        } catch (Throwable $e) {
            throw new PaymentException(
                'Unable to initialize caregiver payouts right now.',
                $e->getMessage()
            );
        }

        $profile->forceFill([
            'stripe_connect_account_id' => (string) $account->id,
            'stripe_connect_last_synced_at' => now(),
        ])->save();

        return (string) $account->id;
    }

    public function createConnectOnboardingLink(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        if ($this->isBypass()) {
            return $returnUrl;
        }

        try {
            $link = $this->client()->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ], $this->requestOptions(
                'connect-onboarding:'.$accountId.':'.(string) \Illuminate\Support\Str::uuid()
            ));
        } catch (Throwable $e) {
            throw new PaymentException(
                'Unable to open payout onboarding right now.',
                $e->getMessage()
            );
        }

        $url = (string) ($link->url ?? '');
        if ($url === '') {
            throw new PaymentException('Unable to open payout onboarding right now.');
        }

        return $url;
    }

    /**
     * @return array{charges_enabled:bool,payouts_enabled:bool,details_submitted:bool}
     */
    public function syncCaregiverConnectAccount(CaregiverProfile $profile): array
    {
        $accountId = $this->ensureCaregiverConnectAccount($profile);

        if ($this->isBypass()) {
            $profile->forceFill([
                'stripe_connect_account_id' => $accountId,
                'stripe_charges_enabled' => true,
                'stripe_payouts_enabled' => true,
                'stripe_connect_onboarding_completed_at' => $profile->stripe_connect_onboarding_completed_at ?: now(),
                'stripe_connect_last_synced_at' => now(),
            ])->save();

            return [
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted' => true,
            ];
        }

        try {
            $account = $this->client()->accounts->retrieve($accountId);
        } catch (Throwable $e) {
            throw new PaymentException('Unable to refresh payout status right now.', $e->getMessage());
        }

        $chargesEnabled = (bool) ($account->charges_enabled ?? false);
        $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);
        $detailsSubmitted = (bool) ($account->details_submitted ?? false);

        $profile->forceFill([
            'stripe_connect_account_id' => $accountId,
            'stripe_charges_enabled' => $chargesEnabled,
            'stripe_payouts_enabled' => $payoutsEnabled,
            'stripe_connect_onboarding_completed_at' => ($chargesEnabled && $payoutsEnabled)
                ? ($profile->stripe_connect_onboarding_completed_at ?: now())
                : null,
            'stripe_connect_last_synced_at' => now(),
        ])->save();

        return [
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'details_submitted' => $detailsSubmitted,
        ];
    }

    /**
     * @return array{
     *   payment_intent_id:string,
     *   status:string,
     *   amount:int,
     *   authorization_expires_at:\Carbon\CarbonInterface|null,
     *   failure_message?:string,
     *   client_secret?:string
     * }
     */
    public function createManualAuthorization(
        CareBooking $booking,
        string $customerId,
        string $paymentMethodId,
        int $amountCents,
        string $currency,
        ?string $idempotencyKey = null,
    ): array {
        if ($this->isBypass()) {
            return [
                'payment_intent_id' => 'pi_bypass_booking_'.$booking->id,
                'status' => PaymentIntent::STATUS_REQUIRES_CAPTURE,
                'amount' => $amountCents,
                'authorization_expires_at' => now()->addDays(6),
            ];
        }

        if ($amountCents <= 0) {
            throw new PaymentException('Unable to authorize payment for this booking amount.');
        }

        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'description' => 'LoLo Care booking #'.$booking->id,
                'metadata' => [
                    'care_booking_id' => (string) $booking->id,
                    'care_request_id' => (string) $booking->care_request_id,
                    'family_account_id' => (string) $booking->family_account_id,
                    'family_user_id' => (string) $booking->family_user_id,
                    'acting_user_id' => (string) (auth()->id() ?: $booking->family_user_id),
                    'caregiver_user_id' => (string) $booking->caregiver_user_id,
                ],
            ], $this->requestOptions($idempotencyKey));
        } catch (ApiErrorException $e) {
            $errorIntent = $this->extractPaymentIntentFromError($e->getError());
            if ($errorIntent) {
                $intentStatus = (string) ($errorIntent['status'] ?? '');

                return [
                    'payment_intent_id' => (string) ($errorIntent['id'] ?? ''),
                    'status' => $intentStatus,
                    'amount' => (int) ($errorIntent['amount'] ?? $amountCents),
                    'authorization_expires_at' => $this->captureBeforeFromPaymentIntent($errorIntent),
                    'failure_message' => (string) data_get($errorIntent, 'last_payment_error.message', $e->getMessage()),
                    'client_secret' => (string) ($errorIntent['client_secret'] ?? ''),
                ];
            }

            if ($this->isAuthenticationRequiredError($e)) {
                throw new PaymentException(
                    'Card authentication is required. Confirm or replace your card for this visit, then retry authorization.',
                    $e->getMessage()
                );
            }

            throw new PaymentException(
                'Card authorization failed. Confirm or replace your card for this visit, then retry authorization.',
                $e->getMessage()
            );
        }

        $status = (string) $intent->status;
        if (! in_array($status, [PaymentIntent::STATUS_REQUIRES_CAPTURE, PaymentIntent::STATUS_REQUIRES_ACTION], true)) {
            throw new PaymentException(
                'Card authorization needs action. Confirm or replace your card for this visit, then retry authorization.',
                'Unexpected PaymentIntent status: '.$status
            );
        }

        return [
            'payment_intent_id' => (string) $intent->id,
            'status' => $status,
            'amount' => (int) $intent->amount,
            'authorization_expires_at' => $this->captureBeforeFromPaymentIntent(
                json_decode(json_encode($intent), true) ?: []
            ),
            'client_secret' => (string) ($intent->client_secret ?? ''),
        ];
    }

    /**
     * @return array{
     *   payment_intent_id:string,
     *   client_secret:string,
     *   status:string,
     *   amount:int,
     *   authorization_expires_at:\Carbon\CarbonInterface|null
     * }
     */
    public function createManualAuthorizationIntent(
        CareBooking $booking,
        string $customerId,
        string $paymentMethodId,
        int $amountCents,
        string $currency,
        ?string $idempotencyKey = null,
    ): array {
        if ($this->isBypass()) {
            return [
                'payment_intent_id' => 'pi_bypass_booking_'.$booking->id,
                'client_secret' => 'pi_bypass_booking_'.$booking->id.'_secret_bypass',
                'status' => PaymentIntent::STATUS_REQUIRES_CAPTURE,
                'amount' => $amountCents,
                'authorization_expires_at' => now()->addDays(6),
            ];
        }

        if ($amountCents <= 0) {
            throw new PaymentException('Unable to authorize payment for this booking amount.');
        }

        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'customer' => $customerId,
                'capture_method' => 'manual',
                'confirmation_method' => 'automatic',
                'payment_method_options' => [
                    'card' => [
                        'request_three_d_secure' => 'automatic',
                    ],
                ],
                'description' => 'LoLo Care booking #'.$booking->id,
                'metadata' => [
                    'care_booking_id' => (string) $booking->id,
                    'care_request_id' => (string) $booking->care_request_id,
                    'family_account_id' => (string) $booking->family_account_id,
                    'family_user_id' => (string) $booking->family_user_id,
                    'acting_user_id' => (string) (auth()->id() ?: $booking->family_user_id),
                    'caregiver_user_id' => (string) $booking->caregiver_user_id,
                ],
            ], $this->requestOptions($idempotencyKey));
        } catch (ApiErrorException $e) {
            throw new PaymentException(
                'Unable to start card authorization. Update your card and try hiring again.',
                $e->getMessage()
            );
        }

        return $this->paymentIntentPayload($intent);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        if ($this->isBypass()) {
            return [
                'id' => $paymentIntentId,
                'client_secret' => $paymentIntentId.'_secret_bypass',
                'status' => PaymentIntent::STATUS_REQUIRES_CAPTURE,
                'amount' => 100,
            ];
        }

        try {
            $intent = $this->client()->paymentIntents->retrieve($paymentIntentId, [
                'expand' => ['latest_charge.balance_transaction'],
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException('Unable to verify card authorization right now.', $e->getMessage());
        }

        return $this->paymentIntentPayload($intent);
    }

    /**
     * @return array{id:string,status:string,amount_received:int,latest_charge_id?:string,balance_transaction_id?:string,processing_fee_cents?:int,fee_finalized?:bool}
     */
    public function capturePaymentIntent(string $paymentIntentId, int $amountToCaptureCents, ?string $idempotencyKey = null): array
    {
        if ($this->isBypass()) {
            return array_merge([
                'id' => $paymentIntentId,
                'status' => PaymentIntent::STATUS_SUCCEEDED,
                'amount_received' => max(0, $amountToCaptureCents),
            ], $this->bypassFinancialDetails($paymentIntentId, $amountToCaptureCents));
        }

        try {
            $intent = $this->client()->paymentIntents->capture($paymentIntentId, [
                'amount_to_capture' => max(1, $amountToCaptureCents),
            ], $this->requestOptions($idempotencyKey));
        } catch (Throwable $e) {
            throw new PaymentException(
                'Unable to capture payment right now. Please retry in a moment.',
                $e->getMessage()
            );
        }

        if ((string) $intent->status !== PaymentIntent::STATUS_SUCCEEDED) {
            throw new PaymentException(
                'Payment capture did not complete successfully.',
                'Unexpected capture status: '.(string) $intent->status
            );
        }

        try {
            $intent = $this->client()->paymentIntents->retrieve($paymentIntentId, [
                'expand' => ['latest_charge.balance_transaction'],
            ]);
        } catch (Throwable) {
            // Capture already succeeded. The reconciliation worker will finalize fees before transfer.
        }

        return array_merge([
            'id' => (string) $intent->id,
            'status' => (string) $intent->status,
            'amount_received' => (int) $intent->amount_received,
        ], $this->financialDetailsFromPaymentIntent($intent));
    }

    public function cancelPaymentIntent(string $paymentIntentId): void
    {
        if ($paymentIntentId === '') {
            return;
        }

        if ($this->isBypass()) {
            return;
        }

        try {
            $this->client()->paymentIntents->cancel($paymentIntentId, [
                'cancellation_reason' => 'requested_by_customer',
            ], $this->requestOptions('cancel-payment-intent:'.$paymentIntentId));
        } catch (Throwable $e) {
            throw new PaymentException('Unable to cancel the payment authorization.', $e->getMessage());
        }
    }

    /**
     * @param  array<string,string>  $metadata
     * @return array{id:string,status:string}
     */
    public function createTransfer(
        string $destinationAccountId,
        int $amountCents,
        string $currency,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        if ($amountCents <= 0) {
            throw new PaymentException('Transfer amount must be greater than zero.');
        }

        if ($this->isBypass()) {
            return [
                'id' => 'tr_bypass_'.uniqid(),
                'status' => 'paid',
            ];
        }

        try {
            $transfer = $this->client()->transfers->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'destination' => $destinationAccountId,
                'metadata' => $metadata,
            ], $this->requestOptions($idempotencyKey));
        } catch (Throwable $e) {
            throw new PaymentException('Unable to transfer caregiver payout right now.', $e->getMessage());
        }

        return [
            'id' => (string) $transfer->id,
            'status' => (string) ($transfer->status ?? 'pending'),
        ];
    }

    /**
     * Create a delivery-gated transfer tied to the exact source charge.
     *
     * @param  array<string,string>  $metadata
     * @return array{id:string,status:string,amount:int,source_transaction:string,transfer_group:string}
     */
    public function createTransferForCharge(
        string $destinationAccountId,
        string $sourceChargeId,
        string $transferGroup,
        int $amountCents,
        string $currency,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        if ($amountCents <= 0 || $sourceChargeId === '' || $transferGroup === '') {
            throw new PaymentException('Transfer source and amount must be valid.');
        }

        if ($this->isBypass()) {
            $suffix = substr(hash('sha256', (string) ($idempotencyKey ?: $sourceChargeId.'|'.$amountCents)), 0, 18);

            return [
                'id' => 'tr_bypass_'.$suffix,
                'status' => 'paid',
                'amount' => $amountCents,
                'source_transaction' => $sourceChargeId,
                'transfer_group' => $transferGroup,
            ];
        }

        try {
            $transfer = $this->client()->transfers->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'destination' => $destinationAccountId,
                'source_transaction' => $sourceChargeId,
                'transfer_group' => $transferGroup,
                'metadata' => $metadata,
            ], $this->requestOptions($idempotencyKey));
        } catch (Throwable $e) {
            throw new PaymentException('Unable to transfer caregiver earnings right now.', $e->getMessage());
        }

        return [
            'id' => (string) $transfer->id,
            'status' => (string) ($transfer->status ?? 'pending'),
            'amount' => (int) ($transfer->amount ?? $amountCents),
            'source_transaction' => (string) ($transfer->source_transaction ?? $sourceChargeId),
            'transfer_group' => (string) ($transfer->transfer_group ?? $transferGroup),
        ];
    }

    /**
     * @param  array<string,string>  $metadata
     * @return array{id:string,status:string,amount_received:int,latest_charge_id?:string,balance_transaction_id?:string,processing_fee_cents?:int,fee_finalized?:bool}
     */
    public function createAndConfirmCharge(
        CareBooking $booking,
        string $customerId,
        string $paymentMethodId,
        int $amountCents,
        string $currency,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        if ($amountCents <= 0) {
            throw new PaymentException('Invalid additional charge amount.');
        }

        if ($this->isBypass()) {
            $intentId = 'pi_bypass_overage_'.$booking->id.'_'.substr(hash('sha256', (string) ($idempotencyKey ?: $amountCents)), 0, 12);

            return array_merge([
                'id' => $intentId,
                'status' => PaymentIntent::STATUS_SUCCEEDED,
                'amount_received' => $amountCents,
            ], $this->bypassFinancialDetails($intentId, $amountCents));
        }

        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'capture_method' => 'automatic',
                'confirm' => true,
                'off_session' => true,
                'description' => 'LoLo Care overage for booking #'.$booking->id,
                'expand' => ['latest_charge.balance_transaction'],
                'metadata' => array_merge($metadata, [
                    'care_booking_id' => (string) $booking->id,
                    'care_request_id' => (string) $booking->care_request_id,
                    'type' => 'booking_overage',
                ]),
            ], $this->requestOptions($idempotencyKey));
        } catch (ApiErrorException $e) {
            if ($this->isAuthenticationRequiredError($e)) {
                $paymentIntent = $this->extractPaymentIntentFromError($e->getError());

                throw new PaymentActionRequiredException(
                    'An additional authorization is required to complete final charges.',
                    $e->getMessage(),
                    paymentIntentId: is_array($paymentIntent) ? (string) ($paymentIntent['id'] ?? '') : null,
                    clientSecret: is_array($paymentIntent) ? (string) ($paymentIntent['client_secret'] ?? '') : null,
                );
            }

            throw new PaymentException(
                'Unable to collect the additional amount for this shift.',
                $e->getMessage()
            );
        } catch (Throwable $e) {
            throw new PaymentException(
                'Unable to collect the additional amount for this shift.',
                $e->getMessage()
            );
        }

        if ((string) $intent->status !== PaymentIntent::STATUS_SUCCEEDED) {
            throw new PaymentActionRequiredException(
                'Additional charge requires action. Ask the family to re-authorize in Billing.',
                'Unexpected overage status: '.(string) $intent->status,
                paymentIntentId: (string) $intent->id,
                clientSecret: (string) ($intent->client_secret ?? ''),
            );
        }

        if (! is_numeric(data_get(json_decode(json_encode($intent), true) ?: [], 'latest_charge.balance_transaction.fee'))) {
            try {
                $intent = $this->client()->paymentIntents->retrieve((string) $intent->id, [
                    'expand' => ['latest_charge.balance_transaction'],
                ]);
            } catch (Throwable) {
                // The charge succeeded. Fee finalization will be retried before funds are released.
            }
        }

        return array_merge([
            'id' => (string) $intent->id,
            'status' => (string) $intent->status,
            'amount_received' => (int) $intent->amount_received,
        ], $this->financialDetailsFromPaymentIntent($intent));
    }

    /**
     * @return array{id:string,status:string,amount:int}
     */
    public function createRefundForCharge(
        string $chargeId,
        int $amountCents,
        string $reason = 'requested_by_customer',
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        if ($chargeId === '' || $amountCents <= 0) {
            throw new PaymentException('Invalid refund payload.');
        }

        if ($this->isBypass()) {
            return [
                'id' => 're_bypass_'.substr(hash('sha256', (string) ($idempotencyKey ?: $chargeId.'|'.$amountCents)), 0, 18),
                'status' => 'succeeded',
                'amount' => $amountCents,
            ];
        }

        try {
            $refund = $this->client()->refunds->create([
                'charge' => $chargeId,
                'amount' => $amountCents,
                'reason' => $reason,
                'metadata' => $metadata,
            ], $this->requestOptions($idempotencyKey));
        } catch (Throwable $e) {
            throw new PaymentException('Unable to issue refund right now.', $e->getMessage());
        }

        return [
            'id' => (string) $refund->id,
            'status' => (string) ($refund->status ?? 'pending'),
            'amount' => (int) ($refund->amount ?? $amountCents),
        ];
    }

    /**
     * @return array{id:string,status:string,amount_received:int,latest_charge_id?:string,balance_transaction_id?:string,processing_fee_cents?:int,fee_finalized?:bool}
     */
    public function retrievePaymentIntentFinancials(string $paymentIntentId, int $fallbackAmountCents = 0): array
    {
        if ($this->isBypass()) {
            return array_merge([
                'id' => $paymentIntentId,
                'status' => PaymentIntent::STATUS_SUCCEEDED,
                'amount_received' => max(0, $fallbackAmountCents),
            ], $this->bypassFinancialDetails($paymentIntentId, $fallbackAmountCents));
        }

        try {
            $intent = $this->client()->paymentIntents->retrieve($paymentIntentId, [
                'expand' => ['latest_charge.balance_transaction'],
            ]);
        } catch (Throwable $e) {
            throw new PaymentException('Unable to finalize payment processing fees right now.', $e->getMessage());
        }

        return array_merge([
            'id' => (string) $intent->id,
            'status' => (string) $intent->status,
            'amount_received' => (int) ($intent->amount_received ?? 0),
        ], $this->financialDetailsFromPaymentIntent($intent));
    }

    /**
     * @param  array<string,string>  $metadata
     * @return array{id:string,status:string,amount:int}
     */
    public function createRefund(
        string $paymentIntentId,
        int $amountCents,
        string $reason = 'requested_by_customer',
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        if ($paymentIntentId === '' || $amountCents <= 0) {
            throw new PaymentException('Invalid refund payload.');
        }

        if ($this->isBypass()) {
            return [
                'id' => 're_bypass_'.uniqid(),
                'status' => 'succeeded',
                'amount' => $amountCents,
            ];
        }

        try {
            $refund = $this->client()->refunds->create([
                'payment_intent' => $paymentIntentId,
                'amount' => $amountCents,
                'reason' => $reason,
                'metadata' => $metadata,
            ], $this->requestOptions($idempotencyKey));
        } catch (Throwable $e) {
            throw new PaymentException('Unable to issue refund right now.', $e->getMessage());
        }

        $status = (string) ($refund->status ?? '');
        if (! in_array($status, ['succeeded', 'pending'], true)) {
            throw new PaymentException('Refund did not complete successfully.', 'Unexpected refund status: '.$status);
        }

        return [
            'id' => (string) $refund->id,
            'status' => $status,
            'amount' => (int) ($refund->amount ?? $amountCents),
        ];
    }

    /**
     * @param  array<string,string>  $metadata
     * @return array{id:string,status:string,amount:int}
     */
    public function createTransferReversal(
        string $transferId,
        int $amountCents,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        if ($transferId === '' || $amountCents <= 0) {
            throw new PaymentException('Invalid transfer reversal payload.');
        }

        if ($this->isBypass()) {
            return [
                'id' => 'trr_bypass_'.substr(hash('sha256', (string) ($idempotencyKey ?: $transferId.'|'.$amountCents)), 0, 18),
                'status' => 'succeeded',
                'amount' => $amountCents,
            ];
        }

        try {
            $reversal = $this->client()->transfers->createReversal($transferId, [
                'amount' => $amountCents,
                'metadata' => $metadata,
            ], $this->requestOptions($idempotencyKey));
        } catch (Throwable $e) {
            throw new PaymentException('Unable to reverse caregiver transfer right now.', $e->getMessage());
        }

        return [
            'id' => (string) $reversal->id,
            // Stripe creates TransferReversal objects synchronously and does not
            // expose a lifecycle status field. A successful API response is final.
            'status' => 'succeeded',
            'amount' => (int) ($reversal->amount ?? $amountCents),
        ];
    }

    public function availableBalanceCents(?string $currency = null): int
    {
        if ($this->isBypass()) {
            return PHP_INT_MAX;
        }

        $currency = strtolower((string) ($currency ?: $this->currency()));

        try {
            $balance = $this->client()->balance->retrieve();
        } catch (Throwable $e) {
            throw new PaymentException('Unable to retrieve Stripe available balance.', $e->getMessage());
        }

        $total = 0;
        foreach (($balance->available ?? []) as $bucket) {
            if (strtolower((string) ($bucket->currency ?? '')) === $currency) {
                $total += (int) ($bucket->amount ?? 0);
            }
        }

        return $total;
    }

    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        if ($this->isBypass()) {
            $decoded = json_decode($payload, true);
            if (! is_array($decoded)) {
                throw new RuntimeException('Invalid Stripe webhook JSON.');
            }

            return Event::constructFrom($decoded);
        }

        $secrets = $this->webhookSecrets();
        if ($secrets === []) {
            throw new RuntimeException('STRIPE_WEBHOOK_SECRET is not configured.');
        }

        $lastException = null;
        foreach ($secrets as $secret) {
            try {
                return Webhook::constructEvent($payload, $signature, $secret);
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?: new RuntimeException('Invalid Stripe webhook signature.');
    }

    private function client(): StripeSdk
    {
        if ($this->client) {
            return $this->client;
        }

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new RuntimeException('STRIPE_SECRET is not configured.');
        }

        $this->client = new StripeSdk($secret);

        return $this->client;
    }

    /**
     * @return list<string>
     */
    private function webhookSecrets(): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) config('services.stripe.webhook_secret', ''))),
            fn (string $secret): bool => $secret !== ''
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestOptions(?string $idempotencyKey): array
    {
        if (! $idempotencyKey) {
            return [];
        }

        return ['idempotency_key' => $idempotencyKey];
    }

    private function isAuthenticationRequiredError(ApiErrorException $e): bool
    {
        $stripeCode = (string) $e->getStripeCode();

        return in_array($stripeCode, [
            'authentication_required',
            'payment_intent_authentication_failure',
            'invoice_payment_intent_requires_action',
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPaymentIntentFromError(?ErrorObject $error): ?array
    {
        if (! $error || ! isset($error->payment_intent)) {
            return null;
        }

        $paymentIntent = $error->payment_intent;
        if (is_string($paymentIntent) || ! $paymentIntent) {
            return null;
        }

        $decoded = json_decode(json_encode($paymentIntent), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentIntentPayload(PaymentIntent $intent): array
    {
        $payload = json_decode(json_encode($intent), true) ?: [];

        return array_merge([
            'payment_intent_id' => (string) $intent->id,
            'id' => (string) $intent->id,
            'client_secret' => (string) ($intent->client_secret ?? ''),
            'status' => (string) $intent->status,
            'amount' => (int) $intent->amount,
            'amount_received' => (int) ($intent->amount_received ?? 0),
            'authorization_expires_at' => $this->captureBeforeFromPaymentIntent($payload),
            'last_payment_error' => $payload['last_payment_error'] ?? null,
        ], $this->financialDetailsFromPaymentIntent($intent));
    }

    /**
     * @return array{latest_charge_id?:string,balance_transaction_id?:string,processing_fee_cents?:int,fee_finalized:bool}
     */
    private function financialDetailsFromPaymentIntent(PaymentIntent $intent): array
    {
        $payload = json_decode(json_encode($intent), true) ?: [];
        $charge = data_get($payload, 'latest_charge');
        $chargeId = is_array($charge) ? (string) ($charge['id'] ?? '') : (is_string($charge) ? $charge : '');
        $balanceTransaction = is_array($charge) ? ($charge['balance_transaction'] ?? null) : null;
        $balanceTransactionId = is_array($balanceTransaction)
            ? (string) ($balanceTransaction['id'] ?? '')
            : (is_string($balanceTransaction) ? $balanceTransaction : '');
        $fee = is_array($balanceTransaction) && is_numeric($balanceTransaction['fee'] ?? null)
            ? max(0, (int) $balanceTransaction['fee'])
            : null;

        return array_filter([
            'latest_charge_id' => $chargeId !== '' ? $chargeId : null,
            'balance_transaction_id' => $balanceTransactionId !== '' ? $balanceTransactionId : null,
            'processing_fee_cents' => $fee,
            'fee_finalized' => $fee !== null && $chargeId !== '',
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @return array{latest_charge_id:string,balance_transaction_id:string,processing_fee_cents:int,fee_finalized:bool}
     */
    private function bypassFinancialDetails(string $paymentIntentId, int $amountCents): array
    {
        $safeId = substr(hash('sha256', $paymentIntentId), 0, 24);
        $fee = (int) round(max(0, $amountCents) * max(0, (float) config('services.stripe.bypass_processing_fee_percent', 2.9)) / 100);
        if ($amountCents > 0) {
            $fee += max(0, (int) config('services.stripe.bypass_processing_fee_fixed_cents', 30));
        }

        return [
            'latest_charge_id' => 'ch_bypass_'.$safeId,
            'balance_transaction_id' => 'txn_bypass_'.$safeId,
            'processing_fee_cents' => $fee,
            'fee_finalized' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function captureBeforeFromPaymentIntent(array $paymentIntent): ?\Carbon\CarbonInterface
    {
        $captureBeforeTs = (int) data_get($paymentIntent, 'charges.data.0.payment_method_details.card.capture_before', 0);

        if ($captureBeforeTs <= 0) {
            return null;
        }

        return now()->setTimestamp($captureBeforeTs);
    }
}
