<div class="hc-care-workspace hc-care-creation mx-auto" data-inline-support>
    <div class="hc-page hc-create-page">
        @if($aiPrepared)
            <x-alert color="blue">LoLo copied these details into the form. Review and edit everything before you publish. No request was created automatically.</x-alert>
        @endif
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @php
            $selectedTaskIds = collect($selectedTasks)->map(fn ($id) => (int) $id)->all();
            $selectedDayIds = collect($recurring_days)->map(fn ($day) => (int) $day)->unique()->sort()->values()->all();
            $dayOptionsByValue = collect($dayOptions)->keyBy(fn ($day) => (int) $day['value']);
            $hasMoreDetails = trim($additional_info) !== ''
                || trim($recipient_care_notes) !== ''
                || trim($time_expectations) !== ''
                || trim($home_access_notes) !== ''
                || $includeThirdPartyContact
                || trim($third_party_full_name) !== ''
                || trim($third_party_phone) !== '';
            $scheduleLine = $this->scheduleSummary;
            $scheduleStartReady = $request_type !== \App\Models\CareRequest::TYPE_RECURRING || trim($recurring_starts_on) !== '';
            $scheduleEndReady = $request_type !== \App\Models\CareRequest::TYPE_RECURRING
                || $recurring_end_choice !== 'date'
                || (trim($recurring_ends_on) !== '' && $recurring_ends_on >= $recurring_starts_on);
            $scheduleReady = $this->estimatedHours !== null && trim($scheduleLine) !== '' && $scheduleStartReady && $scheduleEndReady;
            $recurringBoundary = $recurring_end_choice === 'date'
                ? ($recurring_ends_on !== '' ? 'Last day: '.$recurring_ends_on : 'Choose the last day')
                : 'Repeats until you end it';
            if ($request_type === \App\Models\CareRequest::TYPE_RECURRING && $recurring_end_choice === 'date' && trim($recurring_ends_on) !== '') {
                try {
                    $recurringBoundary = 'Last day: '.\Carbon\Carbon::parse($recurring_ends_on)->format('M j, Y');
                } catch (\Throwable) {
                    $recurringBoundary = 'Check the last day';
                }
            }
            $firstPlannedVisit = '';
            if ($request_type === \App\Models\CareRequest::TYPE_RECURRING && $scheduleReady) {
                try {
                    $firstDay = \Carbon\Carbon::parse($recurring_starts_on);
                    $firstSlot = $recurring_schedule[(string) $firstDay->dayOfWeek] ?? [];
                    $firstStart = \App\Support\WeeklySchedule::normalizeTime($firstSlot['start_time'] ?? null);
                    $firstEnd = \App\Support\WeeklySchedule::normalizeTime($firstSlot['end_time'] ?? null);
                    if ($firstStart && $firstEnd && in_array($firstDay->dayOfWeek, $selectedDayIds, true)) {
                        $firstPlannedVisit = $firstDay->format('D, M j, Y').' · '.\Carbon\Carbon::createFromFormat('H:i', $firstStart)->format('g:i A').'–'.\Carbon\Carbon::createFromFormat('H:i', $firstEnd)->format('g:i A');
                    }
                } catch (\Throwable) {
                    $firstPlannedVisit = '';
                }
            }
        @endphp

        <header class="hc-create-heading">
            <div>
                <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-create-back"><span aria-hidden="true">←</span> My requests</a>
                <h1>Request care</h1>
                <p>Share the help you need. Find a caregiver who feels right.</p>
            </div>
            <button type="button" x-data x-on:click="$dispatch('open-care-support-chat')" class="hc-care-text-link">Chat with support</button>
        </header>

        <ol class="hc-create-journey" aria-label="Finding care">
            <li aria-current="step"><span class="hc-create-journey-number">1</span><span>Care request</span></li>
            <li><span class="hc-create-journey-number">2</span><span>Find caregivers</span></li>
            <li><span class="hc-create-journey-number">3</span><span>Review applicants</span></li>
            <li><span class="hc-create-journey-number">4</span><span>Get started</span></li>
        </ol>

        <section>
            <form wire:submit="publish" class="hc-create-form">
              <div class="hc-create-fields">
               <section id="care-person" class="scroll-mt-24" aria-labelledby="care-person-heading">
                <div class="hc-create-panel">
                    <div class="hc-create-panel-heading">
                        <div>
                            <p class="hc-create-section-kicker">01 · Person & help</p>
                            <h2 id="care-person-heading" class="mt-1 font-display text-xl font-semibold">What kind of care would help?</h2>
                        </div>
                    </div>
                    <div class="hc-create-panel-body">

        @if ($lastRequestId || $hasSavedHouseholdProfile || $hasSavedRecipientProfile)
            <section class="hc-create-reuse">
                <details>
                    <summary class="cursor-pointer list-none font-semibold text-[#24302D] [&::-webkit-details-marker]:hidden">
                        <span>Use saved information <span class="font-normal text-[#485E53]">(optional)</span></span><span class="hc-create-disclosure-icon" aria-hidden="true">+</span>
                    </summary>
                    <div class="mt-3 space-y-3 border-t border-[#EFE6D8] pt-3">
                        <p class="text-sm text-[#485E53]">
                            Load care and address details from a previous request or your saved information. Check the person, notes and new schedule before publishing.
                        </p>
                        @if ($prefillApplied || $savedProfilesApplied)
                            <p class="text-xs font-semibold text-[#23483F]">
                                {{ $prefillApplied ? 'Last request loaded.' : 'Saved details loaded.' }} Check the schedule before publishing.
                            </p>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            @if ($lastRequestId)
                                <button type="button" wire:click="prefillFromLastRequest" class="hc-secondary-button">
                                    Use last request
                                </button>
                            @endif

                            @if ($hasSavedHouseholdProfile || $hasSavedRecipientProfile)
                                <button type="button" wire:click="applySavedProfiles" class="hc-secondary-button">
                                    Use saved details
                                </button>
                            @endif
                        </div>
                    </div>
                </details>

            </section>
        @endif

                    <div class="space-y-3">
                        <div class="hc-create-type-grid">
                            @foreach ($requestTypeOptions as $option)
                                @php $isRegularChoice = $option['value'] === \App\Models\CareRequest::TYPE_RECURRING; @endphp
                                <label class="hc-create-type-choice {{ $request_type === $option['value'] ? 'is-selected' : '' }}">
                                    <input type="radio" class="sr-only" value="{{ $option['value'] }}" wire:model.live="request_type">
                                    <span class="hc-create-radio-mark" aria-hidden="true">&#10003;</span>
                                    <span>
                                        <span class="hc-create-type-title">{{ $option['label'] }}</span>
                                        <span class="hc-create-type-description">
                                            {{ $isRegularChoice ? 'A repeating weekly schedule with a familiar caregiver.' : 'Care on one specific date and time.' }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @if (app(\App\Services\ContinuousCoverage\ContinuousCoverageAccess::class)->visibleInNavigation(auth()->user()))
                            <a href="{{ route('family.continuous-coverage.create') }}" wire:navigate class="hc-create-coverage-link">
                                <span>
                                    <span class="block text-lg font-semibold text-[#24302D]">Around-the-clock care</span>
                                    <span class="mt-1 block text-sm leading-5 text-[#485E53]">Build substantial coverage across several family-approved caregivers.</span>
                                </span>
                                <span class="shrink-0 text-sm font-semibold text-[#6A4E9A]">Set up →</span>
                            </a>
                        @endif

                        @error('request_type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="mt-5 space-y-5 border-t border-[#E4DDD3] pt-5">
                        <div>
                            <p class="text-sm font-medium text-[#24302D]">Who is receiving care?</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                @foreach ($careForOptions as $option)
                                    <label class="flex min-h-14 cursor-pointer items-center justify-center rounded-xl border px-4 text-sm font-semibold transition focus-within:ring-2 focus-within:ring-[#23483F] focus-within:ring-offset-2 {{ $care_for === $option['value'] ? 'border-[#23483F] bg-[#23483F] text-white' : 'border-[#DED6CA] bg-white text-[#23483F] hover:bg-[#F5F1EB]' }}">
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
                                        <p class="font-semibold text-[#24302D]">Use a saved care profile <span class="font-normal text-[#485E53]">(optional)</span></p>
                                        <p class="mt-1 text-sm text-[#485E53]">Choose the person once and we will share the same caregiver preview with this request.</p>
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
                                            class="rounded-xl border p-3 text-left transition {{ (int) $selected_care_recipient_profile_id === (int) $profile->id ? 'border-[#23483F] bg-[#F2F8F4] ring-1 ring-[#23483F]' : 'border-[#DED6CA] bg-white hover:bg-[#F5F1EB]' }}"
                                        >
                                            <span class="block font-semibold text-[#24302D]">{{ $profile->displayName() }}</span>
                                            <span class="mt-1 block text-xs text-[#485E53]">{{ $profile->relationship_to_family ?: 'Care recipient' }} · Reviewed {{ optional($profile->last_reviewed_at)->format('M j, Y') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @elseif (! $createQuickCareProfile)
                            <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                                <p class="font-semibold text-[#24302D]">Want to tell caregivers a little about this person?</p>
                                <p class="mt-1 text-sm text-[#485E53]">This is optional and takes about a minute.</p>
                                <button type="button" wire:click="$set('createQuickCareProfile', true)" class="hc-secondary-button mt-3">Add a simple care profile</button>
                            </div>
                        @endif

                        @if ($createQuickCareProfile && ! $selected_care_recipient_profile_id)
                            <div class="space-y-4 rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-[#24302D]">Simple care profile</p>
                                        <p class="mt-1 text-sm text-[#485E53]">Caregivers who can view this request will see these answers. You can add more later.</p>
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
                                    <input type="checkbox" wire:model="quick_profile_sharing_acknowledged" class="mt-1 rounded border-[#B7ADA0] text-[#23483F] focus:ring-[#23483F]">
                                    <span>I understand that these answers will be shared only with caregivers who can view this care request. Contact details, the exact address, and date of birth are not included.</span>
                                </label>
                                @error('quick_profile_sharing_acknowledged') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if ($this->recipientIsRequester)
                            <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] p-4 text-sm text-[#23483F]">
                                <p class="font-semibold">Care recipient: {{ $this->resolvedRecipientName }}</p>
                                <p class="mt-1 text-[#485E53]">This request will be marked as care for the person posting it.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input label="Family member receiving care" wire:model.blur="recipient_full_name" />
                                    @error('recipient_full_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-input label="Relationship to you" wire:model.blur="recipient_relationship_to_family" placeholder="Mother, father, spouse, friend" />
                                    @error('recipient_relationship_to_family') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif

                        <div>
                            <p class="text-sm font-medium text-[#24302D]">Help needed</p>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($taskOptions as $task)
                                    <label class="hc-create-task-choice {{ in_array((int) $task['id'], $selectedTaskIds, true) ? 'is-selected' : '' }}">
                                        <input type="checkbox" class="sr-only" value="{{ $task['id'] }}" wire:model.live="selectedTasks">
                                        <span class="hc-create-check-mark" aria-hidden="true">&#10003;</span>
                                        <span>{{ $task['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedTasks') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('selectedTasks.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>


                    </div>
                </div>
                </div>

               </section>
               <section id="care-schedule" class="scroll-mt-24" aria-labelledby="care-schedule-heading">
                <div class="hc-create-panel">
                    <div class="hc-create-panel-heading">
                        <div>
                            <p class="hc-create-section-kicker">02 · Schedule</p>
                            <h2 id="care-schedule-heading" class="mt-1 font-display text-xl font-semibold">{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring care schedule' : 'Visit schedule' }}</h2>
                        </div>
                    </div>
                    <div class="hc-create-panel-body">

                    <div class="space-y-5">
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
                            <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] px-4 py-3 text-sm text-[#23483F]">
                                <span class="font-semibold">Schedule:</span> {{ $this->scheduleSummary }}
                            </div>
                        @else
                            <div class="space-y-5">
                                <div>
                                    <p class="text-base font-semibold text-[#24302D]">Which days each week?</p>
                                    <p class="mt-1 text-sm text-[#485E53]">Choose the days first, then set the time for each day.</p>
                                    <div class="hc-create-days">
                                        @foreach ($dayOptions as $day)
                                            <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border px-2 text-base font-semibold transition focus-within:ring-2 focus-within:ring-[#23483F] focus-within:ring-offset-2 {{ in_array((int) $day['value'], $selectedDayIds, true) ? 'border-[#23483F] bg-[#23483F] text-white' : 'border-[#DED6CA] bg-white text-[#23483F] hover:bg-[#F5F1EB]' }}">
                                                <input type="checkbox" class="sr-only" value="{{ $day['value'] }}" wire:model.live="recurring_days">
                                                {{ $day['label'] }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('recurring_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    @error('recurring_days.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                @if ($selectedDayIds !== [])
                                    <div class="space-y-3" role="region" aria-label="Weekly visit times">
                                        <div>
                                            <p class="text-base font-semibold text-[#24302D]">What time on each day?</p>
                                            <p class="mt-1 text-sm text-[#485E53]">Each day can have a different start time and visit length.</p>
                                        </div>

                                        @foreach ($selectedDayIds as $dayId)
                                            @php
                                                $dayLabel = $dayOptionsByValue->get($dayId)['label'] ?? 'Selected day';
                                                $scheduleRow = $recurring_schedule[(string) $dayId] ?? $recurring_schedule[$dayId] ?? [];
                                                $endTime = \App\Support\WeeklySchedule::normalizeTime($scheduleRow['end_time'] ?? null);
                                            @endphp
                                            <div
                                                wire:key="recurring-day-{{ $dayId }}"
                                                x-data="{
                                                    startTime: @js((string) ($scheduleRow['start_time'] ?? '')),
                                                    durationMinutes: @js((string) ($scheduleRow['duration_minutes'] ?? '60')),
                                                    syncSlot() {
                                                        this.$nextTick(() => this.$wire.updateRecurringScheduleSlot(
                                                            {{ $dayId }},
                                                            this.startTime,
                                                            this.durationMinutes
                                                        ));
                                                    }
                                                }"
                                                class="rounded-xl border border-[#DED6CA] bg-[#FCFAF7] p-4"
                                            >
                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(8rem,0.7fr)_1fr_1fr] md:items-end">
                                                    <div>
                                                        <p class="text-lg font-display font-semibold text-[#24302D]">{{ $dayLabel }}</p>
                                                        @if ($endTime)
                                                            <p class="mt-1 text-sm font-semibold text-[#0F6B5B]">Ends at {{ \Carbon\Carbon::createFromFormat('H:i', $endTime)->format('g:i A') }}</p>
                                                        @else
                                                            <p class="mt-1 text-sm text-[#485E53]">Choose a start and length</p>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <x-input
                                                            type="time"
                                                            id="recurring-start-{{ $dayId }}"
                                                            label="Starts at"
                                                            x-model="startTime"
                                                            x-on:change="syncSlot()"
                                                        />
                                                        @error('recurring_schedule.'.$dayId.'.start_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                    </div>
                                                    <div>
                                                        <x-native-select-field
                                                            id="recurring-duration-{{ $dayId }}"
                                                            label="Visit length"
                                                            x-model="durationMinutes"
                                                            x-on:change="syncSlot()"
                                                            :options="$durationOptions"
                                                        />
                                                        @error('recurring_schedule.'.$dayId.'.duration_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                        @error('recurring_schedule.'.$dayId.'.end_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input type="date" label="When should this schedule begin?" min="{{ $this->minimumStartDate }}" wire:model.change="recurring_starts_on" />
                                        @error('recurring_starts_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @if ($recurring_start_adjustment_message !== '')
                                            <p class="mt-2 text-sm font-semibold text-[#0F6B5B]">{{ $recurring_start_adjustment_message }}</p>
                                        @endif
                                    </div>
                                    <fieldset>
                                        <legend class="text-base font-semibold text-[#24302D]">How long should this repeat?</legend>
                                        <div class="mt-2 grid gap-2">
                                            <label class="flex min-h-12 items-center gap-3 rounded-md border border-[#DED6CA] bg-white px-4 text-base"><input type="radio" value="ongoing" wire:model.live="recurring_end_choice" class="h-5 w-5">Until I stop it</label>
                                            <label class="flex min-h-12 items-center gap-3 rounded-md border border-[#DED6CA] bg-white px-4 text-base"><input type="radio" value="date" wire:model.live="recurring_end_choice" class="h-5 w-5">End on a date</label>
                                        </div>
                                    </fieldset>
                                    @if ($recurring_end_choice === 'date')
                                        <div class="md:col-start-2">
                                            <x-input type="date" label="Last day" min="{{ $recurring_starts_on ?: $this->minimumStartDate }}" wire:model.change="recurring_ends_on" />
                                            @error('recurring_ends_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                </div>
                                <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] px-4 py-3 text-sm text-[#23483F]">
                                    <span class="font-semibold">Schedule:</span> {{ $this->scheduleSummary }}
                                    <p class="mt-2 font-semibold">{{ $recurringBoundary }}</p>
                                    @if ($firstPlannedVisit !== '')
                                        <p class="mt-2"><span class="font-semibold">First planned visit:</span> {{ $firstPlannedVisit }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                </div>

               </section>
               <section id="care-location" class="scroll-mt-24" aria-labelledby="care-location-heading">
                <div class="hc-create-panel">
                    <div class="hc-create-panel-heading">
                        <div>
                            <p class="hc-create-section-kicker">03 · Location & notes</p>
                            <h2 id="care-location-heading" class="mt-1 font-display text-xl font-semibold">Where will care happen?</h2>
                            <p class="text-sm text-[#485E53]">Caregivers see the area before applying. Exact details stay with the request.</p>
                        </div>
                    </div>
                    <div class="hc-create-panel-body">

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <x-input label="Street address" wire:model.blur="address_line1" />
                            @error('address_line1') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <x-input label="Apartment, unit, or gate (optional)" wire:model.blur="address_line2" />
                            @error('address_line2') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="City" wire:model.blur="city" />
                            @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-native-select-field
                                label="State"
                                wire:model.blur="state"
                                :options="collect($usStates)->map(fn($label, $value) => ['label' => $label, 'value' => $value])->values()->all()"
                            />
                            @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="ZIP" wire:model.blur="zip" />
                            @error('zip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                        <details class="hc-create-notes" {{ $hasMoreDetails ? 'open' : '' }}>
                            <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-display text-base font-semibold text-[#24302D]">Care notes & arrival details</p>
                                        <p class="mt-1 text-sm text-[#485E53]">Routines, home access, timing and another contact.</p>
                                    </div>
                                    <span class="hc-create-disclosure-icon" aria-hidden="true">+</span>
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
                                    <input type="checkbox" class="mt-1 rounded border-[#B7ADA0] text-[#23483F] focus:ring-[#23483F]" wire:model.live="includeThirdPartyContact">
                                    <span>
                                        <span class="block font-semibold text-[#24302D]">Add another contact</span>
                                        <span class="mt-1 block text-sm text-[#485E53]">Use this if a family member or helper should also be reachable.</span>
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
                </div>

               </section>
              </div>
              <section id="care-review" class="hc-create-review scroll-mt-24" aria-labelledby="care-review-heading">
                @include('livewire.family.partials.care-request-review')
              </section>
            </form>
        </section>
    </div>
</div>
