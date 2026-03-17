<div>
    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @if (!empty($prelaunchMode))
            <x-alert color="yellow">
                Caregiver pre-launch mode is active. You can review invitations now, but acceptance opens at launch.
            </x-alert>
        @endif

        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-display font-semibold">Invitations</h1>
                <p class="text-sm text-slate-600">Families can invite you directly to requests.</p>
            </div>
            <x-select.styled
                wire:model.live="status"
                :options="[
                    ['label' => 'Pending', 'value' => 'pending'],
                    ['label' => 'Accepted', 'value' => 'accepted'],
                    ['label' => 'Declined', 'value' => 'declined'],
                    ['label' => 'Expired', 'value' => 'expired'],
                    ['label' => 'All', 'value' => 'all'],
                ]"
            />
        </div>

        <div class="space-y-3">
            @forelse ($invitations as $invitation)
                <x-card>
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <p class="font-display font-semibold text-slate-900">{{ $invitation->careRequest?->title }}</p>
                            <p class="text-sm text-slate-600">
                                From {{ $invitation->family?->name }}
                                · {{ $invitation->careRequest?->city }}, {{ $invitation->careRequest?->state }}
                            </p>
                            @if ($invitation->careRequest?->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                <p class="text-xs text-slate-500">One-time · {{ optional($invitation->careRequest?->requested_start_at)->format('M d, Y H:i') }}</p>
                            @else
                                <p class="text-xs text-slate-500">
                                    Recurring ·
                                    {{ collect($invitation->careRequest?->recurring_days ?? [])->map(fn($d)=>['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][(int)$d] ?? null)->filter()->implode(', ') }}
                                    {{ $invitation->careRequest?->recurring_start_time }}-{{ $invitation->careRequest?->recurring_end_time }}
                                </p>
                            @endif
                        </div>
                        <x-badge :text="strtoupper($invitation->status)" color="blue" />
                    </div>

                    @if ($invitation->message)
                        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                            {{ $invitation->message }}
                        </div>
                    @endif

                    @if ($invitation->status === \App\Models\CareRequestInvitation::STATUS_PENDING)
                        <p class="mt-2 text-xs text-slate-500">
                            Respond by SLA: {{ optional($invitation->responseDueAt())->format('M d, H:i') }}
                            @if ($invitation->responseDueAt() && $invitation->responseDueAt()->isPast())
                                <span class="text-red-600"> (overdue)</span>
                            @endif
                        </p>
                    @endif

                    @if ($invitation->status === \App\Models\CareRequestInvitation::STATUS_PENDING)
                        <div class="mt-4 flex items-center gap-2">
                            <x-button color="green" wire:click="accept({{ $invitation->id }})" :disabled="!empty($prelaunchMode)">
                                {{ !empty($prelaunchMode) ? 'Accept at launch' : 'Accept' }}
                            </x-button>
                            <x-button color="red" outline wire:click="decline({{ $invitation->id }})">Decline</x-button>
                        </div>
                    @elseif ($invitation->status === \App\Models\CareRequestInvitation::STATUS_ACCEPTED && $invitation->care_request_application_id)
                        <div class="mt-4">
                            @if ($invitation->application?->conversation)
                                <a href="{{ route('messages.show', $invitation->application->conversation->id) }}" wire:navigate class="hc-link">Open chat</a>
                            @else
                                <a href="{{ route('care-requests.apply', $invitation->care_request_id) }}" wire:navigate class="hc-link">Open application</a>
                            @endif
                        </div>
                    @endif
                </x-card>
            @empty
                <x-card>
                    <p class="text-sm text-slate-600">No invitations in this status.</p>
                </x-card>
            @endforelse
        </div>
    </div>
</div>
