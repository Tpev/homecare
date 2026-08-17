@php
    $ticket = $this->ticket;
    $messages = $this->messages;
    $unreadCount = $this->unreadCount;
    $isClosed = $ticket?->status === \App\Models\SupportTicket::STATUS_CLOSED;
    $isResolved = $ticket?->status === \App\Models\SupportTicket::STATUS_RESOLVED;
    $assistantActive = $ticket?->responder_mode === \App\Models\SupportTicket::RESPONDER_MODE_AUTOMATED;
    $firstName = str((string) auth()->user()?->name)->before(' ')->value() ?: 'there';
    $assignedFirstName = $ticket?->assignedAdmin?->name
        ? str($ticket->assignedAdmin->name)->before(' ')->value()
        : null;
    $guidedTaskClient = $this->guidedTaskClient;
@endphp

<div
    wire:key="support-chat-widget-{{ auth()->id() }}"
    data-testid="support-chat-widget"
    class="support-chat-root"
    x-data="supportChatWidget()"
    data-support-chat-user-id="{{ auth()->id() }}"
    data-initial-ticket-id="{{ $ticket?->id }}"
    data-initial-unread-count="{{ $unreadCount }}"
    data-force-open="{{ $openOnLoad ? 'true' : 'false' }}"
    data-guided-task='@json($guidedTaskClient)'
    x-on:support-chat-message-sent.window="messageSent($event.detail)"
    x-on:support-chat-send-failed.window="messageFailed($event.detail)"
    x-on:support-chat-conversation-reset.window="conversationReset()"
    x-on:support-chat-confirmation-failed.window="confirmationFailed($event.detail)"
    x-on:support-chat-action-completed.window="actionCompleted()"
    x-on:support-chat-guidance-failed.window="guidanceFailed($event.detail)"
    x-on:support-chat-guidance-completed.window="guidanceCompleted()"
    x-on:keydown.window="handleKeydown($event)"
    x-on:online.window="wentOnline()"
    x-on:offline.window="wentOffline()"
    x-cloak
>
    @if ($guidedTaskClient)
        <aside
            x-show="! open"
            data-testid="ai-guided-task-strip"
            class="ai-guide-strip"
            role="status"
            aria-live="polite"
        >
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#9C493A]">LoLo is guiding you</p>
                <p class="mt-1 text-sm font-semibold leading-5 text-[#17313F]">{{ $guidedTaskClient['instruction'] }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" x-on:click="showGuidedTarget()" class="ai-guide-strip-button">Show me</button>
                <button type="button" wire:click="cancelGuidedTask('{{ $guidedTaskClient['id'] }}')" class="ai-guide-strip-button">Stop</button>
                <button type="button" wire:click="transferToPerson" class="ai-guide-strip-button ai-guide-strip-button-primary">Talk to a person</button>
            </div>
        </aside>
    @endif

    <div
        x-show="open && isMobile"
        x-transition.opacity.duration.150ms
        class="support-chat-backdrop"
        aria-hidden="true"
        x-on:click="minimize()"
    ></div>

    <button
        x-ref="launcher"
        x-show="! open"
        x-transition.opacity.scale.90.duration.180ms
        type="button"
        data-testid="support-chat-launcher"
        class="support-chat-launcher"
        aria-haspopup="dialog"
        aria-controls="support-chat-panel"
        aria-label="{{ $unreadCount > 0 ? 'Chat with LoLo Support, '.$unreadCount.' unread '.str('message')->plural($unreadCount) : 'Chat with LoLo Support' }}"
        x-on:click="showPanel()"
    >
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 18.5 4 20l1.15-3.45A8 8 0 1 1 7.5 18.5Z" />
            <path stroke-linecap="round" d="M8 10h8M8 13.5h5" />
        </svg>
        <span class="hidden text-sm font-semibold sm:inline">Support</span>
        @if ($unreadCount > 0)
            <span
                data-testid="support-chat-unread"
                class="support-chat-unread-badge"
                aria-hidden="true"
            >{{ min($unreadCount, 99) }}</span>
        @endif
    </button>

    <section
        id="support-chat-panel"
        x-ref="panel"
        x-show="open"
        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
        x-transition:enter-start="translate-y-5 opacity-0 sm:translate-y-2 sm:scale-[0.98]"
        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
        x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave-end="translate-y-5 opacity-0 sm:translate-y-2 sm:scale-[0.98]"
        data-testid="support-chat-panel"
        class="support-chat-panel"
        role="dialog"
        aria-labelledby="support-chat-title"
        x-bind:aria-modal="isMobile ? 'true' : 'false'"
    >
        <header class="support-chat-header">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/12 text-white" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 18.5 4 20l1.15-3.45A8 8 0 1 1 7.5 18.5Z" />
                            <path stroke-linecap="round" d="M8 10h8M8 13.5h5" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h2 id="support-chat-title" class="truncate font-display text-lg font-semibold text-white">LoLo Support</h2>
                        <p class="truncate text-xs text-[#DDEEEA]">
                            {{ $assistantActive ? 'AI assistant - You can ask for a person anytime' : ($assignedFirstName ? $assignedFirstName.' from LoLo Support will reply here' : 'Leave us a message') }}
                        </p>
                    </div>
                </div>
            </div>
            <button
                type="button"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white"
                aria-label="Minimize support chat"
                x-on:click="minimize()"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" d="M6 12h12" />
                </svg>
            </button>
        </header>

        <div
            x-ref="messages"
            data-testid="support-chat-messages"
            class="support-chat-messages"
            role="log"
            aria-label="Conversation with LoLo Support"
            x-on:scroll.debounce.150ms="rememberScroll()"
        >
            @if ($this->hasOlderMessages)
                <div class="mb-4 text-center">
                    <button type="button" wire:click="loadMore" x-on:click="rememberScroll()" class="inline-flex min-h-11 items-center rounded-xl border border-[#D8D0C5] bg-white px-4 text-sm font-semibold text-[#0F5B52]">Load earlier messages</button>
                </div>
            @endif

            @if (! $ticket)
                <div class="mx-auto flex min-h-full max-w-xs flex-col items-center justify-center px-3 py-8 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#E7F2EE] text-[#0F5B52]" aria-hidden="true">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 18.5 4 20l1.15-3.45A8 8 0 1 1 7.5 18.5Z" />
                            <path stroke-linecap="round" d="M8 10h8M8 13.5h5" />
                        </svg>
                    </span>
                    <h3 class="mt-4 font-display text-xl font-semibold text-[#17313F]">Hi {{ $firstName }}. How can we help?</h3>
                    <p class="mt-2 text-sm leading-6 text-[#68727D]">Send your question and a LoLo support team member will reply here.</p>
                </div>
            @else
                <div class="space-y-3">
                    @if ($assistantActive)
                        <div class="flex justify-center">
                            <button
                                type="button"
                                wire:click="transferToPerson"
                                wire:loading.attr="disabled"
                                class="inline-flex min-h-11 items-center rounded-xl border border-[#B9D7CF] bg-white px-4 text-sm font-semibold text-[#0F5B52] shadow-sm disabled:opacity-60"
                            >Talk to a person</button>
                        </div>
                    @endif
                    <p class="support-chat-time-separator">{{ $ticket->created_at?->format('M j, g:i A') }}</p>
                    <div class="flex justify-end">
                        <article class="support-chat-bubble support-chat-bubble-user">
                            <p class="support-chat-message-text">{{ $ticket->description }}</p>
                            <p class="mt-1.5 text-[11px] text-[#DDEEEA]">
                                {{ (int) $ticket->opener_user_id === (int) auth()->id() ? 'You' : ($ticket->opener?->name ?: 'Family') }} &middot; Sent
                            </p>
                        </article>
                    </div>

                    @php $previousMessageAt = $ticket->created_at; @endphp
                    @foreach ($messages as $message)
                        @php
                            $fromSupport = $message->sender?->role === 'admin'
                                || in_array($message->responder_type, [
                                    \App\Models\SupportTicketMessage::RESPONDER_AUTOMATED,
                                    \App\Models\SupportTicketMessage::RESPONDER_SYSTEM,
                                ], true);
                            $mine = (int) $message->sender_user_id === (int) auth()->id();
                            $showTime = ! $previousMessageAt
                                || ! $message->created_at?->isSameDay($previousMessageAt)
                                || $message->created_at?->diffInMinutes($previousMessageAt) >= 15;
                        @endphp
                        @if ($showTime)
                            <p class="support-chat-time-separator">{{ $message->created_at?->format('M j, g:i A') }}</p>
                        @endif
                        <div wire:key="support-chat-message-{{ $message->id }}" class="flex {{ $fromSupport ? 'justify-start' : 'justify-end' }}">
                            <article class="support-chat-bubble {{ $fromSupport ? 'support-chat-bubble-support' : 'support-chat-bubble-user' }}" title="{{ $message->created_at?->format('M j, Y g:i A') }}">
                                <p class="support-chat-message-text">{{ $message->body }}</p>
                                <p class="mt-1.5 text-[11px] {{ $fromSupport ? 'text-[#626B73]' : 'text-[#DDEEEA]' }}">
                                    @if ($fromSupport)
                                        {{ $message->responder_type === \App\Models\SupportTicketMessage::RESPONDER_AUTOMATED ? 'LoLo Support assistant' : ($message->sender?->name ?: 'LoLo Support') }} &middot; Support
                                    @elseif ($mine)
                                        You &middot; Sent
                                    @else
                                        {{ $message->sender?->name ?: 'Family' }}
                                    @endif
                                </p>

                                @foreach ($message->aiActions as $action)
                                    @php
                                        $actionPayload = (array) $action->payload;
                                        $actionActive = $action->isActive();
                                    @endphp

                                    @if ($action->action_type === \App\Models\AiSupportMessageAction::TYPE_PATH_CHOICES && $actionActive)
                                        <div class="mt-3 grid gap-2" aria-label="Choose care type">
                                            @foreach ((array) ($actionPayload['choices'] ?? []) as $choice)
                                                <button
                                                    type="button"
                                                    wire:click="chooseCarePath('{{ $action->id }}', '{{ $choice['id'] }}')"
                                                    wire:loading.attr="disabled"
                                                    class="min-h-11 rounded-xl bg-[#23483F] px-4 text-left text-sm font-semibold text-white disabled:opacity-60"
                                                >{{ $choice['label'] }}</button>
                                            @endforeach
                                        </div>
                                    @elseif ($action->action_type === \App\Models\AiSupportMessageAction::TYPE_GUIDED_TASK && $actionActive)
                                        <button
                                            type="button"
                                            wire:click="startGuidedTask('{{ $action->id }}')"
                                            wire:loading.attr="disabled"
                                            x-on:click="closeForNavigation()"
                                            class="mr-2 mt-3 inline-flex min-h-11 items-center rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white disabled:opacity-60"
                                        >{{ $actionPayload['label'] ?? 'Show me where' }}</button>
                                    @elseif ($action->action_type === \App\Models\AiSupportMessageAction::TYPE_NAVIGATE && $actionActive)
                                        <a
                                            href="{{ $actionPayload['url'] }}"
                                            wire:navigate
                                            x-on:click="closeForNavigation()"
                                            class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white"
                                        >{{ $actionPayload['label'] ?? 'Open page' }}</a>
                                    @elseif ($action->action_type === \App\Models\AiSupportMessageAction::TYPE_RECAP && ! $action->invalidated_at && ! $action->consumed_at)
                                        @php $recap = (array) ($actionPayload['recap'] ?? []); @endphp
                                        <section
                                            data-support-chat-recap="{{ $action->id }}"
                                            tabindex="-1"
                                            class="mt-3 space-y-3 rounded-2xl border border-[#C8DDD7] bg-white p-4 text-sm text-[#17313F] outline-none focus:ring-2 focus:ring-[#0F5B52] focus:ring-offset-2"
                                            aria-label="Care request recap"
                                            @if ($errors->has('confirmation')) aria-describedby="support-chat-confirmation-error" @endif
                                        >
                                            <h3 class="font-display text-lg font-bold">Review your request</h3>
                                            <dl class="space-y-2">
                                                <div><dt class="font-semibold">Type</dt><dd>{{ $recap['request_type_label'] ?? '' }}</dd></div>
                                                <div><dt class="font-semibold">Who needs care</dt><dd>{{ $recap['recipient'] ?? '' }}</dd></div>
                                                <div><dt class="font-semibold">Help needed</dt><dd>{{ implode(', ', (array) ($recap['tasks'] ?? [])) }}</dd></div>
                                                <div><dt class="font-semibold">Schedule</dt><dd>{{ $recap['schedule'] ?? '' }}</dd></div>
                                                @if (filled($recap['schedule_adjustment'] ?? null))
                                                    <div class="rounded-xl bg-amber-50 p-2 text-amber-900"><dt class="font-semibold">Start-date adjustment</dt><dd>{{ $recap['schedule_adjustment'] }}</dd></div>
                                                @endif
                                                <div><dt class="font-semibold">Address</dt><dd>{{ $recap['address'] ?? '' }}</dd></div>
                                                @if (filled($recap['additional_info'] ?? null))
                                                    <div><dt class="font-semibold">Additional instructions</dt><dd>{{ $recap['additional_info'] }}</dd></div>
                                                @endif
                                                <div><dt class="font-semibold">Caregiver response time</dt><dd>Within {{ $recap['preferred_response_hours'] ?? 12 }} hours</dd></div>
                                            </dl>
                                            <p class="rounded-xl bg-[#F2F8F6] p-3 font-medium">{{ $recap['disclosure'] ?? '' }}</p>
                                            <div class="grid gap-2">
                                                <button
                                                    type="button"
                                                    x-on:click="draft = 'I want to change '; $nextTick(() => { $refs.composer?.focus(); autoResize(); })"
                                                    class="min-h-11 rounded-xl border border-[#0F5B52] px-4 text-sm font-semibold text-[#0F5B52]"
                                                >Modify something</button>
                                                @if (($actionPayload['can_confirm'] ?? false) && $actionActive)
                                                    <button
                                                        type="button"
                                                        wire:click="confirmCareRequest('{{ $action->id }}')"
                                                        wire:loading.attr="disabled"
                                                        class="min-h-11 rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white disabled:opacity-60"
                                                    >Confirm and create request</button>
                                                @elseif (($actionPayload['can_confirm'] ?? false) && $action->expires_at?->isPast())
                                                    <button
                                                        type="button"
                                                        wire:click="renewCareRequestRecap('{{ $action->id }}')"
                                                        wire:loading.attr="disabled"
                                                        class="min-h-11 rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white disabled:opacity-60"
                                                    >Review and confirm again</button>
                                                @endif
                                                <button
                                                    type="button"
                                                    wire:click="discardCareRequestDraft"
                                                    wire:confirm="Discard this private request draft?"
                                                    class="min-h-11 rounded-xl px-4 text-sm font-semibold text-rose-700 underline"
                                                >Discard this draft</button>
                                            </div>
                                        </section>
                                    @elseif ($action->action_type === \App\Models\AiSupportMessageAction::TYPE_RECEIPT)
                                        <a
                                            href="{{ $actionPayload['url'] }}"
                                            wire:navigate
                                            x-on:click="closeForNavigation()"
                                            class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white"
                                        >View request</a>
                                    @elseif ($action->action_type === \App\Models\AiSupportMessageAction::TYPE_RECAP && $action->invalidation_reason === 'actor_logged_out')
                                        <button
                                            type="button"
                                            wire:click="renewCareRequestRecap('{{ $action->id }}')"
                                            wire:loading.attr="disabled"
                                            class="mt-3 min-h-11 rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white disabled:opacity-60"
                                        >Review and confirm again</button>
                                    @endif
                                @endforeach
                            </article>
                        </div>
                        @php $previousMessageAt = $message->created_at; @endphp
                    @endforeach

                </div>
            @endif

            <template x-if="pendingMessage">
                <div class="mt-3 flex justify-end" data-testid="support-chat-pending-message">
                    <article class="support-chat-bubble support-chat-bubble-user" x-bind:class="pendingMessage?.status === 'failed' ? 'support-chat-bubble-failed' : ''">
                        <p class="support-chat-message-text" x-text="pendingMessage?.body ?? ''"></p>
                        <div class="mt-1.5 flex items-center justify-between gap-3 text-[11px] text-[#DDEEEA]">
                            <span x-text="pendingMessage?.status === 'failed' ? 'Not sent' : 'Sending…'"></span>
                            <button
                                x-show="pendingMessage?.status === 'failed'"
                                type="button"
                                class="min-h-11 rounded-lg px-2 font-bold underline underline-offset-2"
                                aria-label="Try sending this support message again"
                                x-on:click="retryPending()"
                            >Try again</button>
                        </div>
                    </article>
                </div>
            </template>
        </div>

        <div class="support-chat-composer-wrap">
            <div x-show="! online" class="mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                You're offline. We'll send when you reconnect.
            </div>

            @if ($isResolved)
                <div class="mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    This conversation is resolved. Replying will reopen it.
                    <button type="button" wire:click="startNewConversation" class="mt-1 block min-h-11 font-semibold text-[#0F5B52] underline">Start a new conversation</button>
                </div>
            @endif

            @if ($isClosed)
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                    <p class="font-semibold text-slate-900">This conversation is closed and read-only.</p>
                    <button type="button" wire:click="startNewConversation" class="mt-2 min-h-11 font-semibold text-[#0F5B52] underline">Start a new conversation</button>
                </div>
            @else
                <form x-on:submit.prevent="sendMessage()" class="space-y-2" novalidate>
                    <label for="support-chat-composer" class="sr-only">Message LoLo Support</label>
                    <div class="flex items-end gap-2 rounded-2xl border border-[#D8D0C5] bg-[#FFFCF8] p-2 shadow-sm transition focus-within:border-[#0F5B52] focus-within:ring-2 focus-within:ring-[#CDE7DF]">
                        <textarea
                            id="support-chat-composer"
                            x-ref="composer"
                            x-model="draft"
                            x-on:input="draftChanged()"
                            rows="1"
                            maxlength="3000"
                            enterkeyhint="send"
                            placeholder="Ask us a question&hellip;"
                            class="support-chat-composer"
                            aria-describedby="support-chat-composer-help support-chat-send-error"
                        ></textarea>
                        <button
                            type="submit"
                            data-testid="support-chat-send"
                            class="flex h-11 min-w-11 shrink-0 items-center justify-center rounded-xl bg-[#23483F] px-3 text-sm font-semibold text-white transition hover:bg-[#173F35] focus:outline-none focus:ring-2 focus:ring-[#0F5B52] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            x-bind:disabled="sending || ! draft.trim()"
                            aria-label="Send message to LoLo Support"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4 4 16 8-16 8 3-8-3-8Z" />
                                <path stroke-linecap="round" d="M7 12h13" />
                            </svg>
                            <span class="sr-only" x-text="sending ? 'Sending' : 'Send'"></span>
                        </button>
                    </div>
                    <div class="flex min-h-5 items-start justify-between gap-3">
                        <p id="support-chat-composer-help" class="text-[11px] leading-4 text-[#626B73]">Replies appear here and in your Support Center.</p>
                        <p class="shrink-0 text-[11px] text-[#626B73]" x-text="draft.length ? `${draft.length}/3000` : ''"></p>
                    </div>
                    <p id="support-chat-send-error" x-show="sendError" x-text="sendError" class="break-words text-sm font-medium text-rose-700" role="alert"></p>
                    @error('messageBody') <p class="break-words text-sm font-medium text-rose-700" role="alert">{{ $message }}</p> @enderror
                    @error('conversation') <p class="break-words text-sm font-medium text-rose-700" role="alert">{{ $message }}</p> @enderror
                    @error('confirmation') <p id="support-chat-confirmation-error" class="break-words text-sm font-medium text-rose-700" role="alert">{{ $message }}</p> @enderror
                    @error('guidedTask') <p class="break-words text-sm font-medium text-rose-700" role="alert">{{ $message }}</p> @enderror
                </form>
            @endif

            <div class="mt-2 flex flex-col gap-1 border-t border-[#E7DED2] pt-2 text-[11px] leading-4 text-[#626B73]">
                <a href="{{ route('support.index') }}" wire:navigate x-on:click="closeForNavigation()" class="inline-flex min-h-11 items-center font-semibold text-[#0F5B52] underline underline-offset-2">Open Support Center</a>
                <p>LoLo Support is not an emergency service. If someone is in immediate danger, call 911.</p>
            </div>
        </div>

        <p class="sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></p>
    </section>
</div>
