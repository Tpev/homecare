<?php

namespace App\Livewire\Messaging;

use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Inbox extends Component
{
    public ?int $activeConversationId = null;
    public string $messageBody = '';
    public string $search = '';
    public int $messagesLimit = 50;

    public function mount(?int $conversation = null): void
    {
        $user = auth()->user();
        abort_unless($user && in_array($user->role, ['family', 'caregiver'], true), 403);

        if ($conversation) {
            $this->setActiveConversation($conversation);
            return;
        }

        $this->activeConversationId = $this->baseConversationQuery($user)
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->value('id');

        if ($this->activeConversationId) {
            $this->markActiveAsRead();
        }
    }

    public function openConversation(int $conversationId): void
    {
        $this->setActiveConversation($conversationId);
        $this->redirect(route('messages.show', $conversationId, false), navigate: true);
    }

    public function refreshThread(): void
    {
        $this->markActiveAsRead();
        $this->dispatch('thread-refreshed');
    }

    public function loadMore(): void
    {
        $this->messagesLimit += 30;
    }

    public function sendMessage(): void
    {
        $this->validate([
            'messageBody' => ['required', 'string', 'min:1', 'max:3000'],
        ]);

        $conversation = $this->activeConversation;
        abort_if(! $conversation, 404);
        abort_unless(auth()->user()->can('sendMessage', $conversation), 403);

        DB::transaction(function () use ($conversation) {
            CareRequestMessage::query()->create([
                'care_request_conversation_id' => $conversation->id,
                'sender_user_id' => auth()->id(),
                'body' => trim($this->messageBody),
            ]);

            $payload = [
                'last_message_at' => now(),
                'last_message_sender_id' => auth()->id(),
            ];

            if (auth()->user()->role === 'family') {
                $payload['family_last_read_at'] = now();
            } else {
                $payload['caregiver_last_read_at'] = now();
            }

            $conversation->forceFill($payload)->save();
        });

        $recipient = auth()->user()->role === 'family'
            ? $conversation->caregiver
            : $conversation->family;

        if ($recipient) {
            app(MarketplaceNotificationService::class)->notify(
                recipients: $recipient,
                eventKey: MarketplaceEvent::MESSAGE_RECEIVED,
                title: 'New message',
                body: auth()->user()->name.' sent you a new message.',
                url: route('messages.show', $conversation->id),
                payload: ['conversation_id' => $conversation->id],
                subject: $conversation,
                dedupeKey: 'message:conversation-'.$conversation->id.'-message-'.($conversation->messages()->max('id') ?? now()->timestamp)
            );
        }

        FunnelTracker::track('message_sent', auth()->user(), $conversation, [
            'conversation_id' => $conversation->id,
        ]);

        $this->reset('messageBody');
        $this->markActiveAsRead();
        $this->dispatch('message-sent');
    }

    public function getConversationsProperty(): Collection
    {
        $user = auth()->user();

        $conversations = $this->baseConversationQuery($user)
            ->when($this->search !== '', function (Builder $query) {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term) {
                    $inner->whereHas('careRequest', fn (Builder $q) => $q->where('title', 'like', $term))
                        ->orWhereHas('family', fn (Builder $q) => $q->where('name', 'like', $term))
                        ->orWhereHas('caregiver', fn (Builder $q) => $q->where('name', 'like', $term));
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return $conversations->map(function (CareRequestConversation $conversation) use ($user) {
            $readAt = $user->role === 'family'
                ? $conversation->family_last_read_at
                : $conversation->caregiver_last_read_at;

            $conversation->is_unread_for_current_user = $conversation->last_message_at
                && (int) $conversation->last_message_sender_id !== (int) $user->id
                && (! $readAt || $conversation->last_message_at->gt($readAt));

            return $conversation;
        });
    }

    public function getActiveConversationProperty(): ?CareRequestConversation
    {
        if (! $this->activeConversationId) {
            return null;
        }

        return $this->baseConversationQuery(auth()->user())
            ->whereKey($this->activeConversationId)
            ->first();
    }

    public function getMessagesProperty(): Collection
    {
        $active = $this->activeConversation;
        if (! $active) {
            return collect();
        }

        return CareRequestMessage::query()
            ->with(['sender:id,name,role'])
            ->where('care_request_conversation_id', $active->id)
            ->latest('created_at')
            ->limit($this->messagesLimit)
            ->get()
            ->sortBy('created_at')
            ->values();
    }

    private function setActiveConversation(int $conversationId): void
    {
        $conversation = $this->baseConversationQuery(auth()->user())
            ->whereKey($conversationId)
            ->firstOrFail();

        $this->authorize('view', $conversation);
        $this->activeConversationId = $conversation->id;
        $this->markActiveAsRead();
    }

    private function markActiveAsRead(): void
    {
        $conversation = $this->activeConversation;
        if (! $conversation) {
            return;
        }

        $currentUser = auth()->user();
        $this->authorize('view', $conversation);

        if ($currentUser->role === 'family') {
            if ((int) $conversation->last_message_sender_id === (int) $currentUser->id) {
                return;
            }
            if ($conversation->family_last_read_at && $conversation->last_message_at && $conversation->family_last_read_at->gte($conversation->last_message_at)) {
                return;
            }
            $conversation->forceFill(['family_last_read_at' => now()])->save();
        } elseif ($currentUser->role === 'caregiver') {
            if ((int) $conversation->last_message_sender_id === (int) $currentUser->id) {
                return;
            }
            if ($conversation->caregiver_last_read_at && $conversation->last_message_at && $conversation->caregiver_last_read_at->gte($conversation->last_message_at)) {
                return;
            }
            $conversation->forceFill(['caregiver_last_read_at' => now()])->save();
        }
    }

    private function baseConversationQuery($user): Builder
    {
        return CareRequestConversation::query()
            ->forUser($user)
            ->with([
                'careRequest:id,title,status,city,state,requested_start_at',
                'family:id,name',
                'caregiver:id,name',
                'application:id,care_request_id,caregiver_user_id,status',
            ]);
    }

    public function render()
    {
        return view('livewire.messaging.inbox');
    }
}
