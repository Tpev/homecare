<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Support Tickets Queue</h1>
                    <p class="mt-1 text-sm text-slate-600">Triage incoming support, disputes, and request-side issues quickly.</p>
                </div>
                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                    {{ $tickets->total() }} tickets
                </div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
            @forelse($tickets as $ticket)
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-medium text-slate-900">#{{ $ticket->id }} {{ $ticket->subject }}</p>
                        <x-badge :text="strtoupper($ticket->status)" color="blue" />
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ strtoupper($ticket->category) }} · {{ strtoupper($ticket->priority) }}
                        · {{ $ticket->opener?->name }} ({{ $ticket->opener?->email }})
                    </p>
                    <p class="text-sm text-slate-700 mt-2">{{ $ticket->description }}</p>
                    @if($ticket->careRequest)
                        <p class="text-xs text-slate-500 mt-1">Request: {{ $ticket->careRequest->title }}</p>
                    @endif
                    @if($ticket->careBooking)
                        <p class="text-xs text-slate-500">Booking: #{{ $ticket->careBooking->id }}</p>
                    @endif
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(16rem,1fr)_auto_auto_auto] xl:items-center">
                        <x-input placeholder="Admin note" wire:model="adminNote" />
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
