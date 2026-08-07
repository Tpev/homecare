<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SupportTicketMessagingService
{
    public function __construct(
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    public function sendUserReply(
        SupportTicket $ticket,
        User $user,
        string $body,
        string $clientMessageId,
    ): SupportTicketMessage {
        $body = $this->validatedBody($body);

        if (! Gate::forUser($user)->allows('view', $ticket)) {
            throw new AuthorizationException;
        }

        $this->ensureTicketIsOpenForReplies($ticket);

        if (! Gate::forUser($user)->allows('reply', $ticket)) {
            throw new AuthorizationException;
        }

        [$message, $created, $assignedAdmin] = DB::transaction(function () use ($ticket, $user, $body, $clientMessageId): array {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureTicketIsOpenForReplies($lockedTicket);

            $message = SupportTicketMessage::query()->firstOrCreate(
                [
                    'support_ticket_id' => $lockedTicket->id,
                    'client_message_id' => $clientMessageId,
                ],
                [
                    'sender_user_id' => $user->id,
                    'kind' => SupportTicketMessage::KIND_PUBLIC,
                    'body' => trim($body),
                ]
            );

            if (! $message->wasRecentlyCreated) {
                return [$message, false, null];
            }

            $updates = [
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => $user->id,
                'opener_last_read_at' => $message->created_at,
                'admin_last_read_at' => null,
            ];

            if ($lockedTicket->status === SupportTicket::STATUS_RESOLVED) {
                $updates['status'] = SupportTicket::STATUS_OPEN;
                $updates['resolved_at'] = null;
            }

            $lockedTicket->forceFill($updates)->save();

            return [$message, true, $lockedTicket->assignedAdmin()->first()];
        });

        if ($created && $assignedAdmin) {
            $this->notifications->notify(
                recipients: $assignedAdmin,
                eventKey: MarketplaceEvent::SUPPORT_TICKET_REPLY,
                title: 'New support ticket reply',
                body: $user->name.' replied to support request #'.$ticket->id.'. Open the conversation to review the full message.',
                url: route('admin.support.tickets.show', $ticket->id),
                payload: ['support_ticket_id' => $ticket->id, 'support_ticket_message_id' => $message->id],
                subject: $ticket,
                dedupeKey: 'support-ticket-'.$ticket->id.'-message-'.$message->id,
            );
        }

        if ($created) {
            $ticket->fresh()->markReadFor($user);
        }

        return $message;
    }

    public function sendAdminReply(
        SupportTicket $ticket,
        User $admin,
        string $body,
        string $clientMessageId,
    ): SupportTicketMessage {
        $body = $this->validatedBody($body);

        if (! $admin->isAdministrator()) {
            throw new AuthorizationException;
        }

        [$message, $created, $opener] = DB::transaction(function () use ($ticket, $admin, $body, $clientMessageId): array {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureTicketIsOpenForReplies($lockedTicket);

            $message = SupportTicketMessage::query()->firstOrCreate(
                [
                    'support_ticket_id' => $lockedTicket->id,
                    'client_message_id' => $clientMessageId,
                ],
                [
                    'sender_user_id' => $admin->id,
                    'kind' => SupportTicketMessage::KIND_PUBLIC,
                    'body' => trim($body),
                ]
            );

            if (! $message->wasRecentlyCreated) {
                return [$message, false, null];
            }

            $updates = [
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => $admin->id,
                'admin_last_read_at' => $message->created_at,
                'opener_last_read_at' => null,
            ];

            if (! $lockedTicket->assigned_admin_id) {
                $updates['assigned_admin_id'] = $admin->id;
            }

            if ($lockedTicket->status === SupportTicket::STATUS_OPEN) {
                $updates['status'] = SupportTicket::STATUS_IN_PROGRESS;
            }

            $lockedTicket->forceFill($updates)->save();

            return [$message, true, $lockedTicket->opener()->first()];
        });

        if ($created && $opener) {
            $this->notifications->notify(
                recipients: $opener,
                eventKey: MarketplaceEvent::SUPPORT_TICKET_REPLY,
                title: 'LoLo Care replied to your support request',
                body: 'Our support team replied to request #'.$ticket->id.': '.$ticket->subject.'.',
                url: route('support.tickets.show', $ticket->id),
                payload: ['support_ticket_id' => $ticket->id, 'support_ticket_message_id' => $message->id],
                subject: $ticket,
                dedupeKey: 'support-ticket-'.$ticket->id.'-message-'.$message->id,
            );
        }

        return $message;
    }

    public function addInternalNote(
        SupportTicket $ticket,
        User $admin,
        string $body,
        string $clientMessageId,
    ): SupportTicketMessage {
        $body = $this->validatedBody($body);

        if (! $admin->isAdministrator()) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($ticket, $admin, $body, $clientMessageId): SupportTicketMessage {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureTicketIsOpenForReplies($lockedTicket);

            return SupportTicketMessage::query()->firstOrCreate(
                [
                    'support_ticket_id' => $lockedTicket->id,
                    'client_message_id' => $clientMessageId,
                ],
                [
                    'sender_user_id' => $admin->id,
                    'kind' => SupportTicketMessage::KIND_INTERNAL_NOTE,
                    'body' => trim($body),
                ]
            );
        });
    }

    private function ensureTicketIsOpenForReplies(SupportTicket $ticket): void
    {
        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'messageBody' => 'Closed tickets are read-only. Reopen the ticket before replying.',
            ]);
        }
    }

    private function validatedBody(string $body): string
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > 3000) {
            throw ValidationException::withMessages([
                'messageBody' => 'Enter a message between 1 and 3,000 characters.',
            ]);
        }

        return $body;
    }
}
