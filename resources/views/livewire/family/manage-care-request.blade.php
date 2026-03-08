<div class="max-w-6xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ $requestItem->title }}</h1>
                    <p class="text-sm text-slate-600">
                        {{ $requestItem->city }}, {{ $requestItem->state }} - {{ optional($requestItem->requested_start_at)->format('M d, Y H:i') }}
                    </p>
                </div>
                <x-badge :text="strtoupper($requestItem->status)" color="blue" />
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="md:col-span-2 space-y-2">
                <p class="font-medium">Tasks</p>
                <ul class="space-y-2">
                    @foreach ($requestItem->tasks as $task)
                        <li class="rounded-md border border-slate-200 px-3 py-2">
                            <p class="font-medium">{{ $task->name }}</p>
                            @if ($task->pivot?->task_note)
                                <p class="text-slate-600">{{ $task->pivot->task_note }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="space-y-2">
                <p class="font-medium">Recipient</p>
                <p>{{ $requestItem->recipient?->full_name }}</p>
                <p class="text-slate-600">{{ $requestItem->recipient?->relationship_to_family }}</p>
                @if ($requestItem->thirdPartyContact)
                    <hr>
                    <p class="font-medium">Third-party contact</p>
                    <p>{{ $requestItem->thirdPartyContact->full_name }}</p>
                    <p class="text-slate-600">{{ $requestItem->thirdPartyContact->phone }}</p>
                @endif
            </div>
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold">Applicants</h2>
                <p class="text-sm text-slate-600">{{ $requestItem->applications->count() }} total</p>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <x-select.styled label="Status" wire:model.live="applicationStatus" :options="$applicationStatusOptions" />
            <x-select.styled
                label="Sort"
                wire:model.live="applicationSort"
                :options="[
                    ['label' => 'Latest first', 'value' => 'latest'],
                    ['label' => 'Oldest first', 'value' => 'oldest'],
                    ['label' => 'Rate high-low', 'value' => 'rate_high'],
                    ['label' => 'Rate low-high', 'value' => 'rate_low'],
                ]"
            />
        </div>

        <div class="space-y-3">
            @forelse ($this->visibleApplications as $application)
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $application->caregiver->name }}</p>
                            <p class="text-sm text-slate-600">
                                {{ $application->caregiver->city }}, {{ $application->caregiver->state }}
                                @if ($application->caregiver->caregiverProfile)
                                    - ${{ number_format((float) $application->caregiver->caregiverProfile->hourly_rate, 2) }}/hr profile rate
                                @endif
                            </p>
                        </div>
                        <x-badge :text="strtoupper($application->status)" color="blue" />
                    </div>

                    @if ($application->proposed_rate)
                        <p class="text-sm mt-2">Proposed: ${{ number_format((float) $application->proposed_rate, 2) }}/hr</p>
                    @endif

                    <p class="text-sm text-slate-700 mt-2 whitespace-pre-line">{{ $application->cover_note }}</p>

                    @if ($requestItem->status === 'open')
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-button color="blue" light wire:click="shortlist({{ $application->id }})">Shortlist</x-button>
                            @if (in_array($application->status, ['shortlisted', 'hired'], true))
                                <x-button color="indigo" light wire:click="startConversation({{ $application->id }})">
                                    {{ $application->conversation ? 'Open chat' : 'Start chat' }}
                                </x-button>
                            @endif
                            <x-button color="red" outline wire:click="reject({{ $application->id }})">Reject</x-button>
                            <x-button color="green" wire:click="hire({{ $application->id }})">Hire caregiver</x-button>
                        </div>
                    @elseif ($application->conversation)
                        <div class="mt-4">
                            <a href="{{ route('messages.show', $application->conversation->id) }}" wire:navigate>
                                <x-button color="indigo" light>Open chat</x-button>
                            </a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-md border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
                    No applicants yet.
                </div>
            @endforelse
        </div>
    </x-card>
</div>
