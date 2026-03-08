<div class="max-w-6xl mx-auto py-8 space-y-6">
    <h1 class="text-xl font-semibold">Caregiver Review Queue</h1>

    @forelse($profiles as $profile)
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <span>{{ $profile->user->name }} ({{ $profile->user->email }})</span>
                    <x-badge text="Under review" color="yellow" />
                </div>
            </x-slot:header>

            <p class="text-sm text-slate-600">{{ $profile->bio }}</p>
            <p class="text-sm mt-2">Rate: ${{ $profile->hourly_rate }}/hr</p>
            <p class="text-xs text-slate-500 mt-1">
                Identity status:
                <span class="font-semibold uppercase">{{ str_replace('_', ' ', $profile->identity_verification_status ?? 'not_started') }}</span>
            </p>

            @error('approval_'.$profile->id)
                <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
            @enderror

            <x-slot:footer>
                <div class="flex flex-wrap gap-2 items-center">
                    <x-button
                        color="green"
                        wire:click="approve({{ $profile->id }})"
                        :disabled="!$profile->hasIdentityVerifiedBadge()"
                    >
                        Approve
                    </x-button>
                    <x-input placeholder="Rejection reason" wire:model="rejection_reason" />
                    <x-button color="red" wire:click="reject({{ $profile->id }})">Reject</x-button>
                    <x-button color="amber" wire:click="suspend({{ $profile->id }})">Suspend</x-button>
                    <x-button color="teal" wire:click="unsuspend({{ $profile->id }})">Unsuspend</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    @empty
        <x-card>
            <p class="text-sm text-slate-600">No caregivers are currently waiting for review.</p>
        </x-card>
    @endforelse

    <x-card>
        <x-slot:header>
            <h2 class="text-lg font-semibold">Trust Badge Management (Active Caregivers)</h2>
        </x-slot:header>

        <div class="space-y-3">
            @forelse($activeProfiles as $profile)
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900">{{ $profile->user->name }}</p>
                            <p class="text-xs text-slate-500">{{ $profile->user->email }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if ($profile->identity_verified_at)
                                    <x-badge color="cyan" text="Identity verified" />
                                @endif
                                @if ($profile->background_check_verified_at)
                                    <x-badge color="green" text="Background check" />
                                @endif
                                @if ($profile->top_caregiver)
                                    <x-badge color="amber" text="Top Caregiver" />
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-button color="cyan" light wire:click="toggleIdentityVerification({{ $profile->id }})">
                                {{ $profile->identity_verified_at ? 'Remove identity' : 'Verify identity' }}
                            </x-button>
                            <x-button color="green" light wire:click="toggleBackgroundCheck({{ $profile->id }})">
                                {{ $profile->background_check_verified_at ? 'Remove background' : 'Verify background' }}
                            </x-button>
                            <x-button color="amber" light wire:click="toggleTopCaregiver({{ $profile->id }})">
                                {{ $profile->top_caregiver ? 'Remove top badge' : 'Set top caregiver' }}
                            </x-button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-600">No active caregivers yet.</p>
            @endforelse
        </div>
    </x-card>
</div>
