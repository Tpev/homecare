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

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold">Request #{{ $careRequest->id }} Operations</h1>
            <p class="mt-1 text-sm text-slate-600">Admin view for full request lifecycle, applicants, booking, and payment state.</p>
        </div>
        <div class="grid grid-cols-1 gap-2 sm:flex sm:items-center">
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
                        {{ $careRequest->city }}, {{ $careRequest->state }} {{ $careRequest->zip }}
                        · {{ $careRequest->request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-badge :text="'REQUEST '.strtoupper((string) $careRequest->status)" color="blue" />
                    @if($careRequest->booking)
                        <x-badge :text="'SHIFT '.strtoupper((string) $careRequest->booking->status)" color="green" />
                    @endif
                </div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Family</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $careRequest->family?->name ?: '—' }}</p>
                <p class="text-slate-500">{{ $careRequest->family?->email ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Recipient</p>
                <p class="mt-1 font-semibold text-slate-900">{{ $careRequest->recipient?->full_name ?: '—' }}</p>
                <p class="text-slate-500">{{ $careRequest->recipient?->relationship_to_family ?: '—' }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Created</p>
                <p class="mt-1 font-semibold text-slate-900">{{ optional($careRequest->created_at)->format('M d, Y H:i') }}</p>
                <p class="text-slate-500">Updated {{ optional($careRequest->updated_at)->diffForHumans() }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3 text-sm">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Pipeline totals</p>
                <p class="mt-1 text-slate-700">
                    {{ $careRequest->applications_count }} app ·
                    {{ $careRequest->invitations_count }} inv ·
                    {{ $careRequest->conversations_count }} chat
                </p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Admin request status override</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="forceRequestStatus('open')" class="rounded-lg border border-sky-200 px-3 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-50">Open</button>
                    <button type="button" wire:click="forceRequestStatus('filled')" class="rounded-lg border border-indigo-200 px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Filled</button>
                    <button type="button" wire:click="forceRequestStatus('cancelled')" class="rounded-lg border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancelled</button>
                    <button type="button" wire:click="forceRequestStatus('expired')" class="rounded-lg border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50">Expired</button>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Admin shift status override</p>
                @if($careRequest->booking)
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button type="button" wire:click="forceBookingStatus('scheduled')" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Scheduled</button>
                        <button type="button" wire:click="forceBookingStatus('in_progress')" class="rounded-lg border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">In progress</button>
                        <button type="button" wire:click="forceBookingStatus('completed')" class="rounded-lg border border-blue-200 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50">Completed</button>
                        <button type="button" wire:click="forceBookingStatus('cancelled')" class="rounded-lg border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancelled</button>
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-500">No booking exists for this request yet.</p>
                @endif
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3">
            <p class="text-xs uppercase tracking-[0.14em] text-rose-700">Danger zone</p>
            <p class="mt-1 text-sm text-rose-800">Delete this request and all linked records allowed by database constraints.</p>
            <div class="mt-2">
                <x-button color="red" light class="w-full justify-center sm:w-auto" sm wire:click="deleteRequest" onclick="if (!confirm('Delete request #{{ $careRequest->id }}? This cannot be undone.')) return false;">Delete request</x-button>
            </div>
        </div>
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
                    <p class="mt-1 text-slate-700">Proposed rate: ${{ number_format((float) ($application->proposed_rate ?? 0), 2) }}/hr</p>
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
                    <p class="mt-1 text-slate-700">Sent {{ optional($invitation->created_at)->format('M d, Y H:i') }}</p>
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
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Shift window</p>
                    <p class="mt-1 text-slate-900">
                        {{ optional($careRequest->booking->scheduled_start_at)->format('M d, Y H:i') ?: '—' }}
                        -
                        {{ optional($careRequest->booking->scheduled_end_at)->format('M d, Y H:i') ?: '—' }}
                    </p>
                    <p class="mt-1 text-slate-700">Caregiver: {{ $careRequest->booking->caregiver?->name ?: '—' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Payment status</p>
                    <p class="mt-1 text-slate-900">{{ strtoupper((string) ($careRequest->booking->payment?->status ?? 'none')) }}</p>
                    <p class="mt-1 text-slate-700">
                        Authorized: ${{ number_format((float) ($careRequest->booking->payment?->authorized_amount ?? 0), 2) }}
                        · Captured: ${{ number_format((float) ($careRequest->booking->payment?->captured_amount ?? 0), 2) }}
                    </p>
                </div>
            </div>
        </x-card>
    @endif
</div>
