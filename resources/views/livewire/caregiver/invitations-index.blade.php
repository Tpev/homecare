<div>
    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @if (!empty($prelaunchMode))
            <x-alert color="yellow">
                Caregiver pre-launch mode is active. You can still accept direct invitations sent by a family.
            </x-alert>
        @endif

        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-display font-semibold">Invitations</h1>
                <p class="text-sm text-[#607080]">Families can invite you directly to requests.</p>
            </div>
            <x-native-select-field
                label="Invitation status"
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
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-display font-semibold text-[#17313F]">{{ $invitation->careRequest?->title }}</p>
                                @if ($invitation->careRequest?->is_private)
                                    <x-badge text="PRIVATE" color="indigo" />
                                @endif
                            </div>
                            <p class="text-sm text-[#607080]">
                                From {{ $invitation->family?->name }}
                                 -  {{ $invitation->careRequest?->city }}, {{ $invitation->careRequest?->state }}
                            </p>
                            @if ($invitation->careRequest?->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                <p class="text-xs text-[#7B8794]">One-time  -  {{ optional($invitation->careRequest?->requested_start_at)->format('M d, Y H:i') }}</p>
                            @else
                                <p class="text-xs text-[#7B8794]">
                                    Recurring · {{ $invitation->careRequest?->recurringScheduleLabel() }}
                                </p>
                            @endif
                            <x-care-recipient-context :recipient="$invitation->careRequest?->recipient" :show-name="true" class="mt-2" />
                        </div>
                        <x-badge :text="strtoupper($invitation->status)" color="blue" />
                    </div>

                    @if ($invitation->message)
                        <div class="mt-3 rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">
                            {{ $invitation->message }}
                        </div>
                    @endif

                    @if ($invitation->status === \App\Models\CareRequestInvitation::STATUS_PENDING)
                        <p class="mt-2 text-xs text-[#7B8794]">
                            Respond by SLA: {{ optional($invitation->responseDueAt())->format('M d, H:i') }}
                            @if ($invitation->responseDueAt() && $invitation->responseDueAt()->isPast())
                                <span class="text-red-600"> (overdue)</span>
                            @endif
                        </p>
                    @endif

                    @if ($invitation->status === \App\Models\CareRequestInvitation::STATUS_PENDING)
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('caregiver.invitations.accept', $invitation->id) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex min-h-11 items-center justify-center rounded-[1rem] bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                                >
                                    Accept
                                </button>
                            </form>
                            <form method="POST" action="{{ route('caregiver.invitations.decline', $invitation->id) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex min-h-11 items-center justify-center rounded-[1rem] border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/30"
                                >
                                    Decline
                                </button>
                            </form>
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
                    <p class="text-sm text-[#607080]">No invitations in this status.</p>
                </x-card>
            @endforelse
        </div>
    </div>
</div>

