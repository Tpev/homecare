<?php

namespace App\Services\CustomerOnboarding;

use App\Exceptions\Payments\PaymentException;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyPaymentSetupLink;
use App\Models\User;
use App\Services\Payments\StripeClient;
use Illuminate\Support\Facades\DB;

class FamilyPaymentSetupService
{
    public const CONSENT_VERSION = 'payment-setup-2026-10-20';

    public function __construct(private readonly StripeClient $stripe) {}

    public function generate(User $admin, FamilyAccount $account): FamilyPaymentSetupLink
    {
        abort_unless($admin->isAdministrator(), 403);
        $sessions = [];
        $link = DB::transaction(function () use ($admin, $account, &$sessions): FamilyPaymentSetupLink {
            $lockedAccount = FamilyAccount::query()->lockForUpdate()->findOrFail($account->id);
            $this->assertAccountActive($lockedAccount);
            $oldLinks = FamilyPaymentSetupLink::query()->where('family_account_id', $account->id)
                ->whereNull('revoked_at')->whereNull('completed_at')->lockForUpdate()->get();
            foreach ($oldLinks as $oldLink) {
                $oldLink->forceFill(['revoked_at' => now()])->save();
                if ($oldLink->stripe_checkout_session_id) {
                    $sessions[] = $oldLink->stripe_checkout_session_id;
                }
            }
            $token = bin2hex(random_bytes(32));

            $link = FamilyPaymentSetupLink::query()->create([
                'family_account_id' => $account->id,
                'created_by_user_id' => $admin->id,
                'token_hash' => hash('sha256', $token),
                'token' => $token,
                'expires_at' => now()->addDays(7),
            ]);
            $this->log($account->id, $admin->id, 'payment_setup_link_generated');

            return $link;
        });

        // Local revocation is durable even if Stripe is temporarily unavailable.
        foreach ($sessions as $sessionId) {
            $this->stripe->expireGuestFamilySetupCheckoutSession($sessionId);
        }

        return $link;
    }

    public function revoke(User $admin, FamilyPaymentSetupLink $link): void
    {
        abort_unless($admin->isAdministrator(), 403);
        $sessionId = DB::transaction(function () use ($admin, $link): ?string {
            FamilyAccount::query()->whereKey($link->family_account_id)->lockForUpdate()->firstOrFail();
            $locked = FamilyPaymentSetupLink::query()->lockForUpdate()->findOrFail($link->id);
            if ($locked->completed_at) {
                return null;
            }
            if (! $locked->revoked_at) {
                $locked->forceFill(['revoked_at' => now()])->save();
                $this->log($locked->family_account_id, $admin->id, 'payment_setup_link_revoked');
            }

            return $locked->stripe_checkout_session_id;
        });
        if ($sessionId) {
            $this->stripe->expireGuestFamilySetupCheckoutSession($sessionId);
        }
    }

    public function urlFor(FamilyPaymentSetupLink $link): string
    {
        return route('public.family-payment-setup.show', ['token' => $link->token]);
    }

    public function findByToken(string $token): ?FamilyPaymentSetupLink
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        return FamilyPaymentSetupLink::query()->with('familyAccount')
            ->where('token_hash', hash('sha256', $token))->first();
    }

    /** Only called after the guest accepts the payment terms. */
    public function start(FamilyPaymentSetupLink $link): string
    {
        $result = DB::transaction(function () use ($link): string|PaymentException {
            $account = FamilyAccount::query()->with('owner')->lockForUpdate()->findOrFail($link->family_account_id);
            $locked = FamilyPaymentSetupLink::query()->lockForUpdate()->findOrFail($link->id);
            $this->assertAccountActive($account);
            if (! $locked->isUsable()) {
                throw new PaymentException('This link is no longer available. Please ask your LoLo contact for a new link.');
            }
            if ($locked->stripe_checkout_session_id && $locked->stripe_checkout_url
                && $locked->stripe_checkout_expires_at?->isFuture()) {
                return $locked->stripe_checkout_url;
            }
            // Stripe requires at least 30 minutes before a Checkout expires.
            if ($locked->expires_at->lessThanOrEqualTo(now()->addMinutes(31))) {
                throw new PaymentException('This link is about to expire. Please ask your LoLo contact for a new link.');
            }

            $customerId = $this->stripe->ensureFamilyAccountCustomer($account);
            $retry = ! $locked->stripe_checkout_session_id
                && $locked->checkout_attempt > 0 && $locked->stripe_checkout_expires_at?->greaterThan(now()->addMinutes(31));
            $attempt = $retry ? $locked->checkout_attempt : $locked->checkout_attempt + 1;
            $expiresAt = $retry ? $locked->stripe_checkout_expires_at->timestamp
                : min(now()->addHours(23)->timestamp, $locked->expires_at->timestamp);
            $successUrl = route('public.family-payment-setup.return', ['token' => $locked->token]);
            $locked->forceFill([
                'consented_at' => $locked->consented_at ?? now(),
                'consent_version' => self::CONSENT_VERSION,
                'stripe_customer_id' => $customerId,
                'stripe_checkout_session_id' => null,
                'stripe_checkout_url' => null,
                'stripe_checkout_expires_at' => \Illuminate\Support\Carbon::createFromTimestamp($expiresAt, config('app.timezone')),
                'checkout_attempt' => $attempt,
            ])->save();
            try {
                $session = $this->stripe->createGuestFamilySetupCheckoutSession(
                    $account->fresh('owner'), $locked->id, $attempt, $successUrl,
                    $this->urlFor($locked).'?checkout=cancel', $expiresAt,
                );
            } catch (PaymentException $exception) {
                // Keep the attempt and exact Stripe parameters across transient
                // errors, so retrying uses the same idempotency key and expiry.
                return $exception;
            }
            $locked->forceFill([
                'stripe_checkout_session_id' => $session['id'],
                'stripe_checkout_url' => $session['url'],
                'stripe_checkout_expires_at' => \Illuminate\Support\Carbon::createFromTimestamp($session['expires_at'], config('app.timezone')),
            ])->save();

            return $session['url'];
        });
        if ($result instanceof PaymentException) {
            throw $result;
        }

        return $result;
    }

    /**
     * Returns true for every guest-link event, including revoked/unknown links,
     * so they can never fall through to the normal authenticated billing path.
     *
     * @param  array<string,mixed>  $payload
     */
    public function handleCheckoutCompleted(array $payload): bool
    {
        $sessionId = (string) ($payload['id'] ?? '');
        $linkId = (int) ($payload['metadata']['onboarding_payment_link_id'] ?? 0);
        $link = $linkId > 0 ? FamilyPaymentSetupLink::query()->find($linkId)
            : ($sessionId !== '' ? FamilyPaymentSetupLink::query()->where('stripe_checkout_session_id', $sessionId)->first() : null);
        if (! $link && $linkId === 0) {
            return false;
        }
        if (! $link || $sessionId === '') {
            return true;
        }

        DB::transaction(function () use ($link, $payload, $sessionId): void {
            $account = FamilyAccount::query()->lockForUpdate()->find($link->family_account_id);
            $locked = FamilyPaymentSetupLink::query()->lockForUpdate()->findOrFail($link->id);
            if (! $account || $account->status !== FamilyAccount::STATUS_ACTIVE || $locked->revoked_at || $locked->completed_at) {
                return;
            }
            if (! $locked->consented_at || $locked->stripe_checkout_session_id !== $sessionId
                || (string) ($payload['customer'] ?? '') !== $locked->stripe_customer_id
                || (int) ($payload['metadata']['family_account_id'] ?? 0) !== (int) $account->id
                || (int) ($payload['metadata']['onboarding_payment_link_id'] ?? 0) !== (int) $locked->id) {
                throw new PaymentException('The payment setup session does not match this link.');
            }

            $session = $this->stripe->retrieveGuestFamilySetupCheckoutSession($sessionId);
            $intent = $session['setup_intent'] ?? null;
            $method = is_array($intent) ? ($intent['payment_method'] ?? null) : null;
            $customerId = (string) $locked->stripe_customer_id;
            $valid = $customerId !== ''
                && $customerId === (string) $account->stripe_customer_id
                && ($session['id'] ?? '') === $sessionId
                && ($session['mode'] ?? '') === 'setup'
                && ($session['status'] ?? '') === 'complete'
                && (int) ($session['expires_at'] ?? 0) > 0
                && (int) ($session['expires_at'] ?? 0) <= $locked->expires_at->timestamp
                && (int) ($session['expires_at'] ?? 0) === $locked->stripe_checkout_expires_at?->timestamp
                && ($session['customer'] ?? '') === $customerId
                && (int) ($session['metadata']['family_account_id'] ?? 0) === (int) $account->id
                && (int) ($session['metadata']['onboarding_payment_link_id'] ?? 0) === (int) $locked->id
                && is_array($intent) && ! empty($intent['id'])
                && ($intent['status'] ?? '') === 'succeeded'
                && ($intent['usage'] ?? '') === 'off_session'
                && ($intent['customer'] ?? '') === $customerId
                && (int) ($intent['metadata']['family_account_id'] ?? 0) === (int) $account->id
                && (int) ($intent['metadata']['onboarding_payment_link_id'] ?? 0) === (int) $locked->id
                && is_array($method) && ! empty($method['id'])
                && ($method['customer'] ?? '') === $customerId;
            if (! $valid) {
                throw new PaymentException('Unable to verify this payment setup session.');
            }
            $card = $this->methodSummary($method);
            if (! $card || $this->isExpired($card)) {
                throw new PaymentException('A valid reusable payment method is required.');
            }
            $this->stripe->setGuestFamilyDefaultPaymentMethod($customerId, (string) $method['id'], $sessionId);
            $this->recordSummary($account, $card);
            $locked->forceFill([
                'completed_at' => now(),
                'stripe_setup_intent_id' => (string) $intent['id'],
                'stripe_checkout_url' => null,
            ])->save();
            $this->log($account->id, null, 'payment_method_saved', ['brand' => $card['brand'], 'last4' => $card['last4']]);
        });

        return true;
    }

    /** @return array{status:string,ready:bool,card:?array,checked_at:mixed,link:?FamilyPaymentSetupLink} */
    public function summaryForAccount(FamilyAccount $account, bool $refresh = false): array
    {
        $customerId = trim((string) ($account->stripe_customer_id ?: $account->owner?->stripe_customer_id));
        if ($refresh) {
            $snapshotVersion = (int) $account->payment_method_snapshot_version;
            if ($customerId !== '' && ! $account->stripe_customer_id) {
                $account->forceFill(['stripe_customer_id' => $customerId])->save();
            }
            // Development bypass must not turn a customer id into a "saved" card.
            if ($customerId === '' || ! $this->stripe->isBypass()) {
                $account->forceFill(['payment_method_refresh_attempted_at' => now()])->save();
                $card = $customerId !== '' ? $this->stripe->defaultPaymentMethodForCustomer($customerId) : null;
                $this->recordSummary($account, $card, $snapshotVersion);
            }
        }
        $customerId = trim((string) ($account->stripe_customer_id ?: $account->owner?->stripe_customer_id));
        $status = $customerId === ''
            ? 'missing' : ($account->payment_method_status ?? 'unknown');
        if ($status === 'ready' && $this->isExpired($account->payment_method_summary)) {
            $status = 'missing';
        }

        return [
            'status' => $status,
            'ready' => $status === 'ready',
            'card' => $account->payment_method_summary,
            'checked_at' => $account->payment_method_checked_at,
            'link' => FamilyPaymentSetupLink::query()->where('family_account_id', $account->id)->latest('id')->first(),
        ];
    }

    /**
     * Stripe reads can finish after a webhook has committed a newer payment
     * method. Compare the revision captured before the read and discard stale
     * results. Verified webhook writes hold the account lock and always advance
     * the revision. Reloading also makes the caller display the winning result.
     */
    public function recordSummary(FamilyAccount $account, ?array $card, ?int $expectedVersion = null): bool
    {
        $updated = FamilyAccount::query()->whereKey($account->id)
            ->when($expectedVersion !== null, fn ($query) => $query->where('payment_method_snapshot_version', $expectedVersion))
            ->update([
                'payment_method_status' => $card && ! $this->isExpired($card) ? 'ready' : 'missing',
                'payment_method_summary' => $card === null ? null : json_encode($card, JSON_THROW_ON_ERROR),
                'payment_method_checked_at' => now(),
                'payment_method_snapshot_version' => DB::raw('payment_method_snapshot_version + 1'),
            ]);
        $account->refresh();

        return $updated > 0;
    }

    private function isExpired(?array $card): bool
    {
        if (! $card || ($card['brand'] ?? '') === 'link') {
            return false;
        }
        $year = (int) ($card['exp_year'] ?? 0);
        $month = (int) ($card['exp_month'] ?? 0);

        return $year > 0 && $month > 0 && ($year < now()->year || ($year === now()->year && $month < now()->month));
    }

    private function methodSummary(array $method): ?array
    {
        if (($method['type'] ?? '') === 'link') {
            return ['id' => $method['id'], 'type' => 'link', 'brand' => 'link', 'last4' => null, 'exp_month' => null, 'exp_year' => null];
        }
        if (($method['type'] ?? '') !== 'card' || ! is_array($method['card'] ?? null)) {
            return null;
        }

        return [
            'id' => $method['id'],
            'type' => 'card',
            'brand' => (string) ($method['card']['brand'] ?? ''),
            'last4' => (string) ($method['card']['last4'] ?? ''),
            'exp_month' => (int) ($method['card']['exp_month'] ?? 0),
            'exp_year' => (int) ($method['card']['exp_year'] ?? 0),
        ];
    }

    private function assertAccountActive(FamilyAccount $account): void
    {
        if ($account->status !== FamilyAccount::STATUS_ACTIVE || $account->owner?->role !== 'family') {
            throw new PaymentException('This family account is unavailable.');
        }
    }

    private function log(int $accountId, ?int $actorId, string $action, array $metadata = []): void
    {
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => $accountId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
