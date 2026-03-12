<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $booking = $requestItem->booking;
        $payment = $booking?->payment;
        $hiredApplication = $requestItem->applications->firstWhere('status', \App\Models\CareRequestApplication::STATUS_HIRED);
        $hiredConversation = $hiredApplication?->conversation;
        $noShowEligibleAt = $booking?->scheduled_start_at?->copy()->addMinutes(30);
        $canMarkNoShow = $booking
            && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED
            && $noShowEligibleAt
            && now()->gte($noShowEligibleAt);
        $postedAgo = \App\Support\CareRequestProgress::postedAgoLabel($requestItem);
        $firstResponse = \App\Support\CareRequestProgress::firstResponseLabel($requestItem);
        $firstHire = \App\Support\CareRequestProgress::firstHireLabel($requestItem);
    @endphp

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-display font-semibold text-slate-900">{{ $requestItem->title }}</h1>
                        <x-badge :text="strtoupper($requestItem->status)" color="blue" />
                    @if ($booking)
                        <x-badge :text="'SHIFT '.strtoupper($booking->status)" color="green" />
                    @endif
                    @if ($payment)
                        <x-badge :text="'PAYMENT '.strtoupper($payment->status)" color="amber" />
                    @endif
                </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $requestItem->city }}, {{ $requestItem->state }}
                        @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            • One-time request
                        @else
                            • Recurring request
                        @endif
                        • {{ $requestItem->preferred_response_hours ?: 12 }}h response target
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($hiredConversation)
                        <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate>
                            <x-button color="indigo">Open chat</x-button>
                        </a>
                    @endif
                    @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                        <x-button color="blue" light wire:click="setActiveTab('applicants')">Review applicants</x-button>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Best next action</p>
                    <p class="mt-1 font-display text-base font-semibold text-slate-900">{{ $bestNextAction['title'] }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $bestNextAction['action'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Response metrics</p>
                    <p class="mt-1 text-sm text-slate-700">Posted: <span class="font-semibold text-slate-900">{{ $postedAgo }}</span></p>
                    <p class="text-sm text-slate-700">First response: <span class="font-semibold text-slate-900">{{ $firstResponse }}</span></p>
                    <p class="text-sm text-slate-700">First hire: <span class="font-semibold text-slate-900">{{ $firstHire }}</span></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Pipeline status</p>
                    <p class="mt-1 text-sm text-slate-700">Applicants: <span class="font-semibold text-slate-900">{{ $requestItem->applications->count() }}</span></p>
                    <p class="text-sm text-slate-700">Invites sent: <span class="font-semibold text-slate-900">{{ $requestItem->invitations->count() }}</span></p>
                    <p class="text-sm text-slate-700">Shift: <span class="font-semibold text-slate-900">{{ strtoupper($booking?->status ?? 'NONE') }}</span></p>
                </div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <button
                type="button"
                wire:click="setActiveTab('overview')"
                class="{{ $activeTab === 'overview' ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Overview</p>
                <p class="text-xs opacity-80">Request details and contact context</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('applicants')"
                class="{{ $activeTab === 'applicants' ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Applicants</p>
                <p class="text-xs opacity-80">{{ $requestItem->applications->count() }} candidate(s)</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('shift')"
                class="{{ $activeTab === 'shift' ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Shift</p>
                <p class="text-xs opacity-80">{{ $booking ? 'Live operations' : 'No booking yet' }}</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('support')"
                class="{{ $activeTab === 'support' ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Support</p>
                <p class="text-xs opacity-80">Reschedule, incidents, disputes</p>
            </button>
        </div>
    </x-card>

    @if ($activeTab === 'overview')
        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">Request context</h2>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3 text-sm">
                <div class="space-y-3 md:col-span-2">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Scope of work</p>
                        <p class="mt-1 text-slate-800">{{ $requestItem->scope_of_work ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Time expectations</p>
                        <p class="mt-1 text-slate-800">{{ $requestItem->time_expectations ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Home access</p>
                        <p class="mt-1 text-slate-800">{{ $requestItem->home_access_notes ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Address</p>
                        <p class="mt-1 text-slate-800">
                            {{ $requestItem->address_line1 }}{{ $requestItem->address_line2 ? ', '.$requestItem->address_line2 : '' }},
                            {{ $requestItem->city }}, {{ $requestItem->state }} {{ $requestItem->zip }}
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Recipient</p>
                        <p class="mt-1 font-medium text-slate-900">{{ $requestItem->recipient?->full_name ?: '-' }}</p>
                        <p class="text-slate-600">{{ $requestItem->recipient?->relationship_to_family ?: '-' }}</p>
                    </div>

                    @if ($requestItem->thirdPartyContact)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Third-party contact</p>
                            <p class="mt-1 font-medium text-slate-900">{{ $requestItem->thirdPartyContact->full_name }}</p>
                            <p class="text-slate-600">{{ $requestItem->thirdPartyContact->phone ?: '-' }}</p>
                            <p class="text-slate-600">{{ $requestItem->thirdPartyContact->email ?: '-' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Task list</h2>
                    <p class="text-sm text-slate-600">{{ $requestItem->tasks->count() }} task(s)</p>
                </div>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @forelse ($requestItem->tasks as $task)
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="font-display font-semibold text-slate-900">{{ $task->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $task->pivot?->task_note ?: 'No additional notes.' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">No tasks attached to this request.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Selected caregiver</h2>
                    <p class="text-sm text-slate-600">{{ $requestItem->invitations->count() }} invite(s) sent</p>
                </div>
            </x-slot:header>

            @if ($hiredApplication)
                <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="font-display text-lg font-semibold text-slate-900">{{ $hiredApplication->caregiver->name }}</p>
                            <p class="text-sm text-slate-600">
                                Platform rate: ${{ number_format((float) $hiredApplication->proposed_rate, 2) }}/hr
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($hiredConversation)
                                <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate>
                                    <x-button color="indigo" light>Open chat</x-button>
                                </a>
                            @endif
                            @if ($booking)
                                <x-button color="blue" light wire:click="setActiveTab('shift')">Go to shift</x-button>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-600">
                    No caregiver hired yet. Review applicants and shortlist/hire from the Applicants tab.
                </div>
            @endif
        </x-card>
    @endif

    @if ($activeTab === 'applicants')
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Applicants</h2>
                    <p class="text-sm text-slate-600">{{ $requestItem->applications->count() }} total</p>
                </div>
            </x-slot:header>

            @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                <div class="mb-5 rounded-xl border border-cyan-200 bg-cyan-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-cyan-700">Smart shortlist</p>
                            <h3 class="font-display text-lg font-semibold text-slate-900">Top suggested caregivers for this request</h3>
                            <p class="text-sm text-slate-600">Invite individually to accelerate first response.</p>
                        </div>
                    </div>

                    @if ($suggestedCaregivers->isNotEmpty())
                        <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-3">
                            @foreach ($suggestedCaregivers as $suggestion)
                                <div class="rounded-xl border border-cyan-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="font-display font-semibold text-slate-900">{{ $suggestion['name'] }}</p>
                                            <p class="text-xs text-slate-600">{{ $suggestion['proximity'] }} • Match score {{ $suggestion['score'] }}</p>
                                        </div>
                                        <span class="inline-flex rounded-full bg-cyan-100 px-2 py-1 text-[11px] font-medium text-cyan-700">
                                            ${{ number_format((float) $suggestion['hourly_rate'], 2) }}/hr
                                        </span>
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @if ($suggestion['identity_verified'])
                                            <span class="inline-flex rounded-full bg-sky-100 px-2 py-1 text-[11px] font-medium text-sky-700">Identity verified</span>
                                        @endif
                                        @if ($suggestion['background_check'])
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-medium text-emerald-700">Background check</span>
                                        @endif
                                        @if ($suggestion['top_caregiver'])
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-[11px] font-medium text-amber-700">Top caregiver</span>
                                        @endif
                                    </div>

                                    <p class="mt-2 text-xs text-slate-600">
                                        Rating {{ number_format((float) $suggestion['average_rating'], 1) }} ({{ (int) $suggestion['reviews_count'] }} reviews)
                                    </p>

                                    <p class="mt-2 text-xs text-slate-500">{{ implode(' • ', array_slice($suggestion['reasons'], 0, 2)) }}</p>

                                    <div class="mt-3">
                                        <x-button color="blue" light wire:click="inviteSuggestedCaregiver({{ $suggestion['user_id'] }})">
                                            Invite caregiver
                                        </x-button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-3 rounded-lg border border-dashed border-cyan-300 bg-white px-3 py-3 text-sm text-slate-600">
                            No auto-suggestions yet. You can still review incoming applicants as they arrive.
                        </div>
                    @endif
                </div>
            @endif

            @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
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
            @else
                <div class="mb-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    Hiring is closed for this request. Applicant list is now read-only.
                </div>
            @endif

            <div class="space-y-3">
                @forelse ($this->visibleApplications as $application)
                    <div class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="font-display text-lg font-semibold text-slate-900">{{ $application->caregiver->name }}</p>
                                <p class="text-sm text-slate-600">
                                    {{ $application->caregiver->city }}, {{ $application->caregiver->state }}
                                    @if ($application->caregiver->caregiverProfile)
                                        • Platform rate ${{ number_format((float) ($application->caregiver->caregiverProfile?->platform_hourly_rate ?? 0), 2) }}/hr
                                    @endif
                                </p>
                            </div>
                            <x-badge :text="strtoupper($application->status)" color="blue" />
                        </div>

                        @if ($application->proposed_rate)
                            <p class="mt-2 text-sm"><span class="font-medium">Platform rate:</span> ${{ number_format((float) $application->proposed_rate, 2) }}/hr</p>
                        @endif

                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $application->cover_note }}</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                                <x-button color="blue" light wire:click="shortlist({{ $application->id }})">Shortlist</x-button>
                                <x-button color="red" outline wire:click="reject({{ $application->id }})">Reject</x-button>
                                <x-button color="green" wire:click="hire({{ $application->id }})">Hire caregiver</x-button>
                            @endif

                            @if (in_array($application->status, ['shortlisted', 'hired'], true))
                                @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                                    <x-button color="indigo" light wire:click="startConversation({{ $application->id }})">
                                        {{ $application->conversation ? 'Open chat' : 'Start chat' }}
                                    </x-button>
                                @elseif ($application->conversation)
                                    <a href="{{ route('messages.show', $application->conversation->id) }}" wire:navigate>
                                        <x-button color="indigo" light>Open chat</x-button>
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-md border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
                        No applicants yet.
                    </div>
                @endforelse
            </div>
        </x-card>
    @endif

    @if ($activeTab === 'shift')
        @if (! $booking)
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Shift operations</h2></x-slot:header>
                <div class="rounded-md border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
                    Shift operations become available once you hire a caregiver.
                </div>
            </x-card>
        @else
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-lg font-semibold">Shift command center</h2>
                        <x-badge :text="strtoupper($booking->status)" color="green" />
                    </div>
                </x-slot:header>

                <div class="space-y-4 text-sm">
                    @if (! $payment)
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Payment authorization is not ready yet. Add or update your card in
                            <a href="{{ route('family.billing.show') }}" wire:navigate class="underline underline-offset-2 font-medium">Billing & Payments</a>.
                        </div>
                    @else
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                            Payment status: <span class="font-semibold text-slate-900">{{ strtoupper($payment->status) }}</span>
                            @if ($payment->amount_authorized_cents)
                                • Authorized ${{ number_format($payment->amount_authorized_cents / 100, 2) }}
                            @endif
                            @if ($payment->amount_captured_cents)
                                • Captured ${{ number_format($payment->amount_captured_cents / 100, 2) }}
                            @endif
                        </div>
                    @endif

                    <p class="text-slate-600">
                        Scheduled: {{ optional($booking->scheduled_start_at)->format('M d, Y H:i') }} - {{ optional($booking->scheduled_end_at)->format('M d, Y H:i') }}
                    </p>

                    @if ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED)
                        <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                            Waiting for caregiver check-in. Shift start is caregiver-driven.
                            @if (! $canMarkNoShow && $noShowEligibleAt)
                                You can mark no-show after {{ $noShowEligibleAt->format('M d, H:i') }}.
                            @endif
                        </div>
                    @elseif ($booking->status === \App\Models\CareBooking::STATUS_IN_PROGRESS)
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                            Shift is in progress. If the shift is done, mark it complete.
                        </div>
                    @elseif ($booking->status === \App\Models\CareBooking::STATUS_PAUSED)
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Caregiver paused the shift for a break. They can resume or end the shift.
                        </div>
                    @elseif ($booking->status === \App\Models\CareBooking::STATUS_COMPLETED && ! $booking->family_confirmed_at)
                        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Caregiver submitted completion. Review details and confirm timesheet.
                        </div>
                    @elseif ($booking->status === \App\Models\CareBooking::STATUS_REVIEWED)
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                            Shift closed. Review submitted.
                        </div>
                    @elseif ($booking->status === \App\Models\CareBooking::STATUS_CANCELLED)
                        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                            Shift cancelled.
                        </div>
                    @elseif ($booking->status === \App\Models\CareBooking::STATUS_DISPUTED)
                        <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                            Shift is under dispute review.
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($hiredConversation)
                            <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate>
                                <x-button color="indigo">Open chat</x-button>
                            </a>
                        @endif

                        @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true))
                            <x-button color="green" wire:click="completeBooking">Mark shift complete</x-button>
                        @endif

                        @if ($booking->status === \App\Models\CareBooking::STATUS_COMPLETED && ! $booking->family_confirmed_at)
                            <x-button color="green" wire:click="completeBooking">Confirm timesheet</x-button>
                        @endif

                        @if ($canMarkNoShow)
                            <x-button color="red" light wire:click="markNoShow">Mark no-show</x-button>
                        @endif
                    </div>

                    @if ($booking->status === \App\Models\CareBooking::STATUS_COMPLETED && ! $booking->family_confirmed_at)
                        <x-input label="Confirmation note (optional)" wire:model="confirmationNote" />
                    @endif

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4 text-xs text-slate-700">
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Caregiver check-in: {{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Caregiver check-out: {{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Family confirmation: {{ optional($booking->family_confirmed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Dispute: {{ strtoupper($booking->dispute_status ?? 'none') }}</div>
                    </div>

                    @if ($booking->expected_minutes || $booking->worked_minutes)
                        <p class="text-xs text-slate-600">
                            Minutes: expected {{ $booking->expected_minutes ?? '-' }} • worked {{ $booking->worked_minutes ?? '-' }}
                        </p>
                    @endif

                    <details class="rounded-lg border border-slate-200 bg-white p-3">
                        <summary class="cursor-pointer font-medium text-slate-900">Task completion snapshot</summary>
                        <div class="mt-3 space-y-2">
                            @forelse ($booking->taskChecks as $taskCheck)
                                <div class="rounded border border-slate-200 px-3 py-2">
                                    <p class="{{ $taskCheck->is_completed ? 'line-through text-slate-500' : 'text-slate-900' }}">{{ $taskCheck->label }}</p>
                                    @if ($taskCheck->notes)
                                        <p class="text-xs text-slate-500">{{ $taskCheck->notes }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-slate-600">No task checks yet.</p>
                            @endforelse
                        </div>
                    </details>

                    <details class="rounded-lg border border-slate-200 bg-white p-3">
                        <summary class="cursor-pointer font-medium text-slate-900">Timeline</summary>
                        <div class="mt-3 max-h-52 space-y-1 overflow-auto text-xs text-slate-600">
                            @forelse ($booking->events->take(20) as $event)
                                <p>{{ optional($event->happened_at)->format('M d H:i') }} • {{ strtoupper(str_replace('_', ' ', $event->event_type)) }}</p>
                            @empty
                                <p>No events yet.</p>
                            @endforelse
                        </div>
                    </details>

                    @if ($booking->changeRequests->count() > 0)
                        <details class="rounded-lg border border-slate-200 bg-white p-3">
                            <summary class="cursor-pointer font-medium text-slate-900">Change requests</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($booking->changeRequests as $change)
                                    <div class="rounded-md border border-slate-200 px-3 py-2">
                                        <p class="font-medium">{{ strtoupper($change->type) }} • {{ strtoupper($change->status) }}</p>
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
                        </details>
                    @endif
                </div>
            </x-card>

            @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true))
                <x-card>
                    <x-slot:header><h2 class="font-display text-lg font-semibold">Leave a review</h2></x-slot:header>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input type="number" min="1" max="5" label="Rating (1-5)" wire:model="reviewRating" />
                        <x-textarea label="Review comment" wire:model="reviewComment" />
                    </div>
                    <x-slot:footer>
                        <x-button color="amber" wire:click="submitReview">Submit review</x-button>
                    </x-slot:footer>
                </x-card>
            @endif
        @endif
    @endif

    @if ($activeTab === 'support')
        @if (! $booking)
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Support</h2></x-slot:header>
                <div class="rounded-md border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
                    Support operations become contextual after a caregiver is hired and a shift exists.
                </div>
            </x-card>
        @else
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Safety and support</h2></x-slot:header>
                <div class="space-y-3">
                    @if (! in_array($booking->status, [\App\Models\CareBooking::STATUS_CANCELLED, \App\Models\CareBooking::STATUS_REVIEWED], true))
                        <details class="rounded border border-slate-200 p-3">
                            <summary class="cursor-pointer font-medium">Request cancellation or reschedule</summary>
                            <div class="mt-3 space-y-4">
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
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <x-input type="datetime-local" label="Proposed start" wire:model="proposedStartAt" />
                                        <x-input type="datetime-local" label="Proposed end" wire:model="proposedEndAt" />
                                    </div>
                                @endif
                                <x-button color="blue" wire:click="submitChangeRequest">Send request</x-button>
                            </div>
                        </details>
                    @endif

                    <details class="rounded border border-slate-200 p-3">
                        <summary class="cursor-pointer font-medium">Support ticket</summary>
                        <div class="mt-3 space-y-4">
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
                            <x-button color="red" wire:click="createSupportTicket">Create support ticket</x-button>
                        </div>
                    </details>

                    <details class="rounded border border-slate-200 p-3">
                        <summary class="cursor-pointer font-medium">Report incident</summary>
                        <div class="mt-3 space-y-4">
                            <x-input label="Incident title" wire:model="incidentTitle" />
                            <x-select.styled
                                label="Severity"
                                wire:model="incidentSeverity"
                                :options="[
                                    ['label' => 'Low', 'value' => 'low'],
                                    ['label' => 'Medium', 'value' => 'medium'],
                                    ['label' => 'High', 'value' => 'high'],
                                ]"
                            />
                            <x-textarea label="Description" wire:model="incidentDescription" />
                            <x-button color="red" light wire:click="reportIncident">Submit incident</x-button>
                        </div>
                    </details>

                    @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true))
                        <details class="rounded border border-slate-200 p-3">
                            <summary class="cursor-pointer font-medium text-red-700">Open dispute</summary>
                            <div class="mt-3 space-y-3">
                                <x-textarea label="Dispute reason" wire:model="disputeReason" />
                                <x-button color="red" wire:click="openDispute">Open dispute</x-button>
                            </div>
                        </details>
                    @endif
                </div>
                <x-slot:footer>
                    <x-button color="green" light wire:click="rebookHiredCaregiver">Rebook & invite hired caregiver</x-button>
                </x-slot:footer>
            </x-card>
        @endif
    @endif
</div>
