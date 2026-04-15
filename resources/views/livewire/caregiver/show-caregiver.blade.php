<div class="hc-page py-8 space-y-6">
    @php
        $photoUrl = $caregiver->profile_photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($caregiver->profile_photo_path) : null;
        $nameParts = preg_split('/\s+/', trim((string) $caregiver->user->name));
        $initials = collect($nameParts)->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $availabilityByDay = $caregiver->availabilities->groupBy('day_of_week');
    @endphp

    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-4">
                <div class="rounded-2xl border border-[#E4DDD3] bg-[#F7F2EA] p-5">
                    <div class="flex flex-col items-center text-center">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $caregiver->user->name }}" class="h-28 w-28 rounded-full object-cover border border-[#E4DDD3]">
                        @else
                            <div class="h-28 w-28 rounded-full bg-[#EAF6F6] text-[#0F3D3E] border border-[#BDD4F7] font-semibold text-3xl flex items-center justify-center">
                                {{ $initials }}
                            </div>
                        @endif

                        <h1 class="mt-4 text-2xl font-display font-semibold text-[#17313F]">{{ $caregiver->user->name }}</h1>
                        <p class="text-sm text-[#607080]">{{ $caregiver->user->city }}, {{ $caregiver->user->state }}</p>
                        <p class="mt-2 text-lg font-semibold text-[#17313F]">${{ number_format((float) $caregiver->resolvePlatformHourlyRate(), 2) }}/hr</p>
                        <p class="text-sm text-[#607080] mt-1">â­ {{ number_format((float) $caregiver->average_rating, 1) }} ({{ $caregiver->reviews_count }} reviews)</p>
                        <p class="text-xs text-[#7B8794] mt-1">Reliability score: {{ number_format((float) $caregiver->reliability_score, 0) }}%</p>

                        <div class="mt-3 flex flex-wrap justify-center gap-2">
                            @if ($caregiver->hasIdentityVerifiedBadge())
                                <x-badge color="cyan" text="Identity verified" />
                            @endif
                            @if ($caregiver->hasBackgroundCheckBadge())
                                <x-badge color="green" text="Background check" />
                            @endif
                            @if ($caregiver->hasTopCaregiverBadge())
                                <x-badge color="amber" text="Top Caregiver" />
                            @endif
                        </div>

                        <p class="mt-4 text-xs text-[#7B8794]">
                            {{ $caregiver->is_accepting_new_clients ? 'Accepting new clients' : 'Not accepting new clients right now' }}
                        </p>

                        <p class="mt-1 text-xs text-[#7B8794]">
                            Response score: {{ $caregiver->invite_response_rate !== null ? number_format((float) $caregiver->invite_response_rate, 0).'%' : 'N/A' }}
                            @if ($caregiver->avg_invite_response_minutes)
                                 -  Avg reply {{ $caregiver->avg_invite_response_minutes }} min
                            @endif
                        </p>

                        @if (auth()->user()?->role === 'family')
                            <div class="mt-4 w-full flex flex-col gap-2">
                                <x-button color="blue" wire:click="openInviteModal" class="w-full">Invite to request</x-button>
                                <x-button :color="$isFavorite ? 'amber' : 'slate'" light wire:click="toggleFavorite" class="w-full">
                                    {{ $isFavorite ? 'Saved caregiver' : 'Save caregiver' }}
                                </x-button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8 space-y-5">
                <div>
                    <h2 class="font-display font-semibold text-[#17313F]">About</h2>
                    <p class="mt-2 text-[#4B5B6B] whitespace-pre-line">{{ $caregiver->bio }}</p>
                </div>

                <div>
                    <h2 class="font-display font-semibold text-[#17313F]">Skills</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($caregiver->skills as $skill)
                            <span class="inline-flex rounded-full bg-[#F0E9E1] px-3 py-1 text-xs text-[#4B5B6B]">{{ $skill->name }}</span>
                        @empty
                            <p class="text-sm text-[#7B8794]">No skills listed yet.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h2 class="font-display font-semibold text-[#17313F]">Languages</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($caregiver->languages as $language)
                            <span class="inline-flex rounded-full bg-[#F0E9E1] px-3 py-1 text-xs text-[#4B5B6B]">{{ $language->name }}</span>
                        @empty
                            <p class="text-sm text-[#7B8794]">No languages listed yet.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h2 class="font-display font-semibold text-[#17313F]">Typical Availability</h2>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($dayNames as $dayIndex => $dayName)
                            <div class="rounded-lg border border-[#E4DDD3] px-3 py-2 text-sm">
                                <p class="font-medium text-[#324457]">{{ $dayName }}</p>
                                @if (($availabilityByDay[$dayIndex] ?? collect())->isNotEmpty())
                                    <p class="text-[#607080]">
                                        {{ ($availabilityByDay[$dayIndex] ?? collect())
                                            ->map(fn($slot) => substr((string) $slot->start_time, 0, 5).' - '.substr((string) $slot->end_time, 0, 5))
                                            ->implode(', ') }}
                                    </p>
                                @else
                                    <p class="text-[#7B8794]">Unavailable</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Non-medical home care only. No nursing, injections, or clinical procedures.
                </div>
            </div>
        </div>
    </x-card>

    @if ($showInviteModal)
        <x-card>
            <x-slot:header>
                <h2 class="font-display font-semibold">Invite caregiver</h2>
            </x-slot:header>

            <div class="space-y-4">
                @if (count($familyRequestOptions) === 0)
                    <x-alert color="yellow">
                        You need an open or draft request first.
                        <a href="{{ route('family.requests.create') }}" wire:navigate class="underline">Create one now</a>.
                    </x-alert>
                @else
                    <x-select.styled
                        label="Select request"
                        wire:model="selectedCareRequestId"
                        :options="$familyRequestOptions"
                    />
                    @error('selectedCareRequestId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <x-textarea
                        label="Invitation message (optional)"
                        wire:model="inviteMessage"
                        hint="Example: We think you'd be a great match for this recurring morning support role."
                    />
                    @error('inviteMessage') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-between">
                    <x-button color="slate" light wire:click="$set('showInviteModal', false)">Cancel</x-button>
                    <x-button color="blue" wire:click="sendInvite" :disabled="count($familyRequestOptions)===0">Send invitation</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    @endif
</div>

