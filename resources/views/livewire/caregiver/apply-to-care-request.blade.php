<div class="max-w-5xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Care request details</h1>
                    <p class="text-sm text-slate-600">{{ $requestItem->title }}</p>
                </div>
                @if ($existingApplication)
                    <x-badge :text="strtoupper($existingApplication->status)" color="blue" />
                @endif
            </div>
        </x-slot:header>

        <div class="space-y-3 text-sm">
            @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                <p><span class="font-medium">When:</span> {{ optional($requestItem->requested_start_at)->format('M d, Y H:i') }} to {{ optional($requestItem->requested_end_at)->format('M d, Y H:i') }}</p>
            @else
                <p>
                    <span class="font-medium">When:</span>
                    Recurring
                    {{ collect($requestItem->recurring_days ?? [])->map(fn($d)=>['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][(int)$d] ?? null)->filter()->implode(', ') }}
                    {{ $requestItem->recurring_start_time }}-{{ $requestItem->recurring_end_time }}
                </p>
            @endif
            <p><span class="font-medium">Location:</span> {{ $requestItem->city }}, {{ $requestItem->state }}</p>
            <p><span class="font-medium">Scope:</span> {{ $requestItem->scope_of_work ?: '-' }}</p>
            <p><span class="font-medium">Time expectations:</span> {{ $requestItem->time_expectations ?: '-' }}</p>
            <p><span class="font-medium">Home access:</span> {{ $requestItem->home_access_notes ?: '-' }}</p>
            <p><span class="font-medium">Response SLA target:</span> {{ $requestItem->preferred_response_hours ?: 12 }}h</p>
        </div>
    </x-card>

    @if ($existingApplication && $existingApplication->booking)
        @php $booking = $existingApplication->booking; @endphp
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Booking lifecycle</h2>
                    <x-badge :text="strtoupper($booking->status)" color="green" />
                </div>
            </x-slot:header>

            <p class="text-sm text-slate-600">
                Scheduled: {{ optional($booking->scheduled_start_at)->format('M d, Y H:i') }} - {{ optional($booking->scheduled_end_at)->format('M d, Y H:i') }}
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-button color="blue" light wire:click="startBooking">Start shift</x-button>
                <x-button color="green" light wire:click="completeBooking">Complete shift</x-button>
                @if (in_array($booking->status, ['completed', 'reviewed'], true))
                    <x-button color="amber" light wire:click="$set('reviewRating', 5)">Add review below</x-button>
                @endif
            </div>

            @if ($booking->changeRequests->count() > 0)
                <div class="mt-4 space-y-2">
                    <p class="text-sm font-medium">Pending change requests</p>
                    @foreach ($booking->changeRequests as $change)
                        <div class="rounded-md border border-slate-200 px-3 py-2 text-sm">
                            <p class="font-medium">{{ strtoupper($change->type) }} - {{ strtoupper($change->status) }}</p>
                            <p class="text-slate-600">{{ $change->reason }}</p>
                            @if ($change->status === 'pending' && (int) $change->requester_user_id !== (int) auth()->id())
                                <div class="mt-2 flex gap-2">
                                    <x-button color="green" light wire:click="resolveChangeRequest({{ $change->id }}, 'accept')">Accept</x-button>
                                    <x-button color="red" light wire:click="resolveChangeRequest({{ $change->id }}, 'reject')">Reject</x-button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif

    <x-card>
        <x-slot:header>
            <h2 class="font-semibold">Application</h2>
        </x-slot:header>

        <div class="space-y-4">
            <x-input type="number" step="0.01" min="15" max="200" label="Your proposed hourly rate ($)" wire:model="proposed_rate" />
            @error('proposed_rate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <x-textarea label="Cover note" wire:model="cover_note" hint="Explain your relevant experience for this request." />
            @error('cover_note') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
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

    @if ($existingApplication && $existingApplication->booking && in_array($existingApplication->booking->status, ['completed', 'reviewed'], true))
        <x-card>
            <x-slot:header><h2 class="font-semibold">Leave a review</h2></x-slot:header>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input type="number" min="1" max="5" label="Rating (1-5)" wire:model="reviewRating" />
                <x-textarea label="Review comment" wire:model="reviewComment" />
            </div>
            <x-slot:footer>
                <x-button color="amber" wire:click="submitReview">Submit review</x-button>
            </x-slot:footer>
        </x-card>
    @endif

    @if ($existingApplication && $existingApplication->booking && ! in_array($existingApplication->booking->status, ['cancelled', 'reviewed'], true))
        <x-card>
            <x-slot:header><h2 class="font-semibold">Request cancellation or reschedule</h2></x-slot:header>
            <div class="space-y-4">
                <x-select.styled
                    label="Change type"
                    wire:model="changeType"
                    :options="[
                        ['label' => 'Cancel booking', 'value' => 'cancel'],
                        ['label' => 'Reschedule booking', 'value' => 'reschedule'],
                    ]"
                />
                <x-textarea label="Reason" wire:model="changeReason" />
                @if ($changeType === 'reschedule')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input type="datetime-local" label="Proposed start" wire:model="proposedStartAt" />
                        <x-input type="datetime-local" label="Proposed end" wire:model="proposedEndAt" />
                    </div>
                @endif
            </div>
            <x-slot:footer>
                <x-button color="blue" wire:click="submitChangeRequest">Send request</x-button>
            </x-slot:footer>
        </x-card>
    @endif

    <x-card>
        <x-slot:header><h2 class="font-semibold">Support / dispute</h2></x-slot:header>
        <div class="space-y-4">
            <x-input label="Subject" wire:model="supportSubject" />
            <x-select.styled
                label="Category"
                wire:model="supportCategory"
                :options="[
                    ['label' => 'General', 'value' => 'general'],
                    ['label' => 'Dispute', 'value' => 'dispute'],
                    ['label' => 'Incident', 'value' => 'incident'],
                    ['label' => 'Cancellation', 'value' => 'cancellation'],
                    ['label' => 'Billing', 'value' => 'billing'],
                ]"
            />
            <x-textarea label="Describe issue" wire:model="supportDescription" />
        </div>
        <x-slot:footer>
            <x-button color="red" wire:click="createSupportTicket">Create support ticket</x-button>
        </x-slot:footer>
    </x-card>
</div>
