<div>
    @if($aiPrepared)
        <div class="hc-page pb-0 pt-4"><x-alert color="blue">LoLo prepared this message. Edit it if needed, then choose Send yourself. It has not been sent.</x-alert></div>
    @endif
    @php
        $active = $this->activeConversation;
        $messages = $this->messages;
        $currentUser = auth()->user();
        $canSend = $active ? $currentUser->can('sendMessage', $active) : false;
        $activeRequest = $active?->careRequest;
        $activeApplication = $active?->application;
        $requestHref = $activeRequest
            ? ($currentUser->role === 'family'
                ? route('family.requests.show', $activeRequest->id)
                : route('care-requests.apply', $activeRequest->id))
            : null;
        $isFamilyHireDecision = $currentUser->role === 'family'
            && $activeRequest?->status === \App\Models\CareRequest::STATUS_OPEN
            && $activeApplication
            && in_array($activeApplication->status, [
                \App\Models\CareRequestApplication::STATUS_APPLIED,
                \App\Models\CareRequestApplication::STATUS_SHORTLISTED,
            ], true);
        $isCaregiverWaitingForHire = $currentUser->role === 'caregiver'
            && $activeRequest?->status === \App\Models\CareRequest::STATUS_OPEN
            && $activeApplication
            && in_array($activeApplication->status, [
                \App\Models\CareRequestApplication::STATUS_APPLIED,
                \App\Models\CareRequestApplication::STATUS_SHORTLISTED,
            ], true);
        $isHiredConversation = $activeApplication?->status === \App\Models\CareRequestApplication::STATUS_HIRED
            || $activeRequest?->status === \App\Models\CareRequest::STATUS_FILLED;
        $requestContextEyebrow = match (true) {
            $isFamilyHireDecision => 'Hire decision',
            $isCaregiverWaitingForHire => 'Waiting for family',
            $isHiredConversation => 'Visit coordination',
            default => 'Request context',
        };
        $requestContextTitle = match (true) {
            $isFamilyHireDecision => 'Chat here, then hire from the request page.',
            $isCaregiverWaitingForHire => 'The family is still deciding.',
            $isHiredConversation => 'This chat is tied to a booked visit.',
            default => 'This chat is tied to a care request.',
        };
        $requestContextBody = match (true) {
            $isFamilyHireDecision => 'Use messages to confirm fit, then return to the request when you are ready to hire.',
            $isCaregiverWaitingForHire => 'Reply to questions here. If they hire you, visit tools will appear on the request page.',
            $isHiredConversation => 'Use this thread for simple coordination. Visit details, support, and review stay on the request page.',
            default => 'Keep request-specific questions here so both sides have the same context.',
        };
        $requestContextAction = match (true) {
            $isFamilyHireDecision => 'Open request to hire',
            $isHiredConversation => 'Open visit',
            default => $currentUser->role === 'family' ? 'Open request' : 'Open request details',
        };
    @endphp

    <div
        class="hc-page py-4 sm:py-6"
        x-data="homecareInboxPolling({ intervalMs: 2000, startsInThread: @js((bool) $active) })"
        x-init="init()"
        x-on:message-compose-focus.window="pausePolling()"
        x-on:message-compose-blur.window="resumePolling()"
        x-on:message-sent.window="resumePolling(); refreshNow()"
    >
        <div class="overflow-hidden rounded-[28px] border border-[#DED6CA] bg-[rgba(255,252,248,0.97)] shadow-lg shadow-[#0F3D3E]/10 grid grid-cols-1 lg:grid-cols-12 min-h-[calc(100vh-9rem)] sm:min-h-[72vh]">
            <aside
                data-ai-target="family.messages.inbox"
                tabindex="-1"
                x-show="!mobileThreadOpen || isDesktop"
                x-transition.opacity
                class="lg:col-span-4 xl:col-span-3 border-r border-[#DED6CA] bg-[rgba(245,241,235,0.65)]"
            >
                <div class="p-4 border-b border-[#DED6CA] bg-[rgba(255,252,248,0.95)]">
                    <p class="text-xs uppercase tracking-[0.16em] text-[#7C5DDC]">Messages</p>
                    <h1 class="text-lg font-display font-semibold text-[#0F172A]">Inbox</h1>
                    <div class="mt-3">
                        <x-input wire:model.blur="search" placeholder="Search by request or name" />
                    </div>
                </div>

                <div class="max-h-[calc(100vh-14rem)] overflow-y-auto lg:max-h-[72vh]">
                    @forelse ($this->conversations as $conversation)
                        @php
                            $isActive = (int) $conversation->id === (int) $activeConversationId;
                            $otherParty = $currentUser->role === 'family' ? $conversation->caregiver : $conversation->family;
                        @endphp
                        <button
                            type="button"
                            wire:click="openConversation({{ $conversation->id }})"
                            x-on:click="manualInboxOpen = false; mobileThreadOpen = true"
                            class="w-full border-b border-[#E6DED3] px-4 py-3.5 text-left transition {{ $isActive ? 'bg-[rgba(124,93,220,0.12)]' : 'bg-[rgba(255,252,248,0.92)] hover:bg-[rgba(245,241,235,0.88)]' }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate font-display font-semibold text-[#0F172A]">{{ $otherParty?->name }}</p>
                                @if ($conversation->is_unread_for_current_user)
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#7C5DDC]"></span>
                                @endif
                            </div>
                            <p class="text-sm text-[#5B6472] truncate">{{ $conversation->careRequest?->title }}</p>
                            <div class="mt-1 flex items-center justify-between">
                                <p class="text-xs text-[#7A8091]">{{ strtoupper((string) $conversation->application?->status) }}</p>
                                <p class="text-xs text-[#7A8091]">{{ optional($conversation->last_message_at)->diffForHumans() }}</p>
                            </div>
                        </button>
                    @empty
                        <div class="p-6 text-sm text-[#5B6472]">
                            No conversations yet.
                        </div>
                    @endforelse
                </div>
            </aside>

            <section
                @if ($active) data-ai-target="family.messages.conversation" tabindex="-1" @endif
                x-show="mobileThreadOpen || isDesktop"
                x-transition.opacity
                class="lg:col-span-8 xl:col-span-9 flex flex-col min-h-[calc(100vh-9rem)] sm:min-h-[72vh]"
            >
                @if ($active)
                    @php
                        $otherParty = $currentUser->role === 'family' ? $active->caregiver : $active->family;
                    @endphp
                    <div class="border-b border-[#DED6CA] p-4 bg-[rgba(255,252,248,0.95)]">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <button
                                    type="button"
                                    x-show="!isDesktop"
                                    x-on:click="manualInboxOpen = true; mobileThreadOpen = false"
                                    class="mb-3 inline-flex min-h-11 items-center gap-2 rounded-full border border-[#DED6CA] bg-[#FFFCF8] px-3 py-2 text-sm font-medium text-[#0F3D3E]"
                                >
                                    <span aria-hidden="true"><<</span>
                                    Back to inbox
                                </button>
                                <p class="text-xs uppercase tracking-[0.16em] text-[#7A8091]">Conversation</p>
                                <h2 class="truncate text-lg font-display font-semibold text-[#0F172A]">{{ $otherParty?->name }}</h2>
                                <p class="truncate text-sm text-[#5B6472]">{{ $active->careRequest?->title }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-[#7A8091]">Request status</p>
                                <x-badge :text="strtoupper((string) $active->careRequest?->status)" color="primary" />
                            </div>
                        </div>

                        @if ($activeRequest && $requestHref)
                            <div class="mt-4 rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] px-4 py-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">{{ $requestContextEyebrow }}</p>
                                        <p class="mt-1 font-display text-base font-semibold leading-snug text-[#17313F]">{{ $requestContextTitle }}</p>
                                        <p class="mt-1 text-sm leading-5 text-[#4B5B6B]">{{ $requestContextBody }}</p>
                                    </div>
                                    <a href="{{ $requestHref }}" wire:navigate class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-[#DED6CA] bg-white px-4 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#FFFCF8]">
                                        {{ $requestContextAction }}
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div
                        class="flex-1 overflow-y-auto px-3 py-4 sm:px-4 sm:py-5 bg-gradient-to-b from-[#FFFCF8] to-[#F5F1EB]/55"
                        x-data="{ jump(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                        x-init="$nextTick(() => jump())"
                        x-on:message-sent.window="jump()"
                    >
                        <div class="space-y-3">
                            @foreach ($messages as $message)
                                @php $mine = (int) $message->sender_user_id === (int) $currentUser->id; @endphp
                                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[88%] rounded-2xl px-4 py-3 shadow-sm md:max-w-[65%] {{ $mine ? 'bg-[#0F3D3E] text-white' : 'border border-[#DED6CA] bg-[#FFFCF8] text-[#223043]' }}">
                                        <p class="text-sm whitespace-pre-line">{{ $message->body }}</p>
                                        <div class="mt-2 flex items-center justify-between gap-3 text-[11px] {{ $mine ? 'text-[#DDEEEA]' : 'text-[#7A8091]' }}">
                                            <span>{{ $message->sender?->name }}</span>
                                            <span>{{ $message->created_at?->format('H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($messages->count() >= $messagesLimit)
                            <div class="mt-4 text-center">
                                <button type="button" wire:click="loadMore" class="hc-secondary-button">Load older messages</button>
                            </div>
                        @endif
                    </div>

                    <div class="sticky bottom-0 border-t border-[#DED6CA] bg-[rgba(255,252,248,0.95)] p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] backdrop-blur sm:p-4">
                        @if (! $canSend)
                            <div class="mb-3 rounded-md border border-[#D7C8A2] bg-[#FFF7E7] px-3 py-2 text-sm text-[#7A5A15]">
                                Chat is locked until the application is shortlisted or hired.
                            </div>
                        @endif

                        <form wire:submit="sendMessage" class="space-y-3" data-chat-compose>
                            <textarea
                                wire:model="messageBody"
                                rows="3"
                                placeholder="Type your message..."
                                @disabled(! $canSend)
                                x-on:focus="$dispatch('message-compose-focus')"
                                x-on:blur="$dispatch('message-compose-blur')"
                                class="w-full rounded-2xl border border-[#DED6CA] bg-[#FFFCF8] px-3 py-3 text-sm text-[#223043] shadow-sm outline-none transition focus:border-[#7C5DDC] focus:ring-2 focus:ring-[#CFC6F7] disabled:cursor-not-allowed disabled:bg-[#F1ECE4] disabled:text-[#7A8091]"
                            ></textarea>
                            @error('messageBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-[#7A8091]">Messages send when you tap Send.</p>
                                <button type="submit" class="hc-primary-button" @disabled(! $canSend)>Send</button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="h-full flex items-center justify-center p-10 bg-[rgba(245,241,235,0.55)]">
                        <div class="text-center max-w-md">
                            <p class="text-sm uppercase tracking-[0.16em] text-[#7A8091]">Inbox</p>
                            <h2 class="mt-1 text-xl font-display font-semibold text-[#0F172A]">Select a conversation</h2>
                            <p class="text-sm text-[#5B6472] mt-2">Pick a family or caregiver thread from the left panel to start chatting.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>

<script>
    if (! window.homecareInboxPolling) {
        window.homecareInboxPolling = function (config = {}) {
            return {
                intervalMs: Number(config.intervalMs || 2000),
                timer: null,
                paused: false,
                startsInThread: Boolean(config.startsInThread || false),
                manualInboxOpen: false,
                mobileThreadOpen: Boolean(config.startsInThread || false),
                isDesktop: false,
                init() {
                    this.handleViewport();
                    window.addEventListener('resize', () => this.handleViewport());

                    if (this.timer) {
                        clearInterval(this.timer);
                    }

                    this.timer = setInterval(() => {
                        if (this.paused || document.hidden) {
                            return;
                        }

                        this.$wire.refreshThread();
                    }, this.intervalMs);
                },
                pausePolling() {
                    this.paused = true;
                },
                resumePolling() {
                    this.paused = false;
                },
                handleViewport() {
                    this.isDesktop = window.innerWidth >= 1024;

                    if (this.isDesktop) {
                        this.mobileThreadOpen = true;
                    } else if (this.startsInThread && ! this.manualInboxOpen) {
                        this.mobileThreadOpen = true;
                    }
                },
                refreshNow() {
                    this.$wire.refreshThread();
                },
            };
        };
    }
</script>


