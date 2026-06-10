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
            $dayLabels = collect($dayOptions)
                ->whereIn('value', $selectedDayIds)
                ->pluck('label')
                ->implode(', ');
            $scheduleLine = $this->scheduleSummary;
        @endphp

        <section class="hc-brand-panel">
            <div class="relative grid grid-cols-1 gap-5 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <p class="hc-brand-kicker text-[#E8E0FF]">New care request</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Tell us what care you need.</h1>
                    <p class="mt-2 max-w-2xl text-sm text-[#F7F1E8]/82">
                        A short request is enough to start. LoLo uses your answers to show caregivers the schedule, location, and care needs clearly.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:col-span-2">
                    <div class="hc-brand-stat">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Type</p>
                        <p class="mt-1 text-lg font-semibold">{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}</p>
                    </div>
                    <div class="hc-brand-stat">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Tasks</p>
                        <p class="mt-1 text-lg font-semibold">{{ count($selectedTaskIds) }}</p>
                    </div>
                    <div class="col-span-2 hc-brand-stat">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Location</p>
                        <p class="mt-1 text-sm font-semibold">{{ trim($city) !== '' && trim($state) !== '' ? $city.', '.$state : 'Add the care address' }}</p>
                    </div>
                </div>
            </div>
        </section>

        @if ($lastRequestId || $hasSavedHouseholdProfile || $hasSavedRecipientProfile)
            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @if ($lastRequestId)
                    <div class="hc-brand-card flex flex-col justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-[#7C5DDC] font-semibold">Recent request</p>
                            <p class="mt-1 text-sm text-[#3C4A5B]">
                                Use details from <span class="font-semibold">{{ $lastRequestSummary['title'] ?? 'your last request' }}</span>.
                            </p>
                            @if ($prefillApplied)
                                <p class="mt-1 text-xs text-[#0F3D3E]">Loaded. Check the schedule before publishing.</p>
                            @endif
                        </div>
                        <button type="button" wire:click="prefillFromLastRequest" class="hc-secondary-button w-full sm:w-auto">Use last request</button>
                    </div>
                @endif

                @if ($hasSavedHouseholdProfile || $hasSavedRecipientProfile)
                    <div class="hc-brand-card flex flex-col justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-[#0F3D3E] font-semibold">Saved family details</p>
                            <p class="mt-1 text-sm text-[#3C4A5B]">
                                Fill in the care address and recipient details you saved before.
                            </p>
                            @if ($savedProfilesApplied)
                                <p class="mt-1 text-xs text-[#0F3D3E]">Saved details loaded.</p>
                            @endif
                        </div>
                        <button type="button" wire:click="applySavedProfiles" class="hc-secondary-button w-full sm:w-auto">Use saved details</button>
                    </div>
                @endif
            </section>
        @endif

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <form wire:submit="publish" class="space-y-5 xl:col-span-8">
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

                        <div>
                            <x-textarea
                                label="Anything important the caregiver should know?"
                                wire:model="additional_info"
                                placeholder="Example: Mom likes a quiet morning routine and needs help with lunch."
                            />
                            @error('additional_info') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <div>
                            <h2 class="font-display text-lg font-semibold">When should care happen?</h2>
                            <p class="text-sm text-[#607080]">Pick the day, start time, and how long the caregiver should stay. We calculate the end time.</p>
                        </div>
                    </x-slot:header>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($requestTypeOptions as $option)
                                <label class="flex min-h-14 cursor-pointer items-center justify-center rounded-xl border px-4 text-sm font-semibold transition {{ $request_type === $option['value'] ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
                                    <input type="radio" class="sr-only" value="{{ $option['value'] }}" wire:model.live="request_type">
                                    {{ $option['label'] === 'Recurring job' ? 'Repeat every week' : 'One visit' }}
                                </label>
                            @endforeach
                        </div>
                        @error('request_type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        @if ($request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <x-input type="date" label="Starting day" min="{{ $this->minimumStartDate }}" wire:model.live="requested_start_date" />
                                    @error('requested_start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-input type="time" label="Starting time" wire:model.live="requested_start_time" />
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
                                    <p class="text-sm font-medium text-[#324457]">Care days</p>
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                                        @foreach ($dayOptions as $day)
                                            <label class="flex h-12 cursor-pointer items-center justify-center rounded-xl border text-sm font-semibold transition {{ in_array((int) $day['value'], $selectedDayIds, true) ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
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
                                        <x-input type="date" label="Starting day" min="{{ $this->minimumStartDate }}" wire:model.live="recurring_starts_on" />
                                        @error('recurring_starts_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-input type="time" label="Starting time" wire:model.live="recurring_start_time" />
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
                                    <div>
                                        <x-input type="date" label="Stop repeating on (optional)" min="{{ $this->minimumStartDate }}" wire:model="recurring_ends_on" />
                                        @error('recurring_ends_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
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

            <aside class="space-y-5 xl:col-span-4">
                <x-card>
                    <x-slot:header>
                        <h2 class="font-display text-lg font-semibold">Request summary</h2>
                    </x-slot:header>

                    <div class="space-y-4 text-sm">
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Title</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $this->resolvedTitle }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">For</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $this->resolvedRecipientName }}</p>
                            <p class="text-[#607080]">{{ $this->resolvedRecipientRelationship }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Schedule</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ trim($scheduleLine) !== '' ? $scheduleLine : 'Schedule not set' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Services</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $selectedTaskNames->implode(', ') ?: 'Choose at least one' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Address</p>
                            <p class="mt-1 font-semibold text-[#17313F]">
                                {{ trim($address_line1) !== '' ? $address_line1 : 'Street address' }}
                            </p>
                            <p class="text-[#607080]">{{ trim($city.', '.$state.' '.$zip) !== ',' ? trim($city.', '.$state.' '.$zip) : 'City, state, ZIP' }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <h2 class="font-display text-lg font-semibold">
                            {{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Estimated weekly cost' : 'Estimated one-time cost' }}
                        </h2>
                    </x-slot:header>

                    @if ($this->estimatedCost !== null && $this->estimatedHours !== null)
                        <div class="space-y-2 text-sm text-[#3C4A5B]">
                            <p>
                                <span class="font-semibold">{{ number_format($this->estimatedHours, 2) }}h</span>
                                x
                                <span class="font-semibold">${{ number_format($this->estimateHourlyRate, 2) }}/hr</span>
                            </p>
                            <p class="font-display text-3xl font-semibold text-[#0F3D3E]">
                                ${{ number_format($this->estimatedCost, 2) }}
                                @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                    <span class="text-base font-sans font-medium">/week</span>
                                @endif
                            </p>
                        </div>
                    @else
                        <p class="text-sm text-[#607080]">Add the schedule to see the estimate.</p>
                    @endif
                </x-card>
            </aside>
        </section>
    </div>
</div>
