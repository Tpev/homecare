<div>
    <div class="hc-page py-8 space-y-6">
        @if ($mode === 'family')
            <section class="hc-hero">
                <p class="text-xs uppercase tracking-[0.2em] text-blue-100">Family Dashboard</p>
                <div class="mt-2 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-display font-semibold">Welcome back, {{ auth()->user()->name }}</h1>
                        <p class="mt-2 text-blue-100">Manage care requests, review applicants, and chat with caregivers in one place.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('family.requests.create') }}" wire:navigate><x-button color="white">Post New Request</x-button></a>
                        <a href="{{ route('messages.index') }}" wire:navigate><x-button color="white" light>Messages</x-button></a>
                        <a href="{{ route('caregivers.search') }}" wire:navigate><x-button color="white" light>Find Caregivers</x-button></a>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="hc-kpi"><p class="hc-kpi-label">Open Requests</p><p class="hc-kpi-value">{{ $familyData['stats']['open_requests'] }}</p></div>
                <div class="hc-kpi"><p class="hc-kpi-label">Filled Requests</p><p class="hc-kpi-value">{{ $familyData['stats']['filled_requests'] }}</p></div>
                <div class="hc-kpi"><p class="hc-kpi-label">Total Applicants</p><p class="hc-kpi-value">{{ $familyData['stats']['total_applicants'] }}</p></div>
                <div class="hc-kpi"><p class="hc-kpi-label">Unread Messages</p><p class="hc-kpi-value">{{ $familyData['stats']['unread_messages'] }}</p></div>
            </section>

            <section class="grid grid-cols-1 xl:grid-cols-5 gap-6">
                <x-card class="xl:col-span-3">
                    <x-slot:header>
                        <div class="flex items-center justify-between">
                            <h2 class="font-display font-semibold">Upcoming & Active Requests</h2>
                            <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-link">View all</a>
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        @forelse ($familyData['upcoming_requests'] as $request)
                            <div class="hc-list-item">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $request->title }}</p>
                                        <p class="text-sm text-slate-600">
                                            {{ $request->city }}, {{ $request->state }}
                                            @if ($request->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                                - {{ optional($request->requested_start_at)->format('M d, Y H:i') }}
                                            @else
                                                - Recurring
                                            @endif
                                        </p>
                                        <p class="text-xs text-slate-500 mt-1">Recipient: {{ $request->recipient?->full_name ?? 'Not set' }}</p>
                                    </div>
                                    <x-badge :text="strtoupper($request->status)" color="blue" />
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <p class="text-sm text-slate-600">{{ $request->applications_count }} applicant(s)</p>
                                    <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="hc-link">Open</a>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">No requests yet. Create your first one.</p>
                        @endforelse
                    </div>
                </x-card>

                <x-card class="xl:col-span-2">
                    <x-slot:header>
                        <h2 class="font-display font-semibold">Latest Applicant Activity</h2>
                    </x-slot:header>

                    <div class="space-y-3">
                        @forelse ($familyData['recent_applicants'] as $application)
                            <div class="hc-list-item">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $application->caregiver?->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $application->careRequest?->title }}</p>
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
                                            <a href="{{ route('family.requests.show', $application->careRequest->id) }}" wire:navigate class="text-xs font-medium text-cyan-700 underline underline-offset-2">View</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">No applicant activity yet.</p>
                        @endforelse
                    </div>
                </x-card>
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
