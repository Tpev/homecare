<div>
    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <section class="hc-brand-panel relative overflow-hidden">
            <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>
            <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-[#4F6FAF]/20 blur-2xl"></div>

            <div class="relative mt-3 flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-2xl">
                    <p class="hc-brand-kicker inline-flex rounded-full px-3 py-1">Family request wizard</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Create a care request in small, clear steps.</h1>
                    <p class="mt-3 text-sm text-[#E5E7EB]">
                        We only ask what matters now. Optional details come last so you can publish faster.
                    </p>
                </div>
                @if ($modeChosen)
                    <span class="rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-sm text-white">Step {{ $step }} of {{ $totalSteps }}</span>
                @endif
            </div>
            @if ($modeChosen)
                <div class="relative mt-4">
                    <div class="h-2 rounded-full bg-white/20">
                        <div class="h-2 rounded-full bg-[#CFC6F7] transition-all duration-300" style="width: {{ $this->progressPercent }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-[#E5E7EB]">{{ $this->progressPercent }}% complete</p>
                    <div class="mt-2 flex items-center gap-2">
                        @for ($index = 1; $index <= $totalSteps; $index++)
                            <span class="h-1.5 flex-1 rounded-full {{ $step >= $index ? 'bg-[#CFC6F7]' : 'bg-white/25' }}"></span>
                        @endfor
                    </div>
                </div>
            @else
                <p class="relative mt-4 text-xs text-[#CFC6F7]">Choose a mode to start. You can publish in minutes.</p>
            @endif
        </section>

        @if (! $modeChosen)
            <section class="hc-brand-card sm:p-6">
                <div class="mb-4">
                    <p class="text-xs uppercase tracking-[0.14em] text-[#7C5DDC]">Start here</p>
                    <h2 class="mt-1 text-xl font-display font-semibold text-[#0F172A]">Choose how you want to post this request</h2>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <button type="button" wire:click="chooseFastTrack" class="group rounded-[1.6rem] border border-[#9ED8C6] bg-[#FFFCF8] p-6 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-xs uppercase tracking-[0.14em] text-[#0F3D3E] font-semibold">Recommended</p>
                        <h3 class="mt-2 text-2xl font-display font-semibold text-[#0F172A]">Fast Track</h3>
                        <p class="mt-2 text-sm text-[#5B6472]">Essential fields only. Great when you want to post quickly and start receiving applicants fast.</p>
                        <ul class="mt-4 space-y-1 text-xs text-[#5B6472]">
                            <li>Schedule window</li>
                            <li>Address and city</li>
                            <li>Services and recipient name</li>
                        </ul>
                        <p class="mt-4 text-sm font-semibold text-[#0F3D3E] group-hover:text-[#0A2F31]">Start fast track →</p>
                    </button>

                    <button type="button" wire:click="chooseCompleteSetup" class="group rounded-[1.6rem] border border-[#DED6CA] bg-[#FFFCF8] p-6 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-xs uppercase tracking-[0.14em] text-[#7C5DDC] font-semibold">More control</p>
                        <h3 class="mt-2 text-2xl font-display font-semibold text-[#0F172A]">Detailed Request</h3>
                        <p class="mt-2 text-sm text-[#5B6472]">Add extra context for tighter matching, including optional recipient and access details.</p>
                        <ul class="mt-4 space-y-1 text-xs text-[#5B6472]">
                            <li>Optional access notes</li>
                            <li>Recipient context and health details</li>
                            <li>Extra matching preferences</li>
                        </ul>
                        <p class="mt-4 text-sm font-semibold text-[#4F6FAF] group-hover:text-[#35548A]">Start detailed request →</p>
                    </button>
                </div>
            </section>
        @else
            @if ($lastRequestId)
                <div class="hc-brand-card flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.14em] text-[#7C5DDC] font-semibold">Quick start</p>
                        <p class="mt-1 text-sm text-[#3C4A5B]">
                            Reuse your last request:
                            <span class="font-semibold">{{ $lastRequestSummary['title'] ?? 'Recent request' }}</span>
                            ({{ $lastRequestSummary['location'] ?? 'Raleigh, NC' }})
                        </p>
                        @if ($prefillApplied)
                            <p class="mt-1 text-xs text-[#7C5DDC]">Fields loaded. Confirm schedule and publish.</p>
                        @endif
                    </div>
                    <button type="button" wire:click="prefillFromLastRequest" class="hc-secondary-button w-full sm:w-auto">Use last request</button>
                </div>
            @endif

            @if ($hasSavedHouseholdProfile || $hasSavedRecipientProfile)
                <div class="hc-brand-card flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.14em] text-[#0F3D3E] font-semibold">Saved family profile</p>
                        <p class="mt-1 text-sm text-[#3C4A5B]">
                            Reuse saved household + care recipient details in one click.
                        </p>
                        @if ($savedProfilesApplied)
                            <p class="mt-1 text-xs text-[#0F3D3E]">Saved profile loaded.</p>
                        @endif
                    </div>
                    <button type="button" wire:click="applySavedProfiles" class="hc-secondary-button w-full sm:w-auto">Use saved profiles</button>
                </div>
            @endif

            <x-card>
            <x-slot:header>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7C5DDC]">Guided flow</p>
                        <p class="text-xs font-semibold text-[#5B6472]">Step {{ $step }} / {{ $totalSteps }}</p>
                    </div>
                    <div class="flex snap-x gap-2 overflow-x-auto pb-1">
                        @foreach ([1 => 'Care need', 2 => 'Schedule + location', 3 => 'Recipient + contacts', 4 => 'Review + publish'] as $index => $label)
                            <div class="min-w-[160px] shrink-0 snap-start rounded-lg border px-3 py-2 text-xs sm:text-sm {{ $step >= $index ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-[#FFFCF8] text-[#5B6472]'  }}">
                                {{ $label }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-slot:header>

            <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="hc-metric-card">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-[#7C5DDC]">Type</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F3D3E]">{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}</p>
                </div>
                <div class="hc-metric-card">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-[#7C5DDC]">Services</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F3D3E]">{{ count($selectedTasks) }}</p>
                </div>
                <div class="hc-metric-card">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-[#7C5DDC]">Location</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F3D3E]">{{ trim($city) !== '' && trim($state) !== '' ? $city.', '.$state : 'Pending' }}</p>
                </div>
                <div class="hc-metric-card">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-[#7C5DDC]">Target reply</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F3D3E]">{{ $preferred_response_hours }}h</p>
                </div>
            </div>

            @if ($step === 1)
                <div class="hc-wizard-step space-y-5">
                    <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-3 text-sm text-[#3C4A5B]">
                        Start with your care need. Keep it simple. We will handle schedule and location in the next step.
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                            <x-textarea
                                label="What help do you need?"
                                wire:model="additional_info"
                                hint="Example: Need someone for my mom tomorrow from 9am to 1pm for companionship and meal prep."
                            />
                            @error('additional_info') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @endif

                        <x-select.styled
                            label="Request type"
                            wire:model.live="request_type"
                            :options="$requestTypeOptions"
                        />
                        @error('request_type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <x-select.styled
                            wire:model="selectedTasks"
                            multiple
                            label="Services needed"
                            :options="collect($taskOptions)->map(fn($item)=>['label'=>$item['name'],'value'=>$item['id']])->values()->all()"
                        />
                        @error('selectedTasks') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                        <details class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Optional now: add a custom request title</summary>
                            <div class="mt-4">
                                <x-input label="Request title (optional)" wire:model="title" hint="If empty, HomeCare creates one automatically." />
                                @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </details>
                    @endif
                </div>
            @elseif ($step === 2)
                <div class="hc-wizard-step space-y-5">
                    <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-3 text-sm text-[#3C4A5B]">
                        {{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring request selected. We only ask recurring schedule fields.' : 'One-time request selected. We only ask one start/end window.' }}
                    </div>

                    @if ($request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input type="datetime-local" label="Start" wire:model="requested_start_at" />
                                @error('requested_start_at') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input type="datetime-local" label="End" wire:model="requested_end_at" />
                                @error('requested_end_at') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @else
                        <div class="space-y-4 rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-4">
                            <x-select.styled
                                wire:model="recurring_days"
                                multiple
                                label="Days of week"
                                :options="$dayOptions"
                            />
                            @error('recurring_days') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('recurring_days.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input type="time" label="Start time" wire:model="recurring_start_time" />
                                    @error('recurring_start_time') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-input type="time" label="End time" wire:model="recurring_end_time" />
                                    @error('recurring_end_time') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-input type="date" label="Starts on" wire:model="recurring_starts_on" />
                                    @error('recurring_starts_on') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                                    <div>
                                        <x-input type="date" label="Ends on (optional)" wire:model="recurring_ends_on" />
                                        @error('recurring_ends_on') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input label="Address line 1" wire:model="address_line1" />
                            @error('address_line1') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                            <div>
                                <x-input label="Address line 2 (optional)" wire:model="address_line2" />
                                @error('address_line2') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                        <div>
                            <x-input label="City" wire:model="city" />
                            @error('city') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-select.styled
                                label="State"
                                wire:model="state"
                                :options="collect($usStates)->map(fn($label,$value)=>['label'=>$label,'value'=>$value])->values()->all()"
                            />
                            @error('state') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="ZIP" wire:model="zip" />
                            @error('zip') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                            <div>
                                <x-input
                                    type="number"
                                    min="1"
                                    max="72"
                                    label="Preferred response (hours)"
                                    wire:model="preferred_response_hours"
                                />
                                @error('preferred_response_hours') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="rounded-[1rem] border border-[#9ED8C6] bg-[#F3FBF7] p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#0F3D3E] font-semibold">
                            @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                Estimated weekly cost
                            @else
                                Estimated one-time cost
                            @endif
                        </p>

                        @if ($this->estimatedCost !== null && $this->estimatedHours !== null)
                            <p class="mt-1 text-sm text-[#3C4A5B]">
                                <span class="font-semibold">{{ number_format($this->estimatedHours, 2) }}h</span>
                                x
                                <span class="font-semibold">${{ number_format($this->estimateHourlyRate, 2) }}/hr</span>
                                =
                                <span class="font-semibold">${{ number_format($this->estimatedCost, 2) }}</span>
                                @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                    /week
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm text-[#3C4A5B]">Set your schedule to preview estimated cost.</p>
                        @endif

                        <p class="mt-1 text-xs text-[#3C4A5B]">Final total is based on confirmed worked time.</p>
                    </div>
                </div>
            @elseif ($step === 3)
                <div class="hc-wizard-step space-y-5">
                    <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-3 text-sm text-[#3C4A5B]">
                        Add who receives care. This helps caregivers decide quickly if they are a good fit.
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input label="Recipient full name" wire:model="recipient_full_name" />
                            @error('recipient_full_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                            <div>
                                <x-input label="Relationship to you (optional)" wire:model="recipient_relationship_to_family" hint="Mother, father, self, etc." />
                                @error('recipient_relationship_to_family') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                        <x-textarea label="Recipient care notes (optional)" wire:model="recipient_care_notes" />
                        @error('recipient_care_notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif

                    @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                        <details class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Optional: add health context</summary>
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <x-input type="date" label="Date of birth" wire:model="recipient_date_of_birth" />
                                    @error('recipient_date_of_birth') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-select.styled label="Gender" wire:model="recipient_gender" :options="$genderOptions" />
                                    @error('recipient_gender') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <x-select.styled label="Mobility level" wire:model="recipient_mobility_level" :options="$mobilityOptions" />
                                    @error('recipient_mobility_level') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </details>
                    @endif

                    @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                        <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-4">
                            <x-checkbox label="Booked by a third-party contact" wire:model="includeThirdPartyContact" />

                            @if ($includeThirdPartyContact)
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-input label="Contact full name" wire:model="third_party_full_name" />
                                        @error('third_party_full_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-input label="Relationship to recipient" wire:model="third_party_relationship_to_recipient" />
                                        @error('third_party_relationship_to_recipient') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-input label="Phone" wire:model="third_party_phone" />
                                        @error('third_party_phone') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <x-input type="email" label="Email (optional)" wire:model="third_party_email" />
                                        @error('third_party_email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="hc-wizard-step space-y-4">
                    @php
                        $taskLookup = collect($taskOptions)->keyBy(fn ($task) => (int) $task['id']);
                        $selectedTaskNames = collect($selectedTasks)
                            ->map(fn ($id) => $taskLookup->get((int) $id)['name'] ?? null)
                            ->filter()
                            ->values();
                    @endphp

                    <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-4">
                        <p class="font-display text-lg font-semibold text-[#0F172A]">{{ $this->resolvedTitle }}</p>
                        <p class="mt-1 text-sm text-[#5B6472]">{{ $city }}, {{ $state }} {{ $zip }}</p>
                        <p class="mt-1 text-xs text-[#7C5DDC]">Response target: {{ $preferred_response_hours }}h</p>

                        @if ($request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            <p class="mt-2 text-sm text-[#3C4A5B]">
                                One-time:
                                {{ $requested_start_at ? \Illuminate\Support\Carbon::parse($requested_start_at)->format('M d, Y H:i') : '-' }}
                                to
                                {{ $requested_end_at ? \Illuminate\Support\Carbon::parse($requested_end_at)->format('M d, Y H:i') : '-' }}
                            </p>
                        @else
                            <p class="mt-2 text-sm text-[#3C4A5B]">
                                Recurring:
                                {{ collect($dayOptions)->whereIn('value', collect($recurring_days)->map(fn($d)=>(int)$d)->all())->pluck('label')->implode(', ') ?: '-' }}
                                {{ $recurring_start_time }}-{{ $recurring_end_time }}
                            </p>
                        @endif
                    </div>

                    <div class="rounded-[1rem] border border-[#9ED8C6] bg-[#F3FBF7] p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#0F3D3E] font-semibold">
                            @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                Estimated weekly cost
                            @else
                                Estimated one-time cost
                            @endif
                        </p>
                        @if ($this->estimatedCost !== null && $this->estimatedHours !== null)
                            <p class="mt-1 text-sm text-[#3C4A5B]">
                                {{ number_format($this->estimatedHours, 2) }}h x ${{ number_format($this->estimateHourlyRate, 2) }}/hr
                                = <span class="font-semibold">${{ number_format($this->estimatedCost, 2) }}</span>
                                @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                    /week
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm text-[#3C4A5B]">Schedule incomplete. Add schedule details before publish.</p>
                        @endif
                    </div>

                    <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-4">
                        <p class="text-sm"><span class="font-medium">Care need:</span> {{ $additional_info ?: 'No additional note provided.' }}</p>
                        <p class="mt-2 text-sm"><span class="font-medium">Services:</span> {{ $selectedTaskNames->implode(', ') ?: 'None selected' }}</p>
                        <p class="mt-2 text-sm"><span class="font-medium">Recipient:</span> {{ $recipient_full_name ?: 'Care recipient' }} ({{ $recipient_relationship_to_family ?: 'Family member' }})</p>
                    </div>

                    @if ($request_mode === \App\Livewire\Family\CreateCareRequestWizard::MODE_COMPLETE_SETUP)
                        <details class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Optional: refine matching details before publish</summary>
                            <div class="mt-4 space-y-4">
                                <x-textarea label="Scope of work (optional)" wire:model="scope_of_work" hint="If empty, this will be generated from selected services." />
                                @error('scope_of_work') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                                <x-input label="Time expectations (optional)" wire:model="time_expectations" hint="Example: Arrive 10 minutes early." />
                                @error('time_expectations') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                                <x-textarea label="Home access notes (optional)" wire:model="home_access_notes" hint="Gate, parking, lockbox, etc." />
                                @error('home_access_notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </details>
                    @endif

                    <div class="rounded-[1rem] border border-[#D7C8A2] bg-[#FFF7E7] px-4 py-3 text-sm text-[#7A5A15]">
                        Once published, active caregivers can apply and you can invite specific profiles directly.
                    </div>
                </div>
            @endif

            <x-slot:footer>
                <div class="sticky bottom-2 rounded-2xl border border-[#DED6CA] bg-white/95 p-2 shadow-lg backdrop-blur-sm sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" wire:click="previousStep" :disabled="$step === 1" class="hc-secondary-button w-full sm:w-auto" wire:loading.attr="disabled" wire:target="previousStep,nextStep,publish">Back</button>
                    @if ($step < $totalSteps)
                        <button type="button" wire:click="nextStep" class="hc-primary-button w-full sm:w-auto" wire:loading.attr="disabled" wire:target="nextStep,publish,previousStep">
                            Continue
                        </button>
                    @else
                        <button type="button" wire:click="publish" class="hc-primary-button w-full sm:w-auto" wire:loading.attr="disabled" wire:target="publish,nextStep,previousStep">
                            Publish request
                        </button>
                    @endif
                    </div>
                </div>
            </x-slot:footer>
        </x-card>
        @endif
    </div>
</div>





