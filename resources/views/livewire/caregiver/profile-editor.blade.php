<div class="hc-page space-y-5 py-5 sm:py-8">
    @php
        $statusLabel = strtoupper(str_replace('_', ' ', (string) $profile->status));
        $statusTone = match ($profile->status) {
            'active' => 'bg-emerald-100 text-emerald-700',
            'under_review' => 'bg-amber-100 text-amber-800',
            'suspended' => 'bg-rose-100 text-rose-700',
            default => 'bg-[#F0E9E1] text-[#4B5B6B]',
        };
    @endphp

    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert color="red">Please fix the highlighted fields and save again.</x-alert>
    @endif

    <section class="relative overflow-hidden rounded-3xl border border-[#0F3D3E]/80 bg-[#0F3D3E] px-5 py-5 text-white shadow-xl sm:px-6 sm:py-6">
        <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-8 -bottom-12 h-36 w-36 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.16em] text-[#D7DEE6]">Caregiver profile workspace</p>
                    <h1 class="mt-1 text-2xl font-display font-semibold leading-tight">Your profile is ready.</h1>
                    <p class="mt-1 text-sm text-[#D7DEE6]">Update your details, save once, and your profile quality is refreshed immediately.</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusTone }}">
                    {{ $statusLabel }}
                </span>
            </div>

            <div class="rounded-2xl border border-white/15 bg-white/10 p-3">
                <p class="text-[11px] uppercase tracking-[0.14em] text-[#D7DEE6]">What happens after you save</p>
                <p class="mt-2 text-sm text-white">Your profile updates immediately and stays ready for review or matching.</p>
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
        <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm lg:col-span-2">
            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Trust setup</p>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[#17313F]">Identity verification</p>
                    <p class="text-xs text-[#7B8794]">
                        {{ strtoupper(str_replace('_', ' ', $profile->identity_verification_status ?? 'not_started')) }}
                        @if ($profile->identity_verified_at)
                            - Verified {{ $profile->identity_verified_at->format('M d, Y H:i') }}
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

    <section class="rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm sm:p-5">
        <div class="space-y-5">
            @php
                $currentPhotoUrl = $profile_photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($profile_photo_path) : null;
                $tempPhotoUrl = $profile_photo ? $profile_photo->temporaryUrl() : null;
                $displayPhotoUrl = $tempPhotoUrl ?: $currentPhotoUrl;
            @endphp

            <div>
                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Profile photo</p>
                <div class="mt-2 rounded-2xl border border-[#E4DDD3] bg-[#FCFAF7] p-4">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0">
                            @if ($displayPhotoUrl)
                                <img src="{{ $displayPhotoUrl }}" alt="Current caregiver profile photo" class="h-20 w-20 rounded-full object-cover border border-[#D9D1C5] shadow-sm">
                            @else
                                <div class="flex h-20 w-20 items-center justify-center rounded-full border border-dashed border-[#CFC4B7] bg-white text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">
                                    Photo
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <x-upload
                                label="Update profile photo"
                                wire:model="profile_photo"
                                accept="image/jpeg,image/png,image/webp"
                            />
                            <p class="text-xs text-[#607080]">Use a JPG, PNG, or WEBP image up to 10 MB.</p>
                            @error('profile_photo')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">About you</p>
                <div class="mt-2">
                    <x-textarea label="Bio" wire:model="bio" />
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Service area</p>
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
                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Care capabilities</p>
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

    </section>

    <div class="h-20 sm:h-0"></div>

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-[#E4DDD3] bg-white/95 p-3 backdrop-blur sm:static sm:border-0 sm:bg-transparent sm:p-0">
        <div class="mx-auto flex max-w-5xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-[#7B8794]">Save updates to refresh profile quality checks and moderation state.</p>
            <x-button color="blue" wire:click="save" class="w-full sm:w-auto">Save profile updates</x-button>
        </div>
    </div>
</div>

