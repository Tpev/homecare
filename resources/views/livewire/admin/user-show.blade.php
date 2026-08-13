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

    <livewire:admin.ai-support.user-pilot-card :user="$user" :key="'ai-support-pilot-user-'.$user->id" />

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
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold">Family Account support context</h2>
                    @if($familyAccount)
                        <x-badge :text="strtoupper((string) $familyAccount->status)" color="blue" />
                    @endif
                </div>
            </x-slot:header>

            @if(! $familyAccount)
                <p class="text-sm text-slate-500">No Family Account membership history exists for this user.</p>
            @else
                <div class="grid gap-5 lg:grid-cols-2">
                    <section aria-labelledby="family-members-heading">
                        <h3 id="family-members-heading" class="font-semibold text-slate-900">Members</h3>
                        <div class="mt-2 space-y-2">
                            @foreach($familyAccount->memberships as $member)
                                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-semibold text-slate-900">{{ $member->user?->name ?: 'Deleted user' }}</span>
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $member->access_level }} &middot; {{ $member->status }}</span>
                                    </div>
                                    <p class="mt-1 break-all text-slate-600">{{ $member->user?->email }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Joined {{ optional($member->joined_at)->format('M d, Y H:i') ?: '-' }}@if($member->ended_at) &middot; Ended {{ $member->ended_at->format('M d, Y H:i') }}@endif</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section aria-labelledby="family-invitations-heading">
                        <h3 id="family-invitations-heading" class="font-semibold text-slate-900">Invitations</h3>
                        <div class="mt-2 space-y-2">
                            @forelse($familyAccount->invitations as $invitation)
                                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                                    <p class="break-all font-semibold text-slate-900">{{ $invitation->email_normalized }}</p>
                                    <p class="mt-1 text-slate-600">
                                        @if($invitation->accepted_at) Accepted {{ $invitation->accepted_at->format('M d, Y H:i') }}
                                        @elseif($invitation->canceled_at) Canceled {{ $invitation->canceled_at->format('M d, Y H:i') }}
                                        @elseif($invitation->expires_at->isPast()) Expired {{ $invitation->expires_at->format('M d, Y H:i') }}
                                        @else Pending &middot; expires {{ $invitation->expires_at->format('M d, Y H:i') }}
                                        @endif
                                    </p>
                                </div>
                            @empty
                                <p class="rounded-lg border border-slate-200 p-3 text-sm text-slate-500">No invitations.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                @if($familyAccount->status === \App\Models\FamilyAccount::STATUS_ACTIVE && $familyAccount->activeMemberships->where('access_level', \App\Models\FamilyAccountMember::ACCESS_MEMBER)->isNotEmpty())
                    <section class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4" aria-labelledby="transfer-owner-heading">
                        <h3 id="transfer-owner-heading" class="font-semibold text-amber-950">Protected ownership transfer</h3>
                        <p class="mt-1 text-sm text-amber-900">Use only after verifying both people. This changes billing ownership and is permanently audited.</p>
                        <form wire:submit="transferFamilyOwnership" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] lg:items-end">
                            <label class="block text-sm font-medium text-slate-800">
                                New account owner
                                <select wire:model="transferOwnershipMemberId" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 bg-white text-base">
                                    <option value="">Choose an active member</option>
                                    @foreach($familyAccount->activeMemberships->where('access_level', \App\Models\FamilyAccountMember::ACCESS_MEMBER) as $member)
                                        <option value="{{ $member->id }}">{{ $member->user?->name }} ({{ $member->user?->email }})</option>
                                    @endforeach
                                </select>
                                @error('transferOwnershipMemberId') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                            </label>
                            <label class="block text-sm font-medium text-slate-800">
                                Verified reason
                                <input wire:model="transferReason" type="text" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-base" placeholder="Both parties verified by support call" />
                                @error('transferReason') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                                @error('transfer') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                            </label>
                            <x-button type="submit" color="red" class="min-h-11 justify-center">Transfer ownership</x-button>
                        </form>
                    </section>
                @endif

                <section class="mt-6" aria-labelledby="family-audit-heading">
                    <h3 id="family-audit-heading" class="font-semibold text-slate-900">Membership audit history</h3>
                    <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600"><tr><th class="px-3 py-2">When</th><th class="px-3 py-2">Action</th><th class="px-3 py-2">Actor</th><th class="px-3 py-2">Subject</th></tr></thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($familyAccount->activityLogs as $activity)
                                    <tr><td class="whitespace-nowrap px-3 py-2">{{ $activity->created_at->format('M d, Y H:i') }}</td><td class="px-3 py-2 font-medium">{{ str_replace('_', ' ', $activity->action) }}</td><td class="px-3 py-2">{{ $activity->actor?->email ?: 'System' }}</td><td class="px-3 py-2">{{ $activity->subjectUser?->email ?: '-' }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="px-3 py-3 text-slate-500">No audit entries.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </x-card>

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
