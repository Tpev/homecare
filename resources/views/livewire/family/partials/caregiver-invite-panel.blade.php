<div
    x-data
    x-init="$nextTick(() => $refs.search?.focus())"
    x-trap.inert.noscroll="true"
    x-on:keydown.escape.window.prevent="$wire.closeCaregiverInvitePanel()"
    x-on:caregiver-invite-content-top.window="$nextTick(() => $refs.panelScroll?.scrollTo({ top: 0, behavior: 'smooth' }))"
    wire:key="caregiver-invite-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="caregiver-invite-panel-title"
    class="fixed inset-0 z-[100] flex h-dvh w-full items-stretch justify-center overflow-hidden bg-[#17313F]/60 p-0 sm:items-center sm:p-4"
>
    <section class="flex h-full max-h-dvh w-full max-w-[920px] flex-col bg-[#FFFCF8] text-[#17313F] sm:h-auto sm:max-h-[90vh] sm:rounded-3xl sm:border sm:border-[#DED6CA] sm:shadow-2xl">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-[#E4DDD3] bg-white px-4 py-4 sm:px-6">
            <div class="min-w-0">
                <p class="hc-brand-kicker">Caregiver invitation</p>
                <h2 id="caregiver-invite-panel-title" class="mt-1 break-words font-display text-xl font-semibold sm:text-2xl">
                    Invite a caregiver to this request
                </h2>
            </div>
            <button
                type="button"
                wire:click="closeCaregiverInvitePanel"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-[#DED6CA] bg-white px-4 text-sm font-semibold text-[#0F3D3E] hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                aria-label="Close caregiver invitation"
            >
                Close
            </button>
        </header>

        <div x-ref="panelScroll" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6 sm:py-5">
            <div class="rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4" aria-label="Request summary">
                <p class="break-words font-display text-lg font-semibold">{{ $requestItem->title }}</p>
                <dl class="mt-2 grid grid-cols-1 gap-2 text-sm text-[#4B5B6B] sm:grid-cols-3">
                    <div><dt class="font-semibold text-[#17313F]">When</dt><dd>{{ $plainSchedule }}</dd></div>
                    <div><dt class="font-semibold text-[#17313F]">Where</dt><dd>{{ $requestItem->city }}, {{ $requestItem->state }}</dd></div>
                    @if ($requestItem->recipient?->full_name)
                        <div><dt class="font-semibold text-[#17313F]">Care for</dt><dd>{{ $requestItem->recipient->full_name }}</dd></div>
                    @endif
                </dl>
            </div>

            @if ($caregiverInviteFeedback)
                <div
                    role="{{ $caregiverInviteFeedback['type'] === 'error' ? 'alert' : 'status' }}"
                    aria-live="polite"
                    class="mt-4 rounded-xl border px-4 py-3 text-sm font-medium {{ $caregiverInviteFeedback['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($caregiverInviteFeedback['type'] === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-blue-200 bg-blue-50 text-blue-900') }}"
                >
                    {{ $caregiverInviteFeedback['message'] }}
                </div>
            @endif

            @if ($confirmingCaregiver)
                <div class="mt-5" wire:key="caregiver-invite-confirmation-{{ $confirmingCaregiver['user_id'] }}">
                    <button
                        type="button"
                        wire:click="cancelCaregiverInvitation"
                        class="min-h-11 rounded-xl px-1 text-sm font-semibold text-[#0F3D3E] underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                    >
                        Back to caregiver results
                    </button>

                    <div class="mt-2 rounded-2xl border border-[#BDD4F7] bg-white p-4 sm:p-5">
                        <h3 class="break-words font-display text-xl font-semibold">
                            {{ $confirmingReinvite ? 'Invite '.$confirmingCaregiver['first_name'].' again' : 'Invite '.$confirmingCaregiver['first_name'] }} to “{{ $requestItem->title }}”?
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-[#607080]">
                            {{ $plainSchedule }} in {{ $requestItem->city }}, {{ $requestItem->state }}. The caregiver can review the request before replying.
                        </p>

                        <div class="mt-5">
                            <label for="caregiver-invite-message" class="block text-base font-semibold text-[#17313F]">Invitation message (optional)</label>
                            <p id="caregiver-invite-message-hint" class="mt-1 text-sm text-[#607080]">You can change this note before sending.</p>
                            <textarea
                                id="caregiver-invite-message"
                                wire:model="caregiverInviteMessage"
                                aria-describedby="caregiver-invite-message-hint"
                                rows="5"
                                maxlength="1200"
                                class="mt-2 block w-full rounded-xl border border-[#B7ADA0] bg-white px-3 py-3 text-base text-[#17313F] shadow-sm focus:border-[#4F6FAF] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30"
                            ></textarea>
                            @error('caregiverInviteMessage')
                                <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                wire:click="cancelCaregiverInvitation"
                                class="min-h-11 rounded-xl border border-[#B7ADA0] bg-white px-4 py-2 text-sm font-semibold text-[#0F3D3E] hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                wire:click="sendCaregiverInvitation"
                                wire:loading.attr="disabled"
                                wire:target="sendCaregiverInvitation"
                                class="min-h-11 rounded-xl bg-[#0F3D3E] px-4 py-2 text-sm font-semibold text-white hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] disabled:cursor-wait disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="sendCaregiverInvitation">Send invitation</span>
                                <span wire:loading wire:target="sendCaregiverInvitation">Sending invitation…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-5">
                    <label for="caregiver-name-search" class="block text-base font-semibold text-[#17313F]">Search by caregiver name</label>
                    <p id="caregiver-name-search-hint" class="mt-1 text-sm text-[#607080]">Enter at least 2 letters. You can also enter a city.</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input
                            x-ref="search"
                            id="caregiver-name-search"
                            type="search"
                            value="{{ $caregiverSearch }}"
                            x-on:input.debounce.350ms="$wire.set('caregiverSearch', $event.target.value)"
                            aria-describedby="caregiver-name-search-hint caregiver-search-status"
                            autocomplete="off"
                            placeholder="Example: Charles"
                            class="min-h-12 w-full min-w-0 flex-1 rounded-xl border border-[#B7ADA0] bg-white px-4 py-3 text-base text-[#17313F] shadow-sm placeholder:text-[#7B8794] focus:border-[#4F6FAF] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30"
                        >
                        @if (trim($caregiverSearch) !== '')
                            <button
                                type="button"
                                wire:click="clearCaregiverSearch"
                                class="min-h-11 shrink-0 rounded-xl border border-[#B7ADA0] bg-white px-4 py-2 text-sm font-semibold text-[#0F3D3E] hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                            >
                                Clear search
                            </button>
                        @endif
                    </div>
                    <p id="caregiver-search-status" class="sr-only" role="status" aria-live="polite">
                        <span wire:loading wire:target="caregiverSearch">Searching caregivers</span>
                        <span wire:loading.remove wire:target="caregiverSearch">
                            @if (mb_strlen(trim($caregiverSearch)) >= 2)
                                {{ $caregiverSearchResults->count() }} caregivers found
                            @endif
                        </span>
                    </p>
                </div>

                <div wire:loading.flex wire:target="caregiverSearch" class="mt-6 items-center gap-3 rounded-xl border border-[#BDD4F7] bg-blue-50 px-4 py-4 text-sm font-medium text-blue-900">
                    <span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-300 border-t-blue-700" aria-hidden="true"></span>
                    Searching caregivers…
                </div>

                <div wire:loading.remove wire:target="caregiverSearch" class="mt-6 space-y-6">
                    @if (trim($caregiverSearch) === '')
                        @php $visibleInitialSectionCount = collect($caregiverInitialSections)->filter(fn ($section) => $section['caregivers']->isNotEmpty())->count(); @endphp
                        @forelse ($caregiverInitialSections as $section)
                            @if ($section['caregivers']->isNotEmpty())
                                <section aria-labelledby="invite-section-{{ $section['key'] }}">
                                    <h3 id="invite-section-{{ $section['key'] }}" class="font-display text-lg font-semibold">{{ $section['title'] }}</h3>
                                    <p class="mt-1 text-sm text-[#607080]">{{ $section['description'] }}</p>
                                    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                        @foreach ($section['caregivers'] as $caregiver)
                                            @include('livewire.family.partials.caregiver-invite-card', ['caregiver' => $caregiver])
                                        @endforeach
                                    </div>
                                </section>
                            @endif
                        @empty
                        @endforelse

                        @if ($visibleInitialSectionCount === 0)
                            <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-6 text-center">
                                <p class="font-semibold text-[#17313F]">Search for someone you know</p>
                                <p class="mt-1 text-sm text-[#607080]">Enter their first or last name above. We will only show active caregiver profiles.</p>
                            </div>
                        @endif
                    @elseif (mb_strlen(trim($caregiverSearch)) < 2)
                        <div class="rounded-xl border border-[#E4DDD3] bg-white px-4 py-4 text-sm text-[#607080]">
                            Enter at least 2 letters to search.
                        </div>
                    @elseif ($caregiverSearchResults->isEmpty())
                        <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-6 text-center" role="status">
                            <p class="font-semibold text-[#17313F]">No active caregivers found</p>
                            <p class="mt-1 text-sm text-[#607080]">Check the spelling or try part of the caregiver’s name.</p>
                        </div>
                    @else
                        <section aria-labelledby="caregiver-search-results-heading">
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h3 id="caregiver-search-results-heading" class="font-display text-lg font-semibold">Search results</h3>
                                    <p class="mt-1 text-sm text-[#607080]">{{ $caregiverSearchResults->count() }} caregiver{{ $caregiverSearchResults->count() === 1 ? '' : 's' }} found</p>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                @foreach ($caregiverSearchResults as $caregiver)
                                    @include('livewire.family.partials.caregiver-invite-card', ['caregiver' => $caregiver])
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>
