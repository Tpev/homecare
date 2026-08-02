<div
    x-data
    x-init="$nextTick(() => $refs.search?.focus())"
    x-trap.inert.noscroll="true"
    x-on:keydown.escape.window.prevent="$wire.closeCaregiverSearchModal()"
    x-on:coverage-caregiver-content-top.window="$nextTick(() => $refs.panelScroll?.scrollTo({ top: 0, behavior: 'smooth' }))"
    wire:key="coverage-caregiver-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="coverage-caregiver-dialog-title"
    class="fixed inset-0 z-[100] flex h-dvh w-full items-stretch justify-center overflow-hidden bg-[#17313F]/60 p-0 sm:items-center sm:p-4"
>
    <section class="flex h-full max-h-dvh w-full max-w-[920px] flex-col bg-[#FFFCF8] text-[#17313F] sm:h-auto sm:max-h-[90vh] sm:rounded-3xl sm:border sm:border-[#DED6CA] sm:shadow-2xl">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-[#E4DDD3] bg-white px-4 py-4 sm:px-6">
            <div class="min-w-0">
                <p class="hc-brand-kicker">Family-approved care team</p>
                <h2 id="coverage-caregiver-dialog-title" class="mt-1 break-words font-display text-xl font-semibold sm:text-2xl">Add a caregiver to this care team</h2>
            </div>
            <button
                type="button"
                wire:click="closeCaregiverSearchModal"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-[#DED6CA] bg-white px-4 text-sm font-semibold text-[#0F3D3E] hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                aria-label="Close caregiver search"
            >
                Close
            </button>
        </header>

        <div x-ref="panelScroll" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6 sm:py-5">
            <div class="rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4" aria-label="Coverage plan summary">
                <p class="break-words font-display text-lg font-semibold">{{ $plan->title }}</p>
                <dl class="mt-2 grid grid-cols-1 gap-2 text-sm text-[#4B5B6B] sm:grid-cols-3">
                    <div><dt class="font-semibold text-[#17313F]">Coverage</dt><dd>{{ $plan->coverage_pattern === '24_7' ? 'Around the clock' : ucfirst(str_replace('_', ' ', $plan->coverage_pattern)) }}</dd></div>
                    <div><dt class="font-semibold text-[#17313F]">Service area</dt><dd>{{ collect([data_get($plan->address_snapshot, 'city'), data_get($plan->address_snapshot, 'state')])->filter()->implode(', ') ?: 'See plan' }}</dd></div>
                    <div><dt class="font-semibold text-[#17313F]">Care for</dt><dd>{{ $plan->recipientName() }}</dd></div>
                </dl>
            </div>

            @if ($caregiverSearchFeedback)
                <div role="status" aria-live="polite" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                    {{ $caregiverSearchFeedback }}
                </div>
            @endif

            @if ($selectedCaregiver)
                <div class="mt-5" wire:key="coverage-caregiver-confirmation-{{ $selectedCaregiver->user_id }}">
                    <button
                        type="button"
                        wire:click="backToCaregiverSearch"
                        class="min-h-11 rounded-xl px-1 text-sm font-semibold text-[#0F3D3E] underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]"
                    >
                        Back to caregiver results
                    </button>

                    <div class="mt-2 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.9fr)]">
                        @include('livewire.family.partials.continuous-coverage-caregiver-card', ['caregiver' => $selectedCaregiver, 'showAction' => false])

                        <div class="rounded-2xl border border-[#BDD4F7] bg-white p-4 sm:p-5">
                            <h3 class="font-display text-xl font-semibold">Choose invitation preferences</h3>
                            <p class="mt-2 text-sm leading-6 text-[#607080]">These settings determine which future coverage offers this caregiver may receive. They do not assign a shift. After accepting the care-team invitation, the caregiver can separately accept or decline a recurring lane.</p>

                            <label class="mt-5 block">
                                <span class="text-sm font-semibold text-[#17313F]">Initial coverage role</span>
                                <select wire:model="inviteRole" class="mt-2 min-h-12 w-full rounded-xl border-[#B7ADA0] bg-white">
                                    <option value="backup">Backup caregiver</option>
                                    <option value="primary">Primary caregiver</option>
                                </select>
                            </label>

                            <label class="mt-4 flex min-h-12 items-center gap-3 rounded-xl border border-[#DED6CA] bg-[#F7F2EA] px-3 py-2 text-sm font-semibold text-[#344754]">
                                <input type="checkbox" wire:model="inviteReplacementOptIn" class="rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">
                                May receive matching replacement offers
                            </label>

                            <fieldset class="mt-4">
                                <legend class="text-sm font-semibold text-[#17313F]">Eligible days</legend>
                                <p class="mt-1 text-xs text-[#607080]">Choose the days this caregiver may be offered coverage.</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-2">
                                    @foreach($dayNames as $dayIndex => $dayName)
                                        <label class="flex min-h-11 items-center gap-2 rounded-xl border border-[#DED6CA] bg-white px-3 text-sm">
                                            <input type="checkbox" value="{{ $dayIndex }}" wire:model="inviteEligibleDays" class="rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">
                                            {{ substr($dayName, 0, 3) }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('inviteEligibleDays') <p class="mt-2 text-sm font-semibold text-rose-700" role="alert">{{ $message }}</p> @enderror
                            </fieldset>

                            <fieldset class="mt-4">
                                <legend class="text-sm font-semibold text-[#17313F]">Eligible shift types</legend>
                                <p class="mt-1 text-xs text-[#607080]">Leave every option unchecked to allow any shift type.</p>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    @foreach(['daytime'=>'Daytime','overnight'=>'Overnight','6_hour'=>'6 hours','8_hour'=>'8 hours','12_hour'=>'12 hours'] as $type => $label)
                                        <label class="flex min-h-11 items-center gap-2 rounded-xl border border-[#DED6CA] bg-white px-3 text-sm">
                                            <input type="checkbox" value="{{ $type }}" wire:model="inviteEligibleShiftTypes" class="rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('inviteEligibleShiftTypes') <p class="mt-2 text-sm font-semibold text-rose-700" role="alert">{{ $message }}</p> @enderror
                            </fieldset>

                            @error('caregiver') <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700" role="alert">{{ $message }}</p> @enderror

                            <button
                                type="button"
                                wire:click="approveCaregiver({{ $selectedCaregiver->user_id }})"
                                wire:loading.attr="disabled"
                                wire:target="approveCaregiver({{ $selectedCaregiver->user_id }})"
                                class="mt-5 min-h-12 w-full rounded-xl bg-[#0F3D3E] px-4 py-3 font-semibold text-white hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] disabled:cursor-wait disabled:opacity-60"
                            >
                                <span wire:loading.remove wire:target="approveCaregiver({{ $selectedCaregiver->user_id }})">Approve & send invitation</span>
                                <span wire:loading wire:target="approveCaregiver({{ $selectedCaregiver->user_id }})">Sending invitation…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @elseif ($selectedCaregiverId)
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <p class="font-semibold">This caregiver profile is no longer available.</p>
                    <button type="button" wire:click="backToCaregiverSearch" class="mt-3 min-h-11 font-semibold underline underline-offset-4">Back to caregiver results</button>
                </div>
            @else
                <div class="mt-5">
                    <label for="coverage-caregiver-name-search" class="block text-base font-semibold text-[#17313F]">Search by caregiver name</label>
                    <p id="coverage-caregiver-name-search-hint" class="mt-1 text-sm text-[#607080]">Enter at least 2 letters. You can also enter a city.</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input
                            x-ref="search"
                            id="coverage-caregiver-name-search"
                            type="search"
                            value="{{ $caregiverSearch }}"
                            x-on:input.debounce.350ms="$wire.set('caregiverSearch', $event.target.value)"
                            aria-describedby="coverage-caregiver-name-search-hint coverage-caregiver-search-status"
                            autocomplete="off"
                            placeholder="Example: Charles"
                            class="min-h-12 w-full min-w-0 flex-1 rounded-xl border border-[#B7ADA0] bg-white px-4 py-3 text-base text-[#17313F] shadow-sm placeholder:text-[#7B8794] focus:border-[#4F6FAF] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30"
                        >
                        @if (trim($caregiverSearch) !== '')
                            <button type="button" wire:click="clearCaregiverSearch" class="min-h-11 shrink-0 rounded-xl border border-[#B7ADA0] bg-white px-4 py-2 text-sm font-semibold text-[#0F3D3E] hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]">Clear search</button>
                        @endif
                    </div>
                    <p id="coverage-caregiver-search-status" class="sr-only" role="status" aria-live="polite">
                        <span wire:loading wire:target="caregiverSearch">Searching caregivers</span>
                        <span wire:loading.remove wire:target="caregiverSearch">
                            @if (mb_strlen(trim($caregiverSearch)) >= 2)
                                {{ $searchResults->count() }} caregivers found
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
                        @foreach ($caregiverInitialSections as $section)
                            @if ($section['caregivers']->isNotEmpty())
                                <section aria-labelledby="coverage-invite-section-{{ $section['key'] }}">
                                    <h3 id="coverage-invite-section-{{ $section['key'] }}" class="font-display text-lg font-semibold">{{ $section['title'] }}</h3>
                                    <p class="mt-1 text-sm text-[#607080]">{{ $section['description'] }}</p>
                                    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                        @foreach ($section['caregivers'] as $caregiver)
                                            @include('livewire.family.partials.continuous-coverage-caregiver-card', ['caregiver' => $caregiver])
                                        @endforeach
                                    </div>
                                </section>
                            @endif
                        @endforeach

                        @if ($visibleInitialSectionCount === 0)
                            <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-6 text-center">
                                <p class="font-semibold text-[#17313F]">Search for someone you know</p>
                                <p class="mt-1 text-sm text-[#607080]">Enter their first or last name above. We only show caregivers with active profiles.</p>
                            </div>
                        @endif
                    @elseif (mb_strlen(str_replace(['%', '_'], '', trim($caregiverSearch))) < 2)
                        <div class="rounded-xl border border-[#E4DDD3] bg-white px-4 py-4 text-sm text-[#607080]">Enter at least 2 letters to search.</div>
                    @elseif ($searchResults->isEmpty())
                        <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-6 text-center" role="status">
                            <p class="font-semibold text-[#17313F]">No active caregivers found</p>
                            <p class="mt-1 text-sm text-[#607080]">Check the spelling or try part of the caregiver’s name.</p>
                        </div>
                    @else
                        <section aria-labelledby="coverage-caregiver-search-results-heading">
                            <h3 id="coverage-caregiver-search-results-heading" class="font-display text-lg font-semibold">Search results</h3>
                            <p class="mt-1 text-sm text-[#607080]">{{ $searchResults->count() }} caregiver{{ $searchResults->count() === 1 ? '' : 's' }} found</p>
                            <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                @foreach ($searchResults as $caregiver)
                                    @include('livewire.family.partials.continuous-coverage-caregiver-card', ['caregiver' => $caregiver])
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>
