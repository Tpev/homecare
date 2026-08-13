<?php

namespace App\Services\AiSupport;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class AiSupportContextContract
{
    public function __construct(private readonly NavigationTargetRegistry $navigation) {}

    /** @return array<string, mixed> */
    public function manifest(User $user, SupportTicket $ticket, ?string $screenTargetId = null): array
    {
        if (! Gate::forUser($user)->allows('view', $ticket)) {
            throw new AuthorizationException;
        }

        $screen = $screenTargetId && $this->navigation->allowedFor($user, $screenTargetId)
            ? $screenTargetId
            : null;

        return [
            'contract_version' => (string) config('ai_support.context_contract_version'),
            'authenticated_actor' => [
                'user_reference' => (string) $user->id,
                'role' => $user->role,
            ],
            'conversation' => [
                'support_ticket_reference' => (string) $ticket->id,
                'responder_mode' => $ticket->responder_mode,
                'origin_route_name' => $ticket->origin_route,
            ],
            'semantic_screen_target' => $screen,
            'canonical_message_references' => $ticket->publicMessages()
                ->latest('created_at')
                ->limit(12)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->reverse()
                ->values()
                ->all(),
            'persistence_rule' => 'manifest_and_assembled_content_are_memory_only',
        ];
    }

    /** @return array<string, list<string>> */
    public function allowlist(): array
    {
        return [
            'authenticated_actor' => ['user_reference', 'role'],
            'conversation' => ['support_ticket_reference', 'responder_mode', 'origin_route_name'],
            'semantic_screen_target' => ['registered_target_id'],
            'canonical_message_references' => ['message_ids_only'],
        ];
    }
}
