<?php

namespace App\Livewire\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Support\SupportChatService;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ChatWidget extends Component
{
    #[Locked]
    public ?int $ticketId = null;

    #[Locked]
    public ?string $originRoute = null;

    #[Locked]
    public ?string $originPath = null;

    public int $messagesLimit = 40;

    public function mount(?string $originRoute = null, ?string $originPath = null): void
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, ['family', 'caregiver'], true), 403);

        $this->originRoute = $originRoute;
        $this->originPath = $originPath;
        $this->ticketId = app(SupportChatService::class)->conversationFor($user)?->id;
    }

    public function openPanel(): void
    {
        $ticket = $this->ticket;
        if ($ticket) {
            $ticket->markReadFor(auth()->user());
        }
    }

    public function refreshWidget(bool $panelOpen = false): void
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, ['family', 'caregiver'], true), 403);

        if (! $this->ticketId) {
            $this->ticketId = app(SupportChatService::class)->conversationFor($user)?->id;
        }

        $ticket = $this->ticket;
        if (! $ticket) {
            $this->ticketId = null;

            return;
        }

        if ($panelOpen) {
            $ticket->markReadFor($user);
        }
    }

    public function sendMessage(string $body = '', string $clientMessageId = ''): void
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, ['family', 'caregiver'], true), 403);

        try {
            if (! Str::isUuid($clientMessageId)) {
                throw ValidationException::withMessages([
                    'clientMessageId' => 'The message could not be sent. Refresh and try again.',
                ]);
            }

            if ($this->ticketId) {
                $ticket = $this->ticket;
                abort_unless($ticket, 404);
                abort_unless(Gate::forUser($user)->allows('reply', $ticket), 403);

                if ($ticket->initial_client_message_id !== $clientMessageId) {
                    app(SupportTicketMessagingService::class)->sendUserReply(
                        ticket: $ticket,
                        user: $user,
                        body: $body,
                        clientMessageId: $clientMessageId,
                    );
                }
            } else {
                $ticket = app(SupportChatService::class)->startConversation(
                    user: $user,
                    body: $body,
                    clientMessageId: $clientMessageId,
                    originRoute: $this->originRoute,
                    originPath: $this->originPath,
                );
                $this->ticketId = $ticket->id;
            }

            $ticket->fresh()->markReadFor($user);
            $this->messagesLimit = 40;
            $this->resetValidation();
            $this->dispatch(
                'support-chat-message-sent',
                clientId: $clientMessageId,
                ticketId: $this->ticketId,
            );
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->addError('messageBody', $message);
            $this->dispatch(
                'support-chat-send-failed',
                clientId: $clientMessageId,
                message: $message,
            );
        }
    }

    public function startNewConversation(): void
    {
        $ticket = $this->ticket;
        if ($ticket && ! in_array($ticket->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
            $this->addError('conversation', 'Resolve the current conversation before starting another one.');

            return;
        }

        $this->ticketId = null;
        unset($this->ticket);
        $this->messagesLimit = 40;
        $this->resetValidation();
        $this->dispatch('support-chat-conversation-reset');
    }

    public function loadMore(): void
    {
        $this->messagesLimit = min(250, $this->messagesLimit + 30);
    }

    public function getTicketProperty(): ?SupportTicket
    {
        if (! $this->ticketId) {
            return null;
        }

        return SupportTicket::query()
            ->visibleTo(auth()->user())
            ->where('source', SupportTicket::SOURCE_CHAT_WIDGET)
            ->with([
                'opener:id,name,role',
                'assignedAdmin:id,name,role',
                'familyReads' => fn ($query) => $query->where('user_id', auth()->id()),
            ])
            ->find($this->ticketId);
    }

    /** @return Collection<int, SupportTicketMessage> */
    public function getMessagesProperty(): Collection
    {
        if (! $this->ticketId || ! $this->ticket) {
            return collect();
        }

        return SupportTicketMessage::query()
            ->visibleTo(auth()->user())
            ->with('sender:id,name,role')
            ->where('support_ticket_id', $this->ticketId)
            ->latest('created_at')
            ->latest('id')
            ->limit($this->messagesLimit)
            ->get()
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->values();
    }

    public function getHasOlderMessagesProperty(): bool
    {
        if (! $this->ticketId || ! $this->ticket) {
            return false;
        }

        return SupportTicketMessage::query()
            ->visibleTo(auth()->user())
            ->where('support_ticket_id', $this->ticketId)
            ->count() > $this->messagesLimit;
    }

    public function getUnreadCountProperty(): int
    {
        $user = auth()->user();
        if (! $user) {
            return 0;
        }

        return SupportTicket::query()
            ->visibleTo($user)
            ->where('source', SupportTicket::SOURCE_CHAT_WIDGET)
            ->whereNotNull('last_public_message_at')
            ->with(['familyReads' => fn ($query) => $query->where('user_id', $user->id)])
            ->get()
            ->filter(fn (SupportTicket $ticket): bool => $ticket->isUnreadFor($user))
            ->count();
    }

    public function render(): View
    {
        return view('livewire.support.chat-widget');
    }
}
