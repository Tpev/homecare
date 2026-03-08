<div>
    <div class="max-w-5xl mx-auto py-8 space-y-6">
        <x-card>
            <x-slot:header>
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-semibold">New care request</h1>
                            <p class="text-sm text-slate-500">Post one request, then review applicants and hire.</p>
                        </div>
                        <span class="text-sm text-slate-500 whitespace-nowrap">Step {{ $step }} of {{ $totalSteps }}</span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach ([1 => 'Request details', 2 => 'Recipient', 3 => 'Third-party', 4 => 'Review'] as $index => $label)
                            <div class="rounded-md px-3 py-2 text-xs md:text-sm border {{ $step >= $index ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                {{ $label }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-slot:header>

            @if ($step === 1)
                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-4">
                        <x-input label="Request title" wire:model="title" hint="Example: Monday afternoon companionship in Raleigh" />
                        @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        <x-textarea label="Additional info" wire:model="additional_info" hint="Care expectations, home context, routines, pet info, etc." />
                        @error('additional_info') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input type="datetime-local" label="Start date and time" wire:model="requested_start_at" />
                            @error('requested_start_at') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input type="datetime-local" label="End date and time" wire:model="requested_end_at" />
                            @error('requested_end_at') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input label="Address line 1" wire:model="address_line1" />
                            @error('address_line1') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
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
                    </div>

                    <div class="space-y-3">
                        <x-select.styled
                            wire:model="selectedTasks"
                            multiple
                            label="Tasks needed"
                            :options="collect($taskOptions)->map(fn($item)=>['label'=>$item['name'],'value'=>$item['id']])->values()->all()"
                        />
                        @error('selectedTasks') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                        @if (count($selectedTasks) > 0)
                            <div class="grid grid-cols-1 gap-3">
                                @foreach ($selectedTasks as $taskId)
                                    @php
                                        $task = collect($taskOptions)->firstWhere('id', (int) $taskId);
                                        $taskName = $task['name'] ?? 'Task';
                                    @endphp
                                    <x-input
                                        :label="$taskName . ' notes (optional)'"
                                        wire:model="taskNotes.{{ $taskId }}"
                                        placeholder="Any specific detail for this task"
                                    />
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @elseif ($step === 2)
                <div class="space-y-4">
                    <div>
                        <x-input label="Recipient full name" wire:model="recipient_full_name" />
                        @error('recipient_full_name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input type="date" label="Recipient date of birth (optional)" wire:model="recipient_date_of_birth" />
                            @error('recipient_date_of_birth') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-select.styled
                                label="Gender (optional)"
                                wire:model="recipient_gender"
                                :options="$genderOptions"
                            />
                            @error('recipient_gender') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-select.styled
                                label="Mobility level (optional)"
                                wire:model="recipient_mobility_level"
                                :options="$mobilityOptions"
                            />
                            @error('recipient_mobility_level') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <x-input label="Relationship to your account" wire:model="recipient_relationship_to_family" hint="Example: Mother, Self, Uncle" />
                        @error('recipient_relationship_to_family') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <x-textarea label="Recipient care notes (optional)" wire:model="recipient_care_notes" />
                        @error('recipient_care_notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @elseif ($step === 3)
                <div class="space-y-4">
                    <x-checkbox label="Add a third-party contact for coordination" wire:model="includeThirdPartyContact" />

                    @if ($includeThirdPartyContact)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    @else
                        <x-alert color="blue">No extra third-party contact added.</x-alert>
                    @endif
                </div>
            @else
                <div class="space-y-4">
                    <div class="rounded-lg border border-slate-200 p-4 bg-slate-50">
                        <p class="font-medium text-slate-900">{{ $title }}</p>
                        <p class="text-sm text-slate-600 mt-1">{{ $city }}, {{ $state }} {{ $zip }}</p>
                        <p class="text-sm text-slate-600">
                            {{ $requested_start_at ? \Illuminate\Support\Carbon::parse($requested_start_at)->format('M d, Y H:i') : '-' }}
                            to
                            {{ $requested_end_at ? \Illuminate\Support\Carbon::parse($requested_end_at)->format('M d, Y H:i') : '-' }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="font-medium">Recipient</p>
                        <p class="text-sm text-slate-600">{{ $recipient_full_name }} ({{ $recipient_relationship_to_family }})</p>
                    </div>

                    <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Publishing makes this request visible to active caregivers in your service area.
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
