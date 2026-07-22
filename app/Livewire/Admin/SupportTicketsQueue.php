<?php

namespace App\Livewire\Admin;

use App\Models\SupportTicket;
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
            'resolved_at' => match ($status) {
                SupportTicket::STATUS_RESOLVED => $ticket->resolved_at ?: now(),
                SupportTicket::STATUS_CLOSED => $ticket->resolved_at,
                default => null,
            },
        ]);

        session()->flash('status', 'Ticket updated.');
    }

    public function render()
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        $tickets = SupportTicket::query()
            ->with([
                'opener:id,name,email',
                'assignedAdmin:id,name,email',
                'careRequest:id,title',
                'careBooking:id',
                'latestPublicMessage.sender:id,name,role',
            ])
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->priority !== 'all', fn ($query) => $query->where('priority', $this->priority))
            ->orderByRaw('COALESCE(last_public_message_at, created_at) DESC')
            ->paginate(20);

        $tickets->getCollection()->each(function (SupportTicket $ticket): void {
            $ticket->is_unread_for_admin = $ticket->isUnreadForAdmin();
        });

        return view('livewire.admin.support-tickets-queue', compact('tickets'));
    }
}
