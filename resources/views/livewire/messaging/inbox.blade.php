<div>
    @php
        $active = $this->activeConversation;
        $messages = $this->messages;
        $currentUser = auth()->user();
        $canSend = $active ? $currentUser->can('sendMessage', $active) : false;
    @endphp

    <div
        class="hc-page py-4 sm:py-6"
        x-data="homecareInboxPolling({ intervalMs: 2000, startsInThread: @js((bool) $active) })"
        x-init="init()"
        x-on:message-compose-focus.window="pausePolling()"
        x-on:message-compose-blur.window="resumePolling()"
        x-on:message-sent.window="resumePolling(); refreshNow()"
    >
        <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white/95 shadow-lg shadow-slate-200/70 grid grid-cols-1 lg:grid-cols-12 min-h-[calc(100vh-9rem)] sm:min-h-[72vh]">
            <aside
                x-show="!mobileThreadOpen || isDesktop"
                x-transition.opacity
                class="lg:col-span-4 xl:col-span-3 border-r border-slate-200 bg-slate-50/90"
            >
                <div class="p-4 border-b border-slate-200 bg-white">
                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Messages</p>
                    <h1 class="text-lg font-display font-semibold text-slate-900">Inbox</h1>
                    <div class="mt-3">
                        <x-input wire:model.live.debounce.300ms="search" placeholder="Search by request or name" />
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
                            x-on:click="mobileThreadOpen = true"
                            class="w-full text-left px-4 py-3.5 border-b border-slate-200 transition {{ $isActive ? 'bg-cyan-50/70' : 'bg-white hover:bg-slate-50' }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-display font-semibold text-slate-900 truncate">{{ $otherParty?->name }}</p>
                                @if ($conversation->is_unread_for_current_user)
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-600 truncate">{{ $conversation->careRequest?->title }}</p>
                            <div class="mt-1 flex items-center justify-between">
                                <p class="text-xs text-slate-500">{{ strtoupper((string) $conversation->application?->status) }}</p>
                                <p class="text-xs text-slate-500">{{ optional($conversation->last_message_at)->diffForHumans() }}</p>
                            </div>
                        </button>
                    @empty
                        <div class="p-6 text-sm text-slate-600">
                            No conversations yet.
                        </div>
                    @endforelse
                </div>
            </aside>

            <section
                x-show="mobileThreadOpen || isDesktop"
                x-transition.opacity
                class="lg:col-span-8 xl:col-span-9 flex flex-col min-h-[calc(100vh-9rem)] sm:min-h-[72vh]"
            >
                @if ($active)
                    @php
                        $otherParty = $currentUser->role === 'family' ? $active->caregiver : $active->family;
                    @endphp
                    <div class="border-b border-slate-200 p-4 bg-white">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <button
                                    type="button"
                                    x-show="!isDesktop"
                                    x-on:click="mobileThreadOpen = false"
                                    class="mb-3 inline-flex min-h-11 items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700"
                                >
                                    <span aria-hidden="true">←</span>
                                    Back to inbox
                                </button>
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Conversation</p>
                                <h2 class="truncate text-lg font-display font-semibold text-slate-900">{{ $otherParty?->name }}</h2>
                                <p class="truncate text-sm text-slate-600">{{ $active->careRequest?->title }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-slate-500">Request status</p>
                                <x-badge :text="strtoupper((string) $active->careRequest?->status)" color="blue" />
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex-1 overflow-y-auto px-3 py-4 sm:px-4 sm:py-5 bg-gradient-to-b from-white to-slate-50"
                        x-data="{ jump(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                        x-init="$nextTick(() => jump())"
                        x-on:message-sent.window="jump()"
                    >
                        <div class="space-y-3">
                            @foreach ($messages as $message)
                                @php $mine = (int) $message->sender_user_id === (int) $currentUser->id; @endphp
                                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[88%] md:max-w-[65%] rounded-2xl px-4 py-3 shadow-sm {{ $mine ? 'bg-cyan-700 text-white' : 'bg-white border border-slate-200 text-slate-800' }}">
                                        <p class="text-sm whitespace-pre-line">{{ $message->body }}</p>
                                        <div class="mt-2 flex items-center justify-between gap-3 text-[11px] {{ $mine ? 'text-cyan-100' : 'text-slate-500' }}">
                                            <span>{{ $message->sender?->name }}</span>
                                            <span>{{ $message->created_at?->format('H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($messages->count() >= $messagesLimit)
                            <div class="mt-4 text-center">
                                <x-button color="slate" light wire:click="loadMore">Load older messages</x-button>
                            </div>
                        @endif
                    </div>

                    <div class="sticky bottom-0 border-t border-slate-200 bg-white/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] backdrop-blur sm:p-4">
                        @if (! $canSend)
                            <div class="mb-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                Chat is locked until the application is shortlisted or hired.
                            </div>
                        @endif

                        <form wire:submit="sendMessage" class="space-y-3" data-chat-compose>
                            <textarea
                                wire:model.live.debounce.250ms="messageBody"
                                rows="3"
                                placeholder="Type your message..."
                                @disabled(! $canSend)
                                x-on:focus="$dispatch('message-compose-focus')"
                                x-on:blur="$dispatch('message-compose-blur')"
                                class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                            ></textarea>
                            @error('messageBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-slate-500">Syncs live while you chat.</p>
                                <x-button type="submit" color="blue" :disabled="!$canSend">Send</x-button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="h-full flex items-center justify-center p-10 bg-slate-50">
                        <div class="text-center max-w-md">
                            <p class="text-sm uppercase tracking-[0.16em] text-slate-500">Inbox</p>
                            <h2 class="text-xl font-semibold text-slate-900 mt-1">Select a conversation</h2>
                            <p class="text-sm text-slate-600 mt-2">Pick a family or caregiver thread from the left panel to start chatting.</p>
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
                    }
                },
                refreshNow() {
                    this.$wire.refreshThread();
                },
            };
        };
    }
</script>
