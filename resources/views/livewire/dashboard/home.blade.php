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
            <section class="hc-hero">
                <p class="text-xs uppercase tracking-[0.2em] text-emerald-100">Caregiver Dashboard</p>
                <h1 class="text-2xl md:text-3xl font-display font-semibold mt-2">Welcome back, {{ auth()->user()->name }}</h1>
                <p class="mt-2 text-emerald-100">Track your applications, messages, and profile readiness.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('care-requests.index') }}" wire:navigate><x-button color="white">Browse Requests</x-button></a>
                    <a href="{{ route('messages.index') }}" wire:navigate><x-button color="white" light>Messages</x-button></a>
                    <a href="{{ route('caregiver.profile.edit') }}" wire:navigate><x-button color="white" light>Edit Profile</x-button></a>
                </div>
            </section>

            @php
                $identityStatus = $caregiverData['profile']?->identity_verification_status ?? 'not_started';
                $identityApproved = ($caregiverData['profile']?->identity_verified_at !== null) || $identityStatus === 'approved';
            @endphp

            @unless ($identityApproved)
                <section class="rounded-2xl border border-amber-300 bg-amber-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-amber-900">Identity verification required</p>
                            <p class="text-sm text-amber-800">Current status: {{ strtoupper(str_replace('_', ' ', $identityStatus)) }}. Complete this to be eligible for activation.</p>
                        </div>
                        <a href="{{ route('caregiver.verification.show') }}" wire:navigate>
                            <x-button color="amber" sm>Verify identity</x-button>
                        </a>
                    </div>
                </section>
            @endunless

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
                        <p class="text-xs text-slate-500">Cancellations</p>
                        <p class="text-xl font-semibold text-slate-900">{{ (int) $caregiverData['profile']->cancellation_count }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Disputes</p>
                        <p class="text-xl font-semibold text-slate-900">{{ (int) $caregiverData['profile']->dispute_count }}</p>
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

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <x-card>
                    <x-slot:header>
                        <div class="flex items-center justify-between">
                            <h2 class="font-display font-semibold">Recent Applications</h2>
                            <a href="{{ route('care-requests.index') }}" wire:navigate class="hc-link">Browse open requests</a>
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        @forelse ($caregiverData['recent_applications'] as $application)
                            <div class="rounded-lg border border-slate-200 p-3 flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $application->careRequest?->title }}</p>
                                    <p class="text-sm text-slate-600">{{ $application->careRequest?->city }}, {{ $application->careRequest?->state }}</p>
                                </div>
                                <x-badge :text="strtoupper($application->status)" color="blue" />
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">No applications yet.</p>
                        @endforelse
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <div class="flex items-center justify-between">
                            <h2 class="font-display font-semibold">Latest Invitations</h2>
                            <a href="{{ route('caregiver.invitations.index') }}" wire:navigate class="hc-link">Open invitations</a>
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        @forelse ($caregiverData['recent_invitations'] as $invitation)
                            <div class="rounded-lg border border-slate-200 p-3 flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $invitation->careRequest?->title }}</p>
                                    <p class="text-sm text-slate-600">From {{ $invitation->family?->name }}</p>
                                </div>
                                <x-badge :text="strtoupper($invitation->status)" color="blue" />
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">No invitations yet.</p>
                        @endforelse
                    </div>
                </x-card>
            </div>
        @else
            <x-card>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="text-sm text-slate-600 mt-2">Your account is active. Use the navigation to continue.</p>
            </x-card>
        @endif
    </div>
</div>
