<?php

namespace App\Services\Admin;

use App\Models\FamilyAcquisitionSetting;
use App\Models\User;
use App\Services\Messaging\TwilioSmsClient;
use App\Support\StaffAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffOffboardingService
{
    public function preview(User $admin, int $userId, string $expectedEmail, array $aliases = []): array
    {
        $admin = $admin->fresh();
        abort_unless($admin?->isAdministrator() && StaffAccess::allowsUser($admin), 403);
        $user = User::findOrFail($userId);
        $this->validateTarget($admin, $user, $expectedEmail);
        $emails = array_values(array_unique(array_map(StaffAccess::email(...), [$user->email, ...$aliases])));
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)
                || User::whereKeyNot($userId)->whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()
                || DB::table('staff_access_blocks')->where('kind', 'email')->where('value', $email)
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $userId))->exists()) {
                throw ValidationException::withMessages(['email' => 'An alias is invalid or belongs to another account. Review it before offboarding.']);
            }
        }
        // Include previously recorded aliases on an idempotent repeat.
        $emails = array_values(array_unique([...$emails, ...DB::table('staff_access_blocks')
            ->where('user_id', $userId)->where('kind', 'email')->pluck('value')->all()]));
        $phones = array_values(array_unique(array_filter(array_map(TwilioSmsClient::normalizePhone(...), [
            (string) $user->phone, (string) $user->sms_opt_in_phone,
        ]))));
        if ($phones !== [] && User::whereKeyNot($userId)->where(fn ($query) => $query
            ->whereNotNull('phone')->orWhereNotNull('sms_opt_in_phone'))
            ->get(['id', 'phone', 'sms_opt_in_phone'])->contains(fn ($other) => array_intersect($phones, array_map(TwilioSmsClient::normalizePhone(...), [(string) $other->phone, (string) $other->sms_opt_in_phone])) !== [])) {
            throw ValidationException::withMessages(['phone' => 'A recorded phone is shared with another account. Resolve that ownership before blocking delivery.']);
        }
        if (filled($user->notification_email) && ! in_array(StaffAccess::email($user->notification_email), $emails, true)) {
            throw ValidationException::withMessages(['email' => 'Review the notification email and include it as a confirmed alias before offboarding.']);
        }

        return [
            'user_id' => $userId, 'name' => $user->name, 'email' => $user->email,
            'already_revoked' => $user->access_revoked_at !== null,
            'blocked_emails' => $emails, 'blocked_phones' => $phones,
            'lead_assignments' => DB::table('leads')->where('assigned_admin_id', $userId)->count(),
            'caregiver_assignments' => DB::table('caregiver_crm_records')->where('assigned_admin_id', $userId)->count(),
            'support_assignments' => DB::table('support_tickets')->where('assigned_admin_id', $userId)->count(),
            'sessions' => DB::table('sessions')->where('user_id', $userId)->count(),
            'content_api_tokens' => DB::table('content_api_tokens')->where('actor_user_id', $userId)->whereNull('revoked_at')->count(),
            'oauth_access_tokens' => DB::table('content_mcp_oauth_access_tokens')->where('user_id', $userId)->whereNull('revoked_at')->count(),
            'oauth_refresh_tokens' => DB::table('content_mcp_oauth_refresh_tokens')->where('user_id', $userId)->whereNull('revoked_at')->count(),
            'pending_notifications' => DB::table('marketplace_notification_deliveries')->where('user_id', $userId)
                ->whereIn('status', ['pending', 'queued', 'sending', 'failed'])->count(),
            'assignment_action' => 'Return all owned records to the unassigned queues; keep follow-up dates and history.',
        ];
    }

    public function offboard(User $admin, int $userId, string $expectedEmail, array $aliases, string $reason): array
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Record a reason of at least 10 characters.']);
        }

        return DB::transaction(function () use ($admin, $userId, $expectedEmail, $aliases, $reason) {
            User::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $report = $this->preview($admin, $userId, $expectedEmail, $aliases);
            $now = now();

            foreach (['email' => $report['blocked_emails'], 'phone' => $report['blocked_phones']] as $kind => $values) {
                foreach ($values as $value) {
                    $existing = DB::table('staff_access_blocks')->where(compact('kind', 'value'))->lockForUpdate()->first();
                    if ($existing && (int) $existing->user_id !== $userId) {
                        throw ValidationException::withMessages(['identity' => 'A contact is already blocked for a different user.']);
                    }
                    if (! $existing) {
                        DB::table('staff_access_blocks')->insert([
                            'kind' => $kind, 'value' => $value, 'user_id' => $userId,
                            'blocked_by_user_id' => $admin->id, 'reason' => trim($reason),
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }
            }

            $user->forceFill([
                'role' => 'offboarded', 'content_role' => null,
                'access_revoked_at' => $user->access_revoked_at ?? $now,
                'password' => Str::random(96), 'remember_token' => null,
                'phone' => null, 'notification_email' => null,
                'sms_opt_in' => false, 'sms_opted_in_at' => null, 'sms_opt_in_phone' => null,
            ])->save();

            DB::table('sessions')->where('user_id', $userId)->delete();
            DB::table('password_reset_tokens')->whereIn(DB::raw('LOWER(TRIM(email))'), $report['blocked_emails'])->delete();
            DB::table('content_api_tokens')->where('actor_user_id', $userId)->whereNull('revoked_at')
                ->update(['revoked_at' => $now, 'revoked_by_user_id' => $admin->id, 'updated_at' => $now]);
            foreach (['content_mcp_oauth_access_tokens', 'content_mcp_oauth_refresh_tokens'] as $table) {
                DB::table($table)->where('user_id', $userId)->whereNull('revoked_at')
                    ->update(['revoked_at' => $now, 'updated_at' => $now]);
            }
            DB::table('content_mcp_oauth_authorization_codes')->where('user_id', $userId)->whereNull('used_at')
                ->update(['used_at' => $now, 'updated_at' => $now]);
            DB::table('marketplace_notification_deliveries')->where('user_id', $userId)
                ->whereIn('status', ['pending', 'queued', 'sending', 'failed'])
                ->update(['status' => 'suppressed', 'sent_at' => null, 'updated_at' => $now]);
            DB::table('user_notification_preferences')->where('user_id', $userId)
                ->update(['email_enabled' => false, 'sms_enabled' => false, 'push_enabled' => false, 'in_app_enabled' => false, 'updated_at' => $now]);

            foreach (FamilyAcquisitionSetting::query()->lockForUpdate()->get() as $settings) {
                foreach (['new_lead_alert_emails', 'escalation_alert_emails'] as $field) {
                    $settings->$field = implode("\n", array_diff(FamilyAcquisitionSetting::parseEmails($settings->$field), $report['blocked_emails'])) ?: null;
                }
                if (in_array(StaffAccess::email((string) $settings->welcome_email_reply_to), $report['blocked_emails'], true)) {
                    $settings->welcome_email_reply_to = null;
                }
                $settings->updated_by_user_id = $admin->id;
                $settings->save();
            }

            $metadata = json_encode(['previous_owner_id' => $userId, 'reason' => trim($reason)], JSON_THROW_ON_ERROR);
            DB::table('leads')->where('assigned_admin_id', $userId)->orderBy('id')->lockForUpdate()
                ->chunkById(200, function ($leads) use ($admin, $now, $metadata) {
                    DB::table('lead_activities')->insert($leads->map(fn ($lead) => [
                        'lead_id' => $lead->id, 'actor_user_id' => $admin->id, 'type' => 'staff_offboarding',
                        'summary' => 'Returned to the unassigned queue after staff offboarding',
                        'metadata' => $metadata, 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ])->all());
                    DB::table('leads')->whereIn('id', $leads->pluck('id'))->update(['assigned_admin_id' => null, 'updated_at' => $now]);
                });
            DB::table('caregiver_crm_records')->where('assigned_admin_id', $userId)->orderBy('id')->lockForUpdate()
                ->chunkById(200, function ($records) use ($admin, $now, $metadata) {
                    DB::table('caregiver_crm_activities')->insert($records->map(fn ($record) => [
                        'caregiver_user_id' => $record->caregiver_user_id, 'actor_user_id' => $admin->id,
                        'type' => 'staff_offboarding', 'body' => 'Returned to the unassigned queue after staff offboarding.',
                        'metadata' => $metadata, 'occurred_at' => $now, 'created_at' => $now,
                    ])->all());
                    DB::table('caregiver_crm_records')->whereIn('id', $records->pluck('id'))->update([
                        'assigned_admin_id' => null, 'version' => DB::raw('version + 1'), 'updated_at' => $now,
                    ]);
                });
            DB::table('support_tickets')->where('assigned_admin_id', $userId)->orderBy('id')->lockForUpdate()
                ->chunkById(200, function ($tickets) use ($admin, $now, $metadata) {
                    DB::table('support_ticket_activities')->insert($tickets->map(fn ($ticket) => [
                        'support_ticket_id' => $ticket->id, 'actor_user_id' => $admin->id,
                        'action' => 'staff_offboarding', 'metadata' => $metadata, 'created_at' => $now,
                    ])->all());
                    DB::table('support_tickets')->whereIn('id', $tickets->pluck('id'))
                        ->update(['assigned_admin_id' => null, 'claimed_at' => null, 'updated_at' => $now]);
                });

            return $report + ['applied' => true, 'revoked_at' => $user->access_revoked_at->toIso8601String()];
        }, 3);
    }

    private function validateTarget(User $admin, User $user, string $expectedEmail): void
    {
        if ($admin->is($user) || ! in_array($user->role, ['sales', 'sdr', 'offboarded'], true)
            || StaffAccess::email($expectedEmail) !== StaffAccess::email($user->email)) {
            throw ValidationException::withMessages(['user' => 'Select the exact SDR/sales account and matching email; administrators cannot be offboarded by this operation.']);
        }
    }
}
