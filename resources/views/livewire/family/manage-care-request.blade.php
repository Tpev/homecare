<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $booking = $requestItem->booking;
        $payment = $booking?->payment;
        $needsPaymentAuthorization = $payment && in_array($payment->status, [
            \App\Models\CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            \App\Models\CareBookingPayment::STATUS_REAUTH_REQUIRED,
            \App\Models\CareBookingPayment::STATUS_FAILED,
        ], true);
        $hiredApplication = $requestItem->applications->firstWhere('status', \App\Models\CareRequestApplication::STATUS_HIRED);
        $hiredCaregiverName = trim((string) ($hiredApplication?->caregiver?->name ?? ''));
        $hiredCaregiverFirstName = $hiredCaregiverName !== ''
            ? \Illuminate\Support\Str::of($hiredCaregiverName)->before(' ')
            : 'caregiver';
        $hiredConversation = $hiredApplication?->conversation;
        $noShowEligibleAt = $booking?->scheduled_start_at?->copy()->addMinutes(30);
        $canMarkNoShow = $booking
            && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED
            && $noShowEligibleAt
            && now()->gte($noShowEligibleAt);
        $postedAgo = \App\Support\CareRequestProgress::postedAgoLabel($requestItem);
        $firstResponse = \App\Support\CareRequestProgress::firstResponseLabel($requestItem);
        $firstHire = \App\Support\CareRequestProgress::firstHireLabel($requestItem);
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
        $familyReview = $booking?->reviews?->firstWhere('reviewer_user_id', (int) auth()->id());
        $caregiverReview = $booking?->reviews?->firstWhere('reviewer_user_id', (int) ($booking?->caregiver_user_id ?? 0));
        $canLeaveFamilyReview = $booking
            && in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true)
            && ! $familyReview;
        $workedMinutes = (int) ($booking?->worked_minutes ?? 0);
        $workedLabel = sprintf('%02d:%02d', intdiv($workedMinutes, 60), $workedMinutes % 60);
        $shiftRate = (float) ($hiredApplication?->proposed_rate ?? 0);
        $shiftEarnings = $workedMinutes > 0 && $shiftRate > 0
            ? round(($workedMinutes / 60) * $shiftRate, 2)
            : 0;
        $canWithdrawRequest = in_array($requestItem->status, [
            \App\Models\CareRequest::STATUS_DRAFT,
            \App\Models\CareRequest::STATUS_OPEN,
        ], true) && ! $booking;
        $canCancelScheduledShift = $booking && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED;
        $lateCancel = $booking?->scheduled_start_at
            ? now()->diffInMinutes($booking->scheduled_start_at, false) <= 24 * 60
            : false;
        $shiftStatusTone = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'border-[#BDD4F7] bg-[#EEF5FF] text-[#28486F]',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            \App\Models\CareBooking::STATUS_PAUSED => 'border-amber-200 bg-amber-50 text-amber-900',
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED => 'border-indigo-200 bg-indigo-50 text-indigo-900',
            \App\Models\CareBooking::STATUS_CANCELLED,
            \App\Models\CareBooking::STATUS_DISPUTED => 'border-rose-200 bg-rose-50 text-rose-900',
            default => 'border-[#E4DDD3] bg-[#F7F2EA] text-[#4B5B6B]',
        };
        $shiftStatusTitle = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Shift is scheduled',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Caregiver is checked in',
            \App\Models\CareBooking::STATUS_PAUSED => 'Shift is paused',
            \App\Models\CareBooking::STATUS_COMPLETED => 'Timesheet needs review',
            \App\Models\CareBooking::STATUS_REVIEWED => 'Shift is closed',
            \App\Models\CareBooking::STATUS_CANCELLED => 'Shift was cancelled',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Shift is in dispute',
            default => 'Shift status',
        };
        $shiftStatusBody = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Waiting for caregiver check-in. You can cancel before check-in, or mark no-show 30 minutes after scheduled start.',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'The caregiver has started the shift. Watch check-in details, message them, or mark complete when care is finished.',
            \App\Models\CareBooking::STATUS_PAUSED => 'The caregiver paused the shift. They can resume or end from their shift screen.',
            \App\Models\CareBooking::STATUS_COMPLETED => 'The caregiver submitted the timesheet. Review worked time before confirming.',
            \App\Models\CareBooking::STATUS_REVIEWED => 'Payment and review flow is complete.',
            \App\Models\CareBooking::STATUS_CANCELLED => $booking?->no_show_flag ? 'This shift was closed as caregiver no-show.' : 'This shift was cancelled.',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Support is reviewing this shift.',
            default => 'Shift operations are available after hiring.',
        };
        $shiftBadgeColor = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'green',
            \App\Models\CareBooking::STATUS_PAUSED => 'amber',
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED => 'indigo',
            \App\Models\CareBooking::STATUS_CANCELLED,
            \App\Models\CareBooking::STATUS_DISPUTED => 'red',
            default => 'blue',
        };
    @endphp

    @if ($needsPaymentAuthorization)
        <x-alert color="amber">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold">Card authorization needs attention.</p>
                    <p class="text-sm">{{ $payment->last_error ?: 'Confirm the card authorization before the shift is financially protected.' }}</p>
                </div>
                <x-button color="amber" wire:click="startPaymentAuthorization" class="w-full sm:w-auto">Confirm card authorization</x-button>
            </div>
        </x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-display font-semibold text-[#17313F]">{{ $requestItem->title }}</h1>
                        <x-badge :text="strtoupper($requestItem->status)" color="blue" />
                    @if ($booking)
                        <x-badge :text="'SHIFT '.strtoupper($booking->status)" :color="$shiftBadgeColor" />
                    @endif
                    @if ($payment)
                        <x-badge :text="'PAYMENT '.strtoupper($payment->status)" color="amber" />
                    @endif
                </div>
                    <p class="mt-1 text-sm text-[#607080]">
                        {{ $requestItem->city }}, {{ $requestItem->state }}
                        @if ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                            - One-time request
                        @else
                            - Recurring request
                        @endif
                        - {{ $requestItem->preferred_response_hours ?: 12 }}h response target
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                        <x-button color="blue" wire:click="setActiveTab('applicants')" class="w-full sm:w-auto">Review applicants</x-button>
                    @endif
                    @if ($hiredConversation)
                        <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate>
                            <x-button color="indigo" light class="w-full sm:w-auto">Open chat</x-button>
                        </a>
                    @endif
                    @if (! $requestItem->care_plan_id && $booking && $hiredApplication && ! in_array($booking->status, [\App\Models\CareBooking::STATUS_CANCELLED, \App\Models\CareBooking::STATUS_DISPUTED], true))
                        <a href="{{ route('family.care.compose', $requestItem->id) }}" wire:navigate>
                            <x-button color="green" light class="w-full sm:w-auto">Book {{ $hiredCaregiverFirstName }} again</x-button>
                        </a>
                    @endif
                    @if ($canWithdrawRequest)
                        <x-button
                            color="red"
                            light
                            wire:click="withdrawRequest"
                            onclick="if (!confirm('Withdraw this request? Caregivers will no longer be able to apply.')) return false;"
                            class="w-full sm:w-auto"
                        >
                            Withdraw request
                        </x-button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[1.4rem] border border-[#DED6CA] bg-[#F5F1EB] p-4 sm:col-span-2 xl:col-span-1">
                    <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Best next action</p>
                    <p class="mt-1 font-display text-base font-semibold text-[#17313F]">{{ $bestNextAction['title'] }}</p>
                    <p class="mt-1 text-sm text-[#607080]">{{ $bestNextAction['action'] }}</p>
                </div>
                <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                    <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Response metrics</p>
                    <p class="mt-1 text-sm text-[#4B5B6B]">Posted: <span class="font-semibold text-[#17313F]">{{ $postedAgo }}</span></p>
                    <p class="text-sm text-[#4B5B6B]">First response: <span class="font-semibold text-[#17313F]">{{ $firstResponse }}</span></p>
                    <p class="text-sm text-[#4B5B6B]">First hire: <span class="font-semibold text-[#17313F]">{{ $firstHire }}</span></p>
                </div>
                <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4 sm:col-span-2">
                    <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Pipeline status</p>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <div class="rounded-[1rem] bg-[#F5F1EB] px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Applicants</p>
                            <p class="mt-1 text-lg font-semibold text-[#17313F]">{{ $requestItem->applications->count() }}</p>
                        </div>
                        <div class="rounded-[1rem] bg-[#F5F1EB] px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Invites</p>
                            <p class="mt-1 text-lg font-semibold text-[#17313F]">{{ $requestItem->invitations->count() }}</p>
                        </div>
                        <div class="rounded-[1rem] bg-[#F5F1EB] px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.12em] text-[#7B8794]">Shift</p>
                            <p class="mt-1 text-sm font-semibold text-[#17313F]">{{ strtoupper($booking?->status ?? 'NONE') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:header>

        @if ($requestItem->status === \App\Models\CareRequest::STATUS_CANCELLED)
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                This request has been withdrawn. Caregivers can no longer apply or respond to invitations.
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <button
                type="button"
                wire:click="setActiveTab('overview')"
                class="{{ $activeTab === 'overview' ? 'bg-[#0F3D3E] text-[#FAF9F7] border-[#0F3D3E] shadow-sm' : 'bg-[rgba(255,253,250,0.98)] text-[#0F3D3E] border-[#DED6CA] hover:border-[#B7ADA0]' }} rounded-[1.3rem] border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Overview</p>
                <p class="text-xs opacity-80">Request details and contact context</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('applicants')"
                class="{{ $activeTab === 'applicants' ? 'bg-[#0F3D3E] text-[#FAF9F7] border-[#0F3D3E] shadow-sm' : 'bg-[rgba(255,253,250,0.98)] text-[#0F3D3E] border-[#DED6CA] hover:border-[#B7ADA0]' }} rounded-[1.3rem] border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Applicants</p>
                <p class="text-xs opacity-80">{{ $requestItem->applications->count() }} candidate(s)</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('shift')"
                class="{{ $activeTab === 'shift' ? 'bg-[#0F3D3E] text-[#FAF9F7] border-[#0F3D3E] shadow-sm' : 'bg-[rgba(255,253,250,0.98)] text-[#0F3D3E] border-[#DED6CA] hover:border-[#B7ADA0]' }} rounded-[1.3rem] border px-4 py-3 text-left transition"
            >
                <p class="font-display text-base font-semibold">Shift</p>
                <p class="text-xs opacity-80">{{ $booking ? 'Live operations' : 'No booking yet' }}</p>
            </button>

            <button
                type="button"
                wire:click="setActiveTab('support')"
                class="{{ $activeTab === 'support' ? 'bg-[#0F3D3E] text-[#FAF9F7] border-[#0F3D3E] shadow-sm' : 'bg-[rgba(255,253,250,0.98)] text-[#0F3D3E] border-[#DED6CA] hover:border-[#B7ADA0]' }} rounded-[1.3rem] border px-4 py-3 text-left transition"
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
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Address</p>
                        <p class="mt-1 text-[#324457]">
                            {{ $requestItem->address_line1 }}{{ $requestItem->address_line2 ? ', '.$requestItem->address_line2 : '' }},
                            {{ $requestItem->city }}, {{ $requestItem->state }} {{ $requestItem->zip }}
                        </p>
                        @if ($serviceMapEmbedUrl)
                            <div wire:ignore class="mt-3 overflow-hidden rounded-xl border border-[#E4DDD3] bg-[#F7F2EA]">
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

                <div class="space-y-4">
                    <div class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3">
                        <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Recipient</p>
                        <p class="mt-1 font-medium text-[#17313F]">{{ $requestItem->recipient?->full_name ?: '-' }}</p>
                        <p class="text-[#607080]">{{ $requestItem->recipient?->relationship_to_family ?: '-' }}</p>
                    </div>

                    @if ($requestItem->thirdPartyContact)
                        <div class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3">
                            <p class="text-xs font-semibold tracking-wide text-[#7B8794] uppercase">Third-party contact</p>
                            <p class="mt-1 font-medium text-[#17313F]">{{ $requestItem->thirdPartyContact->full_name }}</p>
                            <p class="text-[#607080]">{{ $requestItem->thirdPartyContact->phone ?: '-' }}</p>
                            <p class="text-[#607080]">{{ $requestItem->thirdPartyContact->email ?: '-' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Task list</h2>
                    <p class="text-sm text-[#607080]">{{ $requestItem->tasks->count() }} task(s)</p>
                </div>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @forelse ($requestItem->tasks as $task)
                    <div class="rounded-lg border border-[#E4DDD3] p-3">
                        <p class="font-display font-semibold text-[#17313F]">{{ $task->name }}</p>
                        <p class="mt-1 text-sm text-[#607080]">{{ $task->pivot?->task_note ?: 'No additional notes.' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-[#607080]">No tasks attached to this request.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Selected caregiver</h2>
                    <p class="text-sm text-[#607080]">{{ $requestItem->invitations->count() }} invite(s) sent</p>
                </div>
            </x-slot:header>

            @if ($hiredApplication)
                @php
                    $selectedCaregiverProfile = $hiredApplication->caregiver->caregiverProfile;
                    $selectedCaregiverProfileHref = $selectedCaregiverProfile?->slug
                        ? route('caregivers.show', $selectedCaregiverProfile->slug)
                        : null;
                @endphp
                <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="font-display text-lg font-semibold text-[#17313F]">{{ $hiredApplication->caregiver->name }}</p>
                            <p class="text-sm text-[#607080]">
                                Platform rate: ${{ number_format((float) $hiredApplication->proposed_rate, 2) }}/hr
                            </p>
                            @if ($selectedCaregiverProfile)
                                <p class="mt-1 text-sm text-[#4B5B6B]">
                                    {{ (int) ($selectedCaregiverProfile->years_experience ?? 0) }} year{{ (int) ($selectedCaregiverProfile->years_experience ?? 0) === 1 ? '' : 's' }} experience
                                    @if ($selectedCaregiverProfile->average_rating && $selectedCaregiverProfile->reviews_count > 0)
                                        - {{ number_format((float) $selectedCaregiverProfile->average_rating, 1) }} stars from {{ (int) $selectedCaregiverProfile->reviews_count }} review{{ (int) $selectedCaregiverProfile->reviews_count === 1 ? '' : 's' }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @if ($selectedCaregiverProfileHref)
                                <a href="{{ $selectedCaregiverProfileHref }}" wire:navigate>
                                    <x-button color="blue" light>View profile</x-button>
                                </a>
                            @endif
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
                <div class="rounded-lg border border-dashed border-[#D6CCBE] px-4 py-5 text-sm text-[#607080]">
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
                    <p class="text-sm text-[#607080]">{{ $requestItem->applications->count() }} total</p>
                </div>
            </x-slot:header>

            @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                <div class="mb-5 rounded-xl border border-[#BDD4F7] bg-[#EEF5FF] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                    <p class="text-xs uppercase tracking-[0.12em] text-[#7C5DDC]">Smart shortlist</p>
                            <h3 class="font-display text-lg font-semibold text-[#17313F]">Top suggested caregivers for this request</h3>
                            <p class="text-sm text-[#607080]">Invite individually to accelerate first response.</p>
                        </div>
                    </div>

                    @if ($suggestedCaregivers->isNotEmpty())
                        <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-3">
                            @foreach ($suggestedCaregivers as $suggestion)
                                @php
                                    $suggestionPhotoUrl = !empty($suggestion['profile_photo_path'])
                                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($suggestion['profile_photo_path'])
                                        : null;
                                    $suggestionAverageRating = !empty($suggestion['average_rating'])
                                        ? (float) $suggestion['average_rating']
                                        : null;
                                    $suggestionReviewsCount = (int) ($suggestion['reviews_count'] ?? 0);
                                @endphp
                                <div class="rounded-xl border border-[#BDD4F7] bg-white p-3">
                                    <div class="flex items-start gap-3">
                                        <div class="shrink-0">
                                            @if ($suggestionPhotoUrl)
                                                <img
                                                    src="{{ $suggestionPhotoUrl }}"
                                                    alt="{{ $suggestion['name'] }}"
                                                    class="h-11 w-11 rounded-full border border-[#DED6CA] object-cover"
                                                >
                                            @else
                                                <div class="flex h-11 w-11 items-center justify-center rounded-full border border-[#DED6CA] bg-[#F5F1EB] text-sm font-semibold text-[#0F3D3E]">
                                                    {{ \Illuminate\Support\Str::of($suggestion['name'])->trim()->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <p class="font-display font-semibold text-[#17313F]">{{ $suggestion['name'] }}</p>
                                            <p class="text-xs text-[#607080]">{{ $suggestion['proximity'] }} - Match score {{ $suggestion['score'] }}</p>
                                            <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[#607080]">
                                                @if ($suggestionAverageRating && $suggestionReviewsCount > 0)
                                                    <span class="inline-flex items-center gap-1 font-medium text-[#17313F]">
                                                        <svg viewBox="0 0 20 20" class="h-4 w-4 text-amber-400" fill="currentColor" aria-hidden="true">
                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81H7.03a1 1 0 00.951-.69l1.07-3.292z" />
                                                        </svg>
                                                        {{ number_format($suggestionAverageRating, 1) }}
                                                    </span>
                                                    <span>{{ $suggestionReviewsCount }} review{{ $suggestionReviewsCount === 1 ? '' : 's' }}</span>
                                                @else
                                                    <span class="text-[#7B8794]">No reviews yet</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @if ($suggestion['identity_verified'])
                                            <span class="inline-flex rounded-full bg-[#E8F0FF] px-2 py-1 text-[11px] font-medium text-[#4F6FAF]">Identity verified</span>
                                        @endif
                                        @if ($suggestion['background_check'])
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-medium text-emerald-700">Background check</span>
                                        @endif
                                        @if ($suggestion['top_caregiver'])
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-[11px] font-medium text-amber-700">Top caregiver</span>
                                        @endif
                                    </div>

                                    <p class="mt-2 text-xs text-[#7B8794]">{{ implode(' - ', array_slice($suggestion['reasons'], 0, 2)) }}</p>

                                    <div class="mt-3">
                                        <x-button color="blue" light wire:click="inviteSuggestedCaregiver({{ $suggestion['user_id'] }})">
                                            Invite caregiver
                                        </x-button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-3 rounded-lg border border-dashed border-[#BDD4F7] bg-white px-3 py-3 text-sm text-[#607080]">
                            No auto-suggestions yet. You can still review incoming applicants as they arrive.
                        </div>
                    @endif
                </div>
            @endif

            @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-native-select-field label="Status" wire:model.live="applicationStatus" :options="$applicationStatusOptions" />
                    <x-native-select-field
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
                <div class="mb-4 rounded-md border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">
                    Hiring is closed for this request. Applicant list is now read-only.
                </div>
            @endif

            <div class="space-y-3">
                @forelse ($this->visibleApplications as $application)
                    @php
                        $caregiverProfile = $application->caregiver->caregiverProfile;
                        $photoUrl = $caregiverProfile?->profile_photo_path
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($caregiverProfile->profile_photo_path)
                            : null;
                        $averageRating = $caregiverProfile?->average_rating ? (float) $caregiverProfile->average_rating : null;
                        $reviewsCount = (int) ($caregiverProfile?->reviews_count ?? 0);
                        $profileHref = $caregiverProfile?->slug ? route('caregivers.show', $caregiverProfile->slug) : null;
                        $yearsExperience = (int) ($caregiverProfile?->years_experience ?? 0);
                        $skills = $caregiverProfile?->skills ?? collect();
                        $languages = $caregiverProfile?->languages ?? collect();
                    @endphp
                    <div class="rounded-2xl border border-[#E4DDD3] p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0">
                                @if ($photoUrl)
                                    <img
                                        src="{{ $photoUrl }}"
                                        alt="{{ $application->caregiver->name }}"
                                        class="h-12 w-12 rounded-full border border-[#DED6CA] object-cover"
                                    >
                                @else
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full border border-[#DED6CA] bg-[#F5F1EB] text-sm font-semibold text-[#0F3D3E]">
                                        {{ \Illuminate\Support\Str::of($application->caregiver->name)->trim()->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') }}
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-display text-lg font-semibold text-[#17313F]">{{ $application->caregiver->name }}</p>
                                    <x-badge :text="strtoupper($application->status)" color="blue" />
                                </div>
                                <p class="mt-1 text-sm text-[#607080]">
                                    {{ $application->caregiver->city }}, {{ $application->caregiver->state }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[#607080]">
                                    <span>{{ $yearsExperience }} year{{ $yearsExperience === 1 ? '' : 's' }} experience</span>
                                    @if ($averageRating && $reviewsCount > 0)
                                        <span class="inline-flex items-center gap-1 font-medium text-[#17313F]">
                                            <svg viewBox="0 0 20 20" class="h-4 w-4 text-amber-400" fill="currentColor" aria-hidden="true">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81H7.03a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                            {{ number_format($averageRating, 1) }}
                                        </span>
                                        <span>{{ $reviewsCount }} review{{ $reviewsCount === 1 ? '' : 's' }}</span>
                                    @else
                                        <span class="text-[#7B8794]">No reviews yet</span>
                                    @endif
                                    @if ($caregiverProfile?->reliability_score)
                                        <span>Reliability {{ number_format((float) $caregiverProfile->reliability_score, 0) }}%</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($caregiverProfile)
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ($caregiverProfile->hasIdentityVerifiedBadge())
                                    <x-badge color="cyan" text="Identity verified" />
                                @endif
                                @if ($caregiverProfile->hasBackgroundCheckBadge())
                                    <x-badge color="green" text="Background check" />
                                @endif
                                @if ($caregiverProfile->hasTopCaregiverBadge())
                                    <x-badge color="amber" text="Top Caregiver" />
                                @endif
                                <x-badge color="{{ $caregiverProfile->is_accepting_new_clients ? 'green' : 'slate' }}" text="{{ $caregiverProfile->is_accepting_new_clients ? 'Accepting clients' : 'Limited availability' }}" />
                            </div>

                            @if ($caregiverProfile->bio)
                                <p class="mt-3 text-sm leading-6 text-[#4B5B6B]">{{ \Illuminate\Support\Str::limit((string) $caregiverProfile->bio, 220) }}</p>
                            @endif

                            @if ($skills->isNotEmpty() || $languages->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @if ($skills->isNotEmpty())
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($skills->take(5) as $skill)
                                                <span class="rounded-full bg-[#F0E9E1] px-3 py-1 text-xs font-medium text-[#4B5B6B]">{{ $skill->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($languages->isNotEmpty())
                                        <p class="text-xs text-[#7B8794]">Languages: {{ $languages->take(4)->pluck('name')->implode(', ') }}</p>
                                    @endif
                                </div>
                            @endif
                        @endif

                        @if ($application->cover_note)
                            <p class="mt-3 whitespace-pre-line text-sm text-[#4B5B6B]">{{ $application->cover_note }}</p>
                        @endif

                        <div class="mt-4 space-y-2">
                            @if ($profileHref)
                                <a href="{{ $profileHref }}" wire:navigate class="block sm:inline-block">
                                    <x-button color="blue" light class="w-full sm:w-auto">View full caregiver profile</x-button>
                                </a>
                            @endif

                            @if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <x-button color="green" wire:click="hire({{ $application->id }})" class="w-full">Hire caregiver</x-button>

                                    @if (in_array($application->status, ['shortlisted', 'hired'], true))
                                        <x-button color="indigo" light wire:click="startConversation({{ $application->id }})" class="w-full">
                                            {{ $application->conversation ? 'Open chat' : 'Start chat' }}
                                        </x-button>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <x-button color="blue" light wire:click="shortlist({{ $application->id }})">Shortlist</x-button>
                                    <x-button color="red" outline wire:click="reject({{ $application->id }})">Reject</x-button>
                                </div>
                            @elseif ($application->conversation)
                                <a href="{{ route('messages.show', $application->conversation->id) }}" wire:navigate class="block sm:inline-block">
                                    <x-button color="indigo" light class="w-full sm:w-auto">Open chat</x-button>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-md border border-dashed border-[#D6CCBE] px-4 py-6 text-sm text-[#607080]">
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
                <div class="rounded-md border border-dashed border-[#D6CCBE] px-4 py-6 text-sm text-[#607080]">
                    Shift operations become available once you hire a caregiver.
                </div>
            </x-card>
        @else
            <section class="space-y-4 rounded-3xl border border-[#D8E1D7] bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="hc-brand-kicker">Shift command center</p>
                        <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $requestItem->title }}</h2>
                        <p class="mt-1 text-sm text-[#607080]">Track live care, location, cancellation, and no-show timing from one place.</p>
                    </div>
                    <x-badge :text="strtoupper(str_replace('_', ' ', $booking->status))" :color="$shiftBadgeColor" />
                </div>

                <div class="rounded-2xl border px-4 py-3 {{ $shiftStatusTone }}">
                    <p class="font-display text-lg font-semibold">{{ $shiftStatusTitle }}</p>
                    <p class="mt-1 text-sm">{{ $shiftStatusBody }}</p>
                </div>

                <div class="grid grid-cols-1 gap-3 xl:grid-cols-4">
                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Schedule</p>
                        <p class="mt-2 font-semibold text-[#17313F]">{{ optional($booking->scheduled_start_at)->format('M d, Y') ?: 'Pending' }}</p>
                        <p class="text-sm text-[#607080]">{{ optional($booking->scheduled_start_at)->format('g:i A') ?: '-' }} - {{ optional($booking->scheduled_end_at)->format('g:i A') ?: '-' }}</p>
                    </div>

                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Caregiver</p>
                        <p class="mt-2 font-semibold text-[#17313F]">{{ $hiredApplication?->caregiver?->name ?: 'Selected caregiver' }}</p>
                        <p class="text-sm text-[#607080]">{{ $booking->started_at ? 'Checked in '.$booking->started_at->format('M d, g:i A') : 'Check-in pending' }}</p>
                    </div>

                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 xl:col-span-2">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Care location</p>
                        <p class="mt-2 font-semibold text-[#17313F]">{{ $serviceAddress ?: 'Address not set' }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($serviceMapOpenUrl)
                                <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-[#7C5DDC] underline underline-offset-2">Open map</a>
                            @endif
                            <span class="text-sm text-[#607080]">{{ $requestItem->recipient?->full_name ?: 'Recipient' }} - {{ $requestItem->recipient?->relationship_to_family ?: 'Care recipient' }}</span>
                        </div>
                    </div>
                </div>

                @if ($serviceMapEmbedUrl)
                    <div wire:ignore class="overflow-hidden rounded-2xl border border-[#E4DDD3] bg-[#F7F2EA]">
                        <iframe
                            title="Shift service location map"
                            src="{{ $serviceMapEmbedUrl }}"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            class="h-56 w-full"
                        ></iframe>
                    </div>
                @endif

                @if (! $payment)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Payment authorization is not ready yet.
                        <button type="button" wire:click="startPaymentAuthorization" class="font-semibold underline underline-offset-2">Confirm card authorization</button>
                        or update your card in
                        <a href="{{ route('family.billing.show') }}" wire:navigate class="font-medium underline underline-offset-2">Billing & Payments</a>.
                    </div>
                @else
                    <div class="rounded-xl border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-xs text-[#4B5B6B]">
                        Payment status: <span class="font-semibold text-[#17313F]">{{ strtoupper($payment->status) }}</span>
                        @if ($payment->amount_authorized_cents)
                            - Authorized ${{ number_format($payment->amount_authorized_cents / 100, 2) }}
                        @endif
                        @if ($payment->amount_captured_cents)
                            - Captured ${{ number_format($payment->amount_captured_cents / 100, 2) }}
                        @endif
                        @if ($payment->last_error)
                            <span class="mt-1 block text-amber-800">{{ $payment->last_error }}</span>
                            <button type="button" wire:click="startPaymentAuthorization" class="mt-1 inline-block font-semibold text-amber-900 underline underline-offset-2">Confirm authorization</button>
                            <a href="{{ route('family.billing.show') }}" wire:navigate class="ml-2 mt-1 inline-block font-semibold text-amber-900 underline underline-offset-2">Update billing</a>
                        @endif
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        @if ($hiredConversation)
                            <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate>
                                <x-button color="indigo" class="w-full sm:w-auto">Open chat</x-button>
                            </a>
                        @endif

                        @if ($needsPaymentAuthorization)
                            <x-button color="amber" wire:click="startPaymentAuthorization" class="w-full sm:w-auto">Confirm card authorization</x-button>
                        @endif

                        @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true))
                            <x-button color="green" wire:click="completeBooking" class="w-full sm:w-auto">Mark shift complete</x-button>
                        @endif

                        @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true) && ! $booking->family_confirmed_at)
                            <x-button color="green" wire:click="completeBooking" class="w-full sm:w-auto">Confirm timesheet</x-button>
                        @endif

                        @if ($canMarkNoShow)
                            <x-button color="red" light wire:click="markNoShow" onclick="if (!confirm('Mark this caregiver as no-show? This cancels the shift and affects reliability.')) return false;" class="w-full sm:w-auto">Mark caregiver no-show</x-button>
                        @endif

                        <x-button color="white" light wire:click="setActiveTab('support')" class="w-full sm:w-auto">Safety/support</x-button>
                    </div>

                    @if ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED)
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3 text-sm text-[#4B5B6B]">
                            <p class="font-semibold text-[#17313F]">No-show rule</p>
                            @if ($canMarkNoShow)
                                <p class="mt-1">Available now because the scheduled start was more than 30 minutes ago.</p>
                            @elseif ($noShowEligibleAt)
                                <p class="mt-1">No-show unlocks at {{ $noShowEligibleAt->format('M d, g:i A') }}.</p>
                            @else
                                <p class="mt-1">No-show requires a scheduled start time.</p>
                            @endif
                        </div>
                    @endif
                </div>

                @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true) && ! $booking->family_confirmed_at)
                    <x-input label="Confirmation note (optional)" wire:model="confirmationNote" />
                @endif

                @if ($canCancelScheduledShift)
                    <details class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                        <summary class="cursor-pointer font-display text-base font-semibold text-rose-900">Cancel this scheduled shift</summary>
                        <div class="mt-3 space-y-3 text-sm text-rose-900">
                            <p>
                                This cancels the shift before caregiver check-in and releases the payment authorization when possible.
                                @if ($lateCancel)
                                    It is inside the late-cancellation window.
                                @endif
                            </p>
                            <x-textarea label="Cancellation reason" wire:model="directCancelReason" />
                            @error('directCancelReason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            <x-button color="red" wire:click="cancelScheduledBooking" onclick="if (!confirm('Cancel this scheduled shift?')) return false;">Cancel shift</x-button>
                        </div>
                    </details>
                @endif

                <div class="space-y-4 text-sm">

                    @if ($booking->timesheet_submitted_at || $booking->worked_minutes)
                        <div class="grid grid-cols-1 gap-3 rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3 md:grid-cols-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Worked time</p>
                                <p class="mt-1 text-base font-semibold text-[#17313F]">{{ $workedLabel }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Estimated shift total</p>
                                <p class="mt-1 text-base font-semibold text-[#17313F]">${{ number_format($shiftEarnings, 2) }}</p>
                                <p class="text-xs text-[#7B8794]">{{ '$'.number_format($shiftRate, 2) }}/hr</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Timesheet</p>
                                <p class="mt-1 text-base font-semibold text-[#17313F]">
                                    {{ $booking->family_confirmed_at ? 'Confirmed' : 'Awaiting your confirmation' }}
                                </p>
                                <p class="text-xs text-[#7B8794]">
                                    Submitted {{ optional($booking->timesheet_submitted_at)->format('M d, H:i') ?: 'Pending' }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4 text-xs text-[#4B5B6B]">
                        <div class="rounded-lg border border-[#E4DDD3] bg-white px-3 py-2">Caregiver check-in: {{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-[#E4DDD3] bg-white px-3 py-2">Caregiver check-out: {{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-[#E4DDD3] bg-white px-3 py-2">Family confirmation: {{ optional($booking->family_confirmed_at)->format('M d, H:i') ?: 'Pending' }}</div>
                        <div class="rounded-lg border border-[#E4DDD3] bg-white px-3 py-2">Dispute: {{ strtoupper($booking->dispute_status ?? 'none') }}</div>
                    </div>

                    @if ($booking->expected_minutes || $booking->worked_minutes)
                        <p class="text-xs text-[#607080]">
                            Minutes: expected {{ $booking->expected_minutes ?? '-' }} - worked {{ $booking->worked_minutes ?? '-' }}
                        </p>
                    @endif

                    <details class="rounded-lg border border-[#E4DDD3] bg-white p-3">
                        <summary class="cursor-pointer font-medium text-[#17313F]">Task completion snapshot</summary>
                        <div class="mt-3 space-y-2">
                            @forelse ($booking->taskChecks as $taskCheck)
                                <div class="rounded border border-[#E4DDD3] px-3 py-2">
                                    <p class="{{ $taskCheck->is_completed ? 'line-through text-[#7B8794]' : 'text-[#17313F]' }}">{{ $taskCheck->label }}</p>
                                    @if ($taskCheck->notes)
                                        <p class="text-xs text-[#7B8794]">{{ $taskCheck->notes }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-[#607080]">No task checks yet.</p>
                            @endforelse
                        </div>
                    </details>

                    <details class="rounded-lg border border-[#E4DDD3] bg-white p-3">
                        <summary class="cursor-pointer font-medium text-[#17313F]">Timeline</summary>
                        <div class="mt-3 max-h-52 space-y-1 overflow-auto text-xs text-[#607080]">
                            @forelse ($booking->events->take(20) as $event)
                                <p>{{ optional($event->happened_at)->format('M d H:i') }} - {{ strtoupper(str_replace('_', ' ', $event->event_type)) }}</p>
                            @empty
                                <p>No events yet.</p>
                            @endforelse
                        </div>
                    </details>

                    @if ($booking->changeRequests->count() > 0)
                        <details class="rounded-lg border border-[#E4DDD3] bg-white p-3">
                            <summary class="cursor-pointer font-medium text-[#17313F]">Change requests</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($booking->changeRequests as $change)
                                    <div class="rounded-md border border-[#E4DDD3] px-3 py-2">
                                        <p class="font-medium">{{ strtoupper($change->type) }} - {{ strtoupper($change->status) }}</p>
                                        <p class="text-[#607080]">{{ $change->reason }}</p>
                                        @if ($change->proposed_start_at)
                                            <p class="text-xs text-[#7B8794]">
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
            </section>

            @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true))
                <x-card>
                    <x-slot:header>
                        @if ($canLeaveFamilyReview)
                            <h2 class="font-display text-lg font-semibold">Leave a caregiver review</h2>
                            <p class="text-xs text-[#7B8794]">Tap stars to rate this shift.</p>
                        @else
                            <h2 class="font-display text-lg font-semibold">Your caregiver review</h2>
                            <p class="text-xs text-emerald-600">Review submitted successfully.</p>
                        @endif
                    </x-slot:header>

                    @if ($canLeaveFamilyReview)
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

                        <x-slot:footer>
                            <x-button color="amber" wire:click="submitReview">Submit review</x-button>
                        </x-slot:footer>
                    @else
                        <div class="space-y-3">
                            <div class="flex items-center gap-1">
                                @for ($star = 1; $star <= 5; $star++)
                                    <svg viewBox="0 0 20 20" class="h-6 w-6 {{ ((int) ($familyReview?->rating ?? 0)) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                        <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                    </svg>
                                @endfor
                                <span class="ml-1 text-sm font-medium text-[#4B5B6B]">{{ (int) ($familyReview?->rating ?? 0) }}/5</span>
                            </div>

                            @if ($familyReview?->comment)
                                <p class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">{{ $familyReview->comment }}</p>
                            @else
                                <p class="text-sm text-[#7B8794]">No additional comment was provided.</p>
                            @endif
                        </div>
                    @endif
                </x-card>

                @if ($caregiverReview)
                    <x-card>
                        <x-slot:header>
                            <h2 class="font-display text-lg font-semibold">Caregiver feedback about this shift</h2>
                        </x-slot:header>
                        <div class="space-y-3">
                            <div class="flex items-center gap-1">
                                @for ($star = 1; $star <= 5; $star++)
                                    <svg viewBox="0 0 20 20" class="h-6 w-6 {{ ((int) ($caregiverReview->rating ?? 0)) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                        <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                    </svg>
                                @endfor
                                <span class="ml-1 text-sm font-medium text-[#4B5B6B]">{{ (int) $caregiverReview->rating }}/5</span>
                            </div>

                            @if ($caregiverReview->comment)
                                <p class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">{{ $caregiverReview->comment }}</p>
                            @else
                                <p class="text-sm text-[#7B8794]">No comment left by caregiver.</p>
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
                    Support operations become contextual after a caregiver is hired and a shift exists.
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

                    <details class="rounded border border-[#E4DDD3] p-3">
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
                @if (! $requestItem->care_plan_id && $hiredApplication && ! in_array($booking->status, [\App\Models\CareBooking::STATUS_CANCELLED, \App\Models\CareBooking::STATUS_DISPUTED], true))
                    <x-slot:footer>
                        <a href="{{ route('family.care.compose', $requestItem->id) }}" wire:navigate>
                            <x-button color="green" light>Book {{ $hiredCaregiverFirstName }} again</x-button>
                        </a>
                    </x-slot:footer>
                @endif
            </x-card>
        @endif
    @endif
</div>

@script
<script>
    window.homecareLoadStripeJs = window.homecareLoadStripeJs || (() => new Promise((resolve, reject) => {
        if (window.Stripe) {
            resolve(window.Stripe);

            return;
        }

        const existing = document.querySelector('script[src="https://js.stripe.com/v3/"]');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.Stripe));
            existing.addEventListener('error', () => reject(new Error('Stripe.js could not be loaded.')));

            return;
        }

        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = () => resolve(window.Stripe);
        script.onerror = () => reject(new Error('Stripe.js could not be loaded.'));
        document.head.appendChild(script);
    }));

    $wire.on('confirm-stripe-booking-payment', async (payload) => {
        const detail = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});

        if (window.homecareConfirmingBookingPayment || !detail.clientSecret || !detail.publishableKey) {
            return;
        }

        window.homecareConfirmingBookingPayment = true;

        try {
            const StripeConstructor = await window.homecareLoadStripeJs();
            const stripe = StripeConstructor(detail.publishableKey);
            const confirmParams = {
                return_url: window.location.href,
            };

            if (detail.paymentMethodId) {
                confirmParams.payment_method = detail.paymentMethodId;
            }

            const result = await stripe.confirmCardPayment(detail.clientSecret, confirmParams);

            if (result.error) {
                const paymentIntentId = result.error.payment_intent?.id || detail.paymentIntentId || '';
                await $wire.failStripeAuthorization(paymentIntentId, result.error.message || 'Card authorization failed.');

                return;
            }

            if (result.paymentIntent?.id) {
                await $wire.finalizeStripeAuthorization(result.paymentIntent.id);
            }
        } catch (error) {
            await $wire.failStripeAuthorization(detail.paymentIntentId || '', error?.message || 'Card authorization failed.');
        } finally {
            window.homecareConfirmingBookingPayment = false;
        }
    });
</script>
@endscript
