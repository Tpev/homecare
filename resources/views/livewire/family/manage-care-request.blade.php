<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-display font-semibold">{{ $requestItem->title }}</h1>
                    <p class="text-sm text-slate-600">
                        {{ $requestItem->city }}, {{ $requestItem->state }}
                        @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            - {{ optional($requestItem->requested_start_at)->format('M d, Y H:i') }}
                        @else
                            - Recurring
                        @endif
                    </p>
                </div>
                <x-badge :text="strtoupper($requestItem->status)" color="blue" />
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="md:col-span-2 space-y-2">
                <p><span class="font-medium">Scope:</span> {{ $requestItem->scope_of_work ?: '-' }}</p>
                <p><span class="font-medium">Time expectations:</span> {{ $requestItem->time_expectations ?: '-' }}</p>
                <p><span class="font-medium">Home access:</span> {{ $requestItem->home_access_notes ?: '-' }}</p>
                <p><span class="font-medium">Response target:</span> {{ $requestItem->preferred_response_hours ?: 12 }}h</p>
                <p class="font-medium mt-3">Tasks</p>
                <ul class="space-y-2">
                    @foreach ($requestItem->tasks as $task)
                        <li class="rounded-md border border-slate-200 px-3 py-2">
                            <p class="font-display font-medium">{{ $task->name }}</p>
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

    @if ($requestItem->booking)
        @php $booking = $requestItem->booking; @endphp
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-semibold">Booking lifecycle</h2>
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
                            @if ($change->proposed_start_at)
                                <p class="text-xs text-slate-500">
                                    Proposed:
                                    {{ optional($change->proposed_start_at)->format('M d, Y H:i') }}
                                    to
                                    {{ optional($change->proposed_end_at)->format('M d, Y H:i') }}
                                </p>
                            @endif
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
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display font-semibold">Invited caregivers</h2>
                <p class="text-sm text-slate-600">{{ $requestItem->invitations->count() }} invite(s)</p>
            </div>
        </x-slot:header>

        <div class="space-y-3">
            @forelse ($requestItem->invitations as $invitation)
                <div class="rounded-lg border border-slate-200 p-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-slate-900">{{ $invitation->caregiver?->name }}</p>
                        @if ($invitation->message)
                            <p class="text-xs text-slate-500 mt-1">{{ $invitation->message }}</p>
                        @endif
                    </div>
                    <x-badge :text="strtoupper($invitation->status)" color="blue" />
                </div>
            @empty
                <p class="text-sm text-slate-600">No invitations yet. Invite caregivers directly from their profile page.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display font-semibold">Applicants</h2>
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
                            <p class="font-display font-semibold">{{ $application->caregiver->name }}</p>
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
                        <div class="mt-4 flex flex-wrap gap-2">
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

    @if ($requestItem->booking && in_array($requestItem->booking->status, ['completed', 'reviewed'], true))
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

    @if ($requestItem->booking && ! in_array($requestItem->booking->status, ['cancelled', 'reviewed'], true))
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
            <div class="flex items-center justify-between">
                <x-button color="red" wire:click="createSupportTicket">Create support ticket</x-button>
                <x-button color="green" light wire:click="rebookHiredCaregiver">Rebook & invite hired caregiver</x-button>
            </div>
        </x-slot:footer>
    </x-card>
</div>
