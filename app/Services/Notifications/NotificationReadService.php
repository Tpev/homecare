<?php

namespace App\Services\Notifications;

use App\Models\CareRequestConversation;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\MarketplaceEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class NotificationReadService
{
    public function synchronize(User $user): void
    {
        $notifications = $user->unreadNotifications()
            ->whereIn('data->event_key', [MarketplaceEvent::MESSAGE_RECEIVED, MarketplaceEvent::SUPPORT_TICKET_REPLY])
            ->get(['data']);

        foreach ($notifications->pluck('data.payload.conversation_id')->filter()->unique() as $id) {
            $thread = CareRequestConversation::query()->find($id);
            if ($thread && $thread->isParticipant($user) && ($readAt = $thread->lastReadAtFor($user))) {
                $this->markRelated($user, 'conversation_id', (int) $id, Carbon::parse($readAt));
            }
        }

        foreach ($notifications->pluck('data.payload.support_ticket_id')->filter()->unique() as $id) {
            $ticket = SupportTicket::query()->find($id);
            if (! $ticket || ! Gate::forUser($user)->allows('view', $ticket)) {
                continue;
            }
            // Admin read timestamps are shared, but notification read state is personal.
            if ($user->isAdministrator()) {
                continue;
            }
            $readAt = $user->role === 'family' && $ticket->family_account_id
                ? $ticket->familyReads()->where('user_id', $user->id)->value('last_read_at')
                : $ticket->opener_last_read_at;
            if ($readAt) {
                $this->markRelated($user, 'support_ticket_id', (int) $id, Carbon::parse($readAt));
            }
        }
    }

    public function markRelated(User $user, string $payloadKey, int $subjectId, CarbonInterface $readAt): int
    {
        $cleared = $user->unreadNotifications()
            ->where('data->payload->'.$payloadKey, $subjectId)
            ->where('created_at', '<=', $readAt)
            ->update(['read_at' => $readAt]);

        $user->notificationDeliveries()
            ->where('channel', 'email')
            ->where('status', 'pending')
            ->where('payload->'.$payloadKey, $subjectId)
            ->where('created_at', '<=', $readAt)
            ->update(['status' => 'suppressed']);

        return $cleared;
    }
}
