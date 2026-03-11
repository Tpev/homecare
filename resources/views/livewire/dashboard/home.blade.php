<div>
    <div class="hc-page py-8 space-y-6">
        @if ($mode === 'family')
            @php
                $focusRequests = $familyData['focus_requests'] ?? collect();
                $needsApplicants = $focusRequests->filter(function ($request) {
                    return $request->status === \App\Models\CareRequest::STATUS_OPEN
                        && (int) ($request->pending_candidate_count ?? 0) === 0;
                })->values();
                $readyToReview = $focusRequests->filter(function ($request) {
                    return $request->status === \App\Models\CareRequest::STATUS_OPEN
                        && (int) ($request->pending_candidate_count ?? 0) > 0;
                })->values();
                $activeShifts = $familyData['active_shifts'] ?? collect();
            @endphp

            <section class="hc-hero">
                <p class="text-xs uppercase tracking-[0.2em] text-blue-100">Family Dashboard</p>
                <div class="mt-3 grid grid-cols-1 gap-5 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <h1 class="text-3xl md:text-4xl font-display font-semibold leading-tight">Find reliable care quickly.</h1>
                        <p class="mt-3 text-blue-100 max-w-2xl">
                            Start with AI, review candidates in one place, and move to hire and chat without losing context.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('family.requests.create_ai') }}" wire:navigate><x-button color="white">Post with AI Copilot</x-button></a>
                            <a href="{{ route('family.requests.create') }}" wire:navigate><x-button color="white" light>Use manual form</x-button></a>
                        </div>
                    </div>
                    <div class="lg:col-span-2 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-blue-100">Ready to review</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $familyData['stats']['ready_to_review'] }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-blue-100">Waiting applicants</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $familyData['stats']['waiting_for_applicants'] }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-blue-100">Active shifts</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $familyData['stats']['active_shifts'] }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-blue-100">Unread messages</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $familyData['stats']['unread_messages'] }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <div class="xl:col-span-8 space-y-6">
                    <x-card>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <h2 class="font-display font-semibold">Priority request board</h2>
                                <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-link">View all requests</a>
                            </div>
                        </x-slot:header>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs uppercase tracking-[0.14em] text-amber-700 font-semibold">Needs applicants</p>
                                    <span class="text-sm font-semibold text-amber-900">{{ $needsApplicants->count() }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($needsApplicants->take(3) as $request)
                                        <div class="rounded-lg border border-amber-200 bg-white px-3 py-2">
                                            <p class="font-medium text-slate-900 text-sm">{{ $request->title }}</p>
                                            <p class="text-xs text-slate-500 mt-1">{{ $request->city }}, {{ $request->state }}</p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="text-xs font-medium text-cyan-700 underline underline-offset-2">Open</a>
                                                <a href="{{ route('caregivers.search') }}" wire:navigate class="text-xs font-medium text-cyan-700 underline underline-offset-2">Find</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-amber-800">No requests currently blocked on applicants.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs uppercase tracking-[0.14em] text-sky-700 font-semibold">Ready to review</p>
                                    <span class="text-sm font-semibold text-sky-900">{{ $readyToReview->count() }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($readyToReview->take(3) as $request)
                                        <div class="rounded-lg border border-sky-200 bg-white px-3 py-2">
                                            <p class="font-medium text-slate-900 text-sm">{{ $request->title }}</p>
                                            <p class="text-xs text-slate-500 mt-1">{{ (int) ($request->pending_candidate_count ?? 0) }} candidate(s) waiting</p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <p class="text-xs text-slate-500">{{ $request->city }}, {{ $request->state }}</p>
                                                <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="text-xs font-medium text-indigo-700 underline underline-offset-2">Review</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-sky-800">No requests waiting for candidate review.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs uppercase tracking-[0.14em] text-emerald-700 font-semibold">Active shifts</p>
                                    <span class="text-sm font-semibold text-emerald-900">{{ $activeShifts->count() }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($activeShifts->take(3) as $request)
                                        <div class="rounded-lg border border-emerald-200 bg-white px-3 py-2">
                                            <p class="font-medium text-slate-900 text-sm">{{ $request->title }}</p>
                                            <p class="text-xs text-slate-500 mt-1">Shift {{ strtoupper($request->booking?->status ?? 'n/a') }}</p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <p class="text-xs text-slate-500">{{ optional($request->booking?->scheduled_start_at)->format('M d, H:i') ?: '-' }}</p>
                                                <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="text-xs font-medium text-emerald-700 underline underline-offset-2">Manage</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-emerald-800">No active shifts right now.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </x-card>

                    <x-card>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <h2 class="font-display font-semibold">Latest applicant activity</h2>
                                <a href="{{ route('messages.index') }}" wire:navigate class="hc-link">Open messages</a>
                            </div>
                        </x-slot:header>

                        <div class="space-y-3">
                            @forelse ($familyData['recent_applicants'] as $application)
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $application->caregiver?->name }}</p>
                                            <p class="text-sm text-slate-600">{{ $application->careRequest?->title }}</p>
                                        </div>
                                        <x-badge :text="strtoupper($application->status)" color="blue" />
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <p class="text-xs text-slate-500">${{ number_format((float) $application->proposed_rate, 2) }}/hr</p>
                                        <div class="flex items-center gap-2">
                                            @if ($application->conversation)
                                                <a href="{{ route('messages.show', $application->conversation->id) }}" wire:navigate class="text-xs font-medium text-indigo-700 underline underline-offset-2">Chat</a>
                                            @endif
                                            @if ($application->careRequest)
                                                <a href="{{ route('family.requests.show', $application->careRequest->id) }}" wire:navigate class="text-xs font-medium text-cyan-700 underline underline-offset-2">Review</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-600">No applicant activity yet.</p>
                            @endforelse
                        </div>
                    </x-card>
                </div>

                <div class="xl:col-span-4 space-y-6">
                    <x-card>
                        <x-slot:header><h2 class="font-display font-semibold">Fast actions</h2></x-slot:header>
                        <div class="space-y-3">
                            <a href="{{ route('family.requests.create_ai') }}" wire:navigate class="block rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 hover:bg-slate-100 transition">
                                <p class="font-medium text-slate-900">Create request with AI</p>
                                <p class="text-xs text-slate-500 mt-1">Fastest way to publish a complete request.</p>
                            </a>
                            <a href="{{ route('caregivers.search') }}" wire:navigate class="block rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 hover:bg-slate-100 transition">
                                <p class="font-medium text-slate-900">Invite caregivers directly</p>
                                <p class="text-xs text-slate-500 mt-1">Don’t wait for applications when request is urgent.</p>
                            </a>
                            <a href="{{ route('messages.index') }}" wire:navigate class="block rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 hover:bg-slate-100 transition">
                                <p class="font-medium text-slate-900">Respond in chat now</p>
                                <p class="text-xs text-slate-500 mt-1">Faster replies increase hire conversion.</p>
                            </a>
                        </div>
                    </x-card>

                    <x-card>
                        <x-slot:header><h2 class="font-display font-semibold">Operations signal</h2></x-slot:header>
                        <div class="space-y-3 text-sm">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="font-medium text-slate-900">Open requests</p>
                                <p class="text-slate-600">{{ $familyData['stats']['open_requests'] }} currently running.</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="font-medium text-slate-900">Ready to review</p>
                                <p class="text-slate-600">{{ $familyData['stats']['ready_to_review'] }} request(s) need a shortlist decision.</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="font-medium text-slate-900">Waiting applicants</p>
                                <p class="text-slate-600">{{ $familyData['stats']['waiting_for_applicants'] }} request(s) may need proactive invite.</p>
                            </div>
                            @if (($familyData['urgent_open_requests'] ?? 0) > 0)
                                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900">
                                    <p class="font-medium">Urgent follow-up</p>
                                    <p class="text-xs mt-1">{{ $familyData['urgent_open_requests'] }} request(s) have no applicants after 6+ hours.</p>
                                </div>
                            @endif
                        </div>
                    </x-card>
                </div>
            </section>
        @elseif ($mode === 'caregiver')
            @php
                $profile = $caregiverData['profile'] ?? null;
                $setupCards = $caregiverData['setup_cards'] ?? [];
                $requiredCompleted = (int) ($caregiverData['required_setup_completed'] ?? 0);
                $requiredTotal = (int) ($caregiverData['required_setup_total'] ?? 0);
                $readyForReview = (bool) ($caregiverData['ready_for_review'] ?? false);
                $canSubmitForReview = (bool) ($caregiverData['can_submit_for_review'] ?? false);
            @endphp

            <section class="hc-hero">
                <p class="text-xs uppercase tracking-[0.2em] text-emerald-100">Caregiver Dashboard</p>
                <div class="mt-3 grid grid-cols-1 gap-5 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <h1 class="text-3xl md:text-4xl font-display font-semibold leading-tight">Build a trusted profile and go live.</h1>
                        <p class="mt-3 text-emerald-100 max-w-2xl">
                            Complete the remaining setup cards below, then submit your profile. Reviews usually complete within 1 business day.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('caregiver.shifts.index') }}" wire:navigate><x-button color="white">My shifts</x-button></a>
                            <a href="{{ route('care-requests.index') }}" wire:navigate><x-button color="white">Browse Requests</x-button></a>
                            <a href="{{ route('messages.index') }}" wire:navigate><x-button color="white" light>Messages</x-button></a>
                            <a href="{{ route('caregiver.profile.edit') }}" wire:navigate><x-button color="white" light>Edit Profile</x-button></a>
                        </div>
                    </div>
                    <div class="lg:col-span-2 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-100">Required done</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $requiredCompleted }}/{{ $requiredTotal }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-100">Profile status</p>
                            <p class="mt-1 text-sm font-semibold uppercase">{{ str_replace('_', ' ', (string) ($profile?->status ?? 'draft')) }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-100">Unread messages</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $caregiverData['stats']['unread_messages'] }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-100">Pending invites</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $caregiverData['stats']['invitations_pending'] }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                @if (!empty($caregiverData['active_shift']))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-emerald-700 font-semibold">Live now</p>
                            <p class="font-semibold text-emerald-900">{{ $caregiverData['active_shift']->careRequest?->title ?? 'Current shift' }}</p>
                            <p class="text-sm text-emerald-800 mt-1">
                                {{ $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Paused' : 'Started' }}
                                {{ optional($caregiverData['active_shift']->started_at)->format('M d, H:i') ?: 'just now' }}
                                · {{ $caregiverData['active_shift']->careRequest?->city }}, {{ $caregiverData['active_shift']->careRequest?->state }}
                            </p>
                        </div>
                        @if ($caregiverData['active_shift']->careRequest)
                            <a href="{{ route('care-requests.apply', $caregiverData['active_shift']->careRequest->id) }}" wire:navigate>
                                <x-button color="green">{{ $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Resume shift' : 'Continue shift' }}</x-button>
                            </a>
                        @endif
                    </div>
                @elseif (!empty($caregiverData['next_shift']))
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-sky-700 font-semibold">Next shift</p>
                            <p class="font-semibold text-sky-900">{{ $caregiverData['next_shift']->careRequest?->title ?? 'Upcoming shift' }}</p>
                            <p class="text-sm text-sky-800 mt-1">
                                {{ optional($caregiverData['next_shift']->scheduled_start_at)->format('M d, Y H:i') ?: 'Date pending' }}
                                · {{ $caregiverData['next_shift']->careRequest?->city }}, {{ $caregiverData['next_shift']->careRequest?->state }}
                            </p>
                        </div>
                        @if ($caregiverData['next_shift']->careRequest)
                            <a href="{{ route('care-requests.apply', $caregiverData['next_shift']->careRequest->id) }}" wire:navigate>
                                <x-button color="blue">Open shift details</x-button>
                            </a>
                        @endif
                    </div>
                @endif

                @if (!empty($caregiverData['quick_shifts']) && $caregiverData['quick_shifts']->count() > 0)
                    <x-card>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <h2 class="font-display font-semibold">Shift quick access</h2>
                                <a href="{{ route('caregiver.shifts.index') }}" wire:navigate class="hc-link">View all shifts</a>
                            </div>
                        </x-slot:header>
                        <div class="space-y-3">
                            @foreach ($caregiverData['quick_shifts'] as $shift)
                                @php
                                    $shiftStatus = (string) $shift->status;
                                    $shiftCta = match ($shiftStatus) {
                                        \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Continue shift',
                                        \App\Models\CareBooking::STATUS_PAUSED => 'Resume shift',
                                        \App\Models\CareBooking::STATUS_SCHEDULED => 'Start shift',
                                        \App\Models\CareBooking::STATUS_COMPLETED => 'View recap',
                                        default => 'Open shift',
                                    };
                                @endphp
                                <div class="rounded-xl border border-slate-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $shift->careRequest?->title ?? 'Care request' }}</p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                {{ optional($shift->scheduled_start_at)->format('M d, H:i') ?: 'Date pending' }}
                                                · {{ $shift->careRequest?->city }}, {{ $shift->careRequest?->state }}
                                            </p>
                                        </div>
                                        <x-badge :text="strtoupper($shiftStatus)" color="{{ in_array($shiftStatus, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) ? 'green' : 'blue' }}" />
                                    </div>
                                    @if ($shift->careRequest)
                                        <div class="mt-3">
                                            <a href="{{ route('care-requests.apply', $shift->careRequest->id) }}" wire:navigate>
                                                <x-button color="{{ in_array($shiftStatus, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) ? 'green' : 'blue' }}" light sm>{{ $shiftCta }}</x-button>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif
            </section>

            <section class="space-y-4">
                @if ($canSubmitForReview)
                    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-cyan-900">Ready to submit.</p>
                            <p class="text-sm text-cyan-800">Open the review step, confirm your details, and submit now.</p>
                        </div>
                        <a href="{{ route('caregiver.onboarding', ['step' => 4]) }}" wire:navigate>
                            <x-button color="blue" sm>Submit profile for review</x-button>
                        </a>
                    </div>
                @elseif ($readyForReview)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-emerald-900">Setup complete.</p>
                            <p class="text-sm text-emerald-800">Your profile is already submitted or active.</p>
                        </div>
                        <x-badge color="green" text="{{ strtoupper(str_replace('_', ' ', (string) ($profile?->status ?? 'draft'))) }}" />
                    </div>
                @endif

                @if (count($setupCards) > 0)
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach ($setupCards as $card)
                            <div class="rounded-2xl border {{ $card['required'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.12em] {{ $card['required'] ? 'text-slate-600' : 'text-slate-500' }}">
                                            {{ $card['required'] ? 'Required' : 'Optional' }}
                                        </p>
                                        <h3 class="mt-1 font-display text-lg font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                        <p class="mt-1 text-sm text-slate-600">{{ $card['description'] }}</p>
                                    </div>
                                    <x-badge :color="$card['required'] ? 'amber' : 'slate'" text="PENDING" />
                                </div>
                                <div class="mt-4">
                                    <a href="{{ $card['route'] }}" wire:navigate>
                                        <x-button color="blue" light sm>{{ $card['cta'] }}</x-button>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="font-semibold text-emerald-900">All setup cards are complete.</p>
                        <p class="text-sm text-emerald-800 mt-1">If your profile is still draft, open the review step and submit.</p>
                    </div>
                @endif
            </section>

            @if (!empty($caregiverData['profile']))
                <section class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Reliability score</p>
                        <p class="text-xl font-semibold text-slate-900">{{ number_format((float) $caregiverData['profile']->reliability_score, 0) }}%</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Completed shifts</p>
                        <p class="text-xl font-semibold text-slate-900">{{ (int) $caregiverData['profile']->completed_bookings_count }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Platform rate</p>
                        <p class="text-xl font-semibold text-slate-900">${{ number_format((float) $caregiverData['profile']->resolvePlatformHourlyRate(), 2) }}/hr</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Hired</p>
                        <p class="text-xl font-semibold text-slate-900">{{ $caregiverData['stats']['hired'] }}</p>
                    </div>
                </section>
            @endif

            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="hc-kpi"><p class="hc-kpi-label">Applications</p><p class="hc-kpi-value">{{ $caregiverData['stats']['applications_total'] }}</p></div>
                <div class="hc-kpi"><p class="hc-kpi-label">Shortlisted</p><p class="hc-kpi-value">{{ $caregiverData['stats']['shortlisted'] }}</p></div>
                <div class="hc-kpi"><p class="hc-kpi-label">Hired</p><p class="hc-kpi-value">{{ $caregiverData['stats']['hired'] }}</p></div>
                <div class="hc-kpi"><p class="hc-kpi-label">Pending Invites</p><p class="hc-kpi-value">{{ $caregiverData['stats']['invitations_pending'] }}</p></div>
                <div class="hc-kpi"><p class="hc-kpi-label">Unread Messages</p><p class="hc-kpi-value">{{ $caregiverData['stats']['unread_messages'] }}</p></div>
            </section>
        @else
            <x-card>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="text-sm text-slate-600 mt-2">Your account is active. Use the navigation to continue.</p>
            </x-card>
        @endif
    </div>
</div>
