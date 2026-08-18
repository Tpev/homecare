<div
    class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8"
    x-data="{
        paused: false,
        timer: null,
        init() {
            this.timer = setInterval(() => {
                if (! this.paused) this.$wire.refreshThread()
            }, 5000)
        },
        destroy() {
            if (this.timer) clearInterval(this.timer)
        },
    }"
    x-on:support-compose-focus.window="paused = true"
    x-on:support-compose-blur.window="paused = false"
    x-on:support-message-sent.window="paused = false"
>
    @php
        $ticket = $this->ticket;
        $messages = $this->messages;
        $isClosed = $ticket->status === \App\Models\SupportTicket::STATUS_CLOSED;
        $booking = $ticket->careBooking;
        $correctionPreview = $booking ? $this->correctionPreview : null;
        $bookingCorrections = $booking ? $this->bookingCorrections : collect();
        $timeCorrection = $ticket->timeCorrection;
        $familyMembership = $ticket->familyAccount?->activeMemberships?->firstWhere('user_id', $ticket->opener_user_id);
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @error('correctionApply')
            <x-alert color="red">{{ $message }}</x-alert>
        @enderror

        <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ route('admin.support.tickets') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; Back to support queue</a>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Support ticket #{{ $ticket->id }}</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">{{ $ticket->subject }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($ticket->isChatWidgetConversation())
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-800">Chat</span>
                @endif
                <x-badge :text="strtoupper($ticket->status)" color="blue" />
                <x-badge :text="strtoupper($ticket->priority)" color="slate" />
                <x-badge :text="strtoupper($ticket->category)" color="slate" />
            </div>
        </header>

        @if ($ticket->isChatWidgetConversation())
            <section class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm" aria-label="Chat customer context">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Support chat context</p>
                        <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <h2 class="text-xl font-bold text-slate-950">{{ $ticket->opener?->name ?: 'Former user' }}</h2>
                            <span class="text-sm font-semibold text-slate-600">{{ str((string) ($ticket->opener?->role ?: 'user'))->headline() }}</span>
                            @if ($familyMembership)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $familyMembership->isOwner() ? 'Family account owner' : 'Family member' }}</span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-col gap-1 text-sm text-slate-600 sm:flex-row sm:flex-wrap sm:gap-x-4">
                            @if ($ticket->opener?->email)<a href="mailto:{{ $ticket->opener->email }}" class="break-all font-medium text-emerald-700 underline">{{ $ticket->opener->email }}</a>@endif
                            @if ($ticket->opener?->phone)<a href="tel:{{ $ticket->opener->phone }}" class="font-medium text-emerald-700 underline">{{ $ticket->opener->phone }}</a>@endif
                            @if ($ticket->opener)
                                <a href="{{ route('admin.users.show', $ticket->opener) }}" wire:navigate class="font-medium text-emerald-700 underline">Open user profile</a>
                            @endif
                        </div>
                        <p class="mt-3 break-all text-sm text-slate-600">
                            Started from
                            <span class="font-semibold text-slate-900">{{ $ticket->origin_route ? str($ticket->origin_route)->replace('.', ' ')->headline() : 'an application page' }}</span>
                            @if ($ticket->origin_path) <span class="text-slate-500">({{ $ticket->origin_path }})</span> @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">User presence is not inferred from sign-in status. This MVP updates through five-second polling.</p>
                    </div>

                    <div class="w-full rounded-xl border border-slate-200 bg-slate-50 p-4 lg:w-72">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Conversation owner</p>
                        <p class="mt-1 font-semibold text-slate-950">{{ $ticket->assignedAdmin?->name ?: 'Unassigned' }}</p>
                        @if ($ticket->claimed_at)
                            <p class="mt-1 text-xs text-slate-500">Claimed {{ $ticket->claimed_at->diffForHumans() }}</p>
                        @endif
                        @if (! $ticket->assigned_admin_id)
                            <button type="button" wire:click="claimConversation" class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-700 px-4 text-sm font-bold text-white hover:bg-emerald-800">Claim conversation</button>
                            @error('claim') <p class="mt-2 text-sm font-medium text-amber-800">{{ $message }}</p> @enderror
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if ($booking)
            <section class="overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-sm">
                <div class="border-b border-indigo-100 bg-indigo-50/70 px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-indigo-700">Visit correction</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-950">Booking #{{ $booking->id }}</h2>
                            <p class="mt-1 text-sm text-slate-600">Correct the visit, reconcile payment and payout, notify the user, and resolve this ticket.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-badge :text="'VISIT '.strtoupper($booking->status)" color="blue" />
                            <x-badge :text="'PAYMENT '.strtoupper((string) ($booking->payment?->status ?: 'none'))" color="slate" />
                        </div>
                    </div>
                </div>

                @if ($timeCorrection)
                    @php
                        $originalBooking = (array) data_get($timeCorrection->original_snapshot, 'booking', []);
                        $versions = $timeCorrection->booking?->timeCorrections ?? collect([$timeCorrection]);
                    @endphp
                    <div class="border-b border-indigo-100 bg-[#FBFAFF] p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-indigo-700">Caregiver + family collaboration</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-950">Time correction #{{ $timeCorrection->id }} · {{ $timeCorrection->statusLabel() }}</h3>
                                <p class="mt-1 text-sm text-slate-600">Requested by {{ $timeCorrection->requester?->name }}. {{ $timeCorrection->approvedBy ? 'Approved by '.$timeCorrection->approvedBy->name.' on '.$timeCorrection->approved_at?->format('M j, Y g:i A').'.' : 'Family approval was not recorded.' }}</p>
                            </div>
                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold uppercase text-indigo-800">{{ str_replace('_', ' ', $timeCorrection->status) }}</span>
                        </div>

                        <div class="mt-4 grid gap-3 lg:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                                <p class="text-xs font-bold uppercase text-slate-500">Scheduled</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $booking->scheduled_start_at?->format('M j, g:i A') ?: 'Not set' }} – {{ $booking->scheduled_end_at?->format('g:i A') ?: 'Not set' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                                <p class="text-xs font-bold uppercase text-slate-500">Original app record</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ data_get($originalBooking, 'started_at') ? \Illuminate\Support\Carbon::parse(data_get($originalBooking, 'started_at'))->format('M j, g:i A') : 'No check-in' }} – {{ data_get($originalBooking, 'completed_at') ? \Illuminate\Support\Carbon::parse(data_get($originalBooking, 'completed_at'))->format('g:i A') : 'No end time' }}</p>
                            </div>
                            <div class="rounded-xl border border-indigo-300 bg-indigo-50 p-3 text-sm">
                                <p class="text-xs font-bold uppercase text-indigo-700">Latest requested</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $timeCorrection->proposed_started_at?->format('M j, g:i A') }} – {{ $timeCorrection->proposed_completed_at?->format('g:i A') }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $timeCorrection->durationLabel() }} · {{ $timeCorrection->proposed_break_minutes }} min break · {{ $timeCorrection->familyAmountLabel() }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                                <p class="font-bold text-slate-900">Original location evidence</p>
                                <p class="mt-1">Check-in: {{ data_get($originalBooking, 'check_in_lat') && data_get($originalBooking, 'check_in_lng') ? data_get($originalBooking, 'check_in_lat').', '.data_get($originalBooking, 'check_in_lng').' ('.(data_get($originalBooking, 'check_in_accuracy_meters') ?: '?').'m)' : 'No GPS captured' }}</p>
                                <p>Check-out: {{ data_get($originalBooking, 'check_out_lat') && data_get($originalBooking, 'check_out_lng') ? data_get($originalBooking, 'check_out_lat').', '.data_get($originalBooking, 'check_out_lng').' ('.(data_get($originalBooking, 'check_out_accuracy_meters') ?: '?').'m)' : 'No GPS captured' }}</p>
                                <p class="mt-1">Current payment: <span class="font-semibold">{{ str_replace('_', ' ', $booking->payment?->status ?: 'none') }}</span></p>
                                @if ($timeCorrection->last_error)<p class="mt-2 font-semibold text-amber-800">Escalation: {{ $timeCorrection->last_error }}</p>@endif
                            </div>
                            <div class="space-y-2">
                                @foreach ($versions as $version)
                                    <div class="rounded-xl border border-slate-200 bg-white p-3 text-sm">
                                        <p class="font-bold text-slate-900">Version {{ $version->version }} · {{ $version->statusLabel() }}</p>
                                        <p class="mt-1 text-slate-700">{{ $version->reasonLabel() }}: {{ $version->explanation }}</p>
                                        @if ($version->family_response_note)<p class="mt-1 text-amber-800"><span class="font-semibold">Family response:</span> {{ $version->family_response_note }}</p>@endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
                    <form wire:submit="applyVisitCorrection" class="space-y-4">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="cursor-pointer rounded-xl border p-3 transition {{ $correctionAction === \App\Models\CareBookingCorrection::ACTION_COMPLETE_AND_BILL ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="radio" wire:model.live="correctionAction" value="complete_and_bill" class="sr-only">
                                <span class="block text-sm font-bold text-slate-900">Correct, complete &amp; bill</span>
                                <span class="mt-1 block text-xs text-slate-600">Set approved times, charge or refund the difference, and update payout.</span>
                            </label>
                            <label class="cursor-pointer rounded-xl border p-3 transition {{ $correctionAction === \App\Models\CareBookingCorrection::ACTION_REOPEN ? 'border-amber-500 bg-amber-50' : 'border-slate-200 hover:bg-slate-50' }}">
                                <input type="radio" wire:model.live="correctionAction" value="reopen" class="sr-only">
                                <span class="block text-sm font-bold text-slate-900">Reopen visit</span>
                                <span class="mt-1 block text-xs text-slate-600">Clear accidental tracking so the uncaptured visit can be started again.</span>
                            </label>
                        </div>
                        @error('correctionAction') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                        @if ($correctionAction === \App\Models\CareBookingCorrection::ACTION_COMPLETE_AND_BILL)
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label for="correction-start" class="block text-sm font-semibold text-slate-700">Approved start</label>
                                    <input id="correction-start" type="datetime-local" wire:model.blur="correctionStartedAt" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    @error('correctionStartedAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="correction-end" class="block text-sm font-semibold text-slate-700">Approved end</label>
                                    <input id="correction-end" type="datetime-local" wire:model.blur="correctionCompletedAt" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    @error('correctionCompletedAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="correction-break" class="block text-sm font-semibold text-slate-700">Unpaid break (minutes)</label>
                                    <input id="correction-break" type="number" min="0" max="1440" wire:model.blur="correctionBreakMinutes" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    @error('correctionBreakMinutes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif

                        <div>
                            <label for="correction-reason" class="block text-sm font-semibold text-slate-700">Admin reason</label>
                            <textarea id="correction-reason" rows="3" wire:model.blur="correctionReason" placeholder="What was verified, with whom, and why this correction is needed" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                            @error('correctionReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        @if ($correctionAction === \App\Models\CareBookingCorrection::ACTION_COMPLETE_AND_BILL)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                <input type="checkbox" wire:model="correctionFamilyApproved" class="mt-0.5 rounded border-emerald-300 text-emerald-700 focus:ring-emerald-600">
                                <span>
                                    <span class="block text-sm font-bold text-emerald-950">Family approved this correction</span>
                                    <span class="mt-0.5 block text-xs text-emerald-800">This authorizes LoLo to use the saved family card for any additional amount.</span>
                                </span>
                            </label>
                            @error('correctionFamilyApproved') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                        @endif

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3">
                            <input type="checkbox" wire:model="correctionImpactConfirmed" class="mt-0.5 rounded border-rose-300 text-rose-700 focus:ring-rose-600">
                            <span>
                                <span class="block text-sm font-bold text-rose-950">I reviewed the visit and financial impact</span>
                                <span class="mt-0.5 block text-xs text-rose-800">This action creates an audit record and may charge, refund, transfer, or reverse real funds.</span>
                            </span>
                        </label>
                        @error('correctionImpactConfirmed') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="applyVisitCorrection"
                            wire:confirm="Apply this approved visit correction and its payment/payout changes?"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-indigo-700 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-800 disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                            @disabled($isClosed)
                        >
                            <span wire:loading.remove wire:target="applyVisitCorrection">Apply correction</span>
                            <span wire:loading wire:target="applyVisitCorrection">Reconciling visit and payment...</span>
                        </button>
                        @if ($isClosed)
                            <p class="text-xs text-rose-600">Reopen the support ticket before applying a correction.</p>
                        @endif
                    </form>

                    <div class="space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-sm font-bold text-slate-900">Impact preview</h3>
                            @if ($correctionPreview)
                                @if (! ($correctionPreview['can_apply'] ?? false))
                                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                        {{ $correctionPreview['blocking_message'] }}
                                    </div>
                                @elseif ($correctionAction === \App\Models\CareBookingCorrection::ACTION_REOPEN)
                                    <p class="mt-3 text-sm text-slate-700">Tracking and submitted hours will be cleared. Existing uncaptured authorization remains available.</p>
                                @else
                                    @php
                                        $worked = (int) $correctionPreview['worked_minutes'];
                                        $delta = (int) $correctionPreview['payment_delta_cents'];
                                        $caregiverDelta = (int) $correctionPreview['caregiver_delta_cents'];
                                    @endphp
                                    <dl class="mt-3 space-y-3 text-sm">
                                        <div class="flex items-center justify-between gap-4">
                                            <dt class="text-slate-600">Approved duration</dt>
                                            <dd class="font-bold text-slate-950">{{ intdiv($worked, 60) }}h {{ $worked % 60 }}m</dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-4">
                                            <dt class="text-slate-600">Family charge</dt>
                                            <dd class="font-bold text-slate-950">&#36;{{ number_format($correctionPreview['current_charge_cents'] / 100, 2) }} &rarr; &#36;{{ number_format($correctionPreview['target_charge_cents'] / 100, 2) }}</dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-4 rounded-lg px-2 py-2 {{ $delta > 0 ? 'bg-amber-100 text-amber-950' : ($delta < 0 ? 'bg-emerald-100 text-emerald-950' : 'bg-slate-100 text-slate-800') }}">
                                            <dt>{{ $delta > 0 ? 'Additional charge' : ($delta < 0 ? 'Refund' : 'Payment difference') }}</dt>
                                            <dd class="font-extrabold">&#36;{{ number_format(abs($delta) / 100, 2) }}</dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-4">
                                            <dt class="text-slate-600">Caregiver payout change</dt>
                                            <dd class="font-bold {{ $caregiverDelta < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $caregiverDelta > 0 ? '+' : ($caregiverDelta < 0 ? '-' : '') }}&#36;{{ number_format(abs($caregiverDelta) / 100, 2) }}</dd>
                                        </div>
                                        <div class="flex items-center justify-between gap-4">
                                            <dt class="text-slate-600">Rate used</dt>
                                            <dd class="font-semibold text-slate-900">&#36;{{ number_format((float) $correctionPreview['hourly_rate'], 2) }}/hr</dd>
                                        </div>
                                    </dl>
                                @endif
                            @else
                                <p class="mt-3 text-sm text-slate-600">Enter valid approved times to calculate the exact charge, refund, and payout impact.</p>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-bold text-slate-900">Current record</h3>
                            <dl class="mt-3 space-y-2 text-xs text-slate-600">
                                <div class="flex justify-between gap-3"><dt>Scheduled</dt><dd class="text-right font-semibold text-slate-800">{{ $booking->scheduled_start_at?->format('M j, g:i A') ?: '—' }} – {{ $booking->scheduled_end_at?->format('g:i A') ?: '—' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Recorded</dt><dd class="text-right font-semibold text-slate-800">{{ $booking->started_at?->format('M j, g:i A') ?: 'Not started' }} – {{ $booking->completed_at?->format('g:i A') ?: 'Not ended' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Worked</dt><dd class="font-semibold text-slate-800">{{ is_null($booking->worked_minutes) ? 'Not submitted' : intdiv((int) $booking->worked_minutes, 60).'h '.((int) $booking->worked_minutes % 60).'m' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>Captured</dt><dd class="font-semibold text-slate-800">&#36;{{ number_format(((int) ($booking->payment?->amount_captured_cents ?? 0) - (int) ($booking->payment?->amount_refunded_cents ?? 0)) / 100, 2) }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>

                @if ($bookingCorrections->isNotEmpty())
                    <div class="border-t border-slate-200 bg-slate-50 px-5 py-4">
                        <h3 class="text-sm font-bold text-slate-900">Correction history</h3>
                        <div class="mt-3 space-y-2">
                            @foreach ($bookingCorrections as $correction)
                                <div wire:key="booking-correction-{{ $correction->id }}" class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">#{{ $correction->id }} {{ str_replace('_', ' ', ucfirst($correction->action)) }}</p>
                                        <p class="mt-1 text-xs text-slate-600">{{ $correction->actorAdmin?->name ?: 'Former admin' }} · {{ $correction->created_at?->format('M j, g:i A') }} · {{ $correction->reason }}</p>
                                        @if ($correction->last_error)
                                            <p class="mt-1 text-xs font-semibold text-rose-700">{{ $correction->last_error }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase {{ $correction->status === 'succeeded' ? 'bg-emerald-100 text-emerald-800' : ($correction->status === 'requires_action' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">{{ str_replace('_', ' ', $correction->status) }}</span>
                                        @if (in_array($correction->status, [\App\Models\CareBookingCorrection::STATUS_REQUIRES_ACTION, \App\Models\CareBookingCorrection::STATUS_FAILED], true))
                                            <button type="button" wire:click="retryVisitCorrection({{ $correction->id }})" wire:confirm="Retry this correction using the family’s current saved card?" class="min-h-9 rounded-lg bg-amber-600 px-3 text-xs font-bold text-white hover:bg-amber-700">Retry</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-bold text-slate-950">Conversation with {{ $ticket->opener?->name ?: 'user' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Public replies notify the user. Internal notes stay with the admin team.</p>
                </div>

                <div
                    class="max-h-[62vh] min-h-[32rem] overflow-y-auto bg-slate-50 px-4 py-5 sm:px-6"
                    x-data="{ jump() { this.$el.scrollTop = this.$el.scrollHeight } }"
                    x-init="$nextTick(() => jump())"
                    x-on:support-message-sent.window="$nextTick(() => jump())"
                >
                    @if ($this->hasOlderMessages)
                        <div class="mb-4 text-center">
                            <button type="button" wire:click="loadMore" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Load older messages</button>
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="flex justify-start">
                            <article class="max-w-[88%] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm md:max-w-[72%]">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">Ticket opened</p>
                                <p class="mt-2 whitespace-pre-line text-sm">{{ $ticket->description }}</p>
                                <div class="mt-2 flex items-center justify-between gap-4 text-[11px] text-slate-500">
                                    <span>{{ $ticket->opener?->name ?: 'User' }} · {{ $ticket->opener?->role }}</span>
                                    <span>{{ $ticket->created_at?->format('M j, g:i A') }}</span>
                                </div>
                            </article>
                        </div>

                        @if ($ticket->admin_note)
                            <div class="flex justify-end">
                                <article class="max-w-[88%] rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sky-950 shadow-sm md:max-w-[72%]">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-sky-700">Previous public admin response</p>
                                    <p class="mt-2 whitespace-pre-line text-sm">{{ $ticket->admin_note }}</p>
                                    <p class="mt-2 text-[11px] text-sky-700">Legacy response — still visible to the user</p>
                                </article>
                            </div>
                        @endif

                        @foreach ($messages as $message)
                            @php
                                $internal = $message->isInternalNote();
                                $fromAdmin = ($message->sender?->isAdministrator() ?? false)
                                    || in_array($message->responder_type, [
                                        \App\Models\SupportTicketMessage::RESPONDER_AUTOMATED,
                                        \App\Models\SupportTicketMessage::RESPONDER_SYSTEM,
                                    ], true);
                            @endphp
                            <div wire:key="admin-support-message-{{ $message->id }}" class="flex {{ $internal || $fromAdmin ? 'justify-end' : 'justify-start' }}">
                                <article class="max-w-[88%] rounded-2xl px-4 py-3 shadow-sm md:max-w-[72%] {{ $internal ? 'border border-amber-200 bg-amber-50 text-amber-950' : ($fromAdmin ? 'bg-emerald-800 text-white' : 'border border-slate-200 bg-white text-slate-800') }}">
                                    @if ($internal)
                                        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-amber-700">Internal note · Admin only</p>
                                    @endif
                                    <p class="{{ $internal ? 'mt-2 ' : '' }}whitespace-pre-line text-sm">{{ $message->body }}</p>
                                    <div class="mt-2 flex items-center justify-between gap-4 text-[11px] {{ $internal ? 'text-amber-700' : ($fromAdmin ? 'text-emerald-100' : 'text-slate-500') }}">
                                        <span>{{ $message->responder_type === \App\Models\SupportTicketMessage::RESPONDER_AUTOMATED ? 'LoLo Support assistant' : ($message->sender?->name ?: 'System/former user') }} · {{ $message->responder_type ?: ($message->sender?->role ?: 'unknown') }}</span>
                                        <span>{{ $message->created_at?->format('M j, g:i A') }}</span>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-white p-4 sm:p-5">
                    @if ($isClosed)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            This ticket is closed and read-only. Change its status before adding a reply or internal note.
                        </div>
                    @else
                        <div class="mb-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="messageKind" value="public" class="peer sr-only">
                                <span class="flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 peer-checked:bg-white peer-checked:text-emerald-800 peer-checked:shadow-sm">Reply to user</span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="messageKind" value="internal_note" class="peer sr-only">
                                <span class="flex min-h-10 items-center justify-center rounded-lg px-3 text-sm font-semibold text-slate-600 peer-checked:bg-amber-50 peer-checked:text-amber-800 peer-checked:shadow-sm">Internal note</span>
                            </label>
                        </div>

                        <form wire:submit="sendMessage" class="space-y-3">
                            <textarea
                                wire:model="messageBody"
                                rows="4"
                                placeholder="{{ $messageKind === 'internal_note' ? 'Add a private note for admins...' : 'Write a reply to the user...' }}"
                                x-on:focus="$dispatch('support-compose-focus')"
                                x-on:blur="$dispatch('support-compose-blur')"
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100"
                            ></textarea>
                            @error('messageBody') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-slate-500">
                                    {{ $messageKind === 'internal_note' ? 'Only admins can see this note.' : 'The user will be notified of this reply.' }}
                                </p>
                                <button type="submit" wire:loading.attr="disabled" wire:target="sendMessage" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60">
                                    <span wire:loading.remove wire:target="sendMessage">{{ $messageKind === 'internal_note' ? 'Add note' : 'Send reply' }}</span>
                                    <span wire:loading wire:target="sendMessage">Saving...</span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">User</p>
                    <p class="mt-2 font-bold text-slate-950">{{ $ticket->opener?->name ?: 'Unknown user' }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $ticket->opener?->email }}</p>
                    @if ($ticket->opener?->phone)
                        <p class="mt-1 text-sm text-slate-600">{{ $ticket->opener->phone }}</p>
                    @endif
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $ticket->opener?->role }}</p>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-950">Ticket controls</h2>

                    <form wire:submit="updateStatus" class="mt-4 space-y-2">
                        <label class="block text-sm font-medium text-slate-700" for="ticket-status">Status</label>
                        <select id="ticket-status" wire:model="status" class="min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="open">Open</option>
                            <option value="in_progress">In progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <button type="submit" class="min-h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Update status</button>
                    </form>

                    <form wire:submit="updateAssignment" class="mt-5 space-y-2">
                        <label class="block text-sm font-medium text-slate-700" for="ticket-assignee">Assigned admin</label>
                        <select id="ticket-assignee" wire:model="assignedAdminId" class="min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Unassigned</option>
                            @foreach ($this->adminOptions as $adminId => $adminName)
                                <option value="{{ $adminId }}">{{ $adminName }}</option>
                            @endforeach
                        </select>
                        @error('assignedAdminId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                        <button type="submit" class="min-h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Update assignment</button>
                    </form>

                    @if ($ticket->isHumanOnly() && ! $isClosed && ! $ticket->transcript_deleted_at)
                        <form wire:submit="returnToAutomation" class="mt-5 space-y-2 border-t border-slate-200 pt-5">
                            <label class="block text-sm font-medium text-slate-700" for="return-ai-reason">Return to assistant</label>
                            <textarea id="return-ai-reason" wire:model="returnToAutomationReason" rows="2" placeholder="Why is automation safe again?" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                            @error('returnToAutomationReason') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                            <button type="submit" wire:confirm="Return this conversation to the assistant? The exact user must still have active pilot access." class="min-h-10 w-full rounded-xl border border-amber-300 bg-amber-50 px-3 text-sm font-semibold text-amber-900 hover:bg-amber-100">Return deliberately</button>
                        </form>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-bold text-slate-950">AI evidence</h2>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase {{ $ticket->isHumanOnly() ? 'bg-slate-100 text-slate-700' : 'bg-violet-100 text-violet-800' }}">{{ str_replace('_', ' ', $ticket->responder_mode) }}</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Compact event evidence only. Message content remains in the conversation.</p>

                    <dl class="mt-4 space-y-2 text-xs">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Context contract</dt><dd class="font-semibold text-slate-800">{{ $this->aiContractVersions['context'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Event contract</dt><dd class="font-semibold text-slate-800">{{ $this->aiContractVersions['event'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Retention policy</dt><dd class="font-semibold text-slate-800">{{ $this->aiContractVersions['retention'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Active recaps</dt><dd class="font-semibold text-slate-800">{{ $this->aiActionState['active_recaps'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Expired recaps</dt><dd class="font-semibold text-slate-800">{{ $this->aiActionState['expired_recaps'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Receipts</dt><dd class="font-semibold text-slate-800">{{ $this->aiActionState['receipts'] }}</dd></div>
                    </dl>

                    @foreach ($this->aiDrafts as $draft)
                        <div class="mt-4 rounded-xl border border-violet-200 bg-violet-50 p-3 text-xs text-violet-950">
                            <p class="font-bold">Private request draft</p>
                            <p class="mt-1">{{ $draft->actor?->name ?: 'Former user' }} &middot; {{ str_replace('_', ' ', $draft->request_type ?: 'type not selected') }}</p>
                            <p class="mt-1">State {{ str_replace('_', ' ', $draft->state) }} &middot; version {{ $draft->version }} &middot; expires {{ $draft->expires_at?->format('M j, Y g:i A') }}</p>
                        </div>
                    @endforeach

                    @foreach ($this->aiConfirmedActions as $confirmed)
                        <div data-testid="ai-confirmed-action-receipt" class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-950">
                            <p class="font-bold">Confirmed action receipt</p>
                            <p class="mt-1">{{ $confirmed->receipt_reference }} &middot; {{ $confirmed->outcome_code }}</p>
                            <p class="mt-1">{{ $confirmed->tool_id }} {{ $confirmed->tool_version }} &middot; {{ $confirmed->committed_at?->format('M j, Y g:i A') }}</p>
                        </div>
                    @endforeach

                    <div class="mt-4 space-y-2">
                        @forelse ($this->aiEvidence as $event)
                            <article wire:key="ai-evidence-{{ $event->id }}" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs">
                                <p class="font-bold text-slate-900">{{ str_replace('_', ' ', $event->event_type) }}</p>
                                <p class="mt-1 text-slate-600">{{ $event->actor?->name ?: 'System' }} · {{ $event->occurred_at?->format('M j, g:i A') }}</p>
                                <p class="mt-1 text-slate-500">Result: {{ $event->result_code ?: 'recorded' }}@if($event->capability_id) · {{ $event->capability_id }}@endif</p>
                                @if ($event->knowledge_version_ids)
                                    <p class="mt-1 text-slate-500">KB versions: {{ implode(', ', $event->knowledge_version_ids) }}</p>
                                @endif
                                @if ($event->latency_ms !== null || $event->input_tokens !== null)
                                    <p class="mt-1 text-slate-500">{{ $event->model_configuration_version ?: 'deterministic' }} &middot; {{ $event->latency_ms ?? 0 }} ms &middot; {{ $event->input_tokens ?? 0 }} in / {{ $event->output_tokens ?? 0 }} out &middot; ${{ number_format(($event->cost_microunits ?? 0) / 1_000_000, 4) }}</p>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500">No automated interaction events were recorded.</p>
                        @endforelse
                    </div>
                </section>

                @if ($ticket->careRequest || $ticket->careBooking)
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Related care</p>
                        @if ($ticket->careRequest)
                            <a href="{{ route('admin.requests.show', $ticket->careRequest) }}" wire:navigate class="mt-2 block font-semibold text-emerald-700 hover:underline">
                                Request #{{ $ticket->careRequest->id }}
                            </a>
                            <p class="mt-1 text-sm text-slate-600">{{ $ticket->careRequest->title }}</p>
                        @endif
                        @if ($ticket->careBooking)
                            <p class="mt-3 text-sm text-slate-600">Booking #{{ $ticket->careBooking->id }} · {{ $ticket->careBooking->status }}</p>
                        @endif
                    </section>
                @endif

                <section class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                    <p><span class="font-semibold text-slate-800">Opened:</span> {{ $ticket->created_at?->format('M j, Y g:i A') }}</p>
                    <p class="mt-2"><span class="font-semibold text-slate-800">Last public activity:</span> {{ $ticket->last_public_message_at?->diffForHumans() ?: 'Legacy ticket' }}</p>
                    @if ($ticket->resolved_at)
                        <p class="mt-2"><span class="font-semibold text-slate-800">Resolved:</span> {{ $ticket->resolved_at->format('M j, Y g:i A') }}</p>
                    @endif
                    <p class="mt-2"><span class="font-semibold text-slate-800">Transcript deletion:</span> {{ $ticket->transcript_deleted_at ? 'Completed '.$ticket->transcript_deleted_at->format('M j, Y') : ($ticket->transcript_delete_after?->format('M j, Y') ?: 'Not scheduled while active') }}</p>
                    @foreach ($this->activeRetentionHolds as $hold)
                        <p class="mt-2 rounded-lg bg-amber-50 px-2 py-2 text-amber-900"><span class="font-semibold">Retention hold:</span> {{ str_replace('_', ' ', $hold->reason_category) }} · review {{ $hold->review_at->format('M j, Y') }}@if($hold->expires_at) · expires {{ $hold->expires_at->format('M j, Y') }}@endif</p>
                    @endforeach
                </section>
            </aside>
        </div>
    </div>
</div>
