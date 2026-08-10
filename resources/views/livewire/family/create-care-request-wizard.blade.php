<div>
    <div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @php
            $selectedTaskIds = collect($selectedTasks)->map(fn ($id) => (int) $id)->all();
            $selectedDayIds = collect($recurring_days)->map(fn ($day) => (int) $day)->all();
            $taskLookup = collect($taskOptions)->keyBy(fn ($task) => (int) $task['id']);
            $selectedTaskNames = collect($selectedTaskIds)
                ->map(fn ($id) => $taskLookup->get($id)['name'] ?? null)
                ->filter()
                ->values();
            $hasMoreDetails = trim($additional_info) !== ''
                || trim($recipient_care_notes) !== ''
                || trim($time_expectations) !== ''
                || trim($home_access_notes) !== ''
                || $includeThirdPartyContact
                || trim($third_party_full_name) !== ''
                || trim($third_party_phone) !== '';
            $dayLabels = collect($dayOptions)
                ->whereIn('value', $selectedDayIds)
                ->pluck('label')
                ->implode(', ');
            $scheduleLine = $this->scheduleSummary;
            $careRecipientReady = $this->recipientIsRequester || trim($recipient_full_name) !== '';
            $helpReady = count($selectedTaskIds) > 0;
            $scheduleReady = $this->estimatedHours !== null && trim($scheduleLine) !== '';
            $addressReady = trim($address_line1) !== '' && trim($city) !== '' && trim($state) !== '' && trim($zip) !== '';
            $publishChecklist = [
                ['label' => 'Person', 'ready' => $careRecipientReady, 'help' => $this->resolvedRecipientName],
                ['label' => 'Help', 'ready' => $helpReady, 'help' => $selectedTaskNames->implode(', ') ?: 'Choose help'],
                ['label' => 'Time', 'ready' => $scheduleReady, 'help' => trim($scheduleLine) !== '' ? $scheduleLine : 'Choose day and time'],
                ['label' => 'Address', 'ready' => $addressReady, 'help' => $addressReady ? trim($city.', '.$state.' '.$zip) : 'Add care address'],
            ];
            $readyChecklistCount = collect($publishChecklist)->where('ready', true)->count();
            $publishChecklistStatus = $readyChecklistCount === 4
                ? 'All essentials are ready. Review once, then publish.'
                : $readyChecklistCount.' of 4 essentials ready. Fill the missing pieces, then publish.';
        @endphp

        <section class="hc-brand-panel p-4 sm:p-5">
            <div class="relative max-w-3xl">
                <p class="hc-brand-kicker text-[#E8E0FF]">New care request</p>
                <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Tell us what care you need.</h1>
                <p class="mt-2 text-sm leading-6 text-[#F7F1E8]/82">
                    Start with the person, the help needed, the time, and the address.
                </p>
            </div>
        </section>

        <section class="rounded-2xl border border-[#E4DDD3] bg-[rgba(255,253,250,0.98)] p-3 shadow-sm sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#7B8794]">Before publishing</p>
                    <p class="mt-1 text-sm text-[#3C4A5B]">
                        {{ $publishChecklistStatus }}
                    </p>
                </div>
                <div class="mt-2 flex flex-wrap gap-2 sm:hidden">
                    @foreach ($publishChecklist as $item)
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold {{ $item['ready'] ? 'border-[#CFE1D8] bg-[#F2F8F4] text-emerald-800' : 'border-[#E4DDD3] bg-[#FFFCF8] text-[#6E746F]' }}">
                            {{ $item['label'] }} {{ $item['ready'] ? 'ready' : 'needed' }}
                        </span>
                    @endforeach
                </div>
                <div class="hidden gap-2 sm:grid sm:grid-cols-2 lg:grid-cols-4 lg:min-w-[38rem]">
                    @foreach ($publishChecklist as $item)
                        <div class="rounded-xl border px-3 py-2 {{ $item['ready'] ? 'border-[#CFE1D8] bg-[#F2F8F4]' : 'border-[#E4DDD3] bg-[#FFFCF8]' }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] {{ $item['ready'] ? 'text-emerald-700' : 'text-[#7B8794]' }}">{{ $item['label'] }}</p>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $item['ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-[#F0E9E1] text-[#6E746F]' }}">
                                    {{ $item['ready'] ? 'Ready' : 'Needed' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-medium leading-5 text-[#17313F]">{{ $item['help'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if ($lastRequestId || $hasSavedHouseholdProfile || $hasSavedRecipientProfile)
            <section class="rounded-2xl border border-[#E4DDD3] bg-[rgba(255,253,250,0.98)] px-4 py-3 shadow-sm">
                <details class="sm:hidden">
                    <summary class="cursor-pointer list-none font-semibold text-[#17313F] [&::-webkit-details-marker]:hidden">
                        Use saved information <span class="font-normal text-[#7B8794]">(optional)</span>
                    </summary>
                    <div class="mt-3 space-y-3 border-t border-[#EFE6D8] pt-3">
                        <p class="text-sm text-[#3C4A5B]">
                            Reuse saved information, then adjust today&apos;s date and time.
                        </p>
                        @if ($prefillApplied || $savedProfilesApplied)
                            <p class="text-xs font-semibold text-[#0F3D3E]">
                                {{ $prefillApplied ? 'Last request loaded.' : 'Saved details loaded.' }} Check the schedule before publishing.
                            </p>
                        @endif
                        <div class="grid grid-cols-1 gap-2">
                            @if ($lastRequestId)
                                <button type="button" wire:click="prefillFromLastRequest" class="hc-secondary-button w-full">
                                    Use last request
                                </button>
                            @endif

                            @if ($hasSavedHouseholdProfile || $hasSavedRecipientProfile)
                                <button type="button" wire:click="applySavedProfiles" class="hc-secondary-button w-full">
                                    Use saved details
                                </button>
                            @endif
                        </div>
                    </div>
                </details>

                <div class="hidden flex-col gap-3 sm:flex lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#7B8794]">Save time</p>
                        <p class="mt-1 text-sm text-[#3C4A5B]">
                            Reuse saved information, then adjust today&apos;s date and time.
                        </p>
                        @if ($prefillApplied || $savedProfilesApplied)
                            <p class="mt-1 text-xs font-semibold text-[#0F3D3E]">
                                {{ $prefillApplied ? 'Last request loaded.' : 'Saved details loaded.' }} Check the schedule before publishing.
                            </p>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:flex lg:shrink-0">
                        @if ($lastRequestId)
                            <button type="button" wire:click="prefillFromLastRequest" class="hc-secondary-button w-full lg:w-auto">
                                Use last request
                            </button>
                        @endif

                        @if ($hasSavedHouseholdProfile || $hasSavedRecipientProfile)
                            <button type="button" wire:click="applySavedProfiles" class="hc-secondary-button w-full lg:w-auto">
                                Use saved details
                            </button>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="mx-auto max-w-5xl">
            <form wire:submit="publish" class="space-y-5">
                <x-card>
                    <x-slot:header>
                        <div>
                            <h2 class="font-display text-lg font-semibold">Who and what</h2>
                            <p class="text-sm text-[#607080]">Start with the person receiving care and the help they need.</p>
                        </div>
                    </x-slot:header>

                    <div class="space-y-5">
                        <div>
                            <p class="text-sm font-medium text-[#324457]">Who is receiving care?</p>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($careForOptions as $option)
                                    <label class="flex min-h-14 cursor-pointer items-center justify-center rounded-xl border px-4 text-sm font-semibold transition {{ $care_for === $option['value'] ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
                                        <input type="radio" class="sr-only" value="{{ $option['value'] }}" wire:model.live="care_for">
                                        {{ $option['label'] }}
                                    </label>
                                @endforeach
                            </div>
                            @error('care_for') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if ($careRecipientProfiles->isNotEmpty())
                            <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-[#17313F]">Use a saved care profile <span class="font-normal text-[#607080]">(optional)</span></p>
                                        <p class="mt-1 text-sm text-[#607080]">Choose the person once and we will share the same caregiver preview with this request.</p>
                                    </div>
                                    @if ($selected_care_recipient_profile_id)
                                        <button type="button" wire:click="clearCareRecipientProfile" class="text-sm font-semibold text-[#B54436] underline">Change</button>
                                    @endif
                                </div>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    @foreach ($careRecipientProfiles as $profile)
                                        <button
                                            type="button"
                                            wire:click="selectCareRecipientProfile({{ $profile->id }})"
                                            class="rounded-xl border p-3 text-left transition {{ (int) $selected_care_recipient_profile_id === (int) $profile->id ? 'border-[#0F3D3E] bg-[#F2F8F4] ring-1 ring-[#0F3D3E]' : 'border-[#DED6CA] bg-white hover:bg-[#F5F1EB]' }}"
                                        >
                                            <span class="block font-semibold text-[#17313F]">{{ $profile->displayName() }}</span>
                                            <span class="mt-1 block text-xs text-[#607080]">{{ $profile->relationship_to_family ?: 'Care recipient' }} · Reviewed {{ optional($profile->last_reviewed_at)->format('M j, Y') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @elseif (! $createQuickCareProfile)
                            <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                                <p class="font-semibold text-[#17313F]">Want to tell caregivers a little about this person?</p>
                                <p class="mt-1 text-sm text-[#607080]">This is optional and takes about a minute.</p>
                                <button type="button" wire:click="$set('createQuickCareProfile', true)" class="hc-secondary-button mt-3">Add a simple care profile</button>
                            </div>
                        @endif

                        @if ($createQuickCareProfile && ! $selected_care_recipient_profile_id)
                            <div class="space-y-4 rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-[#17313F]">Simple care profile</p>
                                        <p class="mt-1 text-sm text-[#607080]">Caregivers who can view this request will see these answers. You can add more later.</p>
                                    </div>
                                    <button type="button" wire:click="$set('createQuickCareProfile', false)" class="text-sm font-semibold text-[#B54436] underline">Skip</button>
                                </div>
                                <div>
                                    <x-textarea label="What should a caregiver know?" wire:model="quick_profile_about" placeholder="A few warm, useful details about the person." />
                                    @error('quick_profile_about') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-textarea label="What helps care go well?" wire:model="quick_profile_good_visit" placeholder="Routines, interests, reassurance, or communication tips." />
                                    @error('quick_profile_good_visit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <label class="flex items-start gap-3 rounded-xl border border-[#CFE1D8] bg-white p-3 text-sm">
                                    <input type="checkbox" wire:model="quick_profile_sharing_acknowledged" class="mt-1 rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#0F3D3E]">
                                    <span>I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.</span>
                                </label>
                                @error('quick_profile_sharing_acknowledged') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if ($this->recipientIsRequester)
                            <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] p-4 text-sm text-[#0F3D3E]">
                                <p class="font-semibold">Care recipient: {{ $this->resolvedRecipientName }}</p>
                                <p class="mt-1 text-[#3C4A5B]">This request will be marked as care for the person posting it.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input label="Family member receiving care" wire:model="recipient_full_name" />
                                    @error('recipient_full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-input label="Relationship to you" wire:model="recipient_relationship_to_family" placeholder="Mother, father, spouse, friend" />
                                    @error('recipient_relationship_to_family') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif

                        <div>
                            <p class="text-sm font-medium text-[#324457]">Help needed</p>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($taskOptions as $task)
                                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm font-semibold transition {{ in_array((int) $task['id'], $selectedTaskIds, true) ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
                                        <input type="checkbox" class="sr-only" value="{{ $task['id'] }}" wire:model.live="selectedTasks">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded border {{ in_array((int) $task['id'], $selectedTaskIds, true) ? 'border-white bg-white text-[#0F3D3E]' : 'border-[#B7ADA0] bg-white text-transparent' }}">&#10003;</span>
                                        <span>{{ $task['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedTasks') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('selectedTasks.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <details class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3" {{ $hasMoreDetails ? 'open' : '' }}>
                            <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-display text-base font-semibold text-[#17313F]">More details if you want</p>
                                        <p class="mt-1 text-sm text-[#607080]">Skip this unless it helps the caregiver arrive prepared.</p>
                                    </div>
                                    <span class="rounded-full bg-[#F5F1EB] px-3 py-1 text-xs font-semibold text-[#607080]">Optional</span>
                                </div>
                            </summary>

                            <div class="mt-4 space-y-4 border-t border-[#EFE6D8] pt-4">
                                <x-textarea
                                    label="Anything important for the caregiver?"
                                    wire:model="additional_info"
                                    placeholder="Example: Mom likes a quiet morning routine and needs help with lunch."
                                />
                                @error('additional_info') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-textarea
                                            label="Care notes"
                                            wire:model="recipient_care_notes"
                                            placeholder="Example: Needs reminders to drink water."
                                        />
                                        @error('recipient_care_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-textarea
                                            label="Home access"
                                            wire:model="home_access_notes"
                                            placeholder="Example: Use side door. Small dog at home."
                                        />
                                        @error('home_access_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <x-input
                                    label="Timing note"
                                    wire:model="time_expectations"
                                    placeholder="Example: Please arrive right at 9:00 AM."
                                />
                                @error('time_expectations') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#E4DDD3] bg-white p-3">
                                    <input type="checkbox" class="mt-1 rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#0F3D3E]" wire:model.live="includeThirdPartyContact">
                                    <span>
                                        <span class="block font-semibold text-[#17313F]">Add another contact</span>
                                        <span class="mt-1 block text-sm text-[#607080]">Use this if a family member or helper should also be reachable.</span>
                                    </span>
                                </label>
                                @error('includeThirdPartyContact') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                                @if ($includeThirdPartyContact)
                                    <div class="grid grid-cols-1 gap-4 rounded-xl border border-[#E4DDD3] bg-white p-4 md:grid-cols-2">
                                        <div>
                                            <x-input label="Contact name" wire:model="third_party_full_name" />
                                            @error('third_party_full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <x-input label="Relationship" wire:model="third_party_relationship_to_recipient" placeholder="Son, daughter, neighbor" />
                                            @error('third_party_relationship_to_recipient') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <x-input label="Phone" wire:model="third_party_phone" />
                                            @error('third_party_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <x-input label="Email (optional)" wire:model="third_party_email" />
                                            @error('third_party_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </details>
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <div>
                            <h2 class="font-display text-xl font-semibold">When should care happen?</h2>
                            <p class="text-base text-[#607080]">Choose one visit or a regular weekly schedule. We calculate the end time for you.</p>
                        </div>
                    </x-slot:header>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($requestTypeOptions as $option)
                                <label class="flex min-h-14 cursor-pointer items-center justify-center rounded-lg border px-4 text-lg font-semibold transition {{ $request_type === $option['value'] ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
                                    <input type="radio" class="sr-only" value="{{ $option['value'] }}" wire:model.live="request_type">
                                    {{ $option['label'] }}
                                </label>
                            @endforeach
                        </div>
                        @error('request_type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        @if ($request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input type="date" label="Starting day" min="{{ $this->minimumStartDate }}" wire:model.change="requested_start_date" />
                                    @error('requested_start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-input type="time" label="Starting time" wire:model.change="requested_start_time" />
                                    @error('requested_start_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    @error('requested_start_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-native-select-field
                                        label="Duration (HH:MM)"
                                        wire:model.live="requested_duration_minutes"
                                        :options="$durationOptions"
                                    />
                                    @error('requested_duration_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] px-4 py-3 text-sm text-[#0F3D3E]">
                                <span class="font-semibold">Schedule:</span> {{ $this->scheduleSummary }}
                            </div>
                        @else
                            <div class="space-y-5">
                                <div>
                                    <p class="text-base font-semibold text-[#324457]">Which days each week?</p>
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
                                        @foreach ($dayOptions as $day)
                                            <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border px-2 text-base font-semibold transition {{ in_array((int) $day['value'], $selectedDayIds, true) ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
                                                <input type="checkbox" class="sr-only" value="{{ $day['value'] }}" wire:model.live="recurring_days">
                                                {{ $day['label'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('recurring_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    @error('recurring_days.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input type="date" label="Starting day" min="{{ $this->minimumStartDate }}" wire:model.change="recurring_starts_on" />
                                        @error('recurring_starts_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @if ($recurring_start_adjustment_message !== '')
                                            <p class="mt-2 text-sm font-semibold text-[#0F6B5B]">{{ $recurring_start_adjustment_message }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <x-input type="time" label="Starting time" wire:model.change="recurring_start_time" />
                                        @error('recurring_start_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-native-select-field
                                            label="Duration (HH:MM)"
                                            wire:model.live="recurring_duration_minutes"
                                            :options="$durationOptions"
                                        />
                                        @error('recurring_duration_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @error('recurring_end_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <fieldset>
                                        <legend class="text-base font-semibold text-[#324457]">How long should this repeat?</legend>
                                        <div class="mt-2 grid gap-2">
                                            <label class="flex min-h-12 items-center gap-3 rounded-md border border-[#DED6CA] bg-white px-4 text-base"><input type="radio" value="ongoing" wire:model.live="recurring_end_choice" class="h-5 w-5">Until I stop it</label>
                                            <label class="flex min-h-12 items-center gap-3 rounded-md border border-[#DED6CA] bg-white px-4 text-base"><input type="radio" value="date" wire:model.live="recurring_end_choice" class="h-5 w-5">End on a date</label>
                                        </div>
                                    </fieldset>
                                    @if ($recurring_end_choice === 'date')
                                        <div class="md:col-start-2">
                                            <x-input type="date" label="Last day" min="{{ $recurring_starts_on ?: $this->minimumStartDate }}" wire:model="recurring_ends_on" />
                                            @error('recurring_ends_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                </div>
                                <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] px-4 py-3 text-sm text-[#0F3D3E]">
                                    <span class="font-semibold">Schedule:</span> {{ $this->scheduleSummary }}
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <div>
                            <h2 class="font-display text-lg font-semibold">Where</h2>
                            <p class="text-sm text-[#607080]">Caregivers see the area before applying. Exact details stay with the request.</p>
                        </div>
                    </x-slot:header>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <x-input label="Street address" wire:model="address_line1" />
                            @error('address_line1') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <x-input label="Apartment, unit, or gate (optional)" wire:model="address_line2" />
                            @error('address_line2') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="City" wire:model="city" />
                            @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-native-select-field
                                label="State"
                                wire:model="state"
                                :options="collect($usStates)->map(fn($label, $value) => ['label' => $label, 'value' => $value])->values()->all()"
                            />
                            @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="ZIP" wire:model="zip" />
                            @error('zip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                </x-card>

                <x-card>
                    <x-slot:header>
                        <div>
                            <h2 class="font-display text-lg font-semibold">Review and publish</h2>
                            <p class="text-sm text-[#607080]">Check the essentials before caregivers see the request.</p>
                        </div>
                    </x-slot:header>

                    <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">For</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $this->resolvedRecipientName }}</p>
                            <p class="text-[#607080]">{{ $this->resolvedRecipientRelationship }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Help</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $selectedTaskNames->implode(', ') ?: 'Choose at least one service' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Schedule</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ trim($scheduleLine) !== '' ? $scheduleLine : 'Schedule not set' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Address</p>
                            <p class="mt-1 font-semibold text-[#17313F]">
                                {{ trim($address_line1) !== '' ? $address_line1 : 'Street address' }}
                            </p>
                            <p class="text-[#607080]">{{ trim($city.', '.$state.' '.$zip) !== ',' ? trim($city.', '.$state.' '.$zip) : 'City, state, ZIP' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4 md:col-span-2">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#0F7A55]">
                                {{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Estimated cost for one visit' : 'Estimated one-time cost' }}
                            </p>
                            @if ($this->estimatedCost !== null && $this->estimatedHours !== null)
                                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                    <p class="text-sm text-[#3C4A5B]">
                                        <span class="font-semibold">{{ number_format($this->estimatedHours, 2) }}h</span>
                                        x
                                        <span class="font-semibold">${{ number_format($this->estimateHourlyRate, 2) }}/hr</span>
                                    </p>
                                    <p class="font-display text-3xl font-semibold text-[#0F3D3E]">
                                        ${{ number_format($this->estimatedCost, 2) }}
                                    </p>
                                </div>
                                @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                    <p class="mt-3 text-sm text-[#3C4A5B]">You are not paying for every future visit now. LoLo confirms your card before each visit and charges the final amount after that visit.</p>
                                @endif
                            @else
                                <p class="mt-2 text-sm text-[#607080]">Add the schedule to see the estimate.</p>
                            @endif
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Cancel</a>
                            <button type="submit" class="hc-primary-button w-full sm:w-auto" wire:loading.attr="disabled" wire:target="publish">
                                Publish request
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </section>
    </div>
</div>
