<?php

namespace App\Models\Concerns;

use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToFamilyAccount
{
    protected static function bootBelongsToFamilyAccount(): void
    {
        static::addGlobalScope('authenticated_family_account', function (Builder $query): void {
            $user = auth()->user();

            if (! $user || $user->role !== 'family' || $user->isAdministrator()) {
                return;
            }

            $membership = app(FamilyAccountContext::class)->membershipFor($user);
            $query->where($query->qualifyColumn('family_account_id'), $membership?->family_account_id ?? 0);
        });

        static::creating(function ($model): void {
            $account = null;

            // An authenticated family actor can never select the security boundary.
            // Always resolve it from their active membership and overwrite both IDs.
            if (auth()->user()?->role === 'family' && ! auth()->user()->isAdministrator()) {
                $account = app(FamilyAccountContext::class)->account(auth()->user());
            }

            if (! $account && $model->family_account_id) {
                $account = FamilyAccount::query()->find($model->family_account_id);
            }

            if (! $account && $model->family_user_id) {
                $account = FamilyAccount::query()
                    ->where('owner_user_id', $model->family_user_id)
                    ->where('status', FamilyAccount::STATUS_ACTIVE)
                    ->first();

                if (! $account) {
                    $membership = FamilyAccountMember::query()
                        ->with('familyAccount')
                        ->where('user_id', $model->family_user_id)
                        ->where('status', FamilyAccountMember::STATUS_ACTIVE)
                        ->whereHas('familyAccount', fn (Builder $query) => $query
                            ->where('status', FamilyAccount::STATUS_ACTIVE))
                        ->first();
                    $account = $membership?->familyAccount;
                }

                if (! $account) {
                    $familyUser = User::query()->find($model->family_user_id);
                    if ($familyUser?->role === 'family') {
                        $account = app(FamilyAccountContext::class)->account($familyUser);
                    }
                }
            }

            if ($account) {
                $model->family_account_id = $account->id;
                $model->family_user_id = $account->owner_user_id;
            }
        });
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function scopeForFamilyAccount(Builder $query, FamilyAccount|int $account): Builder
    {
        $accountId = $account instanceof FamilyAccount ? $account->id : $account;

        return $query->where($query->qualifyColumn('family_account_id'), $accountId);
    }
}
