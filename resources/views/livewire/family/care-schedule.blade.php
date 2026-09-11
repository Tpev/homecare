<div class="hc-care-workspace hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    <h1 data-ai-target="family.care_schedule" tabindex="-1" class="sr-only">Care schedule</h1>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-family-care-nav active="schedule" />
        <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button min-h-11 self-end shrink-0">Request new care</a>
    </div>

    <section class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="font-display text-xl font-semibold text-[#17313F]">{{ $totalVisitCount }} upcoming {{ $totalVisitCount === 1 ? 'visit' : 'visits' }}</h2>
                <p class="mt-1 text-sm text-[#607080]">Your confirmed care, by person and date.</p>
                @if ($scheduleView === 'list' && $totalVisitCount > $visits->count())
                    <p class="mt-1 text-xs font-medium text-[#7B8794]">Showing the next {{ $visits->count() }} visits.</p>
                @endif
            </div>
            <div class="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:min-w-[34rem]">
                <x-native-select-field label="Care recipient" wire:model.live="recipient" :options="$recipientOptions" id="care-schedule-recipient" />
                <x-native-select-field label="Care type" wire:model.live="careType" :options="$careTypeOptions" id="care-schedule-type" />
            </div>
        </div>
        <div class="hc-schedule-view-row">
            <div class="hc-schedule-toggle" role="group" aria-label="Schedule view">
                <button type="button" wire:click="setView('list')" aria-pressed="{{ $scheduleView === 'list' ? 'true' : 'false' }}">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M7 5h10M7 10h10M7 15h10M3 5h.01M3 10h.01M3 15h.01" stroke-linecap="round" /></svg>
                    List
                </button>
                <button type="button" wire:click="setView('calendar')" aria-pressed="{{ $scheduleView === 'calendar' ? 'true' : 'false' }}">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="14" height="13" rx="2" /><path d="M6 2v4m8-4v4M3 8h14m-10 3h2m2 0h2m-6 3h2" stroke-linecap="round" /></svg>
                    Calendar
                </button>
            </div>
            <p class="hc-schedule-view-hint">{{ $scheduleView === 'calendar' ? 'Select a date to see every visit.' : 'Visits are shown in date order.' }}</p>
        </div>
    </section>

    @if ($scheduleView === 'calendar')
        @include('livewire.family.partials.care-schedule-calendar')
    @else
    <section class="space-y-8" aria-label="Upcoming care timeline">
        @forelse ($visitSections as $sectionKey => $section)
            <div>
                <div class="mb-4 flex items-end justify-between gap-3 border-b border-[#D8D0C5] pb-3">
                    <div>
                        @if ($sectionKey === 'today')
                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C96B55]">Right now</p>
                        @endif
                        <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $section['label'] }}</h2>
                        <p class="mt-1 text-sm text-[#607080]">{{ $section['description'] }}</p>
                    </div>
                    <span class="rounded-full bg-[#F4EEE5] px-3 py-1 text-xs font-semibold text-[#526474]">{{ $section['visits']->count() }}</span>
                </div>

                <div class="space-y-5">
                    @foreach ($section['visits']->groupBy(fn (array $visit): string => $visit['starts_at']?->toDateString() ?: 'unscheduled') as $date => $dayVisits)
                        @php $day = $dayVisits->first()['starts_at'] ?? null; @endphp
                        <div class="grid gap-3 md:grid-cols-[8rem_minmax(0,1fr)]">
                            <div class="md:pt-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#2F6F62]">{{ $day?->isToday() ? 'Today' : ($day?->isTomorrow() ? 'Tomorrow' : $day?->format('l')) }}</p>
                                <p class="mt-1 font-display text-lg font-semibold text-[#17313F]">{{ $day?->format('M j') ?: 'Date pending' }}</p>
                            </div>
                            <div class="space-y-3 border-l-2 border-[#D8E1D7] pl-4 sm:pl-5">
                                @foreach ($dayVisits as $visit)
                                    @php
                                        $tone = match ($visit['status']['tone']) {
                                            'amber' => 'bg-amber-100 text-amber-900',
                                            'green' => 'bg-emerald-100 text-emerald-800',
                                            'blue' => 'bg-sky-100 text-sky-800',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                    @endphp
                                    <article class="rounded-3xl border border-[#E4DDD3] bg-white p-4 shadow-sm sm:p-5">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#2F6F62]">{{ $visit['type_label'] }}</span>
                                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $tone }}">{{ $visit['status']['label'] }}</span>
                                                </div>
                                                <h3 class="mt-2 font-display text-xl font-semibold text-[#17313F]">{{ $visit['headline'] }}</h3>
                                                <p class="mt-1 text-sm font-medium text-[#324457]">{{ $visit['starts_at']?->format('g:i A') }}@if($visit['ends_at'])–{{ $visit['ends_at']->format('g:i A') }}@endif · {{ $visit['caregiver'] }}</p>
                                                @if ($visit['location'])<p class="mt-1 text-sm text-[#607080]">{{ $visit['location'] }}</p>@endif
                                                <p class="mt-2 text-xs text-[#7B8794]">{{ $visit['reference'] }}</p>
                                            </div>
                                            <div class="flex w-full flex-col gap-2 sm:w-auto">
                                                @if ($visit['payment_needs_action'])
                                                    <a href="{{ $visit['action_url'] }}" wire:navigate class="hc-primary-button w-full sm:w-auto">Fix payment</a>
                                                    <p class="max-w-48 text-xs leading-5 text-amber-800">Required to keep this visit financially protected.</p>
                                                @else
                                                    <a href="{{ $visit['details_url'] }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">View care</a>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-3xl border border-dashed border-[#D6CCBE] bg-[#FFFCF8] px-5 py-10 text-center">
                <h2 class="font-display text-2xl font-semibold text-[#17313F]">No upcoming visits in this view.</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm text-[#607080]">Try another person or care type, or request care when you are ready.</p>
                <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button mt-5 w-full sm:w-auto">Request care</a>
            </div>
        @endforelse

        @if ($hasMoreVisits)
            <div class="rounded-3xl border border-[#D8E1D7] bg-[#F7FBF8] p-4 text-center sm:p-5">
                <p class="text-sm text-[#607080]">Showing {{ $visits->count() }} of {{ $totalVisitCount }} upcoming visits.</p>
                <button type="button" wire:click="loadMoreVisits" class="hc-secondary-button mt-3 w-full sm:w-auto">Show the next {{ min(8, $totalVisitCount - $visits->count()) }}</button>
            </div>
        @endif
    </section>
    @endif
</div>
