<?php

namespace App\Services\Notifications;

use App\Models\CareRequestConversation;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UnreadMessageEmailService
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferences,
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    public function dispatchPending(): int
    {
        // An interrupted transport call has an unknown outcome. Do not resend it,
        // but do allow later messages through after the normal cooldown.
        MarketplaceNotificationDelivery::query()->where('channel', 'email')
            ->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(15))
            ->update(['status' => 'unconfirmed']);

        $groups = MarketplaceNotificationDelivery::query()
            ->where('channel', 'email')->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(config('notifications.message_email_delay_minutes', 5)))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('marketplace_notification_deliveries as recent')
                    ->whereColumn('recent.user_id', 'marketplace_notification_deliveries.user_id')
                    ->whereColumn('recent.event_key', 'marketplace_notification_deliveries.event_key')
                    ->whereColumn('recent.notifiable_type', 'marketplace_notification_deliveries.notifiable_type')
                    ->whereColumn('recent.notifiable_id', 'marketplace_notification_deliveries.notifiable_id')
                    ->where('recent.channel', 'email')
                    ->where(function ($recent): void {
                        $recent->where('recent.status', 'sending')
                            ->orWhere(fn ($sent) => $sent->where('recent.status', 'sent')
                                ->where('recent.sent_at', '>', now()->subMinutes(config('notifications.message_email_cooldown_minutes', 30))))
                            ->orWhere(fn ($uncertain) => $uncertain->where('recent.status', 'unconfirmed')
                                ->where('recent.updated_at', '>', now()->subMinutes(config('notifications.message_email_cooldown_minutes', 30))));
                    });
            })
            ->select('user_id', 'event_key', 'notifiable_type', 'notifiable_id')
            ->selectRaw('MIN(id) as first_id')
            ->groupBy('user_id', 'event_key', 'notifiable_type', 'notifiable_id')
            ->orderBy('first_id')->limit(200)->get();

        $sent = 0;
        foreach ($groups as $group) {
            if (! NotificationDeliveryPolicy::isMessage($group->event_key)) {
                continue;
            }

            $delivery = DB::transaction(function () use ($group): ?MarketplaceNotificationDelivery {
                // Serialize claims for this recipient, including concurrent scheduler runs.
                $user = User::query()->lockForUpdate()->find($group->user_id);
                $pending = $this->threadQuery($group)->where('status', 'pending')->orderBy('id')->lockForUpdate()->get();
                if (! $user || $pending->isEmpty()) {
                    return null;
                }

                $latest = $pending->last();
                if (! $this->preferences->resolve($user, $group->event_key)[NotificationChannels::EMAIL]
                    || ! $this->isUnread($user, $latest)) {
                    $this->threadQuery($group)->whereIn('id', $pending->modelKeys())->update(['status' => 'suppressed']);

                    return null;
                }

                if ($this->threadQuery($group)->where(function (Builder $query): void {
                    $query->where('status', 'sending')
                        ->orWhere(fn (Builder $uncertain) => $uncertain->where('status', 'unconfirmed')
                            ->where('updated_at', '>', now()->subMinutes(config('notifications.message_email_cooldown_minutes', 30))))
                        ->orWhere(fn (Builder $sent) => $sent->where('status', 'sent')
                            ->where('sent_at', '>', now()->subMinutes(config('notifications.message_email_cooldown_minutes', 30))));
                })->exists()) {
                    return null;
                }

                $payload = $latest->payload;
                if ($pending->count() > 1) {
                    $payload['email']['body'] = 'You have '.$pending->count().' new messages in this conversation. Open it to read and reply.';
                }
                $latest->forceFill(['status' => 'sending', 'payload' => $payload])->save();
                $this->threadQuery($group)->whereIn('id', $pending->modelKeys())->where('id', '!=', $latest->id)
                    ->update(['status' => 'batched']);

                return $latest;
            });

            if ($delivery) {
                $user = User::query()->find($delivery->user_id);
                // Recheck after claiming: a user may have read or left the thread meanwhile.
                if (! $user || ! $this->isUnread($user, $delivery)
                    || ! $this->preferences->resolve($user, $delivery->event_key)[NotificationChannels::EMAIL]) {
                    $delivery->update(['status' => 'suppressed']);

                    continue;
                }
                $this->notifications->sendReservedEmail($user, $delivery);
                $sent += $delivery->status === 'sent' ? 1 : 0;
            }
        }

        return $sent;
    }

    private function threadQuery(MarketplaceNotificationDelivery $delivery): Builder
    {
        return MarketplaceNotificationDelivery::query()
            ->where('user_id', $delivery->user_id)->where('event_key', $delivery->event_key)
            ->where('channel', 'email')->where('notifiable_type', $delivery->notifiable_type)
            ->where('notifiable_id', $delivery->notifiable_id);
    }

    private function isUnread(User $user, MarketplaceNotificationDelivery $delivery): bool
    {
        if ($delivery->notifiable_type === (new CareRequestConversation)->getMorphClass()) {
            $thread = CareRequestConversation::query()->find($delivery->notifiable_id);
            if (! $thread || ! $thread->isParticipant($user)) {
                return false;
            }
            $readAt = $thread->lastReadAtFor($user);
        } elseif ($delivery->notifiable_type === (new SupportTicket)->getMorphClass()) {
            $thread = SupportTicket::query()->find($delivery->notifiable_id);
            if (! $thread || $thread->transcript_deleted_at || ! Gate::forUser($user)->allows('view', $thread)) {
                return false;
            }
            $readAt = $user->role === 'family' && $thread->family_account_id
                ? $thread->familyReads()->where('user_id', $user->id)->value('last_read_at')
                : ($user->isAdministrator() ? $thread->admin_last_read_at : $thread->opener_last_read_at);
        } else {
            // A missing/deleted conversation must never cause an unverified email.
            return false;
        }

        return ! $readAt || $delivery->created_at->gt($readAt);
    }
}
