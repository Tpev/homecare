<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @error('booking')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    @error('delete')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h1 class="text-xl font-semibold">Request #{{ $careRequest->id }} Operations</h1>
            <p class="mt-1 text-sm text-slate-600">Admin view for full request lifecycle, applicants, booking, and payment state.</p>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center">
            @if(!$recruitmentUnavailableReason)
                <button type="button" wire:click="openRecruitmentPanel" onclick="document.getElementById('recruitment-heading')?.scrollIntoView({ behavior: 'smooth', block: 'start' })" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Invite caregivers</button>
            @endif
            <a href="{{ route('admin.requests.index') }}" wire:navigate>
                <x-button color="slate" light class="w-full justify-center sm:w-auto" sm>Back to requests</x-button>
            </a>
            <a href="{{ route('admin.users.show', $careRequest->family_user_id) }}" wire:navigate>
                <x-button color="cyan" light class="w-full justify-center sm:w-auto" sm>Open family profile</x-button>
            </a>
        </div>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-lg font-semibold text-slate-900">{{ $careRequest->title ?: 'Untitled request' }}</p>
                    <p class="text-sm text-slate-500">
                        {{ collect([$careRequest->city, $careRequest->state, $careRequest->zip])->filter()->join(', ') ?: 'Location not provided' }}
                        · {{ $careRequest->request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge :text="'REQUEST '.strtoupper((string) $careRequest->status)" color="blue" />
                    @if ($careRequest->is_private)
                        <x-badge text="PRIVATE" color="indigo" />
                    @endif
                    @if($careRequest->booking)
                        <x-badge :text="'SHIFT '.strtoupper((string) $careRequest->booking->status)" color="green" />
                    @endif
                </div>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            @php
                $scheduleSummaries = $careRequest->isRecurring() || !$schedule['confirmed'] ? array_filter([$schedule['requested'], $schedule['confirmed']]) : [$schedule['confirmed'], $schedule['requested']];
            @endphp
            @foreach($scheduleSummaries as $summary)
                @include('livewire.admin.partials.care-request-schedule-detail', ['summary' => $summary, 'primary' => $loop->first])
            @endforeach
            @if($careRequest->isRecurring() && $schedule['next'])
                <p class="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-950"><span class="font-semibold">{{ ($schedule['confirmed']['label'] ?? '') === 'Next confirmed visit' ? 'Next confirmed visit' : 'Next requested visit' }}:</span> {{ $schedule['next'] }}</p>
            @endif
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Family</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $careRequest->family?->name ?: '—' }}</p>
                <p class="break-all text-slate-500">{{ $careRequest->family?->email ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Recipient</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $careRequest->recipient?->full_name ?: '—' }}</p>
                <p class="text-slate-500">{{ $careRequest->recipient?->relationship_to_family ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Care location</p>
                <p class="mt-1 font-semibold text-slate-900">{{ collect([$careRequest->city, $careRequest->state])->filter()->join(', ') ?: 'Location not provided' }}</p>
                <p class="text-slate-500">{{ $careRequest->zip }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Recruitment</p>
                <p class="mt-1 text-slate-700">
                    {{ $careRequest->applications_count }} applicants<br>
                    {{ $careRequest->invitations_count }} family invitations ·
                    {{ $careRequest->conversations_count }} conversations
                </p>
            </div>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            Posted {{ $careRequest->created_at?->copy()->timezone(config('app.timezone'))->format('M j, Y · g:i A T') ?: 'date unavailable' }}
            · Updated {{ $careRequest->updated_at?->diffForHumans() ?: 'date unavailable' }}
        </p>

        @if($careProfileSnapshot)
            <div class="mt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Attached care profile snapshot</p>
                <x-care-recipient-profile-summary :snapshot="$careProfileSnapshot" />
            </div>
        @endif

    </x-card>

    <x-card>
        <x-slot:header>
            <h2 class="text-lg font-semibold">Request details</h2>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Scope of work</p>
                <p class="mt-1 text-slate-900">{{ $careRequest->scope_of_work ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Additional info</p>
                <p class="mt-1 text-slate-900">{{ $careRequest->additional_info ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Timing expectations</p>
                <p class="mt-1 text-slate-900">{{ $careRequest->time_expectations ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Home access notes</p>
                <p class="mt-1 text-slate-900">{{ $careRequest->home_access_notes ?: '—' }}</p>
            </div>
        </div>
    </x-card>

    @include('livewire.admin.partials.care-request-recruitment')

    <x-card>
        <x-slot:header>
            <h2 class="text-lg font-semibold">Applicants ({{ $careRequest->applications_count }})</h2>
        </x-slot:header>

        <div class="space-y-2">
            @forelse($careRequest->applications as $application)
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-slate-900">{{ $application->caregiver?->name ?: 'Unknown caregiver' }}</p>
                        <x-badge :text="strtoupper((string) $application->status)" color="blue" />
                    </div>
                    <p class="mt-1 text-slate-600">{{ $application->caregiver?->email ?: '—' }}</p>
                    @php
                        $appPricing = app(\App\Support\MarketplacePricing::class);
                        $appBooking = $careRequest->booking;
                        $caregiverRate = $appBooking && (int) $appBooking->caregiver_user_id === (int) $application->caregiver_user_id
                            ? $appPricing->caregiverRateForBooking($appBooking)
                            : $appPricing->caregiverQuoteForRequest($careRequest, (int) $application->caregiver_user_id)['caregiver_gross_rate_cents'] / 100;
                    @endphp
                    <p class="mt-1 text-slate-700">Caregiver earnings: ${{ number_format($caregiverRate, 2) }}/hr</p>
                    @if($application->cover_note)
                        <p class="mt-1 text-slate-700">{{ $application->cover_note }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">No applicants yet.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <h2 class="text-lg font-semibold">Invitations ({{ $careRequest->invitations_count }})</h2>
        </x-slot:header>

        <div class="space-y-2">
            @forelse($careRequest->invitations as $invitation)
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-slate-900">{{ $invitation->caregiver?->name ?: 'Unknown caregiver' }}</p>
                        <x-badge :text="strtoupper((string) $invitation->status)" color="cyan" />
                    </div>
                    <p class="mt-1 text-slate-600">{{ $invitation->caregiver?->email ?: '—' }}</p>
                    <p class="mt-1 text-slate-700">Sent {{ $invitation->created_at?->copy()->timezone(config('app.timezone'))->format('M j, Y · g:i A T') }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No invitations sent.</p>
            @endforelse
        </div>
    </x-card>

    @if($careRequest->booking)
        <x-card>
            <x-slot:header>
                <h2 class="text-lg font-semibold">Booking & payment</h2>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Booked caregiver</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $careRequest->booking->caregiver?->name ?: 'Not assigned' }}</p>
                    <p class="mt-1 text-slate-700">Booking #{{ $careRequest->booking->id }} · {{ ucfirst(str_replace('_', ' ', (string) $careRequest->booking->status)) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Payment status</p>
                    <p class="mt-1 text-slate-900">{{ strtoupper((string) ($careRequest->booking->payment?->status ?? 'none')) }}</p>
                    <p class="mt-1 text-slate-700">
                        Authorized: ${{ number_format(($careRequest->booking->payment?->amount_authorized_cents ?? 0) / 100, 2) }}
                        · Captured: ${{ number_format(($careRequest->booking->payment?->amount_captured_cents ?? 0) / 100, 2) }}
                    </p>
                    @if($careRequest->booking->pricing_agreement_id)
                        <p class="mt-1 text-slate-700">Family total: ${{ number_format(($careRequest->booking->family_care_rate_cents + $careRequest->booking->family_processing_fee_rate_cents) / 100, 2) }}/hr · Caregiver: ${{ number_format($careRequest->booking->caregiver_gross_rate_cents / 100, 2) }}/hr</p>
                        @if($careRequest->booking->caregiver_fee_policy === \App\Models\CarePricingAgreement::PLATFORM_PAYS_PROCESSING)
                            <p class="mt-1 text-slate-700">LoLo covers payment processing costs.</p>
                        @endif
                    @endif
                </div>
            </div>
        </x-card>
    @endif

    <details class="rounded-xl border border-slate-200 bg-white" wire:key="admin-controls-{{ $careRequest->id }}" @if($errors->has('checkInOverrideReason')) open @endif>
        <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-slate-700">Admin controls <span class="ml-2 font-normal text-slate-500">Status overrides and deletion</span></summary>
        <div class="space-y-4 border-t border-slate-200 p-4 sm:p-5">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-3">
                    <p class="text-sm font-semibold text-slate-700">Admin request status override</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button type="button" wire:click="forceRequestStatus('open')" class="rounded-lg border border-sky-200 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50">Open</button>
                        <button type="button" wire:click="forceRequestStatus('filled')" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Filled</button>
                        <button type="button" wire:click="forceRequestStatus('cancelled')" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancelled</button>
                        <button type="button" wire:click="forceRequestStatus('expired')" class="rounded-lg border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50">Expired</button>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 p-3">
                    <p class="text-sm font-semibold text-slate-700">Admin shift status override</p>
                    @if($careRequest->booking)
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" wire:click="forceBookingStatus('scheduled')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Scheduled</button>
                            <button type="button" wire:click="forceBookingStatus('in_progress')" class="rounded-lg border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">In progress</button>
                            <button type="button" wire:click="forceBookingStatus('completed')" class="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Completed</button>
                            <button type="button" wire:click="forceBookingStatus('cancelled')" class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancelled</button>
                        </div>
                    @else
                        <p class="mt-2 text-sm text-slate-500">No booking exists for this request yet.</p>
                    @endif
                </div>
            </div>
            @if($careRequest->booking?->care_plan_id)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="font-semibold text-amber-950">Recurring care check-in override</p>
                    @if($careRequest->booking->hasCheckInOverride())
                        <p class="mt-1 text-sm text-amber-900">Allowed {{ $careRequest->booking->check_in_override_at?->copy()->timezone(config('app.timezone'))->format('M d, Y g:i A T') }}. Reason: {{ $careRequest->booking->check_in_override_reason }}</p>
                    @else
                        <p class="mt-1 text-sm text-amber-900">Use only when operations has confirmed the caregiver may start despite timing or payment guards.</p>
                        <textarea wire:model="checkInOverrideReason" rows="2" class="mt-3 w-full rounded-md border-amber-300" placeholder="Required operational reason"></textarea>
                        <x-input-error :messages="$errors->get('checkInOverrideReason')" class="mt-2" />
                        <x-button color="amber" class="mt-3" wire:click="allowRegularCareCheckIn" wire:confirm="Allow check-in for this visit?">Allow check-in</x-button>
                    @endif
                </div>
            @endif
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-sm font-semibold text-rose-800">Delete request</p>
                <p class="mt-1 text-sm text-rose-800">Delete this request and all linked records allowed by database constraints.</p>
                <div class="mt-3">
                    <x-button color="red" light class="w-full justify-center sm:w-auto" sm wire:click="deleteRequest" wire:confirm="Delete request #{{ $careRequest->id }}? This cannot be undone.">Delete request</x-button>
                </div>
            </div>
        </div>
    </details>
</div>
