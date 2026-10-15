<?php

namespace App\Services\Marketplace;

use App\Jobs\SendCareRequestRecruitment;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestRecruitment;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationChannels;
use App\Services\Notifications\NotificationPreferenceResolver;
use App\Support\CaregiverPrelaunch;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class CareRequestRecruitmentService
{
    public const BATCH_LIMIT = 100;

    public function unavailableReason(CareRequest $request): ?string
    {
        if ($request->is_private) {
            return 'Private jobs require an invitation from the family. Use family invitations for this request.';
        }
        if (CaregiverPrelaunch::enabled()) {
            return 'Recruitment emails are unavailable while caregiver applications are in pre-launch mode.';
        }
        if ($request->is_system_generated) {
            return 'Recruitment emails are unavailable for automatically generated visits.';
        }
        if ($request->status !== CareRequest::STATUS_OPEN || ! $request->isAcceptingApplications()
            || ($request->isRecurring() && $request->recurring_ends_on?->copy()->endOfDay()->isPast())) {
            return 'This job is no longer accepting applications.';
        }
        if ($request->applications()->where('status', CareRequestApplication::STATUS_HIRED)->exists()) {
            return 'A caregiver has already been hired for this job.';
        }

        return null;
    }

    /** @return Builder<User> */
    public function candidates(CareRequest $request, string $search = ''): Builder
    {
        $query = $this->eligibleCaregivers($request)
            ->whereNotExists(fn ($recruitments) => $recruitments->selectRaw('1')
                ->from('care_request_recruitments')
                ->whereColumn('caregiver_user_id', 'users.id')
                ->where('care_request_id', $request->id)
                ->where('status', '!=', CareRequestRecruitment::STATUS_QUEUE_FAILED))
            ->whereNotExists(fn ($deliveries) => $deliveries->selectRaw('1')
                ->from('marketplace_notification_deliveries')
                ->whereColumn('user_id', 'users.id')
                ->where('event_key', MarketplaceEvent::CAREGIVER_RECRUITMENT)
                ->where('channel', NotificationChannels::EMAIL)
                ->where('notifiable_type', $request->getMorphClass())
                ->where('notifiable_id', $request->id));

        if ($this->unavailableReason($request)) {
            return $query->whereRaw('1 = 0');
        }

        $term = trim(str_replace(['%', '_'], '', mb_substr($search, 0, 100)));
        if ($term !== '') {
            $query->where(fn (Builder $match) => $match->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('city', 'like', '%'.$term.'%')
                ->orWhereHas('caregiverProfile', fn (Builder $profile) => $profile->where('service_area_zip', 'like', '%'.$term.'%')));
        }

        return $query
            ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM caregiver_profiles WHERE caregiver_profiles.user_id = users.id AND service_area_zip = ?) THEN 0 ELSE 1 END', [$request->zip])
            ->orderByRaw('CASE WHEN city = ? AND state = ? THEN 0 ELSE 1 END', [$request->city, $request->state])
            ->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [$request->state])
            ->orderBy('name')->orderBy('id');
    }

    /** @param array<int, int|string> $ids
     * @return array{queued:int,skipped:int}
     */
    public function enqueue(User $admin, CareRequest $request, array $ids): array
    {
        $this->authorize($admin);
        Validator::make(['caregiverIds' => $ids], [
            'caregiverIds' => ['required', 'array', 'min:1', 'max:'.self::BATCH_LIMIT],
            'caregiverIds.*' => ['required', 'integer', 'min:1'],
        ])->validate();
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $recruitments = DB::transaction(function () use ($admin, $request, $ids): array {
            $this->authorize($admin);
            $lockedRequest = CareRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($reason = $this->unavailableReason($lockedRequest)) {
                throw ValidationException::withMessages(['recruitment' => $reason]);
            }

            $recruitments = [];
            foreach ($this->candidates($lockedRequest)->whereIn('users.id', $ids)->get() as $caregiver) {
                if (! $this->canReceiveRecruitment($caregiver)) {
                    continue;
                }

                $recruitment = CareRequestRecruitment::query()->firstOrCreate([
                    'care_request_id' => $lockedRequest->id,
                    'caregiver_user_id' => $caregiver->id,
                ], [
                    'invited_by_user_id' => $admin->id,
                    'status' => CareRequestRecruitment::STATUS_QUEUED,
                ]);
                if ($recruitment->wasRecentlyCreated) {
                    $recruitments[] = $recruitment;

                    continue;
                }

                $recruitment = CareRequestRecruitment::query()->lockForUpdate()->findOrFail($recruitment->id);
                if ($recruitment->status === CareRequestRecruitment::STATUS_QUEUE_FAILED
                    && ! MarketplaceNotificationDelivery::query()
                        ->where('user_id', $caregiver->id)
                        ->where('dedupe_key', 'care-request-recruitment-'.$recruitment->id.':email')
                        ->exists()) {
                    $recruitment->update([
                        'invited_by_user_id' => $admin->id,
                        'status' => CareRequestRecruitment::STATUS_QUEUED,
                        'status_reason' => null,
                    ]);
                    $recruitments[] = $recruitment;
                }
            }

            return $recruitments;
        }, 3);

        $queued = 0;
        foreach ($recruitments as $recruitment) {
            try {
                // A sync/deferred driver would send mail during the admin's web request.
                $connection = (string) config('queue.default', 'database');
                if (in_array(config('queue.connections.'.$connection.'.driver'), ['sync', 'deferred', 'background', 'failover', 'null'], true)) {
                    $connection = 'database';
                }
                SendCareRequestRecruitment::dispatch($recruitment->id)->onConnection($connection)->afterCommit();
                $queued++;
            } catch (Throwable $exception) {
                // A remote queue may accept the job before its client reports an error.
                // Preserve worker progress and reuse the same row/dedupe key on safe retries.
                $markedFailed = CareRequestRecruitment::query()->whereKey($recruitment->id)
                    ->where('status', CareRequestRecruitment::STATUS_QUEUED)
                    ->update([
                        'status' => CareRequestRecruitment::STATUS_QUEUE_FAILED,
                        'status_reason' => 'The email could not be queued. Check the queue connection and select this caregiver again.',
                    ]);
                if (! $markedFailed && in_array($recruitment->fresh()?->status, [
                    CareRequestRecruitment::STATUS_SENDING, CareRequestRecruitment::STATUS_SENT,
                ], true)) {
                    $queued++;
                }
                report($exception);
            }
        }

        return ['queued' => $queued, 'skipped' => count($ids) - $queued];
    }

    public function send(CareRequestRecruitment $recruitment): void
    {
        $claimed = DB::transaction(function () use ($recruitment): ?CareRequestRecruitment {
            $current = CareRequestRecruitment::query()->lockForUpdate()->find($recruitment->id);
            if (! $current || $current->status !== CareRequestRecruitment::STATUS_QUEUED) {
                return null;
            }
            $request = CareRequest::query()->find($current->care_request_id);
            $caregiver = $request ? $this->eligibleCaregivers($request)->find($current->caregiver_user_id) : null;
            $reason = ! $current->inviter?->isAdministrator()
                ? 'The inviting user no longer has administrator access.'
                : (! $request ? 'This job is no longer available.' : $this->unavailableReason($request));
            if (! $reason && (! $caregiver || ! $this->canReceiveRecruitment($caregiver))) {
                $reason = 'The caregiver is no longer eligible, opted out, or already applied or received a family invitation.';
            }
            if ($reason) {
                $current->update(['status' => CareRequestRecruitment::STATUS_SKIPPED, 'status_reason' => $reason]);

                return null;
            }
            $current->update(['status' => CareRequestRecruitment::STATUS_SENDING]);
            $current->setRelation('careRequest', $request);
            $current->setRelation('caregiver', $caregiver);

            return $current;
        }, 3);

        if (! $claimed) {
            return;
        }

        $dedupeKey = 'care-request-recruitment-'.$claimed->id;
        try {
            $content = app(CareRequestRecruitmentEmail::class)->content($claimed->caregiver, $claimed->careRequest);
            app(MarketplaceNotificationService::class)->notify(
                recipients: $claimed->caregiver,
                eventKey: MarketplaceEvent::CAREGIVER_RECRUITMENT,
                title: $content['title'],
                body: $content['body'],
                url: route('care-requests.apply', $claimed->care_request_id),
                payload: array_merge($content['payload'], [
                    'care_request_id' => $claimed->care_request_id,
                    'recruitment_id' => $claimed->id,
                    'invited_by_user_id' => $claimed->invited_by_user_id,
                ]),
                subject: $claimed->careRequest,
                dedupeKey: $dedupeKey,
                channelOverrides: ['in_app' => false, 'sms' => false, 'push' => false],
            );
            $delivery = MarketplaceNotificationDelivery::query()
                ->where('user_id', $claimed->caregiver_user_id)
                ->where('event_key', MarketplaceEvent::CAREGIVER_RECRUITMENT)
                ->where('dedupe_key', $dedupeKey.':email')
                ->first();
            $claimed->update([
                'status' => $delivery?->status === 'sent' ? CareRequestRecruitment::STATUS_SENT
                    : ($delivery ? CareRequestRecruitment::STATUS_FAILED : CareRequestRecruitment::STATUS_SKIPPED),
                'sent_at' => $delivery?->status === 'sent' ? $delivery->sent_at : null,
                'status_reason' => $delivery?->status === 'sent' ? null
                    : ($delivery ? 'The email transport did not accept the message. Check the delivery logs.' : 'Email delivery is disabled by notification preferences.'),
            ]);
        } catch (Throwable $exception) {
            $claimed->update([
                'status' => CareRequestRecruitment::STATUS_FAILED,
                'status_reason' => 'The email could not be sent. Check the delivery logs.',
            ]);
            report($exception);
        }
    }

    private function authorize(User $admin): void
    {
        if (! User::query()->whereKey($admin->id)->where('role', 'admin')->exists()) {
            throw new AuthorizationException('Only administrators can recruit caregivers.');
        }
    }

    private function canReceiveRecruitment(User $caregiver): bool
    {
        $preferences = app(NotificationPreferenceResolver::class);

        return filter_var($caregiver->email, FILTER_VALIDATE_EMAIL) !== false
            && $caregiver->caregiverProfile?->isMarketplaceReady()
            && (float) $caregiver->caregiverProfile->resolvePlatformHourlyRate() > 0
            && $preferences->resolve($caregiver, MarketplaceEvent::INVITATION_RECEIVED)[NotificationChannels::EMAIL]
            && $preferences->resolve($caregiver, MarketplaceEvent::CAREGIVER_RECRUITMENT)[NotificationChannels::EMAIL];
    }

    /** @return Builder<User> */
    private function eligibleCaregivers(CareRequest $request): Builder
    {
        return User::query()->where('role', 'caregiver')
            ->where('email', 'like', '_%@_%._%')->where('email', 'not like', '% %')
            ->with(['caregiverProfile.skills', 'caregiverProfile.languages', 'caregiverProfile.availabilities'])
            ->whereHas('caregiverProfile', fn (Builder $profile) => $profile->discoverable()
                ->where('is_accepting_new_clients', true)
                ->whereRaw("TRIM(COALESCE(bio, '')) <> ''")
                ->whereNotNull('years_experience')
                ->whereRaw("TRIM(COALESCE(service_area_zip, '')) <> ''")
                ->whereNotNull('service_radius_miles')
                ->where(fn (Builder $identity) => $identity->whereNotNull('identity_verified_at')->orWhere('identity_verification_status', 'approved'))
                ->whereHas('skills')->whereHas('languages')->whereHas('availabilities'))
            ->whereDoesntHave('notificationPreferences', fn (Builder $preferences) => $preferences
                ->whereIn('event_key', [MarketplaceEvent::INVITATION_RECEIVED, MarketplaceEvent::CAREGIVER_RECRUITMENT])
                ->where('email_enabled', false))
            ->whereDoesntHave('careRequestApplications', fn (Builder $applications) => $applications->where('care_request_id', $request->id))
            ->whereDoesntHave('receivedCareRequestInvitations', fn (Builder $invitations) => $invitations->where('care_request_id', $request->id));
    }
}
