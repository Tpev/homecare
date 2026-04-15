<div class="hc-page py-6 space-y-5">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $toneStyles = [
            'success' => 'bg-[rgba(15,61,62,0.12)] text-[#0F3D3E]',
            'warning' => 'bg-amber-100 text-amber-800',
            'danger' => 'bg-rose-100 text-rose-700',
            'info' => 'bg-[rgba(124,93,220,0.12)] text-[#7C5DDC]',
            'neutral' => 'bg-[rgba(79,111,175,0.12)] text-[#4F6FAF]',
        ];
    @endphp

    <section class="hc-brand-panel p-5">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-[#4F6FAF]/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="hc-brand-kicker">Notifications</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Family updates, clearly organized.</h1>
                    <p class="mt-1 text-sm text-[#E5E7EB]">Invites, applicants, hires, shifts, messages, and billing updates.</p>
                </div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-right">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-[#CFC6F7]">Unread</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $unreadCount }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('family.requests.index') }}" wire:navigate>
                    <span class="hc-secondary-button">My requests</span>
                </a>
                <a href="{{ route('messages.index') }}" wire:navigate>
                    <span class="hc-secondary-button">Messages</span>
                </a>
                <button type="button" wire:click="markAllRead">
                    <span class="hc-primary-button">Mark all read</span>
                </button>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="space-y-3 xl:col-span-8">
            <div class="sticky top-16 z-20 -mx-1 px-1">
                <div class="rounded-2xl border border-[#DED6CA] bg-[rgba(255,252,248,0.95)] p-2 shadow-sm backdrop-blur space-y-2">
                    <div class="grid grid-cols-3 gap-1">
                        <button
                            type="button"
                            wire:click="$set('scope', 'unread')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === 'unread' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#5B6472] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]'  }}"
                        >
                            Unread
                        </button>
                        <button
                            type="button"
                            wire:click="$set('scope', 'all')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === 'all' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#5B6472] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]'  }}"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            wire:click="$set('scope', 'read')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === 'read' ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#5B6472] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]'  }}"
                        >
                            Read
                        </button>
                    </div>

                    <x-select.styled
                        wire:model.live="eventFilter"
                        :options="array_merge([['label' => 'All event types', 'value' => 'all']], $eventOptions)"
                    />
                </div>
            </div>

            <div class="space-y-3 pt-1">
                @forelse ($notifications as $item)
                    <article class="rounded-2xl border {{ $item['read_at'] ? 'border-[#E4DDD3] bg-white' : 'border-[#BDD4F7] bg-[#EEF5FF]/30' }} p-4 shadow-sm sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $toneStyles[$item['tone'] ?? 'neutral'] ?? $toneStyles['neutral'] }}">
                                        {{ strtoupper($item['event_label']) }}
                                    </span>
                                    @if (! $item['read_at'])
                                        <span class="rounded-full bg-[rgba(124,93,220,0.12)] px-2 py-0.5 text-[11px] font-semibold text-[#7C5DDC]">UNREAD</span>
                                    @endif
                                </div>
                                <p class="mt-2 font-display text-lg font-semibold text-[#0F172A]">{{ $item['title'] }}</p>
                                <p class="mt-1 text-sm text-[#3C4A5B]">{{ $item['body'] }}</p>
                                <p class="mt-2 text-xs text-[#7A8091]">{{ optional($item['created_at'])->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                            @if ($item['url'] !== '')
                                <button
                                    type="button"
                                    wire:click="openNotification('{{ $item['id'] }}')"
                                    class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-[#0F3D3E] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#123f40] sm:w-auto"
                                >
                                    Open
                                </button>
                            @endif

                            @if (! $item['read_at'])
                                <button
                                    type="button"
                                    wire:click="markRead('{{ $item['id'] }}')"
                                    class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-[#E4DDD3] bg-white px-4 text-sm font-semibold text-[#3C4A5B] transition hover:bg-[#F7F2EA] sm:w-auto"
                                >
                                    Mark read
                                </button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-10 text-center text-sm text-[#5B6472]">
                        No notifications for this filter yet.
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="xl:col-span-4">
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Delivery preferences</h2>
                </x-slot:header>

                <div class="space-y-3">
                    @foreach ($eventOptions as $eventOption)
                        @php
                            $eventKey = $eventOption['value'];
                        @endphp
                        <div class="rounded-xl border border-[#DED6CA] bg-[#FFFCF8] p-3">
                            <p class="text-sm font-semibold text-[#0F172A]">{{ $eventOption['label'] }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-[#3C4A5B]">
                                <label class="flex items-center gap-2 rounded-lg border border-[#DED6CA] bg-[#FFFCF8] px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.in_app">
                                    <span>In-app</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-[#DED6CA] bg-[#FFFCF8] px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.email">
                                    <span>Email</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-[#DED6CA] bg-[#FFFCF8] px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.sms">
                                    <span>SMS (soon)</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-[#DED6CA] bg-[#FFFCF8] px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.push">
                                    <span>Push (soon)</span>
                                </label>
                            </div>
                        </div>
                    @endforeach

                    <button type="button" wire:click="savePreferences">
                        <span class="hc-primary-button w-full">Save preferences</span>
                    </button>
                </div>
            </x-card>
        </aside>
    </div>
</div>




