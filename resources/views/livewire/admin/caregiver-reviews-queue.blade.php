<div class="max-w-6xl mx-auto py-8 space-y-4">
    <h1 class="text-xl font-semibold">Caregiver Review Queue</h1>

    @foreach($profiles as $profile)
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <span>{{ $profile->user->name }} ({{ $profile->user->email }})</span>
                    <x-badge text="Under review" color="yellow" />
                </div>
            </x-slot:header>

            <p class="text-sm text-slate-600">{{ $profile->bio }}</p>
            <p class="text-sm mt-2">Rate: ${{ $profile->hourly_rate }}/hr</p>

            <x-slot:footer>
                <div class="flex gap-2 items-center">
                    <x-button color="green" wire:click="approve({{ $profile->id }})">Approve</x-button>
                    <x-input placeholder="Rejection reason" wire:model="rejection_reason" />
                    <x-button color="red" wire:click="reject({{ $profile->id }})">Reject</x-button>
					<x-button color="amber" wire:click="suspend({{ $profile->id }})">Suspend</x-button>
<x-button color="teal" wire:click="unsuspend({{ $profile->id }})">Unsuspend</x-button>

                </div>
            </x-slot:footer>
        </x-card>
    @endforeach
</div>
