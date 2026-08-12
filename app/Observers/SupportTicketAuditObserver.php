<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;

class SupportTicketAuditObserver
{
    public function updated(SupportTicket $ticket): void
    {
        $tracked = collect(['status', 'assigned_admin_id', 'claimed_at', 'resolved_at'])
            ->filter(fn (string $field): bool => $ticket->wasChanged($field));

        if ($tracked->isEmpty()) {
            return;
        }

        $action = match (true) {
            $ticket->wasChanged('assigned_admin_id')
                && $ticket->getOriginal('assigned_admin_id') === null
                && $ticket->assigned_admin_id !== null => 'conversation_claimed',
            $ticket->wasChanged('assigned_admin_id') => 'assignment_changed',
            $ticket->wasChanged('status') => 'status_changed',
            default => 'resolution_changed',
        };

        SupportTicketActivity::query()->create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'metadata' => $tracked->mapWithKeys(fn (string $field): array => [
                $field => [
                    'from' => $this->serializeValue($ticket->getOriginal($field)),
                    'to' => $this->serializeValue($ticket->getAttribute($field)),
                ],
            ])->all(),
            'created_at' => now(),
        ]);
    }

    private function serializeValue(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
    }
}
