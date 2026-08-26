<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $toneClasses = [
            'green' => 'bg-emerald-100 text-emerald-800',
            'blue' => 'bg-sky-100 text-sky-800',
            'amber' => 'bg-amber-100 text-amber-900',
            'rose' => 'bg-rose-100 text-rose-800',
            'slate' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    <section data-ai-target="family.regular_care" tabindex="-1" class="hc-brand-panel outline-none">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="hc-brand-kicker text-[#E8E0FF]">Arrangements</p>
                <h1 class="mt-1 font-display text-2xl font-semibold leading-tight sm:text-3xl">Every care arrangement, in one place.</h1>
                <p class="mt-2 hidden max-w-2xl text-base text-[#F7F1E8]/82 sm:block">See care still being arranged and ongoing caregiver relationships. Confirmed dates belong in Schedule.</p>
            </div>
            <a href="{{ route('family.requests.create') }}" wire:navigate class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#23483F] shadow-sm transition hover:bg-[#F7F2EA] sm:w-auto">Request care</a>
        </div>
    </section>

    <x-family-care-nav active="arrangements" />

    <section class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-3 shadow-sm sm:p-4">
        <div class="grid gap-3 sm:grid-cols-2 {{ count($recipientOptions ?? []) > 2 ? 'lg:grid-cols-3' : '' }}">
            @if (count($recipientOptions ?? []) > 2)
                <x-native-select-field label="Care recipient" wire:model.live="recipient" :options="$recipientOptions" id="arrangements-recipient" />
            @endif
            <x-native-select-field label="Status" wire:model.live="planView" :options="$planViewOptions" id="arrangements-view" />
            <x-native-select-field label="Care type" wire:model.live="careType" :options="$careTypeOptions" id="arrangements-type" />
        </div>
    </section>

    <section aria-labelledby="arrangement-list-heading" class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C96B55]">Requests and relationships</p>
                <h2 id="arrangement-list-heading" class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $totalArrangementCount }} {{ $totalArrangementCount === 1 ? 'arrangement' : 'arrangements' }}</h2>
            </div>
            <a href="{{ route('family.care.schedule', ['person' => $recipient]) }}" wire:navigate class="hc-link">Open visit schedule</a>
        </div>

        <div class="mt-4 grid gap-3 xl:grid-cols-2">
            @foreach ($arrangements as $item)
                @php $itemTone = $toneClasses[$item['status']['tone']] ?? $toneClasses['slate']; @endphp
                <article class="rounded-2xl border border-[#DED6CA] bg-white p-4 transition hover:border-[#B7ADA0] hover:shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#2F6F62]">{{ $item['type_label'] }}</span>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $itemTone }}">{{ $item['status']['label'] }}</span>
                    </div>
                    <h3 class="mt-2 font-display text-xl font-semibold text-[#17313F]">{{ $item['headline'] }}</h3>
                    <p class="mt-2 text-sm font-medium leading-6 text-[#324457]">{{ $item['schedule'] }}</p>
                    @if (($item['kind'] ?? null) === 'plan' && $item['next_label'])
                        <p class="mt-1 text-sm text-[#607080]">Next visit: {{ $item['next_label'] }}</p>
                    @elseif (($item['kind'] ?? null) === 'request' && $item['location'])
                        <p class="mt-1 text-sm text-[#607080]">{{ $item['location'] }}</p>
                    @endif

                    @if ($item['closed'])
                        <p class="mt-3 rounded-xl bg-[#F4F1EC] px-3 py-2 text-sm text-[#4B5B6B]">{{ ($item['kind'] ?? null) === 'plan' ? 'This arrangement is closed. Its completed visits remain in History.' : 'This request is closed and kept here for reference.' }}</p>
                    @elseif (($item['kind'] ?? null) === 'plan')
                        <p class="mt-3 text-xs font-semibold text-[#2F6F62]">{{ $item['payment_label'] }}</p>
                    @elseif ($item['date_passed'])
                        <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">The requested time has passed. Resolve this request before arranging new care.</p>
                    @endif

                    <div class="mt-4 flex flex-col gap-3 border-t border-[#E4DDD3] pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-xs text-[#7B8794]">{{ $item['reference'] }}</span>
                        <a href="{{ $item['action_url'] }}" wire:navigate class="{{ $item['status']['tone'] === 'amber' ? 'hc-primary-button' : 'hc-secondary-button' }} w-full sm:w-auto">{{ $item['action_label'] }}</a>
                    </div>
                </article>
            @endforeach

            @foreach ($continuousPlans as $coveragePlan)
                <article class="rounded-2xl border border-[#D7CCE9] bg-[#FAF8FD] p-4 transition hover:border-[#B8A7D3] hover:shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#6A4E9A]">Continuous care</span>
                        <span class="rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-semibold text-violet-800">{{ ucfirst($coveragePlan->status) }}</span>
                    </div>
                    <h3 class="mt-2 font-display text-xl font-semibold text-[#17313F]">{{ data_get($coveragePlan->recipient_snapshot, 'full_name', 'Care recipient') }}</h3>
                    <p class="mt-2 text-sm text-[#607080]">Around-the-clock and overnight care coordinated across a care team.</p>
                    <div class="mt-4 border-t border-[#DED5EB] pt-4 text-right">
                        <a href="{{ route('family.continuous-coverage.show', $coveragePlan) }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Manage continuous care</a>
                    </div>
                </article>
            @endforeach

            @if ($arrangements->isEmpty() && $continuousPlans->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-[#F7F2EA] px-4 py-10 text-center xl:col-span-2">
                    <p class="font-display text-xl font-semibold text-[#17313F]">No arrangements in this view.</p>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-[#607080]">Try another status or care type, or request new care.</p>
                    <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button mt-5 w-full sm:w-auto">Request care</a>
                </div>
            @endif
        </div>

        @if ($hasMoreArrangements)
            <div class="mt-4 border-t border-[#E4DDD3] pt-4 text-center">
                <p class="text-sm text-[#607080]">Showing {{ $arrangements->count() + $continuousPlans->count() }} of {{ $totalArrangementCount }} arrangements.</p>
                <button type="button" wire:click="loadMoreArrangements" class="hc-secondary-button mt-3 w-full sm:w-auto">Show more arrangements</button>
            </div>
        @endif
    </section>
</div>
