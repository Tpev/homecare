<div class="max-w-4xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Apply to care request</h1>
                    <p class="text-sm text-slate-600">{{ $requestItem->title }}</p>
                </div>
                @if ($existingApplication)
                    <x-badge :text="strtoupper($existingApplication->status)" color="blue" />
                @endif
            </div>
        </x-slot:header>

        <div class="space-y-4 text-sm">
            <p><span class="font-medium">When:</span> {{ optional($requestItem->requested_start_at)->format('M d, Y H:i') }} to {{ optional($requestItem->requested_end_at)->format('M d, Y H:i') }}</p>
            <p><span class="font-medium">Location:</span> {{ $requestItem->city }}, {{ $requestItem->state }}</p>

            <div>
                <p class="font-medium">Tasks</p>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($requestItem->tasks as $task)
                        <li>
                            {{ $task->name }}
                            @if ($task->pivot?->task_note)
                                <span class="text-slate-600">- {{ $task->pivot->task_note }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="space-y-4">
            <div>
                <x-input type="number" step="0.01" min="15" max="200" label="Your proposed hourly rate ($)" wire:model="proposed_rate" />
                @error('proposed_rate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-textarea label="Cover note" wire:model="cover_note" hint="Explain your relevant experience for this request." />
                @error('cover_note') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between">
                <a href="{{ route('care-requests.index') }}" wire:navigate class="text-sm underline text-slate-700">Back to requests</a>
                <div class="flex items-center gap-2">
                    @if ($existingApplication && in_array($existingApplication->status, ['shortlisted', 'hired'], true))
                        <x-button color="indigo" light wire:click="openChat">Open chat</x-button>
                    @endif
                    <x-button color="green" wire:click="submit">Send application</x-button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</div>
