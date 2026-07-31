<div class="hc-page py-8 space-y-6" wire:key="caregiver-apply-{{ $requestItem->id }}">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $pricing = app(\App\Support\MarketplacePricing::class);
        $booking = $existingApplication?->booking;
        $caregiverReview = $booking?->reviews?->firstWhere('reviewer_user_id', (int) auth()->id());
        $familyReview = $booking?->reviews?->firstWhere('reviewer_user_id', (int) ($booking?->family_user_id ?? 0));
        $canLeaveReview = $booking
            && in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true)
            && ! $caregiverReview;
        $baseRatePerHour = (float) ($existingApplication?->proposed_rate ?? auth()->user()->caregiverProfile?->resolvePlatformHourlyRate() ?? 0);
        $ratePerHour = $pricing->hourlyRateForRequest($requestItem, $baseRatePerHour);
        $canEditApplication = $requestItem->status === \App\Models\CareRequest::STATUS_OPEN;
        $checkInDecision = app(\App\Services\RegularCare\CareBookingCheckInPolicy::class)->evaluate($booking);
        $canCheckIn = $checkInDecision['allowed'];
        $isExpiredRegularVisit = $booking
            && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED
            && $booking->checkInWindowHasClosed();
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
        $payoutReady = (bool) (auth()->user()->caregiverProfile?->stripeConnectIsReady() ?? false);
        $careIsLiveNow = $booking && in_array($booking->status, [
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED,
        ], true);
        $showPayoutSetupAlert = ! $payoutReady && ! $careIsLiveNow;
        $isShiftWorkspace = $activeTab === 'shift';
        $applicationStatus = (string) ($existingApplication?->status ?? '');
        $isNewApplicationDecision = ! $existingApplication && $requestItem->status === \App\Models\CareRequest::STATUS_OPEN;
        $isWaitingForFamilyDecision = $existingApplication
            && ! $booking
            && in_array($applicationStatus, [
                \App\Models\CareRequestApplication::STATUS_APPLIED,
                \App\Models\CareRequestApplication::STATUS_SHORTLISTED,
            ], true);
        $applicationStateEyebrow = $applicationStatus === \App\Models\CareRequestApplication::STATUS_SHORTLISTED
            ? 'Accepted invitation'
            : 'Application sent';
        $applicationStateTitle = $applicationStatus === \App\Models\CareRequestApplication::STATUS_SHORTLISTED
            ? 'You accepted the invitation.'
            : 'Your reply was sent.';
        $applicationStateBody = $applicationStatus === \App\Models\CareRequestApplication::STATUS_SHORTLISTED
            ? 'No visit is booked yet. The family can hire you now or message you if they need to confirm details.'
            : 'No visit is booked yet. The family is reviewing replies and can hire you or send a message.';
        $serviceAddress = trim(collect([
            $requestItem->address_line1,
            $requestItem->address_line2,
            trim($requestItem->city.', '.$requestItem->state.' '.$requestItem->zip),
        ])->filter()->implode(', '));
        $serviceMapEmbedUrl = $serviceAddress !== ''
            ? 'https://www.google.com/maps?q='.rawurlencode($serviceAddress).'&output=embed'
            : null;
        $serviceMapOpenUrl = $serviceAddress !== ''
            ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($serviceAddress)
            : null;
        $requestScheduleSummary = $this->requestScheduleSummary();
        $estimatedRequestMinutes = $this->estimatedRequestMinutes();
        $estimatedRequestHoursLabel = $estimatedRequestMinutes
            ? rtrim(rtrim(number_format($estimatedRequestMinutes / 60, 1), '0'), '.')
            : null;
        $estimatedRequestTotal = $estimatedRequestMinutes
            ? round(($estimatedRequestMinutes / 60) * $ratePerHour, 2)
            : null;
        $workspaceTabs = $booking
            ? [
                ['key' => 'shift', 'label' => 'Visit'],
                ['key' => 'overview', 'label' => 'Care details'],
                ['key' => 'support', 'label' => 'Support'],
            ]
            : [
                ['key' => 'overview', 'label' => 'Overview'],
                ['key' => 'application', 'label' => 'Application'],
            ];
        $showWorkspaceTabs = ! $isWaitingForFamilyDecision && ! $isNewApplicationDecision;
    @endphp

    <section class="{{ $isShiftWorkspace ? 'space-y-3' : 'rounded-3xl border border-[#E4DDD3] bg-white p-4 shadow-sm space-y-3' }}">
        @if ($activeTab !== 'shift')
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-display font-semibold text-[#17313F]">{{ $requestItem->title }}</h1>
                    <p class="mt-1 text-sm text-[#607080]">
                        {{ $requestItem->city }}, {{ $requestItem->state }}
                        - {{ $requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME ? 'One-time' : 'Recurring' }}
                        @if ($existingApplication)
                            - Reply {{ strtoupper($existingApplication->status) }}
                        @endif
                        @if ($booking)
                            - Visit {{ strtoupper($booking->status) }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('care-requests.index') }}" wire:navigate>
                        <x-button color="white" light>Back to requests</x-button>
                    </a>
                    @if ($existingApplication && in_array($existingApplication->status, ['shortlisted', 'hired'], true) && ! ($isWaitingForFamilyDecision && $activeTab === 'application'))
                        <x-button color="indigo" light wire:click="openChat">Open chat</x-button>
                    @endif
                </div>
            </div>
        @else
            <div class="flex items-center justify-between gap-2">
                <a href="{{ route('care-requests.index') }}" wire:navigate>
                    <x-button color="white" light sm>Back to requests</x-button>
                </a>
                @if ($existingApplication && in_array($existingApplication->status, ['shortlisted', 'hired'], true))
                    <x-button color="indigo" light sm wire:click="openChat">Open chat</x-button>
                @endif
            </div>
        @endif

        @if ($showWorkspaceTabs)
            <div wire:key="caregiver-apply-tabs-{{ $requestItem->id }}" class="{{ $isShiftWorkspace ? 'rounded-[1.6rem] border border-[#0F3D3E]/80 bg-[#0F3D3E] p-1 shadow-sm' : 'rounded-[1.6rem] border border-[#DED6CA] bg-[#F5F1EB] p-1' }}">
                <div class="grid gap-1 {{ $booking ? 'grid-cols-3' : 'grid-cols-2' }}">
                    @foreach ($workspaceTabs as $tab)
                        <button
                            type="button"
                            wire:click="setActiveTab('{{ $tab['key'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="setActiveTab"
                            class="rounded-xl px-2 py-2 text-xs font-medium transition sm:text-sm {{ $activeTab === $tab['key']
                                ? ($isShiftWorkspace ? 'bg-[#FAF9F7] text-[#0F3D3E] shadow-sm' : 'bg-[#0F3D3E] text-[#FAF9F7] shadow-sm')
                                : ($isShiftWorkspace ? 'text-[#F0E9E1]/72 hover:text-[#FAF9F7]' : 'text-[#6E746F] hover:text-[#0F3D3E]') }}"
                        >
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    @if ($activeTab === 'overview')
        @if ($isNewApplicationDecision)
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-[#D7CCE9] bg-white p-5 shadow-sm lg:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#7B8794]">New request</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold leading-tight text-[#17313F]">Interested in this visit?</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[#4B5B6B]">
                        Review the essentials. If the time and location work for you, send your interest to the family.
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">When</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $requestScheduleSummary['primary'] }}</p>
                            <p class="text-sm text-[#607080]">{{ $requestScheduleSummary['secondary'] ?: 'Time pending' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Length</p>
                            <p class="mt-1 font-semibold text-[#17313F]">
                                {{ $estimatedRequestHoursLabel ? $estimatedRequestHoursLabel.'h visit' : 'Duration pending' }}
                            </p>
                            <p class="text-sm text-[#607080]">{{ $estimatedRequestMinutes ? $estimatedRequestMinutes.' minutes' : 'We will show this once set.' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Where</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $requestItem->city }}, {{ $requestItem->state }}</p>
                            <p class="text-sm text-[#607080]">{{ trim((string) $requestItem->zip) !== '' ? $requestItem->zip : 'ZIP not listed' }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Care for</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $requestItem->recipient?->full_name ?: 'Care recipient' }}</p>
                            <p class="text-sm text-[#607080]">{{ $requestItem->recipient?->relationship_to_family ?: 'Relationship not listed' }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <x-button color="green" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="w-full sm:w-auto">
                            <span wire:loading.remove wire:target="submit">I can do this visit</span>
                            <span wire:loading wire:target="submit">Sending...</span>
                        </x-button>
                        <a href="{{ route('care-requests.index') }}" wire:navigate class="inline-flex h-11 w-full items-center justify-center rounded-[1rem] border border-[#DED6CA] bg-white px-4 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB] sm:w-auto">
                            Back to requests
                        </a>
                    </div>

                    <div class="mt-5 rounded-2xl border border-[#E4DDD3] bg-[#F7F2EA] px-4 py-3">
                        <p class="text-sm font-semibold text-[#17313F]">What help is needed</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($requestItem->tasks as $task)
                                <span class="rounded-full border border-[#DED6CA] bg-white px-3 py-1 text-xs font-medium text-[#4B5B6B]">{{ $task->name }}</span>
                            @empty
                                <span class="text-sm text-[#607080]">No tasks listed yet.</span>
                            @endforelse
                        </div>
                        @if (trim((string) $requestItem->scope_of_work) !== '')
                            <p class="mt-3 text-sm leading-6 text-[#4B5B6B]">{{ $requestItem->scope_of_work }}</p>
                        @endif
                    </div>

                    <details class="mt-4 rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
                        <summary class="cursor-pointer list-none text-sm font-semibold text-[#17313F] [&::-webkit-details-marker]:hidden">
                            Add a short note before sending <span class="font-normal text-[#7B8794]">(optional)</span>
                        </summary>
                        <div class="mt-3 border-t border-[#EFE6D8] pt-3">
                            <label for="caregiver-cover-note-{{ $requestItem->id }}" class="sr-only">Optional note to family</label>
                            <textarea
                                id="caregiver-cover-note-{{ $requestItem->id }}"
                                wire:model="cover_note"
                                rows="3"
                                class="block w-full rounded-2xl border border-[#D6CCBE] bg-white px-4 py-3 text-sm text-[#17313F] shadow-sm outline-none transition placeholder:text-[#8A96A3] focus:border-[#4F6FAF] focus:ring-2 focus:ring-[#4F6FAF]/20"
                                placeholder="Example: I am nearby and available for this visit."
                            ></textarea>
                            <p class="mt-1 text-xs text-[#7B8794]">This can be left blank.</p>
                            @error('cover_note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <x-button color="green" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="mt-3 w-full sm:w-auto">
                                <span wire:loading.remove wire:target="submit">Send with note</span>
                                <span wire:loading wire:target="submit">Sending...</span>
                            </x-button>
                        </div>
                    </details>
                </div>

                <aside class="space-y-3">
                    <div class="rounded-[1.6rem] border border-[#0F3D3E]/70 bg-gradient-to-br from-[#0F3D3E] via-[#174A52] to-[#4F6FAF] p-5 text-white shadow-sm">
                        <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-200">Estimated earnings</p>
                        @if ($estimatedRequestTotal !== null)
                            <p class="mt-1 font-display text-4xl font-semibold text-emerald-300">${{ number_format($estimatedRequestTotal, 2) }}</p>
                            <p class="mt-3 text-sm text-[#E7ECF1]">{{ $estimatedRequestHoursLabel }}h @ ${{ number_format($ratePerHour, 2) }}/hr</p>
                        @else
                            <p class="mt-2 text-sm leading-6 text-[#E7ECF1]">Earnings estimate appears once the visit duration is set.</p>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-[#E4DDD3] bg-white px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Family</p>
                        <p class="mt-1 font-semibold text-[#17313F]">{{ $requestItem->family?->name ?: 'Family' }}</p>
                        <p class="text-sm text-[#607080]">They will review your profile before hiring.</p>
                    </div>
                </aside>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm">
                    <p class="text-sm font-semibold text-[#17313F]">Care recipient details</p>
                    <x-care-recipient-context :recipient="$requestItem->recipient" :show-description="true" class="mt-3" />
                    @if (trim((string) $requestItem->time_expectations) !== '')
                        <div class="mt-4 rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Timing note</p>
                            <p class="mt-1 text-sm text-[#4B5B6B]">{{ $requestItem->time_expectations }}</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm">
                    <p class="text-sm font-semibold text-[#17313F]">Location and notes</p>
                    <p class="mt-2 text-sm text-[#4B5B6B]">{{ $serviceAddress ?: 'Address pending' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($serviceMapOpenUrl)
                            <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 items-center justify-center rounded-[0.9rem] border border-[#DED6CA] bg-[#FFFCF8] px-3 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB]">
                                Open map
                            </a>
                        @endif
                    </div>
                    @if (trim((string) $requestItem->home_access_notes) !== '')
                        <div class="mt-4 rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Home access</p>
                            <p class="mt-1 text-sm text-[#4B5B6B]">{{ $requestItem->home_access_notes }}</p>
                        </div>
                    @endif
                </div>
            </section>
        @else
        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">Request context</h2>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3 text-sm">
                <div class="space-y-3 md:col-span-2">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Schedule</p>
                        @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            <p class="mt-1 text-[#324457]">
                                {{ optional($requestItem->requested_start_at)->format('M d, Y H:i') }}
                                to
                                {{ optional($requestItem->requested_end_at)->format('M d, Y H:i') }}
                            </p>
                        @else
                            <p class="mt-1 text-[#324457]">
                                Recurring
                                {{ collect($requestItem->recurring_days ?? [])->map(fn($d) => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $d] ?? null)->filter()->implode(', ') }}
                                {{ $requestItem->recurring_start_time }}-{{ $requestItem->recurring_end_time }}
                            </p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Scope of work</p>
                        <p class="mt-1 text-[#324457]">{{ $requestItem->scope_of_work ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Time expectations</p>
                        <p class="mt-1 text-[#324457]">{{ $requestItem->time_expectations ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Home access</p>
                        <p class="mt-1 text-[#324457]">{{ $requestItem->home_access_notes ?: '-' }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3">
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Recipient</p>
                        <p class="mt-1 font-medium text-[#17313F]">{{ $requestItem->recipient?->full_name ?: '-' }}</p>
                        <p class="text-[#607080]">{{ $requestItem->recipient?->relationship_to_family ?: '-' }}</p>
                        <x-care-recipient-context :recipient="$requestItem->recipient" :show-description="true" class="mt-2" />
                    </div>

                    <div class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3">
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Location</p>
                        <p class="mt-1 text-[#324457]">
                            {{ $requestItem->address_line1 }}{{ $requestItem->address_line2 ? ', '.$requestItem->address_line2 : '' }}
                        </p>
                        <p class="text-[#607080]">{{ $requestItem->city }}, {{ $requestItem->state }} {{ $requestItem->zip }}</p>
                        @if ($serviceMapEmbedUrl)
                            <div wire:ignore class="mt-3 overflow-hidden rounded-xl border border-[#E4DDD3] bg-white">
                                <iframe
                                    title="Service location map"
                                    src="{{ $serviceMapEmbedUrl }}"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    class="h-44 w-full"
                                ></iframe>
                            </div>
                            @if ($serviceMapOpenUrl)
                                <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs font-medium text-[#7C5DDC] underline underline-offset-2">
                                    Open full map
                                </a>
                            @endif
                        @endif
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
                    <div class="rounded-lg border border-[#E4DDD3] p-3">
                        <p class="font-display font-semibold text-[#17313F]">{{ $task->name }}</p>
                        <p class="mt-1 text-sm text-[#607080]">{{ $task->pivot?->task_note ?: 'No additional notes.' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-[#607080]">No tasks listed on this request.</p>
                @endforelse
            </div>
        </x-card>
        @endif
    @endif

    @if ($activeTab === 'application')
        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">{{ $isWaitingForFamilyDecision ? 'Waiting for family' : 'Application' }}</h2>
            </x-slot:header>

            @if ($isWaitingForFamilyDecision)
                <div class="space-y-4">
                    <div class="rounded-3xl border border-[#CFE1D8] bg-[#F2F8F4] p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">{{ $applicationStateEyebrow }}</p>
                        <h3 class="mt-1 font-display text-2xl font-semibold leading-tight text-[#17313F]">{{ $applicationStateTitle }}</h3>
                        <p class="mt-2 text-sm leading-6 text-[#4B5B6B]">{{ $applicationStateBody }}</p>
                        <div class="mt-3 rounded-2xl border border-[#CFE1D8] bg-white/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">Right now</p>
                            <p class="mt-1 text-sm font-semibold text-[#17313F]">No action needed. Keep chat open in case the family has a question.</p>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @if ($applicationStatus === \App\Models\CareRequestApplication::STATUS_SHORTLISTED)
                                <x-button color="indigo" light wire:click="openChat" class="w-full">
                                    Open chat
                                </x-button>
                            @endif
                            <a href="#caregiver-request-details-{{ $requestItem->id }}" class="inline-flex h-11 w-full items-center justify-center rounded-[1rem] border border-[#DED6CA] bg-white px-4 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB]">
                                Review request details
                            </a>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">What happens next</p>
                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                                <p class="font-semibold text-[#17313F]">1. Family reviews</p>
                                <p class="mt-1 text-xs text-[#607080]">They compare replies and profiles.</p>
                            </div>
                            <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                                <p class="font-semibold text-[#17313F]">2. They may chat</p>
                                <p class="mt-1 text-xs text-[#607080]">Answer only if they ask a question.</p>
                            </div>
                            <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                                <p class="font-semibold text-[#17313F]">3. If hired</p>
                                <p class="mt-1 text-xs text-[#607080]">Visit tools appear here.</p>
                            </div>
                        </div>
                    </div>

                    <div id="caregiver-request-details-{{ $requestItem->id }}" class="rounded-2xl border border-[#E4DDD3] bg-white px-4 py-3">
                        <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Visit time</p>
                                @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                    <p class="mt-1 font-semibold text-[#17313F]">
                                        {{ optional($requestItem->requested_start_at)->format('M d, g:i A') ?: 'Time pending' }}
                                    </p>
                                    <p class="text-[#607080]">
                                        to {{ optional($requestItem->requested_end_at)->format('g:i A') ?: 'end pending' }}
                                    </p>
                                @else
                                    <p class="mt-1 font-semibold text-[#17313F]">
                                        {{ collect($requestItem->recurring_days ?? [])->map(fn($d) => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $d] ?? null)->filter()->implode(', ') ?: 'Weekly' }}
                                    </p>
                                    <p class="text-[#607080]">{{ $requestItem->recurring_start_time }}-{{ $requestItem->recurring_end_time }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Care recipient</p>
                                <p class="mt-1 font-semibold text-[#17313F]">{{ $requestItem->recipient?->full_name ?: 'Care recipient' }}</p>
                                <p class="text-[#607080]">{{ $requestItem->recipient?->relationship_to_family ?: 'Relationship not listed' }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Location</p>
                                <p class="mt-1 font-semibold text-[#17313F]">{{ $requestItem->city }}, {{ $requestItem->state }}</p>
                                <p class="text-[#607080]">{{ trim($requestItem->zip) !== '' ? $requestItem->zip : 'ZIP not listed' }}</p>
                            </div>
                        </div>

                        <details class="mt-4 rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                            <summary class="cursor-pointer list-none text-sm font-semibold text-[#17313F] [&::-webkit-details-marker]:hidden">
                                Tasks, address, and notes
                            </summary>
                            <div class="mt-3 space-y-3 border-t border-[#EFE6D8] pt-3 text-sm text-[#4B5B6B]">
                                <div>
                                    <p class="font-semibold text-[#17313F]">Tasks</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @forelse ($requestItem->tasks as $task)
                                            <span class="rounded-full border border-[#DED6CA] bg-white px-3 py-1 text-xs font-medium text-[#4B5B6B]">{{ $task->name }}</span>
                                        @empty
                                            <span>No tasks listed.</span>
                                        @endforelse
                                    </div>
                                </div>
                                @if ($serviceAddress !== '')
                                    <div>
                                        <p class="font-semibold text-[#17313F]">Address</p>
                                        <p>{{ $serviceAddress }}</p>
                                        @if ($serviceMapOpenUrl)
                                            <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-xs font-medium text-[#7C5DDC] underline underline-offset-2">
                                                Open map
                                            </a>
                                        @endif
                                    </div>
                                @endif
                                @if (trim((string) $requestItem->time_expectations) !== '' || trim((string) $requestItem->home_access_notes) !== '')
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        @if (trim((string) $requestItem->time_expectations) !== '')
                                            <div>
                                                <p class="font-semibold text-[#17313F]">Timing note</p>
                                                <p>{{ $requestItem->time_expectations }}</p>
                                            </div>
                                        @endif
                                        @if (trim((string) $requestItem->home_access_notes) !== '')
                                            <div>
                                                <p class="font-semibold text-[#17313F]">Home access</p>
                                                <p>{{ $requestItem->home_access_notes }}</p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </details>
                    </div>

                    @if ($existingApplication->cover_note || $canEditApplication)
                        <details class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
                            <summary class="cursor-pointer list-none text-sm font-semibold text-[#17313F] [&::-webkit-details-marker]:hidden">
                                Your reply
                            </summary>
                            <div class="mt-4 space-y-3 border-t border-[#EFE6D8] pt-4">
                                @if ($existingApplication->cover_note)
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Sent note</p>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#4B5B6B]">{{ $existingApplication->cover_note }}</p>
                                    </div>
                                @endif
                                @if ($canEditApplication)
                                    <div class="space-y-3">
                                        <label for="caregiver-cover-note-{{ $requestItem->id }}" class="block text-sm font-semibold text-[#17313F]">
                                            Edit note <span class="font-normal text-[#7B8794]">(optional)</span>
                                        </label>
                                        <textarea
                                            id="caregiver-cover-note-{{ $requestItem->id }}"
                                            wire:model="cover_note"
                                            rows="4"
                                            class="block w-full rounded-2xl border border-[#D6CCBE] bg-white px-4 py-3 text-sm text-[#17313F] shadow-sm outline-none transition placeholder:text-[#8A96A3] focus:border-[#4F6FAF] focus:ring-2 focus:ring-[#4F6FAF]/20"
                                            placeholder="Add a short note if you want to introduce yourself."
                                        ></textarea>
                                        @error('cover_note') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                        <x-button color="green" wire:click="submit" class="w-full sm:w-auto">Update note</x-button>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>
            @elseif ($canEditApplication)
                <div class="space-y-4">
                    <div class="rounded-[1rem] border border-[#D8D1F1] bg-[#F5F1FB] px-3 py-2 text-sm text-[#0F3D3E]">
                        Care rate applied automatically:
                        <span class="font-semibold">
                            ${{ number_format($pricing->hourlyRateForRequest($requestItem, (float) (auth()->user()->caregiverProfile?->resolvePlatformHourlyRate() ?? 0)), 2) }}/hr
                        </span>
                    </div>

                    <div>
                        <label for="caregiver-cover-note-{{ $requestItem->id }}" class="block text-sm font-semibold text-[#17313F]">
                            Cover note <span class="font-normal text-[#7B8794]">(optional)</span>
                        </label>
                        <textarea
                            id="caregiver-cover-note-{{ $requestItem->id }}"
                            wire:model="cover_note"
                            rows="4"
                            class="mt-2 block w-full rounded-2xl border border-[#D6CCBE] bg-white px-4 py-3 text-sm text-[#17313F] shadow-sm outline-none transition placeholder:text-[#8A96A3] focus:border-[#4F6FAF] focus:ring-2 focus:ring-[#4F6FAF]/20"
                            placeholder="Add a short note if you want to introduce yourself."
                        ></textarea>
                        <p class="mt-1 text-xs text-[#7B8794]">This can be left blank.</p>
                    </div>
                    @error('cover_note') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-slot:footer>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-[#7B8794]">You can send this without a note.</span>
                        <x-button color="green" wire:click="submit">{{ $existingApplication ? 'Update application' : 'Send application' }}</x-button>
                    </div>
                </x-slot:footer>
            @elseif ($existingApplication)
                <div class="space-y-3 text-sm">
                    <p><span class="font-medium">Status:</span> {{ strtoupper($existingApplication->status) }}</p>
                    <p><span class="font-medium">Care rate:</span> ${{ number_format($ratePerHour, 2) }}/hr</p>
                    <p class="whitespace-pre-line text-[#4B5B6B]">{{ $existingApplication->cover_note ?: '-' }}</p>
                </div>
            @else
                <p class="text-sm text-[#607080]">This request is not open for new applications.</p>
            @endif
        </x-card>
    @endif

    @if ($activeTab === 'shift')
        @if (! $booking)
            <section class="rounded-3xl border border-dashed border-[#D6CCBE] bg-white px-5 py-8 text-center shadow-sm">
                <p class="text-xs uppercase tracking-[0.16em] text-[#7B8794]">Visit tools</p>
                <h2 class="mt-2 font-display text-2xl font-semibold text-[#17313F]">No active visit yet</h2>
                <p class="mt-2 text-sm text-[#607080]">
                    Once you are hired, start and resume controls will appear here.
                </p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <a href="{{ route('caregiver.shifts.index') }}" wire:navigate>
                        <x-button color="blue" sm>My visits</x-button>
                    </a>
                    <a href="{{ route('care-requests.index') }}" wire:navigate>
                        <x-button color="white" light sm>Browse requests</x-button>
                    </a>
                </div>
            </section>
        @else
            @php
                $shiftStatus = (string) $booking->status;
                $statusBadgeClass = match ($shiftStatus) {
                    \App\Models\CareBooking::STATUS_IN_PROGRESS => 'bg-emerald-100 text-emerald-800',
                    \App\Models\CareBooking::STATUS_PAUSED => 'bg-amber-100 text-amber-800',
                    \App\Models\CareBooking::STATUS_SCHEDULED => 'bg-[#E8F0FF] text-[#355983]',
                    \App\Models\CareBooking::STATUS_COMPLETED => 'bg-indigo-100 text-indigo-800',
                    \App\Models\CareBooking::STATUS_REVIEWED => 'bg-[#EAF6F6] text-[#0F3D3E]',
                    \App\Models\CareBooking::STATUS_DISPUTED => 'bg-rose-100 text-rose-800',
                    default => 'bg-[#F0E9E1] text-[#4B5B6B]',
                };
                $careRecipientName = trim((string) ($requestItem->recipient?->full_name ?? '')) ?: 'the care recipient';
                $scheduledWindowLabel = trim(
                    (optional($booking->scheduled_start_at)->format('M d, g:i A') ?: 'the scheduled start')
                    .' - '.
                    (optional($booking->scheduled_end_at)->format('g:i A') ?: 'end time pending')
                );
                $commandStatusToneClass = $isExpiredRegularVisit
                    ? 'border-amber-300/40 bg-amber-500/10 text-amber-100'
                    : match ($shiftStatus) {
                    \App\Models\CareBooking::STATUS_SCHEDULED => 'border-[#C8D9F5]/60 bg-[#4F6FAF]/10 text-[#DCE8FF]',
                    \App\Models\CareBooking::STATUS_IN_PROGRESS => 'border-emerald-300/40 bg-emerald-500/10 text-emerald-100',
                    \App\Models\CareBooking::STATUS_PAUSED,
                    \App\Models\CareBooking::STATUS_COMPLETED => 'border-amber-300/40 bg-amber-500/10 text-amber-100',
                    \App\Models\CareBooking::STATUS_REVIEWED => 'border-emerald-300/40 bg-emerald-500/10 text-emerald-100',
                    default => 'border-white/20 bg-white/10 text-[#D7DEE6]',
                };
                $commandStatusTitle = $isExpiredRegularVisit
                    ? 'This past visit is still accessible.'
                    : match ($shiftStatus) {
                    \App\Models\CareBooking::STATUS_SCHEDULED => 'Check in when you arrive.',
                    \App\Models\CareBooking::STATUS_IN_PROGRESS => 'You are checked in.',
                    \App\Models\CareBooking::STATUS_PAUSED => 'The visit is paused.',
                    \App\Models\CareBooking::STATUS_COMPLETED => 'Your timesheet is submitted.',
                    \App\Models\CareBooking::STATUS_REVIEWED => 'This visit is closed.',
                    default => 'Visit tools are ready.',
                };
                $commandStatusBody = $isExpiredRegularVisit
                    ? 'Its check-in window is closed, but the visit record, notes, timeline, and support options remain available.'
                    : match ($shiftStatus) {
                    \App\Models\CareBooking::STATUS_SCHEDULED => 'Next visit for '.$careRecipientName.' is '.$scheduledWindowLabel.'.',
                    \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Stay focused on '.$careRecipientName.'. Pause only for breaks, or end when care is done.',
                    \App\Models\CareBooking::STATUS_PAUSED => 'Resume when you are back with '.$careRecipientName.', or end the visit if care is finished.',
                    \App\Models\CareBooking::STATUS_COMPLETED => 'Family review is the next step. Keep an eye on messages if they have a question.',
                    \App\Models\CareBooking::STATUS_REVIEWED => 'Family review is complete. Your visit record is saved here.',
                    default => 'Use the visit tools for this booking.',
                };
                $visitStageEyebrow = $isExpiredRegularVisit
                    ? 'Past visit record'
                    : match ($shiftStatus) {
                    \App\Models\CareBooking::STATUS_SCHEDULED => 'Upcoming visit',
                    \App\Models\CareBooking::STATUS_IN_PROGRESS,
                    \App\Models\CareBooking::STATUS_PAUSED => 'Live visit',
                    \App\Models\CareBooking::STATUS_COMPLETED => 'Timesheet sent',
                    \App\Models\CareBooking::STATUS_REVIEWED => 'Completed visit',
                    default => 'Visit',
                };
                $visitStageTitle = $isExpiredRegularVisit
                    ? 'Visit record for '.$careRecipientName
                    : match ($shiftStatus) {
                    \App\Models\CareBooking::STATUS_SCHEDULED => 'Get ready for '.$careRecipientName,
                    \App\Models\CareBooking::STATUS_IN_PROGRESS => 'You are with '.$careRecipientName,
                    \App\Models\CareBooking::STATUS_PAUSED => 'Visit paused for '.$careRecipientName,
                    \App\Models\CareBooking::STATUS_COMPLETED => 'Waiting for family review',
                    \App\Models\CareBooking::STATUS_REVIEWED => 'Visit is fully closed',
                    default => $requestItem->title,
                };
                $statusBadgeLabel = $isExpiredRegularVisit
                    ? 'PAST VISIT'
                    : strtoupper(str_replace('_', ' ', $shiftStatus));
            @endphp
            <section class="rounded-[1.9rem] border border-[#0F3D3E]/80 bg-[#0F3D3E] p-5 shadow-xl">
                <div>
                    <div class="mb-4">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-[#D7DEE6]">{{ $visitStageEyebrow }}</p>
                        <div class="mt-2 flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-display text-2xl font-semibold leading-tight text-white">{{ $visitStageTitle }}</h2>
                                <p class="mt-1 text-sm text-[#D7DEE6]">
                                    {{ $requestItem->title }} -
                                    {{ $requestItem->city }}, {{ $requestItem->state }}
                                    - {{ $requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME ? 'One-time' : 'Recurring' }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusBadgeClass }}">
                                {{ $statusBadgeLabel }}
                            </span>
                        </div>
                    </div>

                <div
                    class="space-y-3 text-sm text-white"
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
                    <div x-show="panel === 'live'" x-transition class="space-y-2">
                        @if ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED && ! $booking->caregiver_terms_accepted_at && ! $isExpiredRegularVisit)
                            <div class="rounded-xl border border-amber-300/40 bg-amber-500/10 px-3 py-3 text-sm text-amber-100">
                                Accept the agreement first, then check in when you arrive.
                            </div>
                            <p class="text-xs font-medium text-amber-100">
                                Open Visit record and notes below to accept the agreement.
                            </p>
                        @else
                            <div class="rounded-xl border px-3 py-3 {{ $commandStatusToneClass }}">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] opacity-85">Right now</p>
                                <p class="mt-1 text-sm font-semibold text-white">{{ $commandStatusTitle }}</p>
                                <p class="mt-1 text-sm leading-6">{{ $commandStatusBody }}</p>
                            </div>
                        @endif

                        @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true))
                            <div class="rounded-xl border border-emerald-300/40 bg-emerald-500/10 p-3.5">
                                <p class="text-xs uppercase tracking-[0.12em] text-emerald-100 font-semibold">Live timer</p>
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                                        <p class="text-xs uppercase tracking-[0.12em] text-[#D7DEE6]">Time with client</p>
                                        <p class="text-2xl font-semibold tabular-nums" x-text="timerLabel">00:00</p>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                                        <p class="text-xs uppercase tracking-[0.12em] text-[#D7DEE6]">Estimated earnings</p>
                                        <p class="text-2xl font-semibold tabular-nums" x-text="earningsLabel">$0.00</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($isExpiredRegularVisit)
                            <div class="rounded-xl border border-amber-300/50 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-50">
                                <p>The check-in window has closed. If care was provided but the hours were not entered, report it from this visit so support receives the correct booking record.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-button color="white" light sm wire:click="openHistoricalVisitSupport">Report work or get help</x-button>
                                    @if ($currentRegularCareBooking)
                                        <a href="{{ route('care-requests.apply', $currentRegularCareBooking->care_request_id) }}" wire:navigate>
                                            <x-button color="blue" sm>Open current visit</x-button>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @elseif ($canCheckIn)
                            <x-button
                                color="blue"
                                class="w-full"
                                x-bind:disabled="geoLoading || !canCheckIn"
                                x-on:click.prevent="startWithGps()"
                            >
                                <span x-show="!geoLoading">Start visit</span>
                                <span x-show="geoLoading">Capturing GPS...</span>
                            </x-button>
                        @elseif ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED && $booking->caregiver_terms_accepted_at)
                            <div class="rounded-lg border border-amber-300/50 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-50">
                                {{ $checkInDecision['reason'] }}
                            </div>
                        @endif

                        @if ($canPause)
                            <x-button color="amber" class="w-full" wire:click="pauseBooking">Pause visit</x-button>
                        @endif

                        @if ($canResume)
                            <x-button color="blue" class="w-full" wire:click="resumeBooking">Resume visit</x-button>
                        @endif

                        @if ($canCheckOut)
                            <x-button
                                color="green"
                                class="w-full"
                                x-bind:disabled="geoLoading || !canCheckOut"
                                x-on:click.prevent="endWithGps()"
                            >
                                <span x-show="!geoLoading">End visit</span>
                                <span x-show="geoLoading">Capturing GPS...</span>
                            </x-button>
                        @endif

                        <p class="text-xs text-[#D7DEE6]" x-show="geoMessage" x-text="geoMessage"></p>

                        @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED, \App\Models\CareBooking::STATUS_DISPUTED], true))
                            <div class="rounded-xl border border-indigo-300/40 bg-indigo-500/10 p-3.5">
                                <p class="text-xs uppercase tracking-[0.12em] text-indigo-100 font-semibold">Visit recap</p>
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 text-sm">
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                                        <p class="text-xs text-[#D7DEE6]">Worked time</p>
                                        <p class="font-semibold text-white">{{ $workedLabel }}</p>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                                        <p class="text-xs text-[#D7DEE6]">Rate</p>
                                        <p class="font-semibold text-white">${{ number_format($ratePerHour, 2) }}/hr</p>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                                        <p class="text-xs text-[#D7DEE6]">Estimated earnings</p>
                                        <p class="font-semibold text-white">${{ number_format($estimatedEarnings, 2) }}</p>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2">
                                        <p class="text-xs text-[#D7DEE6]">Break time</p>
                                        <p class="font-semibold text-white">{{ $pausedLabel }}</p>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-3 py-2 sm:col-span-2">
                                        <p class="text-xs text-[#D7DEE6]">GPS verification</p>
                                        <p class="font-semibold text-white">
                                            {{ ($booking->check_in_lat && $booking->check_in_lng && $booking->check_out_lat && $booking->check_out_lng) ? 'Start + End captured' : 'Partial capture' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($showPayoutSetupAlert)
                            <div class="rounded-xl border border-amber-300/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">
                                Payout setup is incomplete. Connect Stripe to receive earnings from completed visits.
                                <a href="{{ route('caregiver.payouts.connect.show') }}" wire:navigate class="font-semibold underline underline-offset-2">Complete setup</a>
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]" x-show="panel === 'live'" x-transition>
                        <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-3 text-white">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#E7ECF1]">Scheduled window</p>
                            <p class="mt-1 font-medium">{{ optional($booking->scheduled_start_at)->format('M d, Y H:i') }} - {{ optional($booking->scheduled_end_at)->format('M d, Y H:i') }}</p>
                        </div>

                        <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-3 text-white">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#E7ECF1]">Care location</p>
                            <p class="mt-1 font-medium">{{ $serviceAddress ?: 'Address pending' }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-[#D7DEE6]">
                                @if ($serviceMapOpenUrl)
                                    <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-white underline underline-offset-2">Open map</a>
                                @endif
                                <span>{{ $requestItem->recipient?->full_name ?: 'Recipient' }} - {{ $requestItem->recipient?->relationship_to_family ?: 'Care recipient' }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($serviceMapEmbedUrl)
                        <details class="rounded-xl border border-white/20 bg-white/10 p-3 text-white" x-show="panel === 'live'" x-transition>
                            <summary class="cursor-pointer font-medium">Map and visit record</summary>
                            <div wire:ignore class="mt-3 overflow-hidden rounded-xl border border-white/20 bg-white/10">
                                <iframe
                                    title="Care location map"
                                    src="{{ $serviceMapEmbedUrl }}"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    class="h-48 w-full"
                                ></iframe>
                            </div>
                        </details>
                    @endif

                    <details class="rounded-xl border border-white/20 bg-white/10 p-3" {{ ! $booking->caregiver_terms_accepted_at ? 'open' : '' }}>
                        <summary class="cursor-pointer font-medium text-white">Visit record and notes</summary>
                        <div class="mt-3 space-y-3">
                    <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <p class="font-medium text-white">Agreement</p>
                        <p class="mt-1 text-xs text-[#D7DEE6]">
                            Family accepted:
                            {{ $booking->family_terms_accepted_at ? $booking->family_terms_accepted_at->format('M d, H:i') : '-' }}
                            - Caregiver accepted:
                            {{ $booking->caregiver_terms_accepted_at ? $booking->caregiver_terms_accepted_at->format('M d, H:i') : 'pending' }}
                        </p>
                        @if (! $booking->caregiver_terms_accepted_at && ! $isExpiredRegularVisit)
                            <div class="mt-2">
                                <x-button color="blue" sm wire:click="acceptBookingAgreement">Accept agreement</x-button>
                            </div>
                        @endif
                    </div>

                    @if ($requestItem->home_access_notes || $requestItem->recipient?->care_notes)
                        <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                            <p class="font-medium text-white">Care notes</p>
                            @if ($requestItem->home_access_notes)
                                <p class="mt-2 text-xs uppercase tracking-[0.12em] text-[#D7DEE6]">Home access</p>
                                <p class="text-sm text-white">{{ $requestItem->home_access_notes }}</p>
                            @endif
                            @if ($requestItem->recipient?->care_notes)
                                <p class="mt-2 text-xs uppercase tracking-[0.12em] text-[#D7DEE6]">Recipient notes</p>
                                <p class="text-sm text-white">{{ $requestItem->recipient->care_notes }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($canCheckIn || $canCheckOut)
                        <div class="rounded-xl bg-white p-3">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <x-input label="Start note (optional)" wire:model="checkInNote" />
                                <x-input label="End note (optional)" wire:model="checkOutNote" />
                            </div>
                        </div>
                    @endif

                    <p class="text-xs text-[#D7DEE6]">
                        Start and end use phone GPS to verify on-site timestamps.
                    </p>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 text-xs text-[#E7ECF1]">
                        <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2">Started: {{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2">Paused at: {{ optional($booking->paused_at)->format('M d, H:i') ?: 'Not paused' }}</div>
                        <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2">Checked out: {{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2">
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
                        <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2">
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
                        <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2">Family confirmed: {{ optional($booking->family_confirmed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-white/15 bg-white/5 px-3 py-2">Break time: {{ $pausedLabel }}</div>
                    </div>

                    @if ($booking->expected_minutes || $booking->worked_minutes)
                        <p class="text-xs text-[#D7DEE6]">
                            Minutes: expected {{ $booking->expected_minutes ?? '-' }} - worked {{ $booking->worked_minutes ?? '-' }}
                        </p>
                    @endif

                    <details class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <summary class="cursor-pointer font-medium text-white">Visit checklist</summary>
                        <div class="mt-3 space-y-2">
                            @forelse ($booking->taskChecks as $taskCheck)
                                <div class="flex items-center justify-between gap-3 rounded border border-white/15 bg-white/5 px-3 py-2">
                                    <div>
                                        <p class="{{ $taskCheck->is_completed ? 'line-through text-[#B8C2CC]' : 'text-white' }}">{{ $taskCheck->label }}</p>
                                        @if ($taskCheck->notes)
                                            <p class="text-xs text-[#D7DEE6]">{{ $taskCheck->notes }}</p>
                                        @endif
                                    </div>
                                    <x-button color="{{ $taskCheck->is_completed ? 'slate' : 'green' }}" sm light wire:click="toggleTaskCheck({{ $taskCheck->id }})">
                                        {{ $taskCheck->is_completed ? 'Undo' : 'Done' }}
                                    </x-button>
                                </div>
                            @empty
                                <p class="text-xs text-[#D7DEE6]">No checklist items yet.</p>
                            @endforelse
                        </div>
                    </details>

                    <details class="rounded-xl border border-white/20 bg-white/10 p-3">
                        <summary class="cursor-pointer font-medium text-white">Timeline</summary>
                        <div class="mt-3 max-h-52 space-y-1 overflow-auto text-xs text-[#D7DEE6]">
                            @forelse ($booking->events->take(20) as $event)
                                <p>{{ optional($event->happened_at)->format('M d H:i') }} - {{ strtoupper(str_replace('_', ' ', $event->event_type)) }}</p>
                            @empty
                                <p>No events yet.</p>
                            @endforelse
                        </div>
                    </details>

                    @if ($booking->changeRequests->count() > 0)
                        <details class="rounded-xl border border-white/20 bg-white/10 p-3">
                            <summary class="cursor-pointer font-medium text-white">Change requests</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($booking->changeRequests as $change)
                                    <div class="rounded border border-white/15 bg-white/5 px-3 py-2">
                                        <p class="font-medium text-white">{{ strtoupper($change->type) }} - {{ strtoupper($change->status) }}</p>
                                        <p class="text-[#D7DEE6]">{{ $change->reason }}</p>
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

                    @if (! in_array($booking->status, [\App\Models\CareBooking::STATUS_CANCELLED, \App\Models\CareBooking::STATUS_REVIEWED], true))
                        <details class="rounded-xl border border-amber-300/40 bg-amber-500/10 p-3">
                            <summary class="cursor-pointer font-medium text-amber-100">Need to cancel or reschedule?</summary>
                            <div class="mt-3 space-y-4 text-sm text-amber-50">
                                <p class="text-xs text-amber-100">
                                    Send a request to the family. If they accept, the visit is cancelled or moved, and late cancellation is tracked automatically.
                                </p>
                                <div class="space-y-4 rounded-xl bg-white p-3 text-[#17313F]">
                                    <x-native-select-field
                                        label="Change type"
                                        wire:model="changeType"
                                        :options="[
                                            ['label' => 'Cancel visit', 'value' => 'cancel'],
                                            ['label' => 'Reschedule visit', 'value' => 'reschedule'],
                                        ]"
                                    />
                                    <x-textarea label="Reason" wire:model="changeReason" />
                                    @error('changeReason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                                    @if ($changeType === 'reschedule')
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <x-input type="datetime-local" label="Proposed start" wire:model="proposedStartAt" />
                                            <x-input type="datetime-local" label="Proposed end" wire:model="proposedEndAt" />
                                        </div>
                                    @endif
                                    <x-button color="amber" wire:click="submitChangeRequest">Send request</x-button>
                                </div>
                            </div>
                        </details>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <x-button color="white" light wire:click="openChat">Open chat</x-button>
                        <x-button color="white" light wire:click="setActiveTab('support')">Open support tools</x-button>
                    </div>
                        </div>
                    </details>
                </div>
                </div>
            </section>

            @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true))
                <x-card>
                    <x-slot:header>
                        @if ($canLeaveReview)
                            <h2 class="font-display text-lg font-semibold">Leave a review</h2>
                            <p class="text-xs text-[#7B8794]">Tap stars to rate this visit.</p>
                        @else
                            <h2 class="font-display text-lg font-semibold">Your review</h2>
                            <p class="text-xs text-emerald-600">Review submitted successfully.</p>
                        @endif
                    </x-slot:header>

                    @if ($canLeaveReview)
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-[#324457]">Rating</p>
                                <div class="mt-2 flex items-center gap-1">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <button
                                            type="button"
                                            wire:click="$set('reviewRating', {{ $star }})"
                                            class="rounded-md p-1 transition hover:scale-105 focus:outline-none focus:ring-2 focus:ring-amber-300"
                                            aria-label="Rate {{ $star }} out of 5"
                                        >
                                            <svg viewBox="0 0 20 20" class="h-8 w-8 {{ ($reviewRating ?? 0) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                                <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                            </svg>
                                        </button>
                                    @endfor
                                </div>
                                <p class="mt-1 text-xs text-[#7B8794]">
                                    @if ($reviewRating)
                                        Selected rating: {{ $reviewRating }}/5
                                    @else
                                        No rating selected yet.
                                    @endif
                                </p>
                                @error('reviewRating') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <x-textarea label="Review comment" wire:model="reviewComment" />
                            @error('reviewComment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="space-y-3">
                            <div class="flex items-center gap-1">
                                @for ($star = 1; $star <= 5; $star++)
                                    <svg viewBox="0 0 20 20" class="h-6 w-6 {{ ((int) ($caregiverReview?->rating ?? 0)) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                        <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                    </svg>
                                @endfor
                                <span class="ml-1 text-sm font-medium text-[#4B5B6B]">{{ (int) ($caregiverReview?->rating ?? 0) }}/5</span>
                            </div>

                            @if ($caregiverReview?->comment)
                                <p class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">{{ $caregiverReview->comment }}</p>
                            @else
                                <p class="text-sm text-[#7B8794]">No additional comment was provided.</p>
                            @endif
                        </div>
                    @endif

                    @if ($canLeaveReview)
                        <x-slot:footer>
                            <x-button color="amber" wire:click="submitReview">Submit review</x-button>
                        </x-slot:footer>
                    @endif
                </x-card>

                @if ($familyReview)
                    <x-card>
                        <x-slot:header>
                            <h2 class="font-display text-lg font-semibold">Family feedback on this visit</h2>
                        </x-slot:header>

                        <div class="space-y-3">
                            <div class="flex items-center gap-1">
                                @for ($star = 1; $star <= 5; $star++)
                                    <svg viewBox="0 0 20 20" class="h-6 w-6 {{ ((int) ($familyReview->rating ?? 0)) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                        <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                    </svg>
                                @endfor
                                <span class="ml-1 text-sm font-medium text-[#4B5B6B]">{{ (int) ($familyReview->rating ?? 0) }}/5</span>
                            </div>

                            @if ($familyReview->comment)
                                <p class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">{{ $familyReview->comment }}</p>
                            @else
                                <p class="text-sm text-[#7B8794]">No comment was provided by the family.</p>
                            @endif
                        </div>
                    </x-card>
                @endif
            @endif
        @endif
    @endif

    @if ($activeTab === 'support')
        @if (! $booking)
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Support</h2></x-slot:header>
                <div class="rounded-md border border-dashed border-[#D6CCBE] px-4 py-6 text-sm text-[#607080]">
                    Support actions become available after you are hired on this request.
                </div>
            </x-card>
        @else
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Safety and support</h2></x-slot:header>
                <div class="space-y-3">
                    @if (! in_array($booking->status, [\App\Models\CareBooking::STATUS_CANCELLED, \App\Models\CareBooking::STATUS_REVIEWED], true))
                        <details class="rounded border border-[#E4DDD3] p-3">
                            <summary class="cursor-pointer font-medium">Request cancellation or reschedule</summary>
                            <div class="mt-3 space-y-4">
                                <x-native-select-field
                                    label="Change type"
                                    wire:model="changeType"
                                    :options="[
                                        ['label' => 'Cancel visit', 'value' => 'cancel'],
                                        ['label' => 'Reschedule visit', 'value' => 'reschedule'],
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

                    <details class="rounded border border-[#E4DDD3] p-3" {{ $isExpiredRegularVisit ? 'open' : '' }}>
                        <summary class="cursor-pointer font-medium">Support ticket</summary>
                        <div class="mt-3 space-y-4">
                            <x-input label="Subject" wire:model="supportSubject" />
                            <x-native-select-field
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

                    <details class="rounded border border-[#E4DDD3] p-3">
                        <summary class="cursor-pointer font-medium">Report incident</summary>
                        <div class="mt-3 space-y-4">
                            <x-input label="Incident title" wire:model="incidentTitle" />
                            <x-native-select-field
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
                        <details class="rounded border border-[#E4DDD3] p-3">
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

                    this.capturePositionAndCall('startBookingWithGeo', 'Starting visit...');
                },
                endWithGps() {
                    if (!this.canCheckOut || this.geoLoading) {
                        return;
                    }

                    this.capturePositionAndCall('completeBookingWithGeo', 'Ending visit...');
                },
                capturePositionAndCall(methodName, successMessage) {
                    if (!navigator.geolocation) {
                        this.geoMessage = 'GPS is not available. Recording a manual timestamp.';
                        this.$wire[methodName](null, null, null);
                        this.geoLoading = false;
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
                            this.geoMessage = 'Could not capture GPS. Recording a manual timestamp.';
                            this.$wire[methodName](null, null, null);
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

</div>



