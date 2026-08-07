<?php

namespace App\Console\Commands;

use App\Models\FamilyAccount;
use App\Models\FamilyAccountMember;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountBackfill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyFamilyAccounts extends Command
{
    protected $signature = 'homecare:verify-family-accounts';

    protected $description = 'Verify Family Account migration and access-boundary invariants.';

    public function handle(): int
    {
        $errors = [];

        $familyUsers = User::query()->where('role', 'family')->get()->reject->isAdministrator();
        $familyUsersWithMembershipHistory = User::query()
            ->where('role', 'family')
            ->whereHas('familyAccountMemberships')
            ->get()
            ->reject->isAdministrator();

        if ($familyUsers->count() !== $familyUsersWithMembershipHistory->count()) {
            $errors[] = "Family users ({$familyUsers->count()}) do not match users with Family Account membership history ({$familyUsersWithMembershipHistory->count()}).";
        }

        FamilyAccount::query()->where('status', FamilyAccount::STATUS_ACTIVE)->each(function (FamilyAccount $account) use (&$errors): void {
            $ownerMemberships = $account->memberships()
                ->where('status', FamilyAccountMember::STATUS_ACTIVE)
                ->where('access_level', FamilyAccountMember::ACCESS_OWNER)
                ->get();

            if ($ownerMemberships->count() !== 1 || (int) $ownerMemberships->first()?->user_id !== (int) $account->owner_user_id) {
                $errors[] = "Family account {$account->id} does not have exactly one matching active owner.";
            }
        });

        $duplicateActiveMemberships = FamilyAccountMember::query()
            ->select('user_id', DB::raw('COUNT(*) AS aggregate'))
            ->where('status', FamilyAccountMember::STATUS_ACTIVE)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('aggregate', 'user_id');

        foreach ($duplicateActiveMemberships as $userId => $count) {
            $errors[] = "User {$userId} has {$count} active Family Account memberships.";
        }

        $membershipsOnInactiveAccounts = FamilyAccountMember::query()
            ->where('status', FamilyAccountMember::STATUS_ACTIVE)
            ->whereHas('familyAccount', fn ($query) => $query->where('status', '!=', FamilyAccount::STATUS_ACTIVE))
            ->pluck('user_id');

        if ($membershipsOnInactiveAccounts->isNotEmpty()) {
            $errors[] = 'Users have active memberships on inactive Family Accounts: '.$membershipsOnInactiveAccounts->implode(', ').'.';
        }

        $ineligibleMembers = FamilyAccountMember::query()
            ->where('status', FamilyAccountMember::STATUS_ACTIVE)
            ->with('user')
            ->get()
            ->filter(fn (FamilyAccountMember $membership) => ! $membership->user
                || $membership->user->role !== 'family'
                || $membership->user->isAdministrator())
            ->pluck('user_id');

        if ($ineligibleMembers->isNotEmpty()) {
            $errors[] = 'Non-family users have active memberships: '.$ineligibleMembers->implode(', ').'.';
        }

        foreach (FamilyAccountBackfill::FAMILY_OWNED_TABLES as $table) {
            $missing = DB::table($table)->whereNull('family_account_id')->count();
            if ($missing > 0) {
                $errors[] = "{$table} has {$missing} records without a Family Account.";
            }

            $missingLegacyOwner = DB::table($table)->whereNull('family_user_id')->count();
            if ($missingLegacyOwner > 0) {
                $errors[] = "{$table} has {$missingLegacyOwner} records without a rollback-compatible legacy owner.";
            }

            $legacyMismatch = DB::table($table)
                ->join('family_accounts', $table.'.family_account_id', '=', 'family_accounts.id')
                ->whereColumn($table.'.family_user_id', '!=', 'family_accounts.owner_user_id')
                ->count();
            if ($legacyMismatch > 0) {
                $errors[] = "{$table} has {$legacyMismatch} records whose legacy owner does not match the Family Account owner.";
            }
        }

        $unmappedFamilySupport = SupportTicket::query()
            ->whereNull('family_account_id')
            ->where(function ($query): void {
                $query->whereHas('opener', fn ($opener) => $opener
                    ->where('role', 'family')
                    ->whereRaw('LOWER(email) != ?', ['test@test.com']))
                    ->orWhereHas('careRequest', fn ($request) => $request->whereNotNull('family_account_id'))
                    ->orWhereHas('careBooking', fn ($booking) => $booking->whereNotNull('family_account_id'));
            })
            ->count();
        if ($unmappedFamilySupport > 0) {
            $errors[] = "support_tickets has {$unmappedFamilySupport} family-care records without a Family Account.";
        }

        $supportWithoutVisibility = SupportTicket::query()
            ->whereNotNull('family_account_id')
            ->whereNull('family_visibility')
            ->count();
        if ($supportWithoutVisibility > 0) {
            $errors[] = "support_tickets has {$supportWithoutVisibility} mapped records without a visibility boundary.";
        }

        $supportWithInvalidVisibility = SupportTicket::query()
            ->whereNotNull('family_account_id')
            ->whereNotIn('family_visibility', ['shared_care', 'owner_only'])
            ->count();
        if ($supportWithInvalidVisibility > 0) {
            $errors[] = "support_tickets has {$supportWithInvalidVisibility} mapped records with an invalid visibility boundary.";
        }

        $supportRequestMismatch = DB::table('support_tickets')
            ->join('care_requests', 'support_tickets.care_request_id', '=', 'care_requests.id')
            ->whereNotNull('support_tickets.family_account_id')
            ->whereColumn('support_tickets.family_account_id', '!=', 'care_requests.family_account_id')
            ->count();
        $supportBookingMismatch = DB::table('support_tickets')
            ->join('care_bookings', 'support_tickets.care_booking_id', '=', 'care_bookings.id')
            ->whereNotNull('support_tickets.family_account_id')
            ->whereColumn('support_tickets.family_account_id', '!=', 'care_bookings.family_account_id')
            ->count();
        if ($supportRequestMismatch + $supportBookingMismatch > 0) {
            $errors[] = 'support_tickets has '.($supportRequestMismatch + $supportBookingMismatch).' records mapped to the wrong Family Account.';
        }

        $duplicateStripeCustomers = FamilyAccount::query()
            ->select('stripe_customer_id', DB::raw('COUNT(*) AS aggregate'))
            ->whereNotNull('stripe_customer_id')
            ->groupBy('stripe_customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('aggregate', 'stripe_customer_id');

        foreach ($duplicateStripeCustomers as $customerId => $count) {
            $errors[] = "Stripe customer {$customerId} belongs to {$count} Family Accounts.";
        }

        if ($errors !== []) {
            $this->error('Family Account verification failed.');
            foreach ($errors as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $this->info('Family Account verification passed.');
        $this->table(['Invariant', 'Value'], [
            ['Family users / membership histories', $familyUsers->count()],
            ['Active Family Accounts', FamilyAccount::query()->where('status', FamilyAccount::STATUS_ACTIVE)->count()],
            ['Active family members', FamilyAccountMember::query()->where('status', FamilyAccountMember::STATUS_ACTIVE)->count()],
            ['Mapped family-owned tables', count(FamilyAccountBackfill::FAMILY_OWNED_TABLES) + 1],
        ]);

        return self::SUCCESS;
    }
}
