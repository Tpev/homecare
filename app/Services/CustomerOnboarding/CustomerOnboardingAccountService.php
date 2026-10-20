<?php

namespace App\Services\CustomerOnboarding;

use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyAccountMember;
use App\Models\FamilyOnboarding;
use App\Models\Lead;
use App\Models\User;
use App\Services\Family\FamilyOnboardingService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use App\Services\FamilyAcquisition\FamilyCrmSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOnboardingAccountService
{
    public function create(User $admin, array $input, ?int $leadId = null): FamilyAccount
    {
        abort_unless($admin->isAdministrator(), 403);
        $input = [
            'name' => trim((string) ($input['name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
            'phone' => trim((string) ($input['phone'] ?? '')),
        ];
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ])->validate();

        // Normalize the lock and stored address so simultaneous admin submissions converge.
        return Cache::lock('customer-onboarding:create:'.hash('sha256', $data['email']), 20)->block(5, function () use ($admin, $data, $leadId): FamilyAccount {
            try {
                return DB::transaction(function () use ($admin, $data, $leadId): FamilyAccount {
                    $lead = $leadId ? Lead::query()->where('lead_type', Lead::TYPE_FAMILY)->lockForUpdate()->findOrFail($leadId) : null;
                    if ($lead && filled($lead->email) && mb_strtolower(trim($lead->email)) !== $data['email']) {
                        throw ValidationException::withMessages(['email' => 'Use this family lead’s email address. Correct the lead first if it is wrong.']);
                    }
                    $matches = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$data['email']])->lockForUpdate()->get();
                    if ($matches->count() > 1) {
                        throw ValidationException::withMessages(['email' => 'Several accounts use this email. Resolve the duplicate accounts before continuing.']);
                    }

                    $user = $matches->first();
                    $newUser = ! $user;
                    if ($user) {
                        $this->assertEligibleOwner($user);
                    } else {
                        $user = User::query()->create([
                            ...$data, 'role' => 'family', 'password' => Str::random(80),
                        ]);
                    }

                    $account = app(FamilyAccountProvisioner::class)->provisionOwner($user, 'customer_onboarding_account_prepared');
                    if ($lead?->family_account_id && (int) $lead->family_account_id !== (int) $account->id) {
                        throw ValidationException::withMessages(['email' => 'This lead is already linked to a different family account.']);
                    }
                    if ($lead && ! $lead->family_account_id) {
                        if (Lead::query()->where('family_account_id', $account->id)->whereKeyNot($lead->id)->exists()) {
                            throw ValidationException::withMessages(['email' => 'This family account is already linked to another lead. Open its existing customer record to continue.']);
                        }
                        $lead->update(['family_account_id' => $account->id]);
                        $lead->activities()->create([
                            'actor_user_id' => $admin->id, 'type' => 'note', 'summary' => 'Family account linked',
                            'body' => 'Account #'.$account->id.' linked in Customer onboarding.',
                            'occurred_at' => now(),
                            'metadata' => ['family_account_id' => $account->id, 'actor_user_id' => $admin->id, 'source' => 'customer_onboarding'],
                        ]);
                    }

                    if ($newUser) {
                        // Registration enrollment imports browser session drafts. Admin preparation
                        // starts a clean draft and never enrolls an established account again.
                        $draft = array_fill_keys(array_merge(...array_values(FamilyOnboardingService::FIELDS)), '');
                        $draft['phone'] = (string) $user->phone;
                        $onboarding = FamilyOnboarding::query()->create([
                            'family_account_id' => $account->id, 'initiated_by_user_id' => $user->id,
                            'source' => 'admin_customer_onboarding', 'draft' => $draft, 'request_context' => [],
                        ]);
                        app(FamilyCrmSyncService::class)->sync($onboarding);
                    }

                    FamilyAccountActivityLog::query()->create([
                        'family_account_id' => $account->id, 'actor_user_id' => $admin->id,
                        'subject_user_id' => $user->id,
                        'action' => $newUser ? 'customer_onboarding_account_created' : 'customer_onboarding_account_linked',
                        'metadata' => ['lead_id' => $lead?->id],
                    ]);

                    return $account->fresh(['owner', 'onboarding']);
                }, 3);
            } catch (QueryException $exception) {
                if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                    throw $exception;
                }
                throw ValidationException::withMessages(['email' => 'The account changed while it was being created. Search this email and try again.']);
            }
        });
    }

    public function sendAccessEmail(User $admin, FamilyAccount $account): string
    {
        abort_unless($admin->isAdministrator(), 403);
        $account = $account->fresh(['owner']);
        abort_unless($account && $account->status === FamilyAccount::STATUS_ACTIVE, 403);
        $owner = $account->owner;
        abort_unless($owner && $owner->role === 'family', 403);
        $this->assertEligibleOwner($owner);

        $accountKey = 'customer-onboarding:access:account:'.$account->id;
        $adminKey = 'customer-onboarding:access:admin:'.$admin->id;
        if (RateLimiter::tooManyAttempts($accountKey, 1) || RateLimiter::tooManyAttempts($adminKey, 10)) {
            throw ValidationException::withMessages(['access' => 'An access email was sent recently. Please wait before sending another.']);
        }
        RateLimiter::hit($accountKey, 60);
        RateLimiter::hit($adminKey, 3600);

        $status = Password::sendResetLink(['email' => $owner->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['access' => __($status)]);
        }
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => $account->id, 'actor_user_id' => $admin->id,
            'subject_user_id' => $owner->id, 'action' => 'customer_onboarding_access_email_sent',
            'metadata' => ['channel' => 'password_reset'],
        ]);

        return $status;
    }

    private function assertEligibleOwner(User $user): void
    {
        if ($user->role !== 'family') {
            throw ValidationException::withMessages(['email' => 'This email belongs to another type of account. Use the family account owner’s email.']);
        }
        $memberships = FamilyAccountMember::query()->where('user_id', $user->id)
            ->where('status', FamilyAccountMember::STATUS_ACTIVE)->get();
        if ($memberships->contains(fn (FamilyAccountMember $member): bool => ! $member->isOwner())) {
            throw ValidationException::withMessages(['email' => 'This person is already a member of a family account. Use that account owner’s email.']);
        }
        $owned = FamilyAccount::query()->where('owner_user_id', $user->id)->get();
        $active = $owned->where('status', FamilyAccount::STATUS_ACTIVE);
        if (($owned->isNotEmpty() && $active->isEmpty()) || $active->count() > 1
            || $memberships->contains(fn (FamilyAccountMember $member): bool => ! $active->contains('id', $member->family_account_id))) {
            throw ValidationException::withMessages(['email' => 'This account is closed or has conflicting ownership. Review the account before continuing.']);
        }
    }
}
