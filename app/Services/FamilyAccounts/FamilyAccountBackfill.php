<?php

namespace App\Services\FamilyAccounts;

use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyAccountMember;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyAccountBackfill
{
    /** @var list<string> */
    public const FAMILY_OWNED_TABLES = [
        'care_requests',
        'care_request_invitations',
        'care_request_conversations',
        'care_bookings',
        'family_caregiver_favorites',
        'care_booking_payments',
        'family_recipient_profiles',
        'family_household_profiles',
        'care_relationships',
        'care_plans',
        'care_booking_time_corrections',
        'continuous_coverage_plans',
        'completed_extra_visit_requests',
    ];

    public function __construct(private readonly FamilyAccountProvisioner $provisioner) {}

    /** @return array{users:int,records:int,support_tickets:int} */
    public function run(?callable $progress = null): array
    {
        $summary = ['users' => 0, 'records' => 0, 'support_tickets' => 0];

        User::query()
            ->where('role', 'family')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$summary, $progress): void {
                foreach ($users as $user) {
                    if ($user->isAdministrator()) {
                        continue;
                    }

                    $existingMembership = FamilyAccountMember::query()
                        ->where('user_id', $user->id)
                        ->where('status', FamilyAccountMember::STATUS_ACTIVE)
                        ->first();

                    // Once family sharing is live, a rerun must not turn an invited
                    // member back into an owner or restore somebody who left/was removed.
                    if ($existingMembership && ! $existingMembership->isOwner()) {
                        continue;
                    }

                    if (! $existingMembership && FamilyAccountMember::query()->where('user_id', $user->id)->exists()) {
                        continue;
                    }

                    $account = $existingMembership?->familyAccount
                        ?? $this->provisioner->provisionOwner($user, 'existing_account_migrated');
                    $mapped = 0;

                    foreach (self::FAMILY_OWNED_TABLES as $table) {
                        $mapped += DB::table($table)
                            ->where('family_user_id', $user->id)
                            ->where(function ($query) use ($account) {
                                $query->whereNull('family_account_id')
                                    ->orWhere('family_account_id', '!=', $account->id);
                            })
                            ->update(['family_account_id' => $account->id]);
                    }

                    DB::table('care_requests')
                        ->where('family_account_id', $account->id)
                        ->whereNull('created_by_user_id')
                        ->update(['created_by_user_id' => $user->id]);
                    DB::table('care_request_invitations')
                        ->where('family_account_id', $account->id)
                        ->whereNull('invited_by_user_id')
                        ->update(['invited_by_user_id' => $user->id]);
                    DB::table('care_bookings')
                        ->where('family_account_id', $account->id)
                        ->whereNotNull('family_confirmed_at')
                        ->whereNull('family_confirmed_by_user_id')
                        ->update(['family_confirmed_by_user_id' => $user->id]);
                    DB::table('care_booking_payments')
                        ->where('family_account_id', $account->id)
                        ->whereNull('initiated_by_user_id')
                        ->update(['initiated_by_user_id' => $user->id]);

                    $supportMapped = SupportTicket::query()
                        ->whereNull('family_account_id')
                        ->where(function ($query) use ($user) {
                            $query->where('opener_user_id', $user->id)
                                ->orWhereHas('careRequest', fn ($requestQuery) => $requestQuery->where('family_user_id', $user->id))
                                ->orWhereHas('careBooking', fn ($bookingQuery) => $bookingQuery->where('family_user_id', $user->id));
                        })
                        ->update([
                            'family_account_id' => $account->id,
                            'family_visibility' => DB::raw("CASE WHEN category IN ('billing', 'account', 'account_access') THEN 'owner_only' ELSE 'shared_care' END"),
                        ]);

                    $this->seedLegacyReadState($user, (int) $account->id);

                    FamilyAccountActivityLog::query()->firstOrCreate(
                        [
                            'family_account_id' => $account->id,
                            'actor_user_id' => null,
                            'action' => 'family_records_backfilled',
                            'subject_user_id' => $user->id,
                        ],
                        ['metadata' => ['records_mapped' => $mapped, 'support_tickets_mapped' => $supportMapped]]
                    );

                    $summary['users']++;
                    $summary['records'] += $mapped;
                    $summary['support_tickets'] += $supportMapped;
                    if ($progress) {
                        $progress($user, $account, $mapped, $supportMapped);
                    }
                }
            });

        return $summary;
    }

    private function seedLegacyReadState(User $owner, int $accountId): void
    {
        DB::table('care_request_conversations')
            ->where('family_account_id', $accountId)
            ->whereNotNull('family_last_read_at')
            ->select(['id', 'family_last_read_at'])
            ->orderBy('id')
            ->chunkById(500, function ($conversations) use ($owner): void {
                $now = now();
                DB::table('family_conversation_reads')->insertOrIgnore(
                    $conversations->map(fn ($conversation) => [
                        'care_request_conversation_id' => $conversation->id,
                        'user_id' => $owner->id,
                        'last_read_at' => $conversation->family_last_read_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });

        DB::table('support_tickets')
            ->where('family_account_id', $accountId)
            ->where('opener_user_id', $owner->id)
            ->whereNotNull('opener_last_read_at')
            ->select(['id', 'opener_last_read_at'])
            ->orderBy('id')
            ->chunkById(500, function ($tickets) use ($owner): void {
                $now = now();
                DB::table('family_support_ticket_reads')->insertOrIgnore(
                    $tickets->map(fn ($ticket) => [
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $owner->id,
                        'last_read_at' => $ticket->opener_last_read_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }
}
