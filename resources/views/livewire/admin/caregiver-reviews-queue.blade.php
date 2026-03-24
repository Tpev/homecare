<div class="hc-page py-8 space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold">Caregiver Review Queue</h1>
            <p class="mt-1 text-sm text-slate-600">Approve launch-ready caregivers, reject incomplete submissions, and manage trust badges.</p>
        </div>
        <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
            {{ $profiles->count() }} awaiting review
        </div>
    </div>

    @forelse($profiles as $profile)
        <x-card>
            <x-slot:header>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <span>{{ $profile->user->name }} ({{ $profile->user->email }})</span>
                    <x-badge text="Under review" color="yellow" />
                </div>
            </x-slot:header>

            <p class="text-sm text-slate-600">{{ $profile->bio }}</p>
            <p class="text-sm mt-2">Platform rate: ${{ number_format((float) $profile->resolvePlatformHourlyRate(), 2) }}/hr</p>
            <p class="text-xs text-slate-500 mt-1">
                Identity status:
                <span class="font-semibold uppercase">{{ str_replace('_', ' ', $profile->identity_verification_status ?? 'not_started') }}</span>
            </p>

            @error('approval_'.$profile->id)
                <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
            @enderror

            <x-slot:footer>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-[auto_minmax(14rem,1fr)] lg:grid-cols-[auto_minmax(16rem,1fr)_auto_auto] lg:items-center">
                    <x-button
                        color="green"
                        wire:click="approve({{ $profile->id }})"
                        :disabled="!$profile->hasIdentityVerifiedBadge()"
                        class="justify-center"
                    >
                        Approve
                    </x-button>
                    <x-input placeholder="Rejection reason" wire:model="rejection_reason" />
                    <div class="grid grid-cols-2 gap-2 lg:contents">
                        <x-button color="red" wire:click="reject({{ $profile->id }})" class="justify-center">Reject</x-button>
                        <x-button color="amber" wire:click="suspend({{ $profile->id }})" class="justify-center">Suspend</x-button>
                    </div>
                    <x-button color="teal" wire:click="unsuspend({{ $profile->id }})" class="justify-center">Unsuspend</x-button>
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
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
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

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <x-button color="cyan" light wire:click="toggleIdentityVerification({{ $profile->id }})" class="justify-center">
                                {{ $profile->identity_verified_at ? 'Remove identity' : 'Verify identity' }}
                            </x-button>
                            <x-button color="green" light wire:click="toggleBackgroundCheck({{ $profile->id }})" class="justify-center">
                                {{ $profile->background_check_verified_at ? 'Remove background' : 'Verify background' }}
                            </x-button>
                            <x-button color="amber" light wire:click="toggleTopCaregiver({{ $profile->id }})" class="justify-center">
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
