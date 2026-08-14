<?php

namespace App\Services\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\User;
use App\Services\AiSupport\AiSupportEligibilityService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportChatService
{
    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly SupportMessageRateLimiter $rateLimiter,
        private readonly AiSupportEligibilityService $aiEligibility,
    ) {}

    public function conversationFor(User $user): ?SupportTicket
    {
        $this->ensureChatUser($user);

        $query = SupportTicket::query()
            ->visibleTo($user)
            ->where('source', SupportTicket::SOURCE_CHAT_WIDGET)
            ->whereNull('transcript_deleted_at');

        $active = (clone $query)
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
            ->orderByRaw('COALESCE(last_public_message_at, created_at) DESC')
            ->first();

        if ($active) {
            return $active;
        }

        $resolved = (clone $query)
            ->where('status', SupportTicket::STATUS_RESOLVED)
            ->orderByRaw('COALESCE(last_public_message_at, created_at) DESC')
            ->first();

        if ($resolved) {
            return $resolved;
        }

        return (clone $query)
            ->where('status', SupportTicket::STATUS_CLOSED)
            ->orderByRaw('COALESCE(last_public_message_at, created_at) DESC')
            ->first();
    }

    public function startConversation(
        User $user,
        string $body,
        string $clientMessageId,
        ?string $originRoute,
        ?string $originPath,
    ): SupportTicket {
        $this->ensureChatUser($user);
        $body = $this->validatedBody($body);

        if (! Str::isUuid($clientMessageId)) {
            throw ValidationException::withMessages([
                'clientMessageId' => 'The message could not be sent. Refresh and try again.',
            ]);
        }

        $existing = SupportTicket::query()
            ->where('opener_user_id', $user->id)
            ->where('initial_client_message_id', $clientMessageId)
            ->first();

        if ($existing) {
            if (! Gate::forUser($user)->allows('view', $existing)) {
                throw new AuthorizationException;
            }

            return $existing;
        }

        $this->rateLimiter->ensureAllowed($user);
        [$safeRoute, $safePath] = $this->sanitizeOrigin($originRoute, $originPath);
        $membership = $user->role === 'family' ? $this->familyAccounts->membershipFor($user) : null;
        $account = $membership?->familyAccount;
        $responderMode = config('ai_support.provider_enabled', false)
            && $this->aiEligibility->evaluate($user, 'support_answers_v1')->allowed
                ? SupportTicket::RESPONDER_MODE_AUTOMATED
                : SupportTicket::RESPONDER_MODE_HUMAN_ONLY;

        try {
            return DB::transaction(function () use ($user, $body, $clientMessageId, $safeRoute, $safePath, $account, $responderMode): SupportTicket {
                $duplicate = SupportTicket::query()
                    ->where('opener_user_id', $user->id)
                    ->where('initial_client_message_id', $clientMessageId)
                    ->first();

                if ($duplicate) {
                    return $duplicate;
                }

                return SupportTicket::query()->create([
                    'family_account_id' => $account?->id,
                    'family_visibility' => $responderMode === SupportTicket::RESPONDER_MODE_AUTOMATED
                        ? 'opener_only'
                        : 'shared_care',
                    'source' => SupportTicket::SOURCE_CHAT_WIDGET,
                    'responder_mode' => $responderMode,
                    'origin_route' => $safeRoute,
                    'origin_path' => $safePath,
                    'opener_user_id' => $user->id,
                    'category' => 'general',
                    'status' => SupportTicket::STATUS_OPEN,
                    'priority' => 'normal',
                    'subject' => $this->subjectFor($body),
                    'description' => $body,
                    'initial_client_message_id' => $clientMessageId,
                ]);
            });
        } catch (QueryException $exception) {
            $duplicate = SupportTicket::query()
                ->where('opener_user_id', $user->id)
                ->where('initial_client_message_id', $clientMessageId)
                ->first();

            if ($duplicate) {
                return $duplicate;
            }

            throw $exception;
        }
    }

    public function claim(SupportTicket $ticket, User $admin): SupportTicket
    {
        if (! $admin->isAdministrator() || ! Gate::forUser($admin)->allows('manage', $ticket)) {
            throw new AuthorizationException;
        }

        $updated = SupportTicket::query()
            ->whereKey($ticket->id)
            ->whereNull('assigned_admin_id')
            ->update([
                'assigned_admin_id' => $admin->id,
                'claimed_at' => now(),
                'status' => $ticket->status === SupportTicket::STATUS_OPEN
                    ? SupportTicket::STATUS_IN_PROGRESS
                    : $ticket->status,
                'updated_at' => now(),
            ]);

        $fresh = SupportTicket::query()->with('assignedAdmin:id,name,email,role')->findOrFail($ticket->id);

        if ($updated === 1) {
            SupportTicketActivity::query()->create([
                'support_ticket_id' => $fresh->id,
                'actor_user_id' => $admin->id,
                'action' => 'conversation_claimed',
                'metadata' => [
                    'assigned_admin_id' => ['from' => null, 'to' => $admin->id],
                    'status' => ['from' => $ticket->status, 'to' => $fresh->status],
                    'claimed_at' => ['from' => null, 'to' => $fresh->claimed_at?->format(DATE_ATOM)],
                ],
                'created_at' => now(),
            ]);

            return $fresh;
        }

        if ((int) $fresh->assigned_admin_id === (int) $admin->id) {
            return $fresh;
        }

        throw ValidationException::withMessages([
            'claim' => 'Already claimed by '.($fresh->assignedAdmin?->name ?: 'another support team member').'.',
        ]);
    }

    /** @return array{?string, ?string} */
    public function sanitizeOrigin(?string $originRoute, ?string $originPath): array
    {
        $routeName = trim((string) $originRoute);
        if ($routeName === '' || mb_strlen($routeName) > 160 || ! Route::has($routeName)) {
            return [null, null];
        }

        $route = Route::getRoutes()->getByName($routeName);
        $path = parse_url((string) $originPath, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return [$routeName, null];
        }

        $path = preg_replace('/[\x00-\x1F\x7F]/u', '', $path) ?? '';
        if ($path === '' || mb_strlen($path) > 1024) {
            return [$routeName, null];
        }

        $containsSensitiveParameter = collect($route?->parameterNames() ?? [])
            ->contains(fn (string $parameter): bool => (bool) preg_match('/token|secret|signature|password|code|hash|key/i', $parameter));

        if ($containsSensitiveParameter && $route) {
            $path = '/'.ltrim($route->uri(), '/');
        }

        return [$routeName, $path];
    }

    private function ensureChatUser(User $user): void
    {
        if (! in_array($user->role, ['family', 'caregiver'], true) || $user->isAdministrator()) {
            throw new AuthorizationException;
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

    private function subjectFor(string $body): string
    {
        $subject = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $body) ?? '';
        $subject = preg_replace('/\s+/u', ' ', trim($subject)) ?? '';

        if ($subject === '') {
            return 'Chat support request';
        }

        return 'Chat: '.Str::limit($subject, 154, '');
    }
}
