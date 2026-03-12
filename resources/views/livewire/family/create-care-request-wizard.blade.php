<div>
    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <section class="hc-hero">
            <p class="text-xs uppercase tracking-[0.2em] text-blue-100">Quick post</p>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-display font-semibold leading-tight">Post a care request in under 2 minutes.</h1>
                    <p class="mt-2 text-sm text-blue-100">
                        Only essential details are required. You can refine the request later while chatting with caregivers.
                    </p>
                </div>
                <span class="rounded-xl border border-white/25 bg-white/10 px-3 py-2 text-sm text-white">Step {{ $step }} of {{ $totalSteps }}</span>
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
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach ([1 => 'Need + schedule', 2 => 'Recipient + contacts', 3 => 'Review + publish'] as $index => $label)
                        <div class="rounded-lg border px-3 py-2 text-xs sm:text-sm {{ $step >= $index ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                            {{ $label }}
                        </div>
                    @endforeach
                </div>
            </x-slot:header>

            @if ($step === 1)
                <div class="space-y-5">
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

                    <details class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Optional: refine matching details</summary>
                        <div class="mt-4 space-y-4">
                            <x-input label="Custom title (optional)" wire:model="title" hint="If empty, HomeCare generates one automatically." />
                            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <x-textarea label="Scope of work (optional)" wire:model="scope_of_work" hint="If empty, generated from selected services." />
                            @error('scope_of_work') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <x-input label="Time expectations (optional)" wire:model="time_expectations" hint="Example: Arrive 10 minutes early." />
                            @error('time_expectations') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <x-textarea label="Home access notes (optional)" wire:model="home_access_notes" hint="Gate, parking, lockbox, etc." />
                            @error('home_access_notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </details>
                </div>
            @elseif ($step === 2)
                <div class="space-y-5">
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
                <div class="space-y-4">
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
                        <p class="mt-2 text-sm"><span class="font-medium">Recipient:</span> {{ $recipient_full_name ?: 'Care recipient' }} ({{ $recipient_relationship_to_family ?: 'Family member' }})</p>
                    </div>

                    <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Once published, active caregivers can apply and you can invite specific profiles directly.
                    </div>
                </div>
            @endif

            <x-slot:footer>
                <div class="flex items-center justify-between">
                    <x-button color="slate" light wire:click="previousStep" :disabled="$step === 1">Back</x-button>
                    @if ($step < $totalSteps)
                        <x-button color="blue" wire:click="nextStep">Continue</x-button>
                    @else
                        <x-button color="green" wire:click="publish">Publish request</x-button>
                    @endif
                </div>
            </x-slot:footer>
        </x-card>
    </div>
</div>
