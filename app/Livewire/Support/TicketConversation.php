<?php

namespace App\Livewire\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TicketConversation extends Component
{
    public int $ticketId;

    public string $messageBody = '';

    public string $clientMessageId = '';

    public int $messagesLimit = 40;

    public function mount(SupportTicket $ticket): void
    {
        $ticket->refresh();
        abort_unless(Gate::forUser(auth()->user())->allows('view', $ticket), 404);

        $this->ticketId = $ticket->id;
        $this->clientMessageId = (string) Str::uuid();
        $ticket->markReadFor(auth()->user());
    }

    public function sendMessage(SupportTicketMessagingService $messaging): void
    {
        $validated = $this->validate([
            'messageBody' => ['required', 'string', 'min:1', 'max:3000', 'regex:/\S/'],
            'clientMessageId' => ['required', 'uuid'],
        ]);

        $ticket = $this->ticket;
        $this->authorize('reply', $ticket);

        $messaging->sendUserReply(
            ticket: $ticket,
            user: auth()->user(),
            body: $validated['messageBody'],
            clientMessageId: $validated['clientMessageId'],
        );

        $this->messageBody = '';
        $this->clientMessageId = (string) Str::uuid();
        $this->resetValidation();
        $this->dispatch('support-message-sent');
    }

    public function loadMore(): void
    {
        $this->messagesLimit = min(250, $this->messagesLimit + 30);
    }

    public function refreshThread(): void
    {
        $this->ticket->markReadFor(auth()->user());
    }

    public function getTicketProperty(): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->with([
                'opener:id,name,email,role',
                'assignedAdmin:id,name,email,role',
                'careRequest:id,title,status',
                'careBooking:id,care_request_id',
            ])
            ->findOrFail($this->ticketId);

        abort_unless(Gate::forUser(auth()->user())->allows('view', $ticket), 404);

        return $ticket;
    }

    public function getMessagesProperty(): Collection
    {
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
        return SupportTicketMessage::query()
            ->visibleTo(auth()->user())
            ->where('support_ticket_id', $this->ticketId)
            ->count() > $this->messagesLimit;
    }

    public function render(): View
    {
        return view('livewire.support.ticket-conversation');
    }
}
