<?php

namespace App\Livewire\Support;

use App\Models\AiSupportGoalJourney;
use App\Models\AiSupportGuidedTask;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\AiSupport\AiSupportEligibilityService;
use App\Services\AiSupport\AiSupportGuidedTaskService;
use App\Services\AiSupport\AiSupportHandoffService;
use App\Services\AiSupport\AiSupportPreparationService;
use App\Services\AiSupport\AiSupportRecapService;
use App\Services\AiSupport\AiSupportRequestDraftService;
use App\Services\AiSupport\AiSupportRuntimeService;
use App\Services\AiSupport\FamilyAssistantHomeService;
use App\Services\AiSupport\FamilyGoalJourneyService;
use App\Services\AiSupport\FamilyIntentJourneyService;
use App\Services\AiSupport\FamilyLifecycleActionService;
use App\Services\Support\SupportChatService;
use App\Services\Support\SupportTicketMessagingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    #[Locked]
    public bool $openOnLoad = false;

    #[Locked]
    public bool $startingNewConversation = false;

    public function mount(?string $originRoute = null, ?string $originPath = null): void
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, ['family', 'caregiver'], true), 403);

        $this->originRoute = $originRoute;
        $this->originPath = $originPath;
        $this->ticketId = app(SupportChatService::class)->conversationFor($user)?->id;
        $guidedTasks = app(AiSupportGuidedTaskService::class);
        $openFromSession = (bool) session()->pull(AiSupportGuidedTaskService::SESSION_OPEN_CHAT_KEY, false);
        $this->openOnLoad = $guidedTasks->claimCompletedResult($user) || $openFromSession;
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

        if (! $this->ticketId && ! $this->startingNewConversation) {
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

        if (app(AiSupportGuidedTaskService::class)->claimCompletedResult($user)) {
            $this->openOnLoad = true;
            $this->dispatch('support-chat-guidance-completed');
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

            $shouldRunAssistant = false;
            if ($this->ticketId) {
                $ticket = $this->ticket;
                abort_unless($ticket, 404);
                abort_unless(Gate::forUser($user)->allows('reply', $ticket), 403);

                if ($ticket->initial_client_message_id !== $clientMessageId) {
                    $sent = app(SupportTicketMessagingService::class)->sendUserReply(
                        ticket: $ticket,
                        user: $user,
                        body: $body,
                        clientMessageId: $clientMessageId,
                    );
                    $shouldRunAssistant = $sent->wasRecentlyCreated;
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
                $this->startingNewConversation = false;
                $shouldRunAssistant = $ticket->wasRecentlyCreated;
            }

            if ($shouldRunAssistant && $ticket->responder_mode === SupportTicket::RESPONDER_MODE_AUTOMATED) {
                app(AiSupportRuntimeService::class)->respond($user, $ticket, trim($body));
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

    public function chooseStartAction(string $message): void
    {
        if (trim($message) === '') {
            $this->dispatch('support-chat-focus-composer');

            return;
        }

        $this->sendMessage($message, (string) Str::uuid());
    }

    public function chooseIntent(string $actionId, string $intentId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        try {
            app(FamilyIntentJourneyService::class)->choose($user, $ticket, $actionId, $intentId);
            $this->resetValidation('intent');
            $this->dispatch('support-chat-action-completed');
        } catch (ValidationException $exception) {
            $this->addError('intent', (string) collect($exception->errors())->flatten()->first());
        }
    }

    public function chooseCarePath(string $actionId, string $path): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        try {
            $result = app(FamilyGoalJourneyService::class)->chooseCarePath($user, $ticket, $actionId, $path);
            if ($result['result'] === 'human') {
                app(AiSupportHandoffService::class)->transfer($user, $ticket, 'user_requested');
            } elseif (filled($result['continue_message'])) {
                app(AiSupportRuntimeService::class)->respond($user, $ticket->fresh(), (string) $result['continue_message']);
            }
            $this->resetValidation('path');
            $this->dispatch('support-chat-action-completed');
        } catch (ValidationException $exception) {
            $this->addError('path', (string) collect($exception->errors())->flatten()->first());
        }
    }

    public function chooseJourney(string $actionId, string $choice): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        try {
            $result = app(FamilyGoalJourneyService::class)->chooseJourney($user, $ticket, $actionId, $choice);
            if ($result['result'] === 'human') {
                app(AiSupportHandoffService::class)->transfer($user, $ticket, 'user_requested');
            } elseif (filled($result['continue_message'])) {
                app(AiSupportRuntimeService::class)->respond($user, $ticket->fresh(), (string) $result['continue_message']);
            }
            $this->resetValidation('journey');
            $this->dispatch('support-chat-action-completed');
        } catch (ValidationException $exception) {
            $this->addError('journey', (string) collect($exception->errors())->flatten()->first());
        }
    }

    public function transferToPerson(): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        app(AiSupportHandoffService::class)->transfer($user, $ticket, 'user_requested');
        $this->dispatch('support-chat-action-completed');
    }

    public function cancelGoalJourney(string $journeyId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        $active = app(FamilyGoalJourneyService::class)->activeFor($user, $ticket);
        abort_unless($active && hash_equals($active->id, $journeyId), 404);
        app(FamilyGoalJourneyService::class)->cancelActive($user, $ticket);
        $this->createAutomatedMessage($ticket, 'I stopped this task. Nothing in the app was changed.');
        $this->dispatch('support-chat-action-completed');
    }

    public function startGuidedTask(string $actionId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);

        try {
            $url = app(AiSupportGuidedTaskService::class)->startFromAction($user, $ticket, $actionId);
            $this->resetValidation('guidedTask');
            $this->redirect($url, navigate: true);
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->addError('guidedTask', $message);
            $this->dispatch('support-chat-guidance-failed', message: $message);
        }
    }

    public function guidedTaskArrived(string $taskId, string $result): void
    {
        $user = auth()->user();
        abort_unless($user, 401);
        $task = app(AiSupportGuidedTaskService::class)->reportArrival($user, $taskId, $result);
        if ($task->state === AiSupportGuidedTask::STATE_FAILED) {
            $this->dispatch(
                'support-chat-guidance-failed',
                message: 'I could not safely highlight that control. Open the chat for the next step.',
            );
        }
    }

    public function cancelGuidedTask(string $taskId): void
    {
        $user = auth()->user();
        abort_unless($user, 401);
        app(AiSupportGuidedTaskService::class)->cancel($user, $taskId);
        $this->dispatch('support-chat-action-completed');
    }

    public function checkGuidedTask(string $taskId): void
    {
        $user = auth()->user();
        abort_unless($user, 401);
        app(AiSupportGuidedTaskService::class)->checkAgain($user, $taskId);
        $this->dispatch('support-chat-action-completed');
    }

    public function openPreparation(string $actionId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        try {
            $url = app(AiSupportPreparationService::class)->applyFromAction($user, $ticket, $actionId);
            $this->resetValidation('preparation');
            $this->redirect($url, navigate: true);
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->addError('preparation', $message);
        }
    }

    public function cancelPreparation(string $preparationId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        app(AiSupportPreparationService::class)->cancel($user, $preparationId, $ticket);
        $this->createAutomatedMessage($ticket, 'Prepared details discarded. Nothing was saved or sent.');
        $this->dispatch('support-chat-action-completed');
    }

    public function cancelActiveProfileChange(): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $user->role === 'family' && $ticket, 404);

        if (app(FamilyLifecycleActionService::class)->cancelActiveProfileDraft($user, $ticket)) {
            $this->createAutomatedMessage($ticket, 'The current profile change was discarded. Nothing was saved.');
        }
        $this->dispatch('support-chat-action-completed');
    }

    public function confirmCareRequest(string $actionId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        try {
            app(AiSupportRecapService::class)->confirm($user, $ticket, $actionId);
            app(FamilyGoalJourneyService::class)->markCompleted($user, $ticket, 'care_request_published');
            $this->resetValidation();
            $this->dispatch('support-chat-action-completed');
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->addError('confirmation', $message);
            $this->dispatch(
                'support-chat-confirmation-failed',
                actionId: $actionId,
                message: $message,
            );
        }
    }

    public function renewCareRequestRecap(string $actionId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        app(AiSupportRecapService::class)->renew($user, $ticket, $actionId);
        $this->resetValidation();
        $this->dispatch('support-chat-action-completed');
    }

    public function confirmDomainAction(string $actionId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        try {
            app(FamilyLifecycleActionService::class)->confirm($user, $ticket, $actionId);
            app(FamilyGoalJourneyService::class)->syncAfterVerifiedStep($user, $ticket, 'domain_action_verified');
            $this->resetValidation();
            $this->dispatch('support-chat-action-completed');
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            $this->addError('confirmation', $message);
            $this->dispatch('support-chat-confirmation-failed', actionId: $actionId, message: $message);
        }
    }

    public function renewDomainAction(string $actionId): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        try {
            app(FamilyLifecycleActionService::class)->renew($user, $ticket, $actionId);
            $this->resetValidation();
            $this->dispatch('support-chat-action-completed');
        } catch (ValidationException $exception) {
            $this->addError('confirmation', (string) collect($exception->errors())->flatten()->first());
        }
    }

    public function discardCareRequestDraft(): void
    {
        $user = auth()->user();
        $ticket = $this->ticket;
        abort_unless($user && $ticket, 404);
        app(AiSupportRequestDraftService::class)->discard($user, $ticket);
        app(FamilyGoalJourneyService::class)->cancelActive($user, $ticket, 'request_draft_discarded');
        $this->createAutomatedMessage($ticket, 'Your private request draft was discarded.');
        $this->dispatch('support-chat-action-completed');
    }

    public function startNewConversation(): void
    {
        $ticket = $this->ticket;
        if ($ticket && ! in_array($ticket->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
            $this->addError('conversation', 'Resolve the current conversation before starting another one.');

            return;
        }

        $this->ticketId = null;
        $this->startingNewConversation = true;
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
            ->with([
                'sender:id,name,role',
                'aiActions' => fn ($query) => $query->where('actor_user_id', auth()->id()),
            ])
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

    public function getActiveGuidedTaskProperty(): ?AiSupportGuidedTask
    {
        $user = auth()->user();

        return $user ? app(AiSupportGuidedTaskService::class)->foregroundFor($user) : null;
    }

    public function getActiveGoalJourneyProperty(): ?AiSupportGoalJourney
    {
        $user = auth()->user();
        $ticket = $this->ticket;

        return $user && $user->role === 'family' && $ticket
            ? app(FamilyGoalJourneyService::class)->activeFor($user, $ticket)
            : null;
    }

    /** @return array{id:string,goal:string,progress:string,instruction:string,state:string,canCancel:bool,hasGuidedTarget:bool}|null */
    public function getGoalJourneyClientProperty(): ?array
    {
        $user = auth()->user();
        $ticket = $this->ticket;

        return $user && $user->role === 'family' && $ticket
            ? app(FamilyGoalJourneyService::class)->clientPayload($user, $ticket)
            : null;
    }

    /** @return array{id:string,targetId:string,instruction:string,label:string,state:string}|null */
    public function getGuidedTaskClientProperty(): ?array
    {
        $user = auth()->user();

        return $user
            ? app(AiSupportGuidedTaskService::class)->clientPayload($user, $this->activeGuidedTask)
            : null;
    }

    /** @return array{personalized:list<array{label:string,message:string}>,general:list<array{label:string,message:?string}>}|null */
    public function getStartExperienceProperty(): ?array
    {
        $user = auth()->user();
        if (! $user || $user->role !== 'family'
            || ! app(AiSupportEligibilityService::class)->evaluate($user, 'support_answers_v1')->allowed) {
            return null;
        }

        return app(FamilyAssistantHomeService::class)->for($user);
    }

    /** @return array<string,mixed>|null */
    public function getActiveProfilePreparationProperty(): ?array
    {
        $user = auth()->user();
        $ticket = $this->ticket;

        return $user && $user->role === 'family' && $ticket
            ? app(FamilyLifecycleActionService::class)->activeProfileContext($user, $ticket)
            : null;
    }

    public function render(): View
    {
        return view('livewire.support.chat-widget');
    }

    private function createAutomatedMessage(SupportTicket $ticket, string $body): ?SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): ?SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
                || $locked->status === SupportTicket::STATUS_CLOSED
                || $locked->transcript_deleted_at) {
                return null;
            }
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id,
                'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => $body,
                'client_message_id' => (string) Str::uuid(),
            ]);
            $locked->forceFill([
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => null,
                'opener_last_read_at' => null,
            ])->save();

            return $message;
        }, 3);
    }
}
