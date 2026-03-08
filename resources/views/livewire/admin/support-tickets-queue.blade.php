<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <h1 class="text-xl font-semibold">Support Tickets Queue</h1>
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
                    <div class="flex items-center justify-between">
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
                    <div class="mt-3 flex flex-wrap gap-2 items-center">
                        <x-input placeholder="Admin note" wire:model="adminNote" />
                        <x-button color="blue" light wire:click="updateStatus({{ $ticket->id }}, 'in_progress')">In progress</x-button>
                        <x-button color="green" light wire:click="updateStatus({{ $ticket->id }}, 'resolved')">Resolve</x-button>
                        <x-button color="slate" light wire:click="updateStatus({{ $ticket->id }}, 'closed')">Close</x-button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-600">No tickets in this filter.</p>
            @endforelse
        </div>
        <x-slot:footer>{{ $tickets->links() }}</x-slot:footer>
    </x-card>
</div>
