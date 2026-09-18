<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserDeletionService
{
    public function delete(User $admin, int $userId): void
    {
        abort_unless($admin->isAdministrator(), 403);

        DB::transaction(function () use ($admin, $userId): void {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            if ($user->is($admin)) {
                $this->blocked('You cannot delete your own admin account.');
            }
            if (in_array($user->role, ['admin', 'sales', 'sdr'], true)) {
                $this->blocked('Staff users cannot be deleted from this screen.');
            }

            // Lock parents before inspecting children so new dependent records
            // cannot race the eligibility checks.
            $accountIds = DB::table('family_accounts')->where('owner_user_id', $userId)
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $this->protectSharedAccounts($userId, $accountIds);

            $requestIds = $this->matching('care_requests', [
                'family_user_id' => [$userId], 'family_account_id' => $accountIds,
            ])->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $profileIds = $this->matching('care_recipient_profiles', [
                'legacy_family_user_id' => [$userId], 'family_account_id' => $accountIds,
            ])->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $versionIds = DB::table('care_recipient_profile_versions')->whereIn('care_recipient_profile_id', $profileIds)
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();

            $this->protectCareHistory($userId, $accountIds, $requestIds, $profileIds, $versionIds);

            // Only isolated setup data is eligible. FK enforcement stays enabled;
            // any unrecognised dependency rolls back the entire operation.
            DB::table('care_requests')->whereIn('id', $requestIds)->delete();
            DB::table('care_recipient_profile_versions')->whereIn('id', $versionIds)->delete();
            DB::table('care_recipient_profiles')->whereIn('id', $profileIds)->delete();
            foreach (['family_recipient_profiles', 'family_household_profiles', 'family_caregiver_favorites'] as $table) {
                $this->matching($table, ['family_user_id' => [$userId], 'family_account_id' => $accountIds])->delete();
            }
            DB::table('family_account_activity_logs')->whereIn('family_account_id', $accountIds)->delete();
            // Existing FK cascades remove owned memberships, invitations,
            // onboarding, welcome requests and delivery records.
            DB::table('family_accounts')->whereIn('id', $accountIds)->delete();
            DB::table('sessions')->where('user_id', $userId)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        }, 3);
    }

    private function protectSharedAccounts(int $userId, array $accountIds): void
    {
        DB::table('family_account_invitations')->whereIn('family_account_id', $accountIds)
            ->orderBy('id')->lockForUpdate()->pluck('id');
        $message = 'This user belongs to a shared family account or has family membership history. Nothing was deleted.';
        $this->rejectIf(DB::table('family_account_members')->where('user_id', $userId)
            ->whereNotIn('family_account_id', $accountIds), $message);
        $this->rejectIf(DB::table('family_account_members')->whereIn('family_account_id', $accountIds)
            ->where('user_id', '!=', $userId), $message);
        $this->rejectIf(DB::table('family_account_invitations')->where('invited_by_user_id', $userId)
            ->whereNotIn('family_account_id', $accountIds), $message);
        $this->rejectIf(DB::table('family_account_invitations')->whereIn('family_account_id', $accountIds)
            ->whereNotNull('accepted_at'), $message);

        // Legacy ownership must never make another family's data eligible.
        foreach ([
            'care_requests' => 'family_user_id',
            'care_recipient_profiles' => 'legacy_family_user_id',
            'family_recipient_profiles' => 'family_user_id',
            'family_household_profiles' => 'family_user_id',
            'family_caregiver_favorites' => 'family_user_id',
        ] as $table => $column) {
            $this->rejectIf(DB::table($table)->where($column, $userId)
                ->whereNotNull('family_account_id')->whereNotIn('family_account_id', $accountIds), $message);
        }

        $this->rejectIf(DB::table('family_account_activity_logs')->whereIn('family_account_id', $accountIds)
            ->whereNotIn('action', [
                'account_created', 'family_registration', 'account_created_compatibility',
                'existing_account_migrated', 'family_records_backfilled', 'care_profile_backfilled',
                'invitation_sent', 'invitation_resent', 'invitation_canceled', 'invitation_delivery_failed',
                'care_profile_created', 'care_profile_draft_updated', 'care_profile_ready',
                'care_profile_made_default', 'care_profile_archived', 'care_profile_restored', 'care_profile_attached',
            ]), 'This family account has activity history that must be retained. Nothing was deleted.');
    }

    private function protectCareHistory(int $userId, array $accountIds, array $requestIds, array $profileIds, array $versionIds): void
    {
        $family = ['family_user_id' => [$userId], 'family_account_id' => $accountIds];
        $participants = $family + ['caregiver_user_id' => [$userId]];
        $profiles = ['care_recipient_profile_id' => $profileIds];
        $versions = ['care_recipient_profile_version_id' => $versionIds];
        $requests = ['care_request_id' => $requestIds];
        $careMessage = 'This user has visits, care plans, or payment history. These records must be retained; nothing was deleted.';

        // Include legacy rows without a family account and every status, including
        // cancelled visits, refunded payments and ended plans.
        foreach ([
            'care_bookings' => $participants + $requests,
            'care_booking_payments' => $participants,
            'care_booking_payment_attempts' => ['family_account_id' => $accountIds],
            'care_pricing_agreements' => ['family_account_id' => $accountIds, 'caregiver_user_id' => [$userId], 'created_by_user_id' => [$userId]],
            'care_relationships' => $participants + $profiles,
            'care_plans' => $participants + $profiles + $versions,
            'continuous_coverage_plans' => $family + $profiles + $versions,
            'completed_extra_visit_requests' => $participants,
            'caregiver_payouts' => ['caregiver_user_id' => [$userId]],
            'caregiver_payout_items' => ['caregiver_user_id' => [$userId]],
            'care_booking_time_corrections' => $family + ['requester_user_id' => [$userId]],
            'care_booking_change_requests' => ['requester_user_id' => [$userId]],
            'care_booking_incidents' => ['reporter_user_id' => [$userId]],
            'care_reviews' => ['reviewer_user_id' => [$userId], 'reviewee_user_id' => [$userId]],
            'care_plan_schedule_changes' => ['requested_by_user_id' => [$userId]],
            'continuous_coverage_roster_members' => ['caregiver_user_id' => [$userId]],
            'continuous_coverage_shift_offers' => ['caregiver_user_id' => [$userId]],
            'continuous_coverage_lane_requests' => ['caregiver_user_id' => [$userId]],
            'continuous_coverage_handoffs' => ['caregiver_user_id' => [$userId]],
        ] as $table => $scope) {
            $this->rejectIf($this->matching($table, $scope), $careMessage);
        }

        foreach ([
            'care_request_applications' => ['caregiver_user_id' => [$userId]] + $requests,
            'care_request_conversations' => $participants + $requests,
            'care_request_invitations' => $participants + $requests,
            'care_request_messages' => ['sender_user_id' => [$userId]],
        ] as $table => $scope) {
            $this->rejectIf($this->matching($table, $scope),
                'This user has caregiver applications, invitations, or conversations. Nothing was deleted.');
        }
        $this->rejectIf($this->matching('support_tickets', [
            'family_account_id' => $accountIds, 'opener_user_id' => [$userId], 'counterparty_user_id' => [$userId],
        ]), 'This user has support tickets that must be retained. Nothing was deleted.');

        $this->rejectIf($this->matching('care_request_recipients', $profiles + $versions)
            ->whereNotIn('care_request_id', $requestIds), 'A care profile is used by another request. Nothing was deleted.');
        $this->rejectIf($this->matching('family_accounts', ['default_care_recipient_profile_id' => $profileIds])
            ->whereNotIn('id', $accountIds), 'A care profile is used by another family. Nothing was deleted.');

        $onboardingIds = DB::table('family_onboardings')->whereIn('family_account_id', $accountIds)
            ->orderBy('id')->lockForUpdate()->pluck('id')->all();
        $visits = DB::table('family_welcome_visits')->whereIn('family_onboarding_id', $onboardingIds)
            ->orderBy('id')->lockForUpdate()->get(['status', 'confirmed_start_at']);
        foreach ($visits as $visit) {
            if (! in_array($visit->status, ['requested', 'cancelled', 'unavailable'], true) || $visit->confirmed_start_at) {
                $this->blocked('This family has a welcome visit being handled by the team. Nothing was deleted.');
            }
        }
    }

    /** @param array<string, array<int, int>> $scope */
    private function matching(string $table, array $scope): Builder
    {
        return DB::table($table)->where(function (Builder $query) use ($scope): void {
            foreach ($scope as $column => $ids) {
                $query->orWhereIn($column, $ids);
            }
        });
    }

    private function rejectIf(Builder $query, string $message): void
    {
        if ($query->lockForUpdate()->first(['id'])) {
            $this->blocked($message);
        }
    }

    private function blocked(string $message): never
    {
        throw ValidationException::withMessages(['delete' => $message]);
    }
}
