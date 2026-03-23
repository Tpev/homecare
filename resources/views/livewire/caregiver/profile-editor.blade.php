<div class="hc-page py-8 space-y-5">
    @php
        $statusLabel = strtoupper(str_replace('_', ' ', (string) $profile->status));
        $statusTone = match ($profile->status) {
            'active' => 'bg-emerald-100 text-emerald-700',
            'under_review' => 'bg-amber-100 text-amber-800',
            'suspended' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert color="red">Please fix the highlighted fields and save again.</x-alert>
    @endif

    <section class="relative overflow-hidden rounded-3xl border border-slate-900/80 bg-slate-950 px-5 py-5 text-white shadow-xl sm:px-6 sm:py-6">
        <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-8 -bottom-12 h-36 w-36 rounded-full bg-cyan-500/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.16em] text-slate-300">Caregiver profile workspace</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight">Your profile is ready.</h1>
                    <p class="mt-1 text-sm text-slate-300">Update your details, save once, and your profile quality is refreshed immediately.</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusTone }}">
                    {{ $statusLabel }}
                </span>
            </div>

            <div class="rounded-2xl border border-white/15 bg-white/10 p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium">Profile completeness</p>
                    <p class="text-sm font-semibold">{{ $completeness }}%</p>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15">
                    <div class="h-full rounded-full bg-emerald-400 transition-all" style="width: {{ $completeness }}%"></div>
                </div>
            </div>
        </div>
    </section>

    @if ($profile->status === 'under_review')
        <x-alert color="yellow">Your profile is under review. Typical review time is within 1 business day.</x-alert>
    @endif

    @if ($profile->rejection_reason)
        <x-alert color="red">
            Review feedback: {{ $profile->rejection_reason }}
            <span class="ml-1">Update your profile, then save to resubmit.</span>
        </x-alert>
    @endif

    <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Trust setup</p>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Identity verification</p>
                    <p class="text-xs text-slate-500">
                        {{ strtoupper(str_replace('_', ' ', $profile->identity_verification_status ?? 'not_started')) }}
                        @if ($profile->identity_verified_at)
                            • Verified {{ $profile->identity_verified_at->format('M d, Y H:i') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('caregiver.verification.show') }}" wire:navigate>
                    <x-button color="blue" light sm>Open verification</x-button>
                </a>
            </div>
        </div>

        <livewire:caregiver.profile-completeness-card :profile="$profile" />
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="space-y-5">
            <div>
                <p class="text-xs uppercase tracking-[0.12em] text-slate-500">About you</p>
                <div class="mt-2">
                    <x-textarea label="Bio" wire:model="bio" />
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Service area</p>
                <div class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input type="number" label="Years experience" wire:model="years_experience" />
                    <x-input label="ZIP" wire:model="service_area_zip" />
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-input label="City" wire:model="city" />
                    <x-input label="State" wire:model="state" maxlength="2" />
                    <x-input type="number" label="Radius (miles)" wire:model="service_radius_miles" />
                </div>
                <div class="mt-4">
                    <x-checkbox label="I am accepting new clients" wire:model="is_accepting_new_clients" />
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Care capabilities</p>
                <div class="mt-2 space-y-4">
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
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">Save updates to refresh your profile quality checks and moderation state.</p>
            <x-button color="blue" wire:click="save">Save profile updates</x-button>
        </div>
    </section>
</div>
