<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <h1 class="text-xl font-semibold">Support Center</h1>
        </x-slot:header>

        <div class="space-y-4">
            <x-input label="Subject" wire:model="subject" />
            <x-textarea label="Describe your issue" wire:model="description" />
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-native-select-field
                    label="Category"
                    wire:model="category"
                    :options="[
                        ['label' => 'General', 'value' => 'general'],
                        ['label' => 'Dispute', 'value' => 'dispute'],
                        ['label' => 'Incident', 'value' => 'incident'],
                        ['label' => 'Cancellation', 'value' => 'cancellation'],
                        ['label' => 'Billing', 'value' => 'billing'],
                    ]"
                />
                <x-native-select-field
                    label="Priority"
                    wire:model="priority"
                    :options="[
                        ['label' => 'Low', 'value' => 'low'],
                        ['label' => 'Normal', 'value' => 'normal'],
                        ['label' => 'High', 'value' => 'high'],
                        ['label' => 'Urgent', 'value' => 'urgent'],
                    ]"
                />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-native-select-field label="Related request (optional)" wire:model="care_request_id" :options="$requestOptions" />
                <x-native-select-field label="Related booking (optional)" wire:model="care_booking_id" :options="$bookingOptions" />
            </div>
        </div>

        <x-slot:footer>
            <x-button color="blue" wire:click="createTicket">Create ticket</x-button>
        </x-slot:footer>
    </x-card>

    <x-card>
        <x-slot:header>
            <h2 class="font-semibold">My tickets</h2>
        </x-slot:header>
        <div class="space-y-3">
            @forelse($tickets as $ticket)
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-slate-900">{{ $ticket->subject }}</p>
                        <x-badge :text="strtoupper($ticket->status)" color="blue" />
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ strtoupper($ticket->category) }} · {{ strtoupper($ticket->priority) }} · #{{ $ticket->id }}</p>
                    <p class="text-sm text-slate-700 mt-2">{{ $ticket->description }}</p>
                    @if ($ticket->admin_note)
                        <div class="mt-2 rounded bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600">
                            Admin note: {{ $ticket->admin_note }}
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-600">No support tickets yet.</p>
            @endforelse
        </div>
    </x-card>
</div>
