<div class="max-w-5xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Edit caregiver profile</h1>
                <x-badge color="blue" text="Completeness {{ $completeness }}%" />
            </div>
        </x-slot:header>

        @if ($profile->status === 'under_review')
            <x-alert color="yellow">Under review. Typical review time: a few hours.</x-alert>
        @endif

        @if ($profile->rejection_reason)
            <x-alert color="red">Rejected reason: {{ $profile->rejection_reason }}</x-alert>
        @endif
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Identity verification</p>
                    <p class="text-xs text-slate-500">
                        Status: {{ strtoupper(str_replace('_', ' ', $profile->identity_verification_status ?? 'not_started')) }}
                        @if ($profile->identity_verified_at)
                            • Verified {{ $profile->identity_verified_at->format('M d, Y H:i') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('caregiver.verification.show') }}">
                    <x-button color="blue" sm light>Open verification</x-button>
                </a>
            </div>
        </div>
<livewire:caregiver.profile-completeness-card :profile="$profile" />

        <div class="space-y-4">
            <x-textarea label="Bio" wire:model="bio" />
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-input type="number" step="0.01" label="Hourly rate" wire:model="hourly_rate" />
                <x-input type="number" label="Years experience" wire:model="years_experience" />
                <x-input label="ZIP" wire:model="service_area_zip" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-input label="City" wire:model="city" />
                <x-input label="State" wire:model="state" maxlength="2" />
                <x-input type="number" label="Radius miles" wire:model="service_radius_miles" />
            </div>
            <x-checkbox label="Accepting new clients" wire:model="is_accepting_new_clients" />

            <x-select.styled
                wire:model="selectedSkills"
                multiple
                label="Skills"
                :options="collect($skillOptions)->map(fn($item)=>['label'=>$item['name'],'value'=>$item['id']])->values()->all()"
            />

            <x-select.styled
                wire:model="selectedLanguages"
                multiple
                label="Languages"
                :options="collect($languageOptions)->map(fn($item)=>['label'=>$item['name'],'value'=>$item['id']])->values()->all()"
            />
        </div>

        <x-slot:footer>
            <x-button color="blue" wire:click="save">Save changes</x-button>
        </x-slot:footer>
    </x-card>
</div>
