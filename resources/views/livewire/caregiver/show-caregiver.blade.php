<div data-ai-target="family.caregiver_profile" tabindex="-1" class="hc-page space-y-6 py-8 outline-none">
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

    @if ($contextRequestSummary)
        <div class="rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4 sm:flex sm:items-center sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-[#17313F]">You are choosing a caregiver for</p>
                <p class="mt-1 break-words font-display text-lg font-semibold text-[#17313F]">{{ $contextRequestSummary['title'] }}</p>
                <p class="mt-1 text-sm text-[#607080]">{{ $contextRequestSummary['schedule'] }} · {{ $contextRequestSummary['location'] }}</p>
            </div>
            <a href="{{ $contextRequestSummary['back_url'] }}" wire:navigate class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-[#B7ADA0] bg-white px-4 py-2 text-sm font-semibold text-[#0F3D3E] hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] sm:mt-0 sm:w-auto">
                Back to request
            </a>
        </div>
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
                                @if ($contextRequestSummary && $contextRelationship)
                                    @if ($contextRelationship['can_invite'])
                                        <x-button color="blue" wire:click="openInviteModal" class="w-full">Invite {{ $contextRelationship['first_name'] }} to this request</x-button>
                                    @elseif ($contextRelationship['can_reinvite'])
                                        <x-button color="blue" wire:click="openInviteModal" class="w-full">Invite {{ $contextRelationship['first_name'] }} again</x-button>
                                    @elseif ($contextRelationship['reply_url'])
                                        <a href="{{ $contextRelationship['reply_url'] }}" wire:navigate class="block">
                                            <x-button color="blue" class="w-full">View reply</x-button>
                                        </a>
                                    @else
                                        <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-3 text-sm text-blue-900">
                                            <p class="font-semibold">{{ $contextRelationship['status_label'] }}</p>
                                            <p class="mt-1 text-xs leading-5">{{ $contextRelationship['status_detail'] }}</p>
                                        </div>
                                    @endif
                                @elseif (count($familyRequestOptions) > 0)
                                    <x-button color="blue" wire:click="openInviteModal" class="w-full">Invite to request</x-button>
                                @else
                                    <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button w-full">Create a request to invite</a>
                                    <p class="text-xs leading-5 text-[#607080]">Caregivers are contacted and hired through a care request with the schedule and care details.</p>
                                @endif
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

                @if ($caregiver->care_experience_answered_at || $caregiver->careExperiences->isNotEmpty())
                    <section class="rounded-2xl border border-[#D8E7DF] bg-[#F4FAF6] p-4" aria-labelledby="care-experience-heading">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 id="care-experience-heading" class="font-display font-semibold text-[#17313F]">Care experience</h2>
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-[#607080]">Self-reported by caregiver</span>
                        </div>

                        @if ($caregiver->careExperiences->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($caregiver->careExperiences->sortBy('sort_order') as $experience)
                                    <span class="inline-flex rounded-full border border-[#CFE1D8] bg-white px-3 py-1 text-xs font-medium text-[#325448]">{{ $experience->label }}</span>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-3 text-sm text-[#607080]">No specialized care experience reported yet.</p>
                        @endif

                        @if (filled($caregiver->care_experience_notes))
                            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#4B5B6B]">{{ $caregiver->care_experience_notes }}</p>
                        @endif
                    </section>
                @endif

                @if ($caregiver->certifications_answered_at || $caregiver->publicCertifications->isNotEmpty())
                    <section class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4" aria-labelledby="credentials-heading">
                        <h2 id="credentials-heading" class="font-display font-semibold text-[#17313F]">Credentials & training</h2>

                        @if ($caregiver->publicCertifications->isEmpty())
                            <p class="mt-3 text-sm text-[#607080]">No current certifications reported.</p>
                        @else
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($caregiver->publicCertifications->sortBy(fn ($credential) => $credential->type?->sort_order ?? 999) as $credential)
                                    @php
                                        $verified = $credential->isCurrentlyVerified();
                                        $expired = $credential->isExpired();
                                        $tone = $verified
                                            ? 'border-emerald-200 bg-emerald-50'
                                            : ($expired ? 'border-amber-200 bg-amber-50' : 'border-[#DED6CA] bg-white');
                                    @endphp
                                    <div class="rounded-xl border p-3 {{ $tone }}">
                                        <p class="font-medium text-[#17313F]">{{ $credential->displayName() }}</p>
                                        @if ($credential->issuer)
                                            <p class="mt-1 text-xs text-[#607080]">Issued by {{ $credential->issuer }}{{ $credential->issuing_state ? ' · '.$credential->issuing_state : '' }}</p>
                                        @endif
                                        @if ($credential->expires_at)
                                            <p class="mt-1 text-xs text-[#607080]">{{ $expired ? 'Expired' : 'Expires' }} {{ $credential->expires_at->format('M j, Y') }}</p>
                                        @endif
                                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $verified ? 'bg-emerald-100 text-emerald-800' : ($expired ? 'bg-amber-100 text-amber-900' : 'bg-[#F0E9E1] text-[#4B5B6B]') }}">
                                            {{ $credential->publicStatusLabel() }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <p class="mt-3 text-xs leading-5 text-[#7B8794]">LoLo verified means our team reviewed the information submitted by the caregiver. It is not a guarantee of current licensure, suitability, or quality. Confirm any credential required for your care.</p>
                        <p class="mt-2 text-xs leading-5 text-[#7B8794]">Certifications do not expand the non-medical services offered through LoLo Care.</p>
                    </section>
                @endif

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
                @if ($contextRequestSummary)
                    <div class="rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] px-4 py-3">
                        <p class="font-semibold text-[#17313F]">{{ $contextRequestSummary['title'] }}</p>
                        <p class="mt-1 text-sm text-[#607080]">{{ $contextRequestSummary['schedule'] }} · {{ $contextRequestSummary['location'] }}</p>
                        <p class="mt-2 text-sm font-medium text-[#17313F]">This request is already selected.</p>
                    </div>
                    @error('selectedCareRequestId') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror

                    <x-textarea
                        label="Invitation message (optional)"
                        wire:model="inviteMessage"
                        hint="You can change this note before sending."
                    />
                    @error('inviteMessage') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                @elseif (count($familyRequestOptions) === 0)
                    <x-alert color="yellow">
                        No care request is available for a new invitation.
                        <a href="{{ route('family.requests.create') }}" wire:navigate class="font-semibold underline">Click here to create a new request.</a>
                    </x-alert>
                @else
                    <x-native-select-field
                        label="Select request"
                        wire:model.live="selectedCareRequestId"
                        :options="array_merge([['label' => 'Choose a request', 'value' => '']], $familyRequestOptions)"
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
                    <x-button color="blue" wire:click="sendInvite" wire:loading.attr="disabled" wire:target="sendInvite" :disabled="!$contextRequestSummary && (count($familyRequestOptions)===0 || !$selectedCareRequestId)">
                        <span wire:loading.remove wire:target="sendInvite">Send invitation</span>
                        <span wire:loading wire:target="sendInvite">Sending…</span>
                    </x-button>
                </div>
            </x-slot:footer>
        </x-card>
    @endif
</div>
