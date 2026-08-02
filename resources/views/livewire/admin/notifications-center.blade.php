<div class="hc-page space-y-5 py-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $toneStyles = [
            'success' => 'bg-emerald-100 text-emerald-800',
            'warning' => 'bg-amber-100 text-amber-800',
            'info' => 'bg-blue-100 text-blue-800',
            'neutral' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <section class="hc-brand-panel p-5">
        <div class="relative flex items-start justify-between gap-4">
            <div>
                <p class="hc-brand-kicker">Operations notifications</p>
                <h1 class="mt-1 font-display text-2xl font-semibold sm:text-3xl">LoLo Care operations updates</h1>
                <p class="mt-1 text-sm text-[#E5E7EB]">Assigned support replies and workflow escalations that need attention.</p>
            </div>
            <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-right">
                <p class="text-[11px] uppercase tracking-[0.14em] text-[#F1E5D2]">Unread</p>
                <p class="mt-1 text-2xl font-semibold">{{ $unreadCount }}</p>
            </div>
        </div>
        <div class="relative mt-4 flex flex-wrap gap-2">
            <a href="{{ route('admin.support.tickets') }}" wire:navigate><span class="hc-secondary-button">Support queue</span></a>
            <button type="button" wire:click="markAllRead"><span class="hc-primary-button">Mark all read</span></button>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="space-y-3 xl:col-span-8">
            <div class="rounded-2xl border border-[#DED6CA] bg-white p-2 shadow-sm">
                <div class="grid grid-cols-3 gap-1">
                    @foreach (['unread' => 'Unread', 'all' => 'All', 'read' => 'Read'] as $value => $label)
                        <button type="button" wire:click="$set('scope', '{{ $value }}')" class="h-11 rounded-xl px-3 text-sm font-medium {{ $scope === $value ? 'bg-[#23483F] text-white' : 'text-[#5B6472] hover:bg-[#F5F1EB]' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="mt-2">
                    <x-native-select-field label="Event type" wire:model.live="eventFilter" :options="array_merge([['label' => 'All event types', 'value' => 'all']], $eventOptions)" />
                </div>
            </div>

            @forelse ($notifications as $item)
                <article class="rounded-2xl border {{ $item['read_at'] ? 'border-[#E4DDD3] bg-white' : 'border-[#C96B55]/40 bg-[#FFF7EA]' }} p-4 shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $toneStyles[$item['tone']] ?? $toneStyles['neutral'] }}">{{ strtoupper($item['event_label']) }}</span>
                        @if (!$item['read_at'])<span class="rounded-full bg-[#F5E7E1] px-2 py-0.5 text-[11px] font-semibold text-[#A84F3F]">UNREAD</span>@endif
                    </div>
                    <p class="mt-2 font-display text-lg font-semibold text-[#23483F]">{{ $item['title'] }}</p>
                    <p class="mt-1 text-sm text-[#53645D]">{{ $item['body'] }}</p>
                    @if (!empty($item['details']))
                        <dl class="mt-3 grid grid-cols-1 gap-1 text-xs text-[#53645D] sm:grid-cols-2">
                            @foreach ($item['details'] as $detail)
                                <div class="flex gap-1"><dt class="font-semibold">{{ $detail['label'] }}:</dt><dd>{{ $detail['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                    <p class="mt-2 text-xs text-[#7A8091]">{{ optional($item['created_at'])->diffForHumans() }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($item['url'] !== '')<button type="button" wire:click="openNotification('{{ $item['id'] }}')" class="inline-flex min-h-11 items-center rounded-xl bg-[#23483F] px-4 text-sm font-semibold text-white">Open</button>@endif
                        @if (!$item['read_at'])<button type="button" wire:click="markRead('{{ $item['id'] }}')" class="inline-flex min-h-11 items-center rounded-xl border border-[#DED6CA] bg-white px-4 text-sm font-semibold text-[#53645D]">Mark read</button>@endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-10 text-center text-sm text-[#5B6472]">No notifications for this filter.</div>
            @endforelse
        </section>

        <aside class="xl:col-span-4">
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Delivery preferences</h2></x-slot:header>
                <div class="space-y-3">
                    <p class="text-sm text-[#5B6472]">These settings apply to notifications sent directly to your admin account. Shared operations alerts continue to use the configured operations email list.</p>
                    <p class="text-xs text-[#7A8091]">SMS and push are not offered yet.</p>
                    @foreach ($eventOptions as $eventOption)
                        @php($eventKey = $eventOption['value'])
                        <div class="rounded-xl border border-[#DED6CA] bg-[#FFFCF8] p-3">
                            <p class="text-sm font-semibold text-[#23483F]">{{ $eventOption['label'] }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-[#53645D]">
                                <label class="flex items-center gap-2 rounded-lg border border-[#DED6CA] px-2 py-2"><input type="checkbox" wire:model="preferences.{{ $eventKey }}.in_app"><span>In-app</span></label>
                                @if ($eventKey === \App\Support\MarketplaceEvent::SUPPORT_TICKET_CREATED)
                                    <span class="flex items-center rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-2 py-2">Shared ops email</span>
                                @else
                                    <label class="flex items-center gap-2 rounded-lg border border-[#DED6CA] px-2 py-2"><input type="checkbox" wire:model="preferences.{{ $eventKey }}.email"><span>Email</span></label>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <button type="button" wire:click="savePreferences"><span class="hc-primary-button w-full">Save preferences</span></button>
                </div>
            </x-card>
        </aside>
    </div>
</div>
