<div class="hc-page py-6 space-y-5">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $toneStyles = [
            'success' => 'bg-emerald-100 text-emerald-700',
            'warning' => 'bg-amber-100 text-amber-800',
            'danger' => 'bg-rose-100 text-rose-700',
            'info' => 'bg-sky-100 text-sky-700',
            'neutral' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <section class="relative overflow-hidden rounded-3xl border border-slate-900/80 bg-slate-950 p-5 text-white shadow-xl">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-cyan-500/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-300">Notifications</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Family updates, clearly organized.</h1>
                    <p class="mt-1 text-sm text-slate-300">Invites, applicants, hires, shifts, messages, and billing updates.</p>
                </div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-right">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Unread</p>
                    <p class="mt-1 text-2xl font-semibold">{{ $unreadCount }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('family.requests.index') }}" wire:navigate>
                    <x-button color="white" light sm>My requests</x-button>
                </a>
                <a href="{{ route('messages.index') }}" wire:navigate>
                    <x-button color="white" light sm>Messages</x-button>
                </a>
                <button type="button" wire:click="markAllRead">
                    <x-button color="white" sm>Mark all read</x-button>
                </button>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="space-y-3 xl:col-span-8">
            <div class="sticky top-16 z-20 -mx-1 px-1">
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur space-y-2">
                    <div class="grid grid-cols-3 gap-1">
                        <button
                            type="button"
                            wire:click="$set('scope', 'unread')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === 'unread' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        >
                            Unread
                        </button>
                        <button
                            type="button"
                            wire:click="$set('scope', 'all')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === 'all' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            wire:click="$set('scope', 'read')"
                            class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $scope === 'read' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
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
                    <article class="rounded-2xl border {{ $item['read_at'] ? 'border-slate-200 bg-white' : 'border-cyan-200 bg-cyan-50/30' }} p-4 shadow-sm sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $toneStyles[$item['tone'] ?? 'neutral'] ?? $toneStyles['neutral'] }}">
                                        {{ strtoupper($item['event_label']) }}
                                    </span>
                                    @if (! $item['read_at'])
                                        <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-[11px] font-semibold text-cyan-700">UNREAD</span>
                                    @endif
                                </div>
                                <p class="mt-2 font-display text-lg font-semibold text-slate-900">{{ $item['title'] }}</p>
                                <p class="mt-1 text-sm text-slate-700">{{ $item['body'] }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ optional($item['created_at'])->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                            @if ($item['url'] !== '')
                                <button
                                    type="button"
                                    wire:click="openNotification('{{ $item['id'] }}')"
                                    class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 sm:w-auto"
                                >
                                    Open
                                </button>
                            @endif

                            @if (! $item['read_at'])
                                <button
                                    type="button"
                                    wire:click="markRead('{{ $item['id'] }}')"
                                    class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto"
                                >
                                    Mark read
                                </button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-600">
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
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-900">{{ $eventOption['label'] }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-700">
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.in_app">
                                    <span>In-app</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.email">
                                    <span>Email</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.sms">
                                    <span>SMS (soon)</span>
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2">
                                    <input type="checkbox" wire:model="preferences.{{ $eventKey }}.push">
                                    <span>Push (soon)</span>
                                </label>
                            </div>
                        </div>
                    @endforeach

                    <button type="button" wire:click="savePreferences">
                        <x-button color="blue" class="w-full">Save preferences</x-button>
                    </button>
                </div>
            </x-card>
        </aside>
    </div>
</div>

