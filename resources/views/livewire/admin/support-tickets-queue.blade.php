<div class="hc-page space-y-6 py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Support Tickets Queue</h1>
                    <p class="mt-1 text-sm text-slate-600">Triage conversations, structured requests, and operational issues.</p>
                </div>
                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                    {{ $tickets->total() }} tickets
                </div>
            </div>
        </x-slot:header>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-select.styled
                label="Status"
                wire:model.live="status"
                :options="[
                    ['label' => 'Open', 'value' => 'open'],
                    ['label' => 'In progress', 'value' => 'in_progress'],
                    ['label' => 'Resolved', 'value' => 'resolved'],
                    ['label' => 'Closed', 'value' => 'closed'],
                    ['label' => 'All', 'value' => 'all'],
                ]"
            />
            <x-select.styled
                label="Priority"
                wire:model.live="priority"
                :options="[
                    ['label' => 'All', 'value' => 'all'],
                    ['label' => 'Low', 'value' => 'low'],
                    ['label' => 'Normal', 'value' => 'normal'],
                    ['label' => 'High', 'value' => 'high'],
                    ['label' => 'Urgent', 'value' => 'urgent'],
                ]"
            />
        </div>

        <div class="space-y-3">
            @forelse ($tickets as $ticket)
                <div
                    data-testid="support-ticket-card"
                    class="rounded-lg border p-3 {{ $ticket->is_unread_for_admin ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200 bg-white' }}"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <p class="min-w-0 break-words font-medium text-slate-900">#{{ $ticket->id }} {{ $ticket->subject }}</p>
                            @if ($ticket->isChatWidgetConversation())
                                <span class="rounded-full bg-[#E6F2EE] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-[#0F5B52]">Chat</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">Support center</span>
                            @endif
                            @if ($ticket->is_unread_for_admin)
                                <span class="rounded-full bg-emerald-700 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">Unread</span>
                            @endif
                        </div>
                        <x-badge :text="strtoupper($ticket->status)" color="blue" />
                    </div>

                    <p class="mt-1 text-xs text-slate-500">
                        {{ strtoupper($ticket->category) }} &middot; {{ strtoupper($ticket->priority) }}
                        &middot; {{ $ticket->opener?->name ?: 'Former user' }}
                        &middot; {{ str((string) ($ticket->opener?->role ?: 'user'))->headline() }}
                        &middot; {{ ($ticket->last_public_message_at ?: $ticket->created_at)?->diffForHumans() }}
                    </p>

                    <p class="mt-2 break-words text-sm text-slate-700 [overflow-wrap:anywhere]">
                        {{ $ticket->latestPublicMessage?->body ?: $ticket->description }}
                    </p>

                    @if ($ticket->isChatWidgetConversation() && ($ticket->origin_route || $ticket->origin_path))
                        <p class="mt-2 break-all text-xs text-slate-500">
                            Started from {{ $ticket->origin_route ? str($ticket->origin_route)->replace('.', ' ')->headline() : 'the app' }}
                            @if ($ticket->origin_path) &middot; {{ $ticket->origin_path }} @endif
                        </p>
                    @endif

                    @if ($ticket->careRequest)
                        <p class="mt-1 text-xs text-slate-500">Request: {{ $ticket->careRequest->title }}</p>
                    @endif
                    @if ($ticket->careBooking)
                        <p class="text-xs text-slate-500">Booking: #{{ $ticket->careBooking->id }}</p>
                    @endif

                    <p class="mt-2 text-xs text-slate-500">Assigned: {{ $ticket->assignedAdmin?->name ?: 'Unassigned' }}</p>
                    @error('claim.'.$ticket->id)
                        <p class="mt-2 text-sm font-medium text-amber-800">{{ $message }}</p>
                    @enderror

                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_auto_auto_auto_auto_auto] xl:items-center">
                        <a href="{{ route('admin.support.tickets.show', $ticket) }}" wire:navigate class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800">Open conversation</a>
                        @if ($ticket->isChatWidgetConversation() && ! $ticket->assigned_admin_id)
                            <button type="button" wire:click="claimConversation({{ $ticket->id }})" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-4 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Claim conversation</button>
                        @endif
                        <x-button color="blue" light wire:click="updateStatus({{ $ticket->id }}, 'in_progress')" class="justify-center">In progress</x-button>
                        <x-button color="green" light wire:click="updateStatus({{ $ticket->id }}, 'resolved')" class="justify-center">Resolve</x-button>
                        <x-button color="slate" light wire:click="updateStatus({{ $ticket->id }}, 'closed')" class="justify-center">Close</x-button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-600">No tickets in this filter.</p>
            @endforelse
        </div>

        <x-slot:footer>{{ $tickets->links() }}</x-slot:footer>
    </x-card>
</div>
