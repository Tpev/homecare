<?php

namespace App\Observers;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;

class SupportTicketAuditObserver
{
    public function updated(SupportTicket $ticket): void
    {
        $tracked = collect([
            'status', 'assigned_admin_id', 'claimed_at', 'resolved_at', 'responder_mode',
            'transferred_to_human_at', 'returned_to_automation_at', 'handoff_reason_code',
            'retention_started_at', 'transcript_delete_after',
        ])
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

        if ($ticket->wasChanged('retention_started_at') || $ticket->wasChanged('transcript_delete_after')) {
            $ticket->aiInteractionEvents()->update([
                'retention_started_at' => $ticket->retention_started_at,
                'delete_after' => $ticket->retention_started_at?->copy()->addMonths(
                    (int) config('ai_support.interaction_event_months', 24)
                ),
            ]);
        }
    }

    private function serializeValue(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
    }
}
