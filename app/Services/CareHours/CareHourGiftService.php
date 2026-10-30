<?php

namespace App\Services\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareHourGift;
use App\Models\CareHourGiftVerification;
use App\Models\CareHourPurchase;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CareHourGiftService
{
    public function __construct(private readonly FamilyAccountContext $families, private readonly CareHourLedgerService $ledger) {}

    public function catalog(): array
    {
        $catalog = [];
        foreach (array_keys((array) config('care_hours.packages', [])) as $hours) {
            $catalog[(int) $hours] = $this->quote((int) $hours);
        }

        return $catalog;
    }

    public function quote(int $hours): array
    {
        $package = config('care_hours.packages.'.$hours);
        if (! is_array($package) || ! in_array($hours, [5, 10, 20, 40], true)) {
            throw ValidationException::withMessages(['package' => 'Choose one of the available packages.']);
        }
        $quote = [
            'paid_hours' => $hours, 'paid_minutes' => (int) $package['paid_minutes'], 'bonus_minutes' => 0,
            'total_minutes' => (int) $package['paid_minutes'], 'amount_cents' => (int) $package['amount_cents'],
            'conditional_bonus_minutes' => config('care_hours.welcome_enabled', false) && $hours !== 5 ? (int) $package['bonus_minutes'] : 0,
            'currency' => 'usd', 'terms_version' => (string) config('care_hours.terms_version', '2026-10-21').'-gift-v1',
        ];
        $quote['quote_key'] = $this->quoteKey('gift-purchase', $quote);

        return $quote;
    }

    public function requestVerification(string $email, string $name): CareHourGiftVerification
    {
        $this->assertSalesAvailable();
        $this->assertBuyerAllowed($email);

        return $this->issueVerification($email, $name);
    }

    public function requestReceiptVerification(CareHourPurchase $purchase, string $email): CareHourGiftVerification
    {
        if (! $purchase->gift()->exists() || ! hash_equals(strtolower($purchase->buyer_email), strtolower(trim($email)))) {
            throw ValidationException::withMessages(['email' => 'Use the email address from this purchase.']);
        }

        return $this->issueVerification($email, $purchase->buyer_name, $purchase->id);
    }

    public function verifyReceipt(CareHourPurchase $purchase, string $verificationId, #[\SensitiveParameter] string $code): string
    {
        $verification = CareHourGiftVerification::query()->find($verificationId);
        if (! $verification || (int) $verification->care_hour_purchase_id !== (int) $purchase->id
            || ! hash_equals(strtolower($purchase->buyer_email), $verification->email)) {
            throw ValidationException::withMessages(['code' => 'This code is invalid or expired. Request a new code.']);
        }

        return $this->verify($verificationId, $code);
    }

    public function verify(string $verificationId, #[\SensitiveParameter] string $code): string
    {
        $token = Str::random(64);
        $valid = DB::transaction(function () use ($verificationId, $code, $token): bool {
            $verification = CareHourGiftVerification::query()->lockForUpdate()->find($verificationId);
            if (! $verification || $verification->verified_at || $verification->expires_at->isPast() || $verification->attempts >= 5) {
                return false;
            }
            $verification->increment('attempts');
            if (! Hash::check($code, $verification->code_hash)) {
                return false;
            }
            $verification->forceFill(['verified_at' => now(), 'token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addDay()])->save();

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'This code is invalid or expired. Request a new code.']);
        }

        return $token;
    }

    public function verifiedBuyer(#[\SensitiveParameter] string $token): array
    {
        $verification = $this->verification($token);
        if ($verification->care_hour_purchase_id !== null) {
            throw new AuthorizationException('Confirm your email for a new gift purchase.');
        }

        return ['email' => $verification->email, 'name' => $verification->name];
    }

    public function start(#[\SensitiveParameter] string $verifiedToken, int $hours, string $quoteKey, string $requestKey, string $successUrl, string $cancelUrl): CareHourPurchase
    {
        $verification = $this->verification($verifiedToken);
        if ($verification->care_hour_purchase_id !== null) {
            throw new AuthorizationException('Confirm your email for a new gift purchase.');
        }
        if (strlen($requestKey) < 16 || strlen($requestKey) > 200) {
            throw ValidationException::withMessages(['purchase' => 'Refresh the purchase page and try again.']);
        }
        $key = hash('sha256', 'gift|'.$verification->email.'|'.$requestKey);
        $purchase = DB::transaction(function () use ($verification, $hours, $quoteKey, $key, $successUrl, $cancelUrl): CareHourPurchase {
            CareHourGiftVerification::query()->lockForUpdate()->findOrFail($verification->id);
            $existing = CareHourPurchase::query()->where('request_key', $key)->first();
            if ($existing) {
                if (! $existing->gift()->exists() || $existing->paid_minutes !== $hours * 60) {
                    throw ValidationException::withMessages(['purchase' => 'This purchase request already belongs to another selection.']);
                }

                return $existing;
            }
            $this->assertSalesAvailable();
            $this->assertBuyerAllowed($verification->email);
            $quote = $this->quote($hours);
            if (! hash_equals($quote['quote_key'], $quoteKey)) {
                throw ValidationException::withMessages(['package' => 'The available gift offer changed. Review the updated package before paying.']);
            }
            $publicId = (string) Str::uuid();
            $purchase = CareHourPurchase::query()->create([
                'public_id' => $publicId, 'request_key' => $key, 'care_hour_account_id' => null, 'family_account_id' => null,
                'buyer_user_id' => null, 'buyer_email' => $verification->email, 'buyer_name' => $verification->name,
                'paid_minutes' => $quote['paid_minutes'], 'bonus_minutes' => 0, 'total_minutes' => $quote['paid_minutes'],
                'amount_cents' => $quote['amount_cents'], 'currency' => $quote['currency'], 'status' => 'pending',
                'terms_version' => $quote['terms_version'], 'quote' => $quote, 'terms_accepted_at' => now(),
                'success_url' => str_replace('{PURCHASE_ID}', $publicId, $successUrl),
                'cancel_url' => str_replace('{PURCHASE_ID}', $publicId, $cancelUrl),
            ]);
            $purchase->gift()->create(['welcome_bonus_minutes' => $quote['conditional_bonus_minutes']]);

            return $purchase;
        }, 3);

        return app(CareHourPurchaseService::class)->startCheckout($purchase);
    }

    public function authorizeReceipt(CareHourPurchase $purchase, ?User $user = null, #[\SensitiveParameter] ?string $verifiedToken = null): void
    {
        if (! $purchase->gift()->exists()) {
            throw new AuthorizationException('This is not a gift receipt.');
        }
        if ($user && $purchase->buyer_user_id && (int) $purchase->buyer_user_id === (int) $user->id) {
            return;
        }
        if ($verifiedToken) {
            $verification = $this->verification($verifiedToken);
            if (($verification->care_hour_purchase_id === null || (int) $verification->care_hour_purchase_id === (int) $purchase->id)
                && hash_equals(strtolower($purchase->buyer_email), $verification->email)) {
                return;
            }
        }
        throw new AuthorizationException('Confirm the buyer email address to view this receipt.');
    }

    public function codeForBuyer(CareHourPurchase $purchase, ?User $user = null, #[\SensitiveParameter] ?string $verifiedToken = null): ?string
    {
        $purchase->refresh();
        $this->authorizeReceipt($purchase, $user, $verifiedToken);
        $gift = $purchase->gift;

        return $this->claimable($purchase, $gift) ? $gift->encrypted_code : null;
    }

    /** Called only inside the purchase lock after the authoritative payment checks succeed. */
    public function preparePaidGift(CareHourPurchase $purchase): void
    {
        $gift = $purchase->gift()->lockForUpdate()->firstOrFail();
        if ($gift->code_hash || $gift->claimed_at || $purchase->status !== 'paid' || ! $purchase->paid_at
            || ! $purchase->stripe_payment_intent_id || ! $purchase->stripe_charge_id || $purchase->amount_reversed_cents > 0) {
            return;
        }
        // 24 independently random symbols from a 32-character alphabet provide 120 bits of entropy.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $raw = '';
        for ($i = 0; $i < 24; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $gift->forceFill(['code_hash' => hash('sha256', $raw), 'encrypted_code' => implode('-', str_split($raw, 4)), 'ready_at' => now()])->save();
    }

    public function previewForClaim(#[\SensitiveParameter] string $code, CareHourAccount $account, User $actor): array
    {
        $this->assertRecipient($account, $actor);
        $gift = $this->findCode($code);
        $purchase = app(CareHourPurchaseService::class)->refresh($gift->purchase);
        $gift->refresh();
        if (! $this->claimable($purchase, $gift)) {
            $this->unavailable();
        }

        return $this->claimQuote($gift, $purchase, $account);
    }

    /** Safe before sign-in: no buyer, recipient, provider identity, or bearer code is returned. */
    public function inspectForClaim(#[\SensitiveParameter] string $code): array
    {
        $gift = $this->findCode($code);
        $purchase = app(CareHourPurchaseService::class)->refresh($gift->purchase);
        $gift->refresh();
        if (! $this->claimable($purchase, $gift)) {
            $this->unavailable();
        }

        return ['paid_minutes' => (int) $purchase->paid_minutes, 'conditional_bonus_minutes' => (int) $gift->welcome_bonus_minutes];
    }

    public function resolveSelfClaimAccount(#[\SensitiveParameter] string $code, User $actor): CareHourAccount
    {
        return $this->resolveFamilyClaimAccount($code, $actor);
    }

    public function resolveFamilyClaimAccount(#[\SensitiveParameter] string $code, User $actor): CareHourAccount
    {
        $gift = $this->findCode($code);
        $purchase = app(CareHourPurchaseService::class)->refresh($gift->purchase);
        $gift->refresh();
        if (! $this->claimable($purchase, $gift)) {
            $this->unavailable();
        }
        if ($actor->role !== 'family') {
            throw new AuthorizationException('Sign in to your family account to claim these hours.');
        }
        $family = $this->families->account($actor);

        return DB::transaction(function () use ($actor, $family, $gift): CareHourAccount {
            $family->newQuery()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            app(CareHourEligibilityService::class)->assertFamilyEligible((int) $family->id, 'recipient');
            $account = app(CareHourIdentityService::class)->ensureFamilyWallet($actor, $gift);
            $account = CareHourAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $this->assertRecipient($account, $actor);
            // Match claim's account -> purchase -> gift order. A concurrent hold or claim
            // rolls back wallet creation, without provider I/O under these locks.
            $purchase = CareHourPurchase::query()->whereKey($gift->care_hour_purchase_id)->lockForUpdate()->firstOrFail();
            $currentGift = CareHourGift::query()->whereKey($gift->id)->lockForUpdate()->firstOrFail();
            if (! $this->claimable($purchase, $currentGift)) {
                $this->unavailable();
            }

            return $account;
        }, 3);
    }

    public function resolveClaimAccount(#[\SensitiveParameter] string $code, User $actor, string $recipientSelection): CareHourAccount
    {
        $gift = $this->findCode($code);
        $purchase = app(CareHourPurchaseService::class)->refresh($gift->purchase);
        $gift->refresh();
        if (! $this->claimable($purchase, $gift)) {
            $this->unavailable();
        }
        if (! preg_match('/^(account|profile|recipient):([1-9][0-9]*)$/D', $recipientSelection, $selection)) {
            throw ValidationException::withMessages(['recipient' => 'Choose an existing care recipient.']);
        }
        app(CareHourEligibilityService::class)->assertFamilyEligible((int) $this->families->account($actor)->id, 'recipient');
        $identities = app(CareHourIdentityService::class);
        $selected = match ($selection[1]) {
            'account' => CareHourAccount::query()->whereKey((int) $selection[2])
                ->where('family_account_id', $this->families->account($actor)->id)->first(),
            'profile' => $identities->profilesFor($actor)->firstWhere('id', (int) $selection[2]),
            'recipient' => $identities->recipientsFor($actor)->firstWhere('id', (int) $selection[2]),
        };
        if (! $selected) {
            throw new AuthorizationException('Choose an existing care recipient in your family.');
        }
        if ($selected instanceof CareHourAccount) {
            $this->assertRecipient($selected, $actor);
        }

        return $this->resolveFamilyClaimAccount($code, $actor);
    }

    public function claim(#[\SensitiveParameter] string $code, CareHourAccount $account, User $actor, string $quoteKey): CareHourPurchase
    {
        $this->assertRecipient($account, $actor, false);
        $gift = $this->findCode($code);
        // Refresh the original payment, including refunds and disputes, before assigning any entitlement.
        app(CareHourPurchaseService::class)->refresh($gift->purchase);

        return DB::transaction(function () use ($gift, $account, $actor, $quoteKey): CareHourPurchase {
            FamilyAccount::query()->whereKey($account->family_account_id)->lockForUpdate()->firstOrFail();
            $account = CareHourAccount::query()->lockForUpdate()->findOrFail($account->id);
            $this->assertRecipient($account, $actor, false);
            $purchase = CareHourPurchase::query()->lockForUpdate()->findOrFail($gift->care_hour_purchase_id);
            $gift = CareHourGift::query()->lockForUpdate()->findOrFail($gift->id);
            if ($gift->claimed_at && (int) $gift->claimed_by_user_id === (int) $actor->id
                && (int) $purchase->care_hour_account_id === (int) $account->id && hash_equals((string) $gift->claim_quote_key, $quoteKey)) {
                return $purchase;
            }
            app(CareHourEligibilityService::class)->assertFamilyEligible((int) $account->family_account_id, 'recipient');
            if (! $this->claimable($purchase, $gift)) {
                $this->unavailable();
            }
            $quote = $this->claimQuote($gift, $purchase, $account);
            if (! hash_equals($quote['quote_key'], $quoteKey)) {
                throw ValidationException::withMessages(['gift_code' => 'The welcome offer changed. Review the gift again before claiming it.']);
            }
            $purchase->forceFill([
                'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
                'bonus_minutes' => $quote['bonus_minutes'], 'total_minutes' => $quote['total_minutes'],
            ])->save();
            if ($purchase->bonus_minutes > 0) {
                DB::table('care_hour_welcome_claims')->updateOrInsert(['care_hour_account_id' => $account->id], [
                    'care_hour_purchase_id' => $purchase->id, 'status' => 'awarded', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->ledger->issuePurchase($purchase);
            $purchase->forceFill(['status' => 'issued', 'issued_at' => now()])->save();
            $gift->forceFill(['claimed_by_user_id' => $actor->id, 'claimed_at' => now(), 'claim_quote_key' => $quoteKey])->save();
            FamilyAccountActivityLog::query()->create([
                'family_account_id' => $account->family_account_id, 'actor_user_id' => $actor->id, 'action' => 'care_hours_gift_claimed',
                'metadata' => ['purchase_id' => $purchase->id, 'care_hour_account_id' => $account->id, 'paid_minutes' => $purchase->paid_minutes, 'bonus_minutes' => $purchase->bonus_minutes],
            ]);
            app(CareHourPurchaseService::class)->queueFamilyNotices($purchase);

            return $purchase;
        }, 3);
    }

    private function claimQuote(CareHourGift $gift, CareHourPurchase $purchase, CareHourAccount $account): array
    {
        $welcome = app(CareHourPurchaseService::class)->welcomeClaim($account);
        if ($gift->welcome_bonus_minutes > 0 && $welcome?->status === 'reserved') {
            throw ValidationException::withMessages(['gift_code' => 'Another purchase is confirming your family’s welcome offer. Please try again when it finishes.']);
        }
        $bonus = (! $welcome || $welcome->status === 'available') ? $gift->welcome_bonus_minutes : 0;
        $quote = ['paid_hours' => $purchase->paid_minutes / 60, 'paid_minutes' => $purchase->paid_minutes,
            'bonus_minutes' => $bonus, 'total_minutes' => $purchase->paid_minutes + $bonus,
            'amount_cents' => $purchase->amount_cents, 'currency' => $purchase->currency];
        $quote['quote_key'] = $this->quoteKey('gift-claim|'.$purchase->public_id.'|'.$account->id.'|'.($welcome->status ?? 'available'), $quote);

        return $quote;
    }

    private function findCode(#[\SensitiveParameter] string $code): CareHourGift
    {
        $normalized = preg_replace('/[\s-]+/', '', strtoupper(trim($code)));
        if (! preg_match('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{24}$/D', $normalized)) {
            $this->unavailable();
        }
        $gift = CareHourGift::query()->where('code_hash', hash('sha256', $normalized))->first();
        if (! $gift) {
            $this->unavailable();
        }

        return $gift;
    }

    private function claimable(CareHourPurchase $purchase, CareHourGift $gift): bool
    {
        return $gift->ready_at !== null && $gift->claimed_at === null && $purchase->care_hour_account_id === null
            && $purchase->family_account_id === null && $purchase->status === 'paid' && $purchase->paid_at !== null
            && $purchase->stripe_payment_intent_id !== null && $purchase->stripe_charge_id !== null && $purchase->livemode !== null
            && $purchase->amount_reversed_cents === 0 && $purchase->correction_state === null
            && ($purchase->dispute_id === null || $purchase->dispute_status === 'won');
    }

    private function assertRecipient(CareHourAccount $account, User $actor, bool $enforceEligibility = true): void
    {
        if (! $this->families->canAccessAccount($actor, (int) $account->family_account_id)) {
            throw new AuthorizationException('You do not have access to this care balance.');
        }
        if (! $account->active || $account->familyAccount->status !== FamilyAccount::STATUS_ACTIVE || $account->currency !== 'usd') {
            throw ValidationException::withMessages(['recipient' => 'Your family care balance is currently unavailable.']);
        }
        if ($enforceEligibility) {
            app(CareHourEligibilityService::class)->assertFamilyEligible((int) $account->family_account_id, 'recipient');
        }
    }

    private function assertSalesAvailable(): void
    {
        if (! app(CareHourAccess::class)->canBuyGift(auth()->user())) {
            throw ValidationException::withMessages(['purchase' => 'New care-hour gifts are currently unavailable.']);
        }
    }

    private function assertBuyerAllowed(string $email): void
    {
        $actor = auth()->user();
        if (config('care_hours.pilot_only', true)
            && (! app(CareHourAccess::class)->allows($actor) || ! hash_equals(strtolower(trim((string) $actor?->email)), strtolower(trim($email))))) {
            throw ValidationException::withMessages(['email' => 'Use your signed-in account email for this gift.']);
        }
    }

    private function verification(#[\SensitiveParameter] string $token): CareHourGiftVerification
    {
        $verification = CareHourGiftVerification::query()->where('token_hash', hash('sha256', $token))
            ->whereNotNull('verified_at')->where('token_expires_at', '>', now())->first();
        if (! $verification) {
            throw new AuthorizationException('Confirm your email address before continuing.');
        }

        return $verification;
    }

    private function issueVerification(string $email, string $name, ?int $purchaseId = null): CareHourGiftVerification
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        Validator::make(compact('email', 'name'), ['email' => 'required|email|max:254', 'name' => 'required|string|max:120'])->validate();
        $key = 'care-hour-gift-verify:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Please wait before requesting another code.']);
        }
        RateLimiter::hit($key, 900);
        $code = (string) random_int(100000, 999999);
        $verification = CareHourGiftVerification::query()->create([
            'id' => (string) Str::uuid(), 'care_hour_purchase_id' => $purchaseId, 'email' => $email, 'name' => $name,
            'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes((int) config('care_hours.verification_minutes', 20)),
        ]);
        Notification::route('mail', [$email => $name])->notify(new CareHourVerificationNotification($code));

        return $verification;
    }

    private function quoteKey(string $scope, array $quote): string
    {
        return hash_hmac('sha256', $scope.'|'.json_encode($quote), (string) config('app.key'));
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['gift_code' => 'This gift code is unavailable, already claimed, or awaiting payment review.']);
    }
}
