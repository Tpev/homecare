<div class="max-w-5xl mx-auto py-8 space-y-6">
    <x-card>
        <x-slot:header>
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <h1 class="text-xl font-semibold">Caregiver onboarding</h1>
                    <span class="text-sm text-slate-500 whitespace-nowrap">Step {{ $step }} of {{ $totalSteps }}</span>
                </div>

                <div class="grid grid-cols-4 gap-2">
                    @foreach ([1 => 'Profile', 2 => 'Service area', 3 => 'Availability', 4 => 'Review'] as $index => $label)
                        <div class="rounded-md px-3 py-2 text-xs md:text-sm border {{ $step >= $index ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                            {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>
        </x-slot:header>

        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @if ($step === 1)
            <div class="space-y-4">
                <x-upload label="Profile photo" wire:model="profile_photo" />
                <x-textarea label="Bio" wire:model="bio" hint="Minimum 40 characters" />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-input type="number" min="0" max="60" label="Years of experience" wire:model="years_experience" />
                    <x-input type="date" max="{{ now()->subYears(18)->toDateString() }}" label="Date of birth" wire:model="date_of_birth" />
                    <x-input label="City" wire:model="city" />
                </div>

                <x-select.styled
                    label="State"
                    wire:model="state"
                    :options="collect($usStates)->map(fn($label,$value)=>['label'=>$label,'value'=>$value])->values()->all()"
                />

                <x-select.styled
                    wire:model="selectedLanguages"
                    multiple
                    label="Languages"
                    :options="collect($languageOptions)->map(fn($item)=>['label'=>$item['name'],'value'=>$item['id']])->values()->all()"
                />
            </div>
        @elseif ($step === 2)
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-input label="ZIP" wire:model="service_area_zip" />
                    <x-input type="number" min="1" max="60" label="Radius (miles)" wire:model="service_radius_miles" />
                </div>
            </div>
        @elseif ($step === 3)
            <div class="space-y-4">
                @foreach ($days as $dayIndex => $dayName)
                    <div class="rounded-lg border p-3 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="font-medium">{{ $dayName }}</p>
                            <x-button color="slate" light wire:click="addRange({{ $dayIndex }})">Add range</x-button>
                        </div>

                        @foreach ($availability[$dayIndex] as $rangeIndex => $range)
                            <div class="grid grid-cols-5 gap-2 items-end">
                                <div class="col-span-2">
                                    <x-input type="time" step="900" label="Start" wire:model="availability.{{ $dayIndex }}.{{ $rangeIndex }}.start" />
                                </div>
                                <div class="col-span-2">
                                    <x-input type="time" step="900" label="End" wire:model="availability.{{ $dayIndex }}.{{ $rangeIndex }}.end" />
                                </div>
                                <div>
                                    <x-button color="red" outline wire:click="removeRange({{ $dayIndex }}, {{ $rangeIndex }})">Remove</x-button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @error('availability') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @else
            <div class="space-y-4">
                <x-checkbox label="I am accepting new clients" wire:model="is_accepting_new_clients" />
                <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    After submit, your profile goes under review. Expect a decision within 1 business day.
                </div>

                <div class="rounded-md border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900">Identity verification</p>
                            <p class="text-xs text-slate-500 mt-1">
                                Status:
                                <span class="font-semibold uppercase">
                                    {{ str_replace('_', ' ', $profile->identity_verification_status ?? 'not_started') }}
                                </span>
                            </p>
                        </div>
                        <a href="{{ route('caregiver.verification.show') }}">
                            <x-button color="blue" sm light>Start / retry verification</x-button>
                        </a>
                    </div>
                </div>

                <div class="rounded-md border {{ $taskPreferencesComplete ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-3 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900">Task comfort selection</p>
                            <p class="text-xs text-slate-600 mt-1">
                                {{ $taskPreferencesComplete ? 'Completed. Task preferences saved.' : 'Required before submit.' }}
                            </p>
                        </div>
                        <a href="{{ route('caregiver.tasks.edit') }}" wire:navigate>
                            <x-button color="blue" sm light>Set comfortable tasks</x-button>
                        </a>
                    </div>
                </div>

                <div class="rounded-md border {{ $insuranceComplete ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-3 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900">Insurance setup</p>
                            <p class="text-xs text-slate-600 mt-1">
                                {{ $insuranceComplete ? 'Completed. Insurance answer recorded.' : 'Optional, but recommended.' }}
                            </p>
                        </div>
                        <a href="{{ route('caregiver.insurance.edit') }}" wire:navigate>
                            <x-button color="blue" sm light>Open insurance step</x-button>
                        </a>
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                    Optional: add an intro video to improve profile conversion.
                    <a href="{{ route('caregiver.video.edit') }}" wire:navigate class="font-medium underline underline-offset-2 ml-1">Record / upload intro video</a>
                </div>

                @error('identity_verification')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('task_preferences')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-between">
                <x-button color="slate" light wire:click="previousStep" :disabled="$step === 1">Back</x-button>
                <div class="flex gap-2">
                    @if ($step < $totalSteps)
                        <x-button color="blue" wire:click="nextStep">Continue</x-button>
                    @else
                        <x-button color="green" wire:click="submitForReview">Submit for review</x-button>
                    @endif
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</div>
