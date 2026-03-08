<?php

namespace App\Livewire\Admin;

use App\Models\SupportTicket;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SupportTicketsQueue extends Component
{
    use WithPagination;

    public string $status = 'open';
    public string $priority = 'all';
    public string $adminNote = '';

    public function updateStatus(int $ticketId, string $status): void
    {
        $this->validate([
            'adminNote' => ['nullable', 'string', 'max:3000'],
        ]);

        if (! in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            return;
        }

        $ticket = SupportTicket::query()->findOrFail($ticketId);
        $ticket->update([
            'status' => $status,
            'admin_note' => trim($this->adminNote) ?: $ticket->admin_note,
            'assigned_admin_id' => auth()->id(),
            'resolved_at' => $status === 'resolved' ? now() : $ticket->resolved_at,
        ]);

        $this->adminNote = '';
        session()->flash('status', 'Ticket updated.');
    }

    public function render()
    {
        $tickets = SupportTicket::query()
            ->with(['opener:id,name,email', 'careRequest:id,title', 'careBooking:id'])
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->priority !== 'all', fn ($query) => $query->where('priority', $this->priority))
            ->latest()
            ->paginate(20);

        return view('livewire.admin.support-tickets-queue', compact('tickets'));
    }
}
