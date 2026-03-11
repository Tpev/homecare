<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $booking = $existingApplication?->booking;
        $ratePerHour = (float) ($existingApplication?->proposed_rate ?? auth()->user()->caregiverProfile?->resolvePlatformHourlyRate() ?? 0);
        $canEditApplication = $requestItem->status === \App\Models\CareRequest::STATUS_OPEN;
        $canCheckIn = $booking
            && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED
            && $booking->caregiver_terms_accepted_at !== null;
        $canPause = $booking && $booking->status === \App\Models\CareBooking::STATUS_IN_PROGRESS;
        $canResume = $booking && $booking->status === \App\Models\CareBooking::STATUS_PAUSED;
        $canCheckOut = $booking && in_array($booking->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true);
        $workedMinutes = (int) ($booking?->worked_minutes ?? 0);
        $workedHours = intdiv($workedMinutes, 60);
        $workedRemainingMinutes = $workedMinutes % 60;
        $workedLabel = sprintf('%02d:%02d', $workedHours, $workedRemainingMinutes);
        $estimatedEarnings = $workedMinutes > 0 ? round(($workedMinutes / 60) * $ratePerHour, 2) : 0;
        $pausedSeconds = (int) ($booking?->total_paused_seconds ?? 0);
        $pausedLabel = sprintf('%02d:%02d', intdiv($pausedSeconds, 3600), intdiv($pausedSeconds % 3600, 60));
    @endphp

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-display font-semibold text-slate-900">{{ $requestItem->title }}</h1>
                        @if ($existingApplication)
                            <x-badge :text="'APPLICATION '.strtoupper($existingApplication->status)" color="blue" />
                        @endif
                        @if ($booking)
                            <x-badge :text="'SHIFT '.strtoupper($booking->status)" color="green" />
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $requestItem->city }}, {{ $requestItem->state }}
                        @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            • One-time request
                        @else
                            • Recurring request
                        @endif
                        • Response SLA {{ $requestItem->preferred_response_hours ?: 12 }}h
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('care-requests.index') }}" wire:navigate>
                        <x-button color="white" light>Back to requests</x-button>
                    </a>
                    @if ($existingApplication && in_array($existingApplication->status, ['shortlisted', 'hired'], true))
                        <x-button color="indigo" light wire:click="openChat">Open chat</x-button>
                    @endif
                </div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <button
                type="button"
                wire:click="setActiveTab('overview')"
                class="{{ $activeTab === 'overview' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Overview</p>
                <p class="text-xs opacity-80">Request details and tasks</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('application')"
                class="{{ $activeTab === 'application' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Application</p>
                <p class="text-xs opacity-80">{{ $existingApplication ? 'Your proposal' : 'Apply to this request' }}</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('shift')"
                class="{{ $activeTab === 'shift' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Shift</p>
                <p class="text-xs opacity-80">{{ $booking ? 'Check in, track, complete' : 'Not hired yet' }}</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('support')"
                class="{{ $activeTab === 'support' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300' }} rounded-lg border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Support</p>
                <p class="text-xs opacity-80">Changes, incidents, disputes</p>
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
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Schedule</p>
                        @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            <p class="mt-1 text-slate-800">
                                {{ optional($requestItem->requested_start_at)->format('M d, Y H:i') }}
                                to
                                {{ optional($requestItem->requested_end_at)->format('M d, Y H:i') }}
                            </p>
                        @else
                            <p class="mt-1 text-slate-800">
                                Recurring
                                {{ collect($requestItem->recurring_days ?? [])->map(fn($d) => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $d] ?? null)->filter()->implode(', ') }}
                                {{ $requestItem->recurring_start_time }}-{{ $requestItem->recurring_end_time }}
                            </p>
                        @endif
                    </div>
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
                </div>

                <div class="space-y-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Recipient</p>
                        <p class="mt-1 font-medium text-slate-900">{{ $requestItem->recipient?->full_name ?: '-' }}</p>
                        <p class="text-slate-600">{{ $requestItem->recipient?->relationship_to_family ?: '-' }}</p>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Location</p>
                        <p class="mt-1 text-slate-800">
                            {{ $requestItem->address_line1 }}{{ $requestItem->address_line2 ? ', '.$requestItem->address_line2 : '' }}
                        </p>
                        <p class="text-slate-600">{{ $requestItem->city }}, {{ $requestItem->state }} {{ $requestItem->zip }}</p>
                    </div>
                </div>
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">Task list</h2>
            </x-slot:header>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @forelse ($requestItem->tasks as $task)
                    <div class="rounded-lg border border-slate-200 p-3">
                        <p class="font-display font-semibold text-slate-900">{{ $task->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $task->pivot?->task_note ?: 'No additional notes.' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">No tasks listed on this request.</p>
                @endforelse
            </div>
        </x-card>
    @endif

    @if ($activeTab === 'application')
        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">Application</h2>
            </x-slot:header>

            @if ($canEditApplication)
                <div class="space-y-4">
                    <div class="rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">
                        Platform rate applied automatically:
                        <span class="font-semibold">
                            ${{ number_format((float) (auth()->user()->caregiverProfile?->resolvePlatformHourlyRate() ?? 0), 2) }}/hr
                        </span>
                    </div>

                    <x-textarea label="Cover note" wire:model="cover_note" hint="Explain your relevant experience for this request." />
                    @error('cover_note') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-slot:footer>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-500">Strong cover notes improve shortlisting odds.</span>
                        <x-button color="green" wire:click="submit">{{ $existingApplication ? 'Update application' : 'Send application' }}</x-button>
                    </div>
                </x-slot:footer>
            @elseif ($existingApplication)
                <div class="space-y-3 text-sm">
                    <p><span class="font-medium">Status:</span> {{ strtoupper($existingApplication->status) }}</p>
                    <p><span class="font-medium">Platform rate:</span> ${{ number_format((float) $existingApplication->proposed_rate, 2) }}/hr</p>
                    <p class="whitespace-pre-line text-slate-700">{{ $existingApplication->cover_note ?: '-' }}</p>
                </div>
            @else
                <p class="text-sm text-slate-600">This request is not open for new applications.</p>
            @endif
        </x-card>
    @endif

    @if ($activeTab === 'shift')
        @if (! $booking)
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Shift operations</h2></x-slot:header>
                <div class="rounded-md border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
                    Shift operations become available once you are hired.
                </div>
            </x-card>
        @else
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <h2 class="font-display text-lg font-semibold">Shift focus mode</h2>
                        <x-badge :text="strtoupper($booking->status)" color="green" />
                    </div>
                </x-slot:header>

                <div
                    class="space-y-4 text-sm"
                    x-data="homecareShiftFocus({
                        startedAt: @js(optional($booking->started_at)?->toIso8601String()),
                        pausedAt: @js(optional($booking->paused_at)?->toIso8601String()),
                        totalPausedSeconds: @js((int) ($booking->total_paused_seconds ?? 0)),
                        isPaused: @js((bool) ($booking->status === \App\Models\CareBooking::STATUS_PAUSED)),
                        ratePerHour: @js($ratePerHour),
                        canCheckIn: @js((bool) $canCheckIn),
                        canPause: @js((bool) $canPause),
                        canResume: @js((bool) $canResume),
                        canCheckOut: @js((bool) $canCheckOut),
                    })"
                    x-init="init()"
                >
                    <div class="grid grid-cols-3 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-1">
                        <button type="button" @click="panel = 'live'" class="rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="panel === 'live' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                            Live
                        </button>
                        <button type="button" @click="panel = 'tasks'" class="rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="panel === 'tasks' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                            Tasks
                        </button>
                        <button type="button" @click="panel = 'details'" class="rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="panel === 'details' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                            Details
                        </button>
                    </div>

                    <p class="text-slate-600" x-show="panel === 'live'" x-transition>
                        Scheduled: {{ optional($booking->scheduled_start_at)->format('M d, Y H:i') }} - {{ optional($booking->scheduled_end_at)->format('M d, Y H:i') }}
                    </p>

                    @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4" x-show="panel === 'live'" x-transition>
                            <p class="text-xs uppercase tracking-[0.12em] text-emerald-700 font-semibold">Shift live</p>
                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="rounded-lg border border-emerald-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Timer</p>
                                    <p class="text-xl font-semibold text-slate-900 tabular-nums" x-text="timerLabel">00:00</p>
                                </div>
                                <div class="rounded-lg border border-emerald-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Estimated earnings so far</p>
                                    <p class="text-xl font-semibold text-slate-900 tabular-nums" x-text="earningsLabel">$0.00</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3" x-show="panel === 'details'" x-transition>
                        <p class="font-medium text-slate-900">Agreement</p>
                        <p class="mt-1 text-xs text-slate-600">
                            Family accepted:
                            {{ $booking->family_terms_accepted_at ? $booking->family_terms_accepted_at->format('M d, H:i') : '-' }}
                            • Caregiver accepted:
                            {{ $booking->caregiver_terms_accepted_at ? $booking->caregiver_terms_accepted_at->format('M d, H:i') : 'pending' }}
                        </p>
                        @if (! $booking->caregiver_terms_accepted_at)
                            <div class="mt-2">
                                <x-button color="blue" sm wire:click="acceptBookingAgreement">Accept agreement</x-button>
                            </div>
                        @endif
                    </div>

                    <div x-show="panel === 'live'" x-transition class="space-y-2">
                        @if ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED && ! $booking->caregiver_terms_accepted_at)
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                Accept the agreement first, then check in when you arrive.
                            </div>
                            <button type="button" @click="panel = 'details'" class="text-xs font-medium text-amber-800 underline underline-offset-2">
                                Open details to accept agreement
                            </button>
                        @elseif ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED)
                            <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                                Ready to start. Check in when you arrive at the care location.
                            </div>
                        @elseif ($booking->status === \App\Models\CareBooking::STATUS_IN_PROGRESS)
                            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                                Shift in progress. Pause for break or end when done.
                            </div>
                        @elseif ($booking->status === \App\Models\CareBooking::STATUS_PAUSED)
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                Shift is paused. Resume when back or end shift directly.
                            </div>
                        @elseif ($booking->status === \App\Models\CareBooking::STATUS_COMPLETED)
                            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                Timesheet submitted. Waiting for family confirmation.
                            </div>
                        @elseif ($booking->status === \App\Models\CareBooking::STATUS_REVIEWED)
                            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                                Shift closed and reviewed.
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2" x-show="panel === 'details'" x-transition>
                        <x-input label="Start note (optional)" wire:model="checkInNote" />
                        <x-input label="End note (optional)" wire:model="checkOutNote" />
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600" x-show="panel === 'details'" x-transition>
                        We capture phone GPS when you start and end the shift to timestamp on-site activity.
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" x-show="panel === 'live'" x-transition>
                        @if ($canCheckIn)
                            <x-button
                                color="blue"
                                x-bind:disabled="geoLoading || !canCheckIn"
                                x-on:click.prevent="startWithGps()"
                            >
                                <span x-show="!geoLoading">Start shift</span>
                                <span x-show="geoLoading">Capturing GPS...</span>
                            </x-button>
                        @endif

                        @if ($canPause)
                            <x-button color="amber" wire:click="pauseBooking">Pause shift</x-button>
                        @endif

                        @if ($canResume)
                            <x-button color="blue" wire:click="resumeBooking">Resume shift</x-button>
                        @endif

                        @if ($canCheckOut)
                            <x-button
                                color="green"
                                x-bind:disabled="geoLoading || !canCheckOut"
                                x-on:click.prevent="endWithGps()"
                            >
                                <span x-show="!geoLoading">End shift</span>
                                <span x-show="geoLoading">Capturing GPS...</span>
                            </x-button>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500" x-show="panel === 'live' && geoMessage" x-text="geoMessage"></p>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-6 text-xs text-slate-700" x-show="panel === 'details'" x-transition>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Started: {{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Paused at: {{ optional($booking->paused_at)->format('M d, H:i') ?: 'Not paused' }}</div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Checked out: {{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            Start GPS:
                            @if ($booking->check_in_lat && $booking->check_in_lng)
                                Captured
                                @if ($booking->check_in_accuracy_meters)
                                    ({{ number_format((float) $booking->check_in_accuracy_meters, 0) }}m)
                                @endif
                            @else
                                Missing
                            @endif
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            End GPS:
                            @if ($booking->check_out_lat && $booking->check_out_lng)
                                Captured
                                @if ($booking->check_out_accuracy_meters)
                                    ({{ number_format((float) $booking->check_out_accuracy_meters, 0) }}m)
                                @endif
                            @else
                                Pending
                            @endif
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Family confirmed: {{ optional($booking->family_confirmed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">Break time: {{ $pausedLabel }}</div>
                    </div>

                    @if ($booking->expected_minutes || $booking->worked_minutes)
                        <p class="text-xs text-slate-600" x-show="panel === 'details'" x-transition>
                            Minutes: expected {{ $booking->expected_minutes ?? '-' }} • worked {{ $booking->worked_minutes ?? '-' }}
                        </p>
                    @endif

                    @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED, \App\Models\CareBooking::STATUS_DISPUTED], true))
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4" x-show="panel === 'live'" x-transition>
                            <p class="text-xs uppercase tracking-[0.12em] text-blue-700 font-semibold">Shift recap</p>
                            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 text-sm">
                                <div class="rounded-lg border border-blue-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Worked time</p>
                                    <p class="font-semibold text-slate-900">{{ $workedLabel }}</p>
                                </div>
                                <div class="rounded-lg border border-blue-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Rate</p>
                                    <p class="font-semibold text-slate-900">${{ number_format($ratePerHour, 2) }}/hr</p>
                                </div>
                                <div class="rounded-lg border border-blue-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Estimated earnings</p>
                                    <p class="font-semibold text-slate-900">${{ number_format($estimatedEarnings, 2) }}</p>
                                </div>
                                <div class="rounded-lg border border-blue-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Break time</p>
                                    <p class="font-semibold text-slate-900">{{ $pausedLabel }}</p>
                                </div>
                                <div class="rounded-lg border border-blue-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">GPS verification</p>
                                    <p class="font-semibold text-slate-900">
                                        {{ ($booking->check_in_lat && $booking->check_in_lng && $booking->check_out_lat && $booking->check_out_lng) ? 'Start + End captured' : 'Partial capture' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <details class="rounded-lg border border-slate-200 bg-white p-3" x-show="panel === 'tasks'" x-transition>
                        <summary class="cursor-pointer font-medium text-slate-900">Shift checklist</summary>
                        <div class="mt-3 space-y-2">
                            @forelse ($booking->taskChecks as $taskCheck)
                                <div class="flex items-center justify-between gap-3 rounded border border-slate-200 px-3 py-2">
                                    <div>
                                        <p class="{{ $taskCheck->is_completed ? 'line-through text-slate-500' : 'text-slate-900' }}">{{ $taskCheck->label }}</p>
                                        @if ($taskCheck->notes)
                                            <p class="text-xs text-slate-500">{{ $taskCheck->notes }}</p>
                                        @endif
                                    </div>
                                    <x-button color="{{ $taskCheck->is_completed ? 'slate' : 'green' }}" sm light wire:click="toggleTaskCheck({{ $taskCheck->id }})">
                                        {{ $taskCheck->is_completed ? 'Undo' : 'Done' }}
                                    </x-button>
                                </div>
                            @empty
                                <p class="text-xs text-slate-600">No checklist items yet.</p>
                            @endforelse
                        </div>
                    </details>

                    <details class="rounded-lg border border-slate-200 bg-white p-3" x-show="panel === 'details'" x-transition>
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
                        <details class="rounded-lg border border-slate-200 bg-white p-3" x-show="panel === 'details'" x-transition>
                            <summary class="cursor-pointer font-medium text-slate-900">Change requests</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($booking->changeRequests as $change)
                                    <div class="rounded border border-slate-200 px-3 py-2">
                                        <p class="font-medium">{{ strtoupper($change->type) }} • {{ strtoupper($change->status) }}</p>
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
                        </details>
                    @endif

                    <div class="flex flex-wrap gap-2" x-show="panel === 'details'" x-transition>
                        <x-button color="indigo" light wire:click="openChat">Open chat</x-button>
                        <x-button color="slate" light wire:click="setActiveTab('support')">Open support tools</x-button>
                    </div>
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
                    Support actions become available after you are hired on this request.
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
            </x-card>
        @endif
    @endif
</div>

<script>
    if (! window.homecareShiftTracker) {
        window.homecareShiftTracker = function (config) {
            return {
                startedAt: config.startedAt || null,
                pausedAt: config.pausedAt || null,
                totalPausedSeconds: Number(config.totalPausedSeconds || 0),
                isPaused: Boolean(config.isPaused),
                ratePerHour: Number(config.ratePerHour || 0),
                canCheckIn: Boolean(config.canCheckIn),
                canPause: Boolean(config.canPause),
                canResume: Boolean(config.canResume),
                canCheckOut: Boolean(config.canCheckOut),
                geoLoading: false,
                geoMessage: '',
                timerLabel: '00:00',
                earningsLabel: '$0.00',
                tickHandle: null,
                init() {
                    this.updateLiveCounters();
                    this.tickHandle = setInterval(() => this.updateLiveCounters(), 1000);
                },
                elapsedSeconds() {
                    if (!this.startedAt) {
                        return 0;
                    }

                    const start = new Date(this.startedAt).getTime();
                    if (!Number.isFinite(start)) {
                        return 0;
                    }

                    let totalPaused = this.totalPausedSeconds;
                    if (this.isPaused && this.pausedAt) {
                        const pausedAtMs = new Date(this.pausedAt).getTime();
                        if (Number.isFinite(pausedAtMs)) {
                            totalPaused += Math.max(0, Math.floor((Date.now() - pausedAtMs) / 1000));
                        }
                    }

                    return Math.max(0, Math.floor((Date.now() - start) / 1000) - totalPaused);
                },
                updateLiveCounters() {
                    const elapsed = this.elapsedSeconds();
                    const hours = Math.floor(elapsed / 3600);
                    const minutes = Math.floor((elapsed % 3600) / 60);
                    this.timerLabel = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;

                    const earned = (elapsed / 3600) * this.ratePerHour;
                    this.earningsLabel = `$${earned.toFixed(2)}`;
                },
                startWithGps() {
                    if (!this.canCheckIn || this.geoLoading) {
                        return;
                    }

                    this.capturePositionAndCall('startBookingWithGeo', 'Starting shift...');
                },
                endWithGps() {
                    if (!this.canCheckOut || this.geoLoading) {
                        return;
                    }

                    this.capturePositionAndCall('completeBookingWithGeo', 'Ending shift...');
                },
                capturePositionAndCall(methodName, successMessage) {
                    if (!navigator.geolocation) {
                        this.geoMessage = 'GPS is not available on this device/browser.';
                        return;
                    }

                    this.geoLoading = true;
                    this.geoMessage = 'Capturing GPS location...';

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const lat = Number(position.coords.latitude);
                            const lng = Number(position.coords.longitude);
                            const accuracy = Number(position.coords.accuracy || 0);

                            this.geoMessage = successMessage;
                            this.$wire[methodName](lat, lng, accuracy);
                            this.geoLoading = false;
                        },
                        () => {
                            this.geoMessage = 'Could not capture GPS. Enable location permissions and retry.';
                            this.geoLoading = false;
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 15000,
                            maximumAge: 0,
                        }
                    );
                },
            };
        };
    }

    if (! window.homecareShiftFocus) {
        window.homecareShiftFocus = function (config) {
            return Object.assign({ panel: 'live' }, window.homecareShiftTracker(config));
        };
    }
</script>
