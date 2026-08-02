<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationChannels;
use App\Services\Ops\OpsAlertService;
use App\Support\MarketplaceEvent;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SupportTicketObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly OpsAlertService $opsAlerts,
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    public function created(SupportTicket $ticket): void
    {
        $ticket = $ticket->fresh(['opener']) ?? $ticket;
        $this->opsAlerts->notifySupportTicketCreated($ticket);

        $admins = User::query()->where('role', 'admin')->get();
        if ($admins->isEmpty()) {
            return;
        }

        $this->notifications->notify(
            recipients: $admins,
            eventKey: MarketplaceEvent::SUPPORT_TICKET_CREATED,
            title: 'New '.str($ticket->priority ?: 'normal')->headline()->lower().' priority support request',
            body: ($ticket->opener?->name ?: 'A LoLo Care user').' opened support request #'.$ticket->id.'.',
            url: route('admin.support.tickets.show', $ticket),
            payload: ['support_ticket_id' => $ticket->id],
            subject: $ticket,
            dedupeKey: 'support-ticket-created:ticket-'.$ticket->id,
            channelOverrides: [NotificationChannels::EMAIL => false],
        );
    }
}
