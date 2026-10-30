<?php

namespace App\Services\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareHourContributionLink;
use App\Models\CareHourContributionVerification;
use App\Models\CareHourPurchase;
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

class CareHourContributionService
{
    public function __construct(private readonly FamilyAccountContext $families) {}

    /** The raw private link token is returned once and is never stored. */
    public function createLink(CareHourAccount $account, User $actor): array
    {
        $this->assertOwner($account, $actor);
        app(CareHourEligibilityService::class)->assertFamilyEligible((int) $account->family_account_id, 'care_hours');
        if (! app(CareHourAccess::class)->canContribute($actor) || ! $account->active) {
            throw ValidationException::withMessages(['care_hours' => 'New contribution links are currently unavailable.']);
        }
        $account = app(CareHourIdentityService::class)->walletFor($actor) ?? $account;
        if (! $account->active) {
            throw ValidationException::withMessages(['care_hours' => 'New contribution links are currently unavailable.']);
        }
        $token = Str::random(64);
        $link = CareHourContributionLink::query()->create([
            'care_hour_account_id' => $account->id, 'created_by_user_id' => $actor->id,
            'token_hash' => hash('sha256', $token), 'display_name' => 'Family care balance',
        ]);

        return ['link' => $link, 'token' => $token];
    }

    public function revoke(CareHourContributionLink $link, User $actor): void
    {
        $this->assertOwner($link->account, $actor);
        $link->forceFill(['revoked_at' => $link->revoked_at ?: now()])->save();
    }

    public function resolve(string $token): CareHourContributionLink
    {
        $link = CareHourContributionLink::query()->where('token_hash', hash('sha256', $token))->firstOrFail();
        $this->assertAvailable($link);

        return $link;
    }

    public function assertAvailable(CareHourContributionLink $link): void
    {
        if (app(CareHourEligibilityService::class)->reasonFor($link->account)) {
            throw ValidationException::withMessages(['care_hours' => 'This family is not currently accepting care-hour contributions. Please contact the family.']);
        }
        if (! app(CareHourAccess::class)->canContribute(auth()->user())
            || $link->fresh()->revoked_at || ! $link->account->fresh()->active) {
            throw ValidationException::withMessages(['care_hours' => 'This contribution link is no longer accepting purchases.']);
        }
    }

    public function requestVerification(CareHourContributionLink $link, string $email, string $name): CareHourContributionVerification
    {
        $this->assertAvailable($link);
        $this->assertBuyerAllowed($email);

        return $this->issueVerification($link, $email, $name);
    }

    public function assertBuyerAllowed(string $email): void
    {
        $actor = auth()->user();
        if (config('care_hours.pilot_only', true)
            && (! app(CareHourAccess::class)->allows($actor) || ! hash_equals(strtolower(trim((string) $actor?->email)), strtolower(trim($email))))) {
            throw ValidationException::withMessages(['email' => 'Use your signed-in account email for this purchase.']);
        }
    }

    public function requestReceiptVerification(CareHourPurchase $purchase, string $email): CareHourContributionVerification
    {
        if (! $purchase->care_hour_contribution_link_id || ! hash_equals(strtolower($purchase->buyer_email), strtolower(trim($email)))) {
            throw ValidationException::withMessages(['email' => 'Use the email address from this purchase.']);
        }

        return $this->issueVerification($purchase->contributionLink, $email, $purchase->buyer_name);
    }

    public function verifyReceipt(CareHourPurchase $purchase, string $verificationId, string $code): string
    {
        $verification = CareHourContributionVerification::query()->find($verificationId);
        if (! $verification || (int) $verification->care_hour_contribution_link_id !== (int) $purchase->care_hour_contribution_link_id
            || ! hash_equals(strtolower($purchase->buyer_email), $verification->email)) {
            throw ValidationException::withMessages(['code' => 'This code is invalid or expired. Request a new code.']);
        }

        return $this->verify($purchase->contributionLink, $verificationId, $code);
    }

    public function verify(CareHourContributionLink $link, string $verificationId, string $code): string
    {
        $token = Str::random(64);
        // Return an error marker so an incorrect code's attempt counter is committed.
        $valid = DB::transaction(function () use ($link, $verificationId, $code, $token): bool {
            $verification = CareHourContributionVerification::query()->lockForUpdate()->find($verificationId);
            if (! $verification || (int) $verification->care_hour_contribution_link_id !== (int) $link->id
                || $verification->verified_at || $verification->expires_at->isPast() || $verification->attempts >= 5) {
                return false;
            }
            $verification->increment('attempts');
            if (! Hash::check($code, $verification->code_hash)) {
                return false;
            }
            $verification->forceFill([
                'verified_at' => now(), 'token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addDay(),
            ])->save();

            return true;
        });
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'This code is invalid or expired. Request a new code.']);
        }

        return $token;
    }

    public function verifiedBuyer(CareHourContributionLink $link, string $token): array
    {
        $verification = CareHourContributionVerification::query()
            ->where('care_hour_contribution_link_id', $link->id)
            ->where('token_hash', hash('sha256', $token))->whereNotNull('verified_at')
            ->where('token_expires_at', '>', now())->first();
        if (! $verification) {
            throw new AuthorizationException('Confirm your email address before continuing.');
        }

        return ['email' => $verification->email, 'name' => $verification->name];
    }

    private function issueVerification(CareHourContributionLink $link, string $email, string $name): CareHourContributionVerification
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        Validator::make(compact('email', 'name'), ['email' => 'required|email|max:254', 'name' => 'required|string|max:120'])->validate();
        $key = 'care-hour-verify:'.hash('sha256', $link->id.'|'.$email);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Please wait before requesting another code.']);
        }
        RateLimiter::hit($key, 900);
        $code = (string) random_int(100000, 999999);
        $verification = CareHourContributionVerification::query()->create([
            'id' => (string) Str::uuid(), 'care_hour_contribution_link_id' => $link->id,
            'email' => $email, 'name' => $name, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes((int) config('care_hours.verification_minutes', 20)),
        ]);
        Notification::route('mail', [$email => $name])->notify(new CareHourVerificationNotification($code));

        return $verification;
    }

    private function assertOwner(CareHourAccount $account, User $actor): void
    {
        $membership = $this->families->membershipFor($actor, false);
        if (! $membership?->isOwner() || (int) $membership->family_account_id !== (int) $account->family_account_id) {
            throw new AuthorizationException('Only the family account owner can manage contribution links.');
        }
    }
}
