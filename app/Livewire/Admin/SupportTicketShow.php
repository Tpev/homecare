<?php

namespace App\Livewire\Admin;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SupportTicketShow extends Component
{
    public int $ticketId;

    public string $messageBody = '';

    public string $messageKind = SupportTicketMessage::KIND_PUBLIC;

    public string $clientMessageId = '';

    public string $status = SupportTicket::STATUS_OPEN;

    public string $assignedAdminId = '';

    public int $messagesLimit = 50;

    public function mount(SupportTicket $ticket): void
    {
        $ticket->refresh();
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $this->authorize('manage', $ticket);

        $this->ticketId = $ticket->id;
        $this->status = $ticket->status;
        $this->assignedAdminId = $ticket->assigned_admin_id ? (string) $ticket->assigned_admin_id : '';
        $this->clientMessageId = (string) Str::uuid();
        $ticket->markReadForAdmin();
    }

    public function sendMessage(SupportTicketMessagingService $messaging): void
    {
        $validated = $this->validate([
            'messageBody' => ['required', 'string', 'min:1', 'max:3000', 'regex:/\S/'],
            'messageKind' => ['required', Rule::in([
                SupportTicketMessage::KIND_PUBLIC,
                SupportTicketMessage::KIND_INTERNAL_NOTE,
            ])],
            'clientMessageId' => ['required', 'uuid'],
        ]);

        $ticket = $this->ticket;

        if ($validated['messageKind'] === SupportTicketMessage::KIND_INTERNAL_NOTE) {
            $this->authorize('addInternalNote', $ticket);
            $messaging->addInternalNote(
                ticket: $ticket,
                admin: auth()->user(),
                body: $validated['messageBody'],
                clientMessageId: $validated['clientMessageId'],
            );
        } else {
            $this->authorize('replyAsAdmin', $ticket);
            $messaging->sendAdminReply(
                ticket: $ticket,
                admin: auth()->user(),
                body: $validated['messageBody'],
                clientMessageId: $validated['clientMessageId'],
            );
        }

        $this->messageBody = '';
        $this->clientMessageId = (string) Str::uuid();
        $this->syncControlsFromTicket();
        $this->resetValidation();
        $this->dispatch('support-message-sent');
    }

    public function updateStatus(): void
    {
        $validated = $this->validate([
            'status' => ['required', Rule::in([
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_RESOLVED,
                SupportTicket::STATUS_CLOSED,
            ])],
        ]);

        $ticket = $this->ticket;
        $this->authorize('manage', $ticket);

        $ticket->forceFill([
            'status' => $validated['status'],
            'assigned_admin_id' => $ticket->assigned_admin_id ?: auth()->id(),
            'resolved_at' => match ($validated['status']) {
                SupportTicket::STATUS_RESOLVED => $ticket->resolved_at ?: now(),
                SupportTicket::STATUS_CLOSED => $ticket->resolved_at,
                default => null,
            },
        ])->save();

        $this->syncControlsFromTicket();
        session()->flash('status', 'Ticket status updated.');
    }

    public function updateAssignment(): void
    {
        $validated = $this->validate([
            'assignedAdminId' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->where('role', 'admin')
                        ->orWhereRaw('lower(email) = ?', ['test@test.com']);
                }),
            ],
        ]);

        $ticket = $this->ticket;
        $this->authorize('manage', $ticket);
        $ticket->forceFill([
            'assigned_admin_id' => filled($validated['assignedAdminId'])
                ? (int) $validated['assignedAdminId']
                : null,
        ])->save();

        $this->syncControlsFromTicket();
        session()->flash('status', 'Ticket assignment updated.');
    }

    public function loadMore(): void
    {
        $this->messagesLimit = min(300, $this->messagesLimit + 40);
    }

    public function refreshThread(): void
    {
        $this->ticket->markReadForAdmin();
    }

    public function getTicketProperty(): SupportTicket
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        $ticket = SupportTicket::query()
            ->with([
                'opener:id,name,email,phone,role',
                'counterparty:id,name,email,phone,role',
                'assignedAdmin:id,name,email,role',
                'careRequest:id,title,status',
                'careBooking:id,care_request_id,status',
            ])
            ->findOrFail($this->ticketId);

        $this->authorize('manage', $ticket);

        return $ticket;
    }

    public function getMessagesProperty(): Collection
    {
        return SupportTicketMessage::query()
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
            ->where('support_ticket_id', $this->ticketId)
            ->count() > $this->messagesLimit;
    }

    /** @return array<int, string> */
    public function getAdminOptionsProperty(): array
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->where('role', 'admin')
                    ->orWhereRaw('lower(email) = ?', ['test@test.com']);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.admin.support-ticket-show');
    }

    private function syncControlsFromTicket(): void
    {
        $ticket = SupportTicket::query()->findOrFail($this->ticketId);
        $this->status = $ticket->status;
        $this->assignedAdminId = $ticket->assigned_admin_id ? (string) $ticket->assigned_admin_id : '';
    }
}
