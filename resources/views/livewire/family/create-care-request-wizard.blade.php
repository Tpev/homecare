<div>
    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <section class="relative overflow-hidden rounded-3xl border border-slate-900/80 bg-slate-950 p-5 text-white shadow-xl">
            <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-cyan-500/20 blur-2xl"></div>
            <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>

            <div class="relative mt-3 flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-2xl">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-slate-300">Family Request Wizard</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Create a care request in small, clear steps.</h1>
                    <p class="mt-2 text-sm text-slate-300">
                        We only ask what matters now. Optional details come last so you can publish faster.
                    </p>
                </div>
                <span class="rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-sm text-white">Step {{ $step }} of {{ $totalSteps }}</span>
            </div>
            <div class="relative mt-4">
                <div class="h-2 rounded-full bg-white/20">
                    <div class="h-2 rounded-full bg-cyan-300 transition-all duration-300" style="width: {{ $this->progressPercent }}%"></div>
                </div>
                <p class="mt-2 text-xs text-slate-300">{{ $this->progressPercent }}% complete</p>
                <div class="mt-2 flex items-center gap-2">
                    @for ($index = 1; $index <= $totalSteps; $index++)
                        <span class="h-1.5 flex-1 rounded-full {{ $step >= $index ? 'bg-cyan-300' : 'bg-white/25' }}"></span>
                    @endfor
                </div>
            </div>
        </section>

        @if ($lastRequestId)
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-cyan-700 font-semibold">Quick start</p>
                    <p class="mt-1 text-sm text-cyan-900">
                        Reuse your last request:
                        <span class="font-semibold">{{ $lastRequestSummary['title'] ?? 'Recent request' }}</span>
                        ({{ $lastRequestSummary['location'] ?? 'Raleigh, NC' }})
                    </p>
                    @if ($prefillApplied)
                        <p class="mt-1 text-xs text-cyan-700">Fields loaded. Confirm schedule and publish.</p>
                    @endif
                </div>
                <button type="button" wire:click="prefillFromLastRequest">
                    <x-button color="blue" light>Use last request</x-button>
                </button>
            </div>
        @endif

        <x-card>
            <x-slot:header>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Guided flow</p>
                        <p class="text-xs font-semibold text-slate-600">Step {{ $step }} / {{ $totalSteps }}</p>
                    </div>
                    <div class="flex snap-x gap-2 overflow-x-auto pb-1">
                        @foreach ([1 => 'Care need', 2 => 'Schedule + location', 3 => 'Recipient + contacts', 4 => 'Review + publish'] as $index => $label)
                            <div class="min-w-[160px] shrink-0 snap-start rounded-lg border px-3 py-2 text-xs sm:text-sm {{ $step >= $index ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                                {{ $label }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-slot:header>

            <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-slate-500">Type</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-slate-500">Services</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ count($selectedTasks) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-slate-500">Location</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ trim($city) !== '' && trim($state) !== '' ? $city.', '.$state : 'Pending' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-[0.12em] text-slate-500">Target reply</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $preferred_response_hours }}h</p>
                </div>
            </div>

            @if ($step === 1)
                <div class="hc-wizard-step space-y-5">
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
                        Start with your care need. Keep it simple. We will handle schedule and location in the next step.
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <x-textarea
                            label="What help do you need?"
                            wire:model="additional_info"
                            hint="Example: Need someone for my mom tomorrow from 9am to 1pm for companionship and meal prep."
                        />
                        @error('additional_info') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

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

                    <details class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Optional now: add a custom request title</summary>
                        <div class="mt-4">
                            <x-input label="Request title (optional)" wire:model="title" hint="If empty, HomeCare creates one automatically." />
                            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </details>
                </div>
            @elseif ($step === 2)
                <div class="hc-wizard-step space-y-5">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
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
                        <div class="space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
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
                                <div>
                                    <x-input type="date" label="Ends on (optional)" wire:model="recurring_ends_on" />
                                    @error('recurring_ends_on') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input label="Address line 1" wire:model="address_line1" />
                            @error('address_line1') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="Address line 2 (optional)" wire:model="address_line2" />
                            @error('address_line2') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
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
                    </div>

                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-emerald-700 font-semibold">
                            @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                Estimated weekly cost
                            @else
                                Estimated one-time cost
                            @endif
                        </p>

                        @if ($this->estimatedCost !== null && $this->estimatedHours !== null)
                            <p class="mt-1 text-sm text-emerald-900">
                                <span class="font-semibold">{{ number_format($this->estimatedHours, 2) }}h</span>
                                ×
                                <span class="font-semibold">${{ number_format($this->estimateHourlyRate, 2) }}/hr</span>
                                =
                                <span class="font-semibold">${{ number_format($this->estimatedCost, 2) }}</span>
                                @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                    /week
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm text-emerald-900">Set your schedule to preview estimated cost.</p>
                        @endif

                        <p class="mt-1 text-xs text-emerald-700">Final total is based on confirmed worked time.</p>
                    </div>
                </div>
            @elseif ($step === 3)
                <div class="hc-wizard-step space-y-5">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        Recipient details are optional except when a third-party contact is booking on their behalf.
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input label="Recipient full name (optional)" wire:model="recipient_full_name" />
                            @error('recipient_full_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input label="Relationship to you (optional)" wire:model="recipient_relationship_to_family" hint="Mother, father, self, etc." />
                            @error('recipient_relationship_to_family') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <x-textarea label="Recipient care notes (optional)" wire:model="recipient_care_notes" />
                    @error('recipient_care_notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <details class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Optional: add health context</summary>
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

                    <div class="rounded-lg border border-slate-200 p-4">
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

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="font-semibold text-slate-900">{{ $this->resolvedTitle }}</p>
                        <p class="text-sm text-slate-600 mt-1">{{ $city }}, {{ $state }} {{ $zip }}</p>
                        <p class="text-xs text-slate-500 mt-1">Response target: {{ $preferred_response_hours }}h</p>

                        @if ($request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            <p class="mt-2 text-sm text-slate-700">
                                One-time:
                                {{ $requested_start_at ? \Illuminate\Support\Carbon::parse($requested_start_at)->format('M d, Y H:i') : '-' }}
                                to
                                {{ $requested_end_at ? \Illuminate\Support\Carbon::parse($requested_end_at)->format('M d, Y H:i') : '-' }}
                            </p>
                        @else
                            <p class="mt-2 text-sm text-slate-700">
                                Recurring:
                                {{ collect($dayOptions)->whereIn('value', collect($recurring_days)->map(fn($d)=>(int)$d)->all())->pluck('label')->implode(', ') ?: '-' }}
                                {{ $recurring_start_time }}-{{ $recurring_end_time }}
                            </p>
                        @endif
                    </div>

                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-emerald-700 font-semibold">
                            @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                Estimated weekly cost
                            @else
                                Estimated one-time cost
                            @endif
                        </p>
                        @if ($this->estimatedCost !== null && $this->estimatedHours !== null)
                            <p class="mt-1 text-sm text-emerald-900">
                                {{ number_format($this->estimatedHours, 2) }}h × ${{ number_format($this->estimateHourlyRate, 2) }}/hr
                                = <span class="font-semibold">${{ number_format($this->estimatedCost, 2) }}</span>
                                @if ($request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                    /week
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm text-emerald-900">Schedule incomplete. Add schedule details before publish.</p>
                        @endif
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="text-sm"><span class="font-medium">Care need:</span> {{ $additional_info }}</p>
                        <p class="mt-2 text-sm"><span class="font-medium">Services:</span> {{ $selectedTaskNames->implode(', ') ?: 'None selected' }}</p>
                        <p class="mt-2 text-sm"><span class="font-medium">Recipient:</span> {{ $recipient_full_name ?: 'Care recipient' }} ({{ $recipient_relationship_to_family ?: 'Family member' }})</p>
                    </div>

                    <details class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Optional: refine matching details before publish</summary>
                        <div class="mt-4 space-y-4">
                            <x-textarea label="Scope of work (optional)" wire:model="scope_of_work" hint="If empty, this will be generated from selected services." />
                            @error('scope_of_work') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <x-input label="Time expectations (optional)" wire:model="time_expectations" hint="Example: Arrive 10 minutes early." />
                            @error('time_expectations') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <x-textarea label="Home access notes (optional)" wire:model="home_access_notes" hint="Gate, parking, lockbox, etc." />
                            @error('home_access_notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </details>

                    <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Once published, active caregivers can apply and you can invite specific profiles directly.
                    </div>
                </div>
            @endif

            <x-slot:footer>
                <div class="sticky bottom-2 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-lg backdrop-blur-sm sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <x-button color="slate" light wire:click="previousStep" :disabled="$step === 1" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="previousStep,nextStep,publish">Back</x-button>
                    @if ($step < $totalSteps)
                        <x-button color="blue" wire:click="nextStep" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="nextStep,publish,previousStep">
                            Continue
                        </x-button>
                    @else
                        <x-button color="green" wire:click="publish" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="publish,nextStep,previousStep">
                            Publish request
                        </x-button>
                    @endif
                    </div>
                </div>
            </x-slot:footer>
        </x-card>
    </div>
</div>
