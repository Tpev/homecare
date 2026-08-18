<div class="hc-page py-8 space-y-6">
    @if($aiPrepared)
        <x-alert color="blue">LoLo prepared these support details. Review and edit them before you create the ticket. Nothing was submitted automatically.</x-alert>
    @endif
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
                        ['label' => 'Time correction', 'value' => 'time_correction'],
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
                <div class="rounded-lg border p-3 {{ $ticket->is_unread_for_opener ? 'border-blue-300 bg-blue-50/50' : 'border-slate-200' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-slate-900">{{ $ticket->subject }}</p>
                            @if ($ticket->is_unread_for_opener)
                                <span class="rounded-full bg-blue-600 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white">New reply</span>
                            @endif
                        </div>
                        <x-badge :text="strtoupper($ticket->status)" color="blue" />
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ strtoupper($ticket->category) }} · {{ strtoupper($ticket->priority) }} · #{{ $ticket->id }}</p>
                    <p class="text-sm text-slate-700 mt-2">{{ $ticket->description }}</p>
                    @if ($ticket->admin_note)
                        <div class="mt-2 rounded bg-slate-50 border border-slate-200 px-3 py-2 text-xs text-slate-600">
                            Previous admin response: {{ $ticket->admin_note }}
                        </div>
                    @endif
                    @if ($ticket->latestPublicMessage)
                        <p class="mt-2 truncate text-xs text-slate-500">
                            Latest: {{ $ticket->latestPublicMessage->sender?->name ?: 'Support' }} — {{ $ticket->latestPublicMessage->body }}
                        </p>
                    @endif
                    <div class="mt-3 flex justify-end">
                        <a href="{{ route('support.tickets.show', $ticket) }}" wire:navigate class="inline-flex min-h-10 items-center justify-center rounded-xl bg-[#0F6B5B] px-4 text-sm font-semibold text-white hover:bg-[#0B594C]">
                            Open conversation
                        </a>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-600">No support tickets yet.</p>
            @endforelse
        </div>
    </x-card>
</div>
