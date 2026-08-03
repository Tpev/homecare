<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @error('identity')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold">User Profile Review</h1>
            <p class="mt-1 text-sm text-slate-600">Full account context, activity, and moderation details in one place.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" wire:navigate>
            <x-button color="slate" light class="w-full justify-center sm:w-auto" sm>Back to users</x-button>
        </a>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-lg font-semibold text-slate-900">{{ $user->name }}</p>
                    <p class="text-sm text-slate-500">{{ $user->email }}</p>
                </div>
                <x-badge :text="strtoupper((string) ($user->role ?: 'user'))" color="blue" />
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Registered</p>
                <p class="mt-1 font-semibold text-slate-900">{{ optional($user->created_at)->format('M d, Y H:i') }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Location</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $user->city ?: '-' }}{{ $user->state ? ', '.$user->state : '' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Phone</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $user->phone ?: '-' }}</p>
            </div>
        </div>
    </x-card>

    @if($caregiverProfile)
        <x-card>
            <x-slot:header>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold">Caregiver Profile</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :text="strtoupper((string) $caregiverProfile->status)" color="yellow" />
                        @if($caregiverProfile->slug)
                            <a href="{{ route('caregivers.show', $caregiverProfile->slug) }}" target="_blank" rel="noopener noreferrer">
                                <x-button color="cyan" light sm>Public profile</x-button>
                            </a>
                        @endif
                    </div>
                </div>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Bio</p>
                    <p class="mt-1 text-slate-900">{{ $caregiverProfile->bio ?: '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Experience</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $caregiverProfile->years_experience ?? '-' }} years</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Service Area</p>
                    <p class="mt-1 font-semibold text-slate-900">
                        ZIP {{ $caregiverProfile->service_area_zip ?: '-' }} · Radius {{ $caregiverProfile->service_radius_miles ?? '-' }} mi
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Trust & Review</p>
                            <p class="mt-1 text-slate-900">
                                Identity:
                                <span class="font-semibold">{{ strtoupper((string) ($caregiverProfile->identity_verification_status ?: 'not_started')) }}</span>
                            </p>
                            <p class="text-slate-900">
                                Checked:
                                <span class="font-semibold">{{ optional($caregiverProfile->identity_verification_checked_at)->format('M d, Y H:i') ?: '-' }}</span>
                            </p>
                            <p class="text-slate-900">
                                Submitted:
                                <span class="font-semibold">{{ optional($caregiverProfile->review_submitted_at)->format('M d, Y H:i') ?: '-' }}</span>
                            </p>
                            @if($caregiverProfile->latestIdentityVerification)
                                <p class="text-slate-900">
                                    Latest source:
                                    <span class="font-semibold">
                                        {{ data_get($caregiverProfile->latestIdentityVerification->decision_payload, 'source') === 'admin_override' ? 'Admin override' : 'Didit session' }}
                                    </span>
                                </p>
                            @endif
                        </div>

                        @if($caregiverProfile->identity_verification_status !== \App\Models\CaregiverIdentityVerification::STATUS_APPROVED || ! $caregiverProfile->identity_verified_at)
                            <div class="w-full sm:w-auto">
                                <x-button
                                    color="green"
                                    class="w-full justify-center sm:w-auto"
                                    wire:click="approveIdentityVerification"
                                    loading="approveIdentityVerification"
                                >
                                    Approve KYC manually
                                </x-button>
                                <p class="mt-2 text-xs text-slate-500 sm:max-w-[16rem]">
                                    Use only after confirming the caregiver's identity documents outside the automated flow.
                                </p>
                            </div>
                        @else
                            <div class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">
                                KYC approved
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Skills</p>
                    <p class="mt-2 text-slate-900">
                        {{ $caregiverProfile->skills->pluck('name')->join(', ') ?: '-' }}
                    </p>
                </div>
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Languages</p>
                    <p class="mt-2 text-slate-900">
                        {{ $caregiverProfile->languages->pluck('name')->join(', ') ?: '-' }}
                    </p>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Availability Ranges</p>
                @if($caregiverProfile->availabilities->isEmpty())
                    <p class="mt-2 text-slate-500">No availability submitted.</p>
                @else
                    <ul class="mt-2 grid grid-cols-1 gap-2 text-slate-900 sm:grid-cols-2">
                        @foreach($caregiverProfile->availabilities as $availability)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                Day {{ $availability->day_of_week }} · {{ substr((string) $availability->start_time, 0, 5) }} - {{ substr((string) $availability->end_time, 0, 5) }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @include('livewire.admin.partials.caregiver-background-review', ['profile' => $caregiverProfile])
        </x-card>
    @endif

    @if($user->role === 'family')
        <x-card>
            <x-slot:header>
                <h2 class="text-lg font-semibold">Recent Family Requests</h2>
            </x-slot:header>

            @if($latestFamilyRequests->isEmpty())
                <p class="text-sm text-slate-500">No care requests posted yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($latestFamilyRequests as $request)
                        <div class="rounded-lg border border-slate-200 p-3 text-sm">
                            <p class="font-semibold text-slate-900">{{ $request->title }}</p>
                            <p class="text-slate-600">{{ strtoupper((string) $request->status) }} · {{ optional($request->created_at)->format('M d, Y H:i') }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    @endif

    @if($user->role === 'caregiver')
        <x-card>
            <x-slot:header>
                <h2 class="text-lg font-semibold">Recent Caregiver Activity</h2>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Applications</p>
                    @if($latestCaregiverApplications->isEmpty())
                        <p class="mt-2 text-slate-500">No applications yet.</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach($latestCaregiverApplications as $application)
                                <li class="text-slate-900">
                                    {{ $application->careRequest?->title ?: 'Request #'.$application->care_request_id }}
                                    · <span class="font-semibold">{{ strtoupper((string) $application->status) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Bookings</p>
                    @if($latestCaregiverBookings->isEmpty())
                        <p class="mt-2 text-slate-500">No bookings yet.</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach($latestCaregiverBookings as $booking)
                                <li class="text-slate-900">
                                    {{ $booking->careRequest?->title ?: 'Request #'.$booking->care_request_id }}
                                    · <span class="font-semibold">{{ strtoupper((string) $booking->status) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </x-card>
    @endif
</div>
