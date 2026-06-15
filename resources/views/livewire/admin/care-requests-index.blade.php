<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @error('delete')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    @error('booking')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Request Operations</h1>
                    <p class="mt-1 text-sm text-slate-600">Full admin visibility and controls across all job requests.</p>
                </div>
                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-500">Showing {{ $requests->total() }} request(s)</div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">All</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($summary['all']) }}</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-sky-700">Open</p>
                <p class="mt-1 text-2xl font-black text-sky-900">{{ number_format($summary['open']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-indigo-700">Filled</p>
                <p class="mt-1 text-2xl font-black text-indigo-900">{{ number_format($summary['filled']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-rose-700">Cancelled</p>
                <p class="mt-1 text-2xl font-black text-rose-900">{{ number_format($summary['cancelled']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-emerald-700">With booking</p>
                <p class="mt-1 text-2xl font-black text-emerald-900">{{ number_format($summary['with_booking']) }}</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-input
                    label="Search"
                    placeholder="Title, family, email, city, ZIP"
                    wire:model.blur="q"
                />
            </div>
            <x-select.styled label="Status" wire:model.live="status" :options="$statusOptions" />
            <x-select.styled label="Type" wire:model.live="requestType" :options="$typeOptions" />
            <div class="grid grid-cols-2 gap-3">
                <x-select.styled label="Sort" wire:model.live="sort" :options="$sortOptions" />
                <x-select.styled
                    label="Rows"
                    wire:model.live="perPage"
                    :options="[
                        ['label' => '25', 'value' => 25],
                        ['label' => '50', 'value' => 50],
                        ['label' => '100', 'value' => 100],
                    ]"
                />
            </div>
        </div>

        <div class="mt-5 space-y-3 md:hidden">
            @forelse($requests as $request)
                @php
                    $requestTone = match ($request->status) {
                        \App\Models\CareRequest::STATUS_OPEN => 'blue',
                        \App\Models\CareRequest::STATUS_FILLED => 'indigo',
                        \App\Models\CareRequest::STATUS_CANCELLED => 'red',
                        \App\Models\CareRequest::STATUS_EXPIRED => 'amber',
                        default => 'slate',
                    };
                    $bookingTone = match ($request->booking?->status) {
                        \App\Models\CareBooking::STATUS_IN_PROGRESS,
                        \App\Models\CareBooking::STATUS_PAUSED => 'green',
                        \App\Models\CareBooking::STATUS_COMPLETED,
                        \App\Models\CareBooking::STATUS_REVIEWED => 'blue',
                        \App\Models\CareBooking::STATUS_CANCELLED => 'red',
                        \App\Models\CareBooking::STATUS_DISPUTED => 'amber',
                        default => 'slate',
                    };
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-base font-semibold text-slate-900">{{ $request->title ?: 'Untitled request' }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $request->family?->name ?: 'Unknown' }} · {{ $request->city }}, {{ $request->state }}</p>
                            <p class="text-xs text-slate-500">{{ $request->request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }} · #{{ $request->id }}</p>
                        </div>
                        <x-badge :text="strtoupper((string) $request->status)" :color="$requestTone" />
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($request->booking)
                            <x-badge :text="'BOOKING '.strtoupper((string) $request->booking->status)" :color="$bookingTone" />
                        @endif
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                            {{ $request->applications_count }} app · {{ $request->invitations_count }} inv · {{ $request->conversations_count }} chat
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'open')" class="rounded-xl border border-sky-200 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50">Open</button>
                        <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'filled')" class="rounded-xl border border-indigo-200 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Filled</button>
                        <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'cancelled')" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancel</button>
                        <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'expired')" class="rounded-xl border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50">Expire</button>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-2">
                        <a href="{{ route('admin.requests.show', $request) }}" wire:navigate class="block">
                            <x-button color="cyan" light class="w-full justify-center">Open request</x-button>
                        </a>
                        <x-button color="red" light class="w-full justify-center" wire:click="deleteRequest({{ $request->id }})" onclick="if (!confirm('Delete request #{{ $request->id }}? This cannot be undone.')) return false;">Delete</x-button>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    No requests found for the current filters.
                </div>
            @endforelse
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-slate-200 md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Request</th>
                        <th class="px-4 py-3">Family</th>
                        <th class="px-4 py-3">Pipeline</th>
                        <th class="px-4 py-3">Status control</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($requests as $request)
                        @php
                            $requestTone = match ($request->status) {
                                \App\Models\CareRequest::STATUS_OPEN => 'blue',
                                \App\Models\CareRequest::STATUS_FILLED => 'indigo',
                                \App\Models\CareRequest::STATUS_CANCELLED => 'red',
                                \App\Models\CareRequest::STATUS_EXPIRED => 'amber',
                                default => 'slate',
                            };
                            $bookingTone = match ($request->booking?->status) {
                                \App\Models\CareBooking::STATUS_IN_PROGRESS,
                                \App\Models\CareBooking::STATUS_PAUSED => 'green',
                                \App\Models\CareBooking::STATUS_COMPLETED,
                                \App\Models\CareBooking::STATUS_REVIEWED => 'blue',
                                \App\Models\CareBooking::STATUS_CANCELLED => 'red',
                                \App\Models\CareBooking::STATUS_DISPUTED => 'amber',
                                default => 'slate',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <p class="font-semibold text-slate-900">{{ $request->title ?: 'Untitled request' }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    #{{ $request->id }} · {{ $request->city }}, {{ $request->state }} {{ $request->zip }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $request->request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}
                                    · {{ optional($request->created_at)->format('M d, Y H:i') }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="font-semibold text-slate-900">{{ $request->family?->name ?: 'Unknown' }}</p>
                                <p class="text-xs text-slate-500">{{ $request->family?->email ?: '—' }}</p>
                                <p class="mt-1 text-xs text-slate-600">Recipient: {{ $request->recipient?->full_name ?: '—' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge :text="strtoupper((string) $request->status)" :color="$requestTone" />
                                    @if($request->booking)
                                        <x-badge :text="'BOOKING '.strtoupper((string) $request->booking->status)" :color="$bookingTone" />
                                    @endif
                                </div>
                                <p class="mt-2 text-xs text-slate-600">
                                    {{ $request->applications_count }} app ·
                                    {{ $request->invitations_count }} inv ·
                                    {{ $request->conversations_count }} chat ·
                                    {{ $request->tasks_count }} task
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap gap-1">
                                    <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'open')" class="rounded-lg border border-sky-200 px-2 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-50">Open</button>
                                    <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'filled')" class="rounded-lg border border-indigo-200 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Filled</button>
                                    <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'cancelled')" class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancel</button>
                                    <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'expired')" class="rounded-lg border border-amber-200 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50">Expire</button>
                                </div>
                                @if($request->booking)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <button type="button" wire:click="forceBookingStatus({{ $request->id }}, 'scheduled')" class="rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50">Shift: scheduled</button>
                                        <button type="button" wire:click="forceBookingStatus({{ $request->id }}, 'completed')" class="rounded-lg border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Shift: completed</button>
                                        <button type="button" wire:click="forceBookingStatus({{ $request->id }}, 'cancelled')" class="rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Shift: cancelled</button>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.requests.show', $request) }}" wire:navigate>
                                        <x-button color="cyan" light sm>Open</x-button>
                                    </a>
                                    <x-button
                                        color="red"
                                        light
                                        sm
                                        wire:click="deleteRequest({{ $request->id }})"
                                        onclick="if (!confirm('Delete request #{{ $request->id }}? This cannot be undone.')) return false;"
                                    >
                                        Delete
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">No requests found for the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <div class="pt-2">
                {{ $requests->links() }}
            </div>
        </x-slot:footer>
    </x-card>
</div>
