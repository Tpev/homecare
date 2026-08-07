<?php

namespace App\Services\FamilyAccounts;

use App\Mail\FamilyAccountInvitationMail;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyAccountInvitation;
use App\Models\FamilyAccountMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FamilyAccountInvitationService
{
    public const EXPIRY_DAYS = 7;

    public function __construct(private readonly FamilyAccountContext $context) {}

    /** @return array{invitation:FamilyAccountInvitation,token:string,delivered:bool} */
    public function send(User $owner, string $email, ?string $ipAddress = null): array
    {
        $membership = $this->context->membership($owner);
        if (! $membership->isOwner()) {
            throw ValidationException::withMessages(['email' => 'Only the account owner can invite someone.']);
        }

        $normalizedEmail = $this->normalizeEmail($email);
        $this->assertInvitationAllowed($owner, $membership->familyAccount, $normalizedEmail);
        $this->hitRateLimits($owner, $membership->familyAccount, $normalizedEmail, $ipAddress);

        $issued = $this->issue($membership->familyAccount, $owner, $normalizedEmail, 'invitation_sent');
        $issued['delivered'] = $this->deliver($issued['invitation'], $issued['token']);

        return $issued;
    }

    /** @return array{invitation:FamilyAccountInvitation,token:string,delivered:bool} */
    public function resend(User $owner, FamilyAccountInvitation $invitation, ?string $ipAddress = null): array
    {
        $membership = $this->context->membership($owner);
        if (! $membership->isOwner() || (int) $membership->family_account_id !== (int) $invitation->family_account_id) {
            throw ValidationException::withMessages(['invitation' => 'Only the account owner can manage this invitation.']);
        }

        $this->assertInvitationAllowed($owner, $membership->familyAccount, $invitation->email_normalized);
        $this->hitRateLimits($owner, $membership->familyAccount, $invitation->email_normalized, $ipAddress);

        $issued = $this->issue($membership->familyAccount, $owner, $invitation->email_normalized, 'invitation_resent');
        $issued['delivered'] = $this->deliver($issued['invitation'], $issued['token']);

        return $issued;
    }

    public function cancel(User $owner, FamilyAccountInvitation $invitation): FamilyAccountInvitation
    {
        $membership = $this->context->membership($owner);
        if (! $membership->isOwner() || (int) $membership->family_account_id !== (int) $invitation->family_account_id) {
            throw ValidationException::withMessages(['invitation' => 'Only the account owner can manage this invitation.']);
        }

        return DB::transaction(function () use ($owner, $invitation): FamilyAccountInvitation {
            $account = FamilyAccount::query()->whereKey($invitation->family_account_id)->lockForUpdate()->firstOrFail();
            if ($account->status !== FamilyAccount::STATUS_ACTIVE || (int) $account->owner_user_id !== (int) $owner->id) {
                throw ValidationException::withMessages(['invitation' => 'Only the current account owner can manage this invitation.']);
            }

            $locked = FamilyAccountInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isUsable()) {
                return $locked->fresh();
            }

            $locked->forceFill([
                'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
                'canceled_at' => now(),
                'canceled_by_user_id' => $owner->id,
            ])->save();

            $this->log($locked->family_account_id, $owner->id, 'invitation_canceled', null, [
                'email' => $this->maskedEmail($locked->email_normalized),
            ]);

            return $locked->fresh();
        });
    }

    public function findByToken(string $token): ?FamilyAccountInvitation
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }

        return FamilyAccountInvitation::query()
            ->with('familyAccount.owner')
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    public function requireUsableToken(string $token): FamilyAccountInvitation
    {
        $invitation = $this->findByToken($token);

        if (! $invitation || ! $invitation->isUsable() || $invitation->familyAccount?->status !== FamilyAccount::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'invitation' => $this->unavailableMessage($invitation),
            ]);
        }

        return $invitation;
    }

    public function accept(User $user, string $token): FamilyAccountMember
    {
        $invitation = $this->requireUsableToken($token);
        $normalizedUserEmail = $this->normalizeEmail((string) $user->email);

        if ($normalizedUserEmail !== $invitation->email_normalized) {
            throw ValidationException::withMessages([
                'invitation' => 'Sign in with the email address that received this invitation.',
            ]);
        }

        if (! $user->email_verified_at || $user->role !== 'family' || $user->isAdministrator()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation cannot be joined with your current LoLo account. Contact LoLo Support and we will help you safely connect the accounts.',
            ]);
        }

        return DB::transaction(function () use ($user, $token): FamilyAccountMember {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedInvitation = FamilyAccountInvitation::query()
                ->with('familyAccount')
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $lockedInvitation || ! $lockedInvitation->isUsable()
                || $lockedInvitation->familyAccount?->status !== FamilyAccount::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'invitation' => $this->unavailableMessage($lockedInvitation),
                ]);
            }

            $activeMembership = FamilyAccountMember::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', FamilyAccountMember::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($activeMembership && (int) $activeMembership->family_account_id !== (int) $lockedInvitation->family_account_id) {
                if (! $this->canLeaveEmptyOwnedAccount($lockedUser, $activeMembership)) {
                    throw ValidationException::withMessages([
                        'invitation' => 'This invitation cannot be joined with your current LoLo account. Contact LoLo Support and we will help you safely connect the accounts.',
                    ]);
                }

                $activeMembership->forceFill([
                    'status' => FamilyAccountMember::STATUS_LEFT,
                    'ended_at' => now(),
                    'ended_by_user_id' => $lockedUser->id,
                ])->save();
                $activeMembership->familyAccount()->update(['status' => FamilyAccount::STATUS_CLOSED]);
            }

            $membership = FamilyAccountMember::query()->updateOrCreate(
                [
                    'family_account_id' => $lockedInvitation->family_account_id,
                    'user_id' => $lockedUser->id,
                ],
                [
                    'access_level' => FamilyAccountMember::ACCESS_MEMBER,
                    'status' => FamilyAccountMember::STATUS_ACTIVE,
                    'joined_at' => now(),
                    'ended_at' => null,
                    'ended_by_user_id' => null,
                ]
            );

            $lockedInvitation->forceFill([
                'accepted_at' => now(),
                'accepted_by_user_id' => $lockedUser->id,
            ])->save();

            $this->log($lockedInvitation->family_account_id, $lockedUser->id, 'invitation_accepted', $lockedUser->id);

            return $membership->fresh(['familyAccount.owner', 'user']);
        });
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = Str::lower(trim($email));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }

        return $normalized;
    }

    private function assertInvitationAllowed(User $owner, FamilyAccount $account, string $normalizedEmail): void
    {
        if ($normalizedEmail === $this->normalizeEmail((string) $owner->email)) {
            throw ValidationException::withMessages(['email' => 'You already have access to this account.']);
        }

        if ($account->activeMemberships()
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$normalizedEmail]))
            ->exists()) {
            throw ValidationException::withMessages(['email' => 'This person already has access.']);
        }
    }

    private function hitRateLimits(User $owner, FamilyAccount $account, string $email, ?string $ipAddress): void
    {
        $keys = [
            'family-invite:owner:'.$owner->id,
            'family-invite:account:'.$account->id,
            'family-invite:email:'.hash('sha256', $email),
            'family-invite:ip:'.hash('sha256', (string) $ipAddress),
        ];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, 20)) {
                throw ValidationException::withMessages([
                    'email' => 'Please wait before sending another invitation.',
                ]);
            }
        }

        foreach ($keys as $key) {
            RateLimiter::hit($key, 3600);
        }
    }

    /** @return array{invitation:FamilyAccountInvitation,token:string} */
    private function issue(FamilyAccount $account, User $owner, string $email, string $action): array
    {
        return DB::transaction(function () use ($account, $owner, $email, $action): array {
            $lockedAccount = FamilyAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($lockedAccount->status !== FamilyAccount::STATUS_ACTIVE
                || (int) $lockedAccount->owner_user_id !== (int) $owner->id) {
                throw ValidationException::withMessages(['email' => 'Only the current account owner can invite someone.']);
            }

            $token = bin2hex(random_bytes(32));

            $invitation = FamilyAccountInvitation::query()->updateOrCreate(
                [
                    'family_account_id' => $account->id,
                    'email_normalized' => $email,
                ],
                [
                    'invited_by_user_id' => $owner->id,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addDays(self::EXPIRY_DAYS),
                    'accepted_at' => null,
                    'accepted_by_user_id' => null,
                    'canceled_at' => null,
                    'canceled_by_user_id' => null,
                ]
            );

            $this->log($account->id, $owner->id, $action, null, [
                'email' => $this->maskedEmail($email),
            ]);

            return ['invitation' => $invitation->fresh('familyAccount.owner'), 'token' => $token];
        });
    }

    private function deliver(FamilyAccountInvitation $invitation, string $token): bool
    {
        try {
            Mail::to($invitation->email_normalized)->send(new FamilyAccountInvitationMail($invitation, $token));

            return true;
        } catch (Throwable $exception) {
            $this->log($invitation->family_account_id, $invitation->invited_by_user_id, 'invitation_delivery_failed', null, [
                'email' => $this->maskedEmail($invitation->email_normalized),
                'exception' => $exception::class,
            ]);
            report($exception);

            return false;
        }
    }

    private function canLeaveEmptyOwnedAccount(User $user, FamilyAccountMember $membership): bool
    {
        if (! $membership->isOwner()) {
            return false;
        }

        $account = $membership->familyAccount;
        if ((int) $account->owner_user_id !== (int) $user->id || $account->stripe_customer_id || $user->stripe_customer_id) {
            return false;
        }

        foreach (FamilyAccountBackfill::FAMILY_OWNED_TABLES as $table) {
            if (DB::table($table)->where('family_account_id', $account->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    private function unavailableMessage(?FamilyAccountInvitation $invitation): string
    {
        if ($invitation?->expires_at?->isPast() && ! $invitation->accepted_at && ! $invitation->canceled_at) {
            return 'This invitation has expired. Ask the account owner to send a new one.';
        }

        return 'This invitation is no longer available.';
    }

    private function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).'***@'.$domain;
    }

    private function log(int $accountId, ?int $actorId, string $action, ?int $subjectUserId = null, array $metadata = []): void
    {
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => $accountId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'subject_user_id' => $subjectUserId,
            'metadata' => $metadata ?: null,
        ]);
    }
}
