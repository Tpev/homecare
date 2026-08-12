<?php

namespace App\Livewire\Admin;

use App\Models\SupportTicket;
use App\Services\Support\SupportChatService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SupportTicketsQueue extends Component
{
    use WithPagination;

    public string $status = 'open';

    public string $priority = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    public function updateStatus(int $ticketId, string $status): void
    {
        if (! in_array($status, [
            SupportTicket::STATUS_OPEN,
            SupportTicket::STATUS_IN_PROGRESS,
            SupportTicket::STATUS_RESOLVED,
            SupportTicket::STATUS_CLOSED,
        ], true)) {
            return;
        }

        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $this->authorize('manage', $ticket);
        $ticket->update([
            'status' => $status,
            'assigned_admin_id' => $ticket->assigned_admin_id ?: auth()->id(),
            'claimed_at' => $ticket->claimed_at ?: now(),
            'resolved_at' => match ($status) {
                SupportTicket::STATUS_RESOLVED => $ticket->resolved_at ?: now(),
                SupportTicket::STATUS_CLOSED => $ticket->resolved_at,
                default => null,
            },
        ]);

        session()->flash('status', 'Ticket updated.');
    }

    public function claimConversation(int $ticketId, SupportChatService $chat): void
    {
        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $this->authorize('manage', $ticket);

        try {
            $claimed = $chat->claim($ticket, auth()->user());
            session()->flash('status', 'Conversation claimed by '.$claimed->assignedAdmin?->name.'.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->addError('claim.'.$ticketId, collect($exception->errors())->flatten()->first());
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        $tickets = SupportTicket::query()
            ->with([
                'opener:id,name,email',
                'assignedAdmin:id,name,email',
                'lastPublicMessageSender:id,name,role',
                'careRequest:id,title',
                'careBooking:id',
                'latestPublicMessage.sender:id,name,role',
            ])
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->priority !== 'all', fn ($query) => $query->where('priority', $this->priority))
            ->orderByRaw('CASE WHEN last_public_message_at IS NOT NULL AND (admin_last_read_at IS NULL OR last_public_message_at > admin_last_read_at) THEN 0 ELSE 1 END')
            ->orderByRaw('COALESCE(last_public_message_at, created_at) DESC')
            ->paginate(20);

        $tickets->getCollection()->each(function (SupportTicket $ticket): void {
            $ticket->is_unread_for_admin = $ticket->isUnreadForAdmin();
        });

        return view('livewire.admin.support-tickets-queue', compact('tickets'));
    }
}
