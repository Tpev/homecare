<div class="hc-page space-y-5 py-6">
    @if(session('status'))<x-alert color="green">{{ session('status') }}</x-alert>@endif
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div><a href="{{ route('admin.care-plans.index') }}" wire:navigate class="font-semibold text-[#0F5B52] underline">Back to regular care</a><h1 class="mt-3 font-display text-3xl font-semibold text-[#17313F]">Plan #{{ $plan->id }} - {{ $plan->title }}</h1><p class="mt-1 text-base text-[#526474]">{{ $plan->family?->name }} with {{ $plan->caregiver?->name }} - {{ ucfirst(str_replace('_', ' ', $plan->status)) }}</p></div>
        <div class="flex flex-wrap gap-2"><x-button color="blue" light wire:click="regenerate" wire:confirm="Generate any missing visits for this plan?">Generate missing visits</x-button><x-button color="green" light wire:click="preparePayments" wire:confirm="Prepare due per-visit payment authorizations now?">Prepare due payments</x-button></div>
    </header>

    <section class="grid gap-4 rounded-lg border border-[#D8D0C5] bg-white p-5 md:grid-cols-2 xl:grid-cols-4">
        <div><p class="text-sm font-semibold text-[#6A7784]">Family</p><p class="mt-1 font-semibold text-[#17313F]">{{ $plan->family?->name }}</p><p class="text-sm text-[#526474]">{{ $plan->family?->email }} - {{ $plan->family?->phone }}</p></div>
        <div><p class="text-sm font-semibold text-[#6A7784]">Caregiver</p><p class="mt-1 font-semibold text-[#17313F]">{{ $plan->caregiver?->name }}</p><p class="text-sm text-[#526474]">{{ $plan->caregiver?->email }} - {{ $plan->caregiver?->phone }}</p></div>
        <div><p class="text-sm font-semibold text-[#6A7784]">Schedule</p><p class="mt-1 font-semibold text-[#17313F]">{{ app(\App\Services\RegularCare\CarePlanService::class)->scheduleLabel($plan) }}</p><p class="text-sm text-[#526474]">{{ $plan->timezone }}</p></div>
        <div><p class="text-sm font-semibold text-[#6A7784]">Agreement</p><p class="mt-1 font-semibold text-[#17313F]">{{ ucfirst(str_replace('_', ' ', $plan->status)) }}</p><p class="text-sm text-[#526474]">Version {{ $plan->schedule_version }} - Source #{{ $plan->source_care_request_id }}</p></div>
    </section>

    <section class="rounded-lg border border-[#D8D0C5] bg-white">
        <div class="border-b border-[#E7E0D8] px-5 py-4"><h2 class="font-display text-2xl font-semibold text-[#17313F]">Completed extra visit audit</h2><p class="text-base text-[#526474]">Every submitted version, family decision, resulting booking, and payment outcome is preserved here.</p></div>
        <div class="divide-y divide-[#E7E0D8]">
            @forelse($plan->completedExtraVisitRequests as $report)
                @php
                    $start = $report->proposed_started_at?->copy()->setTimezone($report->timezone);
                    $end = $report->proposed_completed_at?->copy()->setTimezone($report->timezone);
                    $submittedFinancial = $report->financial_preview ?? [];
                    $finalFinancial = $report->final_financial_preview ?? [];
                @endphp
                <article class="grid gap-4 px-5 py-5 text-sm lg:grid-cols-[170px_1.3fr_1fr_1fr]">
                    <div><p class="font-semibold text-[#17313F]">Report #{{ $report->id }} · v{{ $report->version }}</p><p class="mt-1 text-[#526474]">{{ $report->statusLabel() }}</p><p class="mt-1 text-[#526474]">Submitted {{ $report->submitted_at?->format('M j, Y g:i A') }}</p></div>
                    <div><p class="font-semibold text-[#17313F]">{{ $start?->format('M j, Y g:i A') }} to {{ $end?->format('M j, g:i A T') }}</p><p class="mt-1 text-[#526474]">{{ $report->durationLabel() }} worked · {{ $report->proposed_break_minutes }} minute break</p><p class="mt-2 text-[#324457]">{{ $report->reasonLabel() }}: {{ $report->explanation }}</p>@if($report->family_response_note)<p class="mt-2 text-amber-800">Family response: {{ $report->family_response_note }}</p>@endif</div>
                    <div><p class="font-semibold text-[#17313F]">Financial record</p><p class="mt-1 text-[#526474]">Submitted charge: ${{ number_format(((int) data_get($submittedFinancial, 'total_charge_cents', 0))/100, 2) }}</p><p class="text-[#526474]">Final captured: ${{ number_format(((int) data_get($finalFinancial, 'amount_captured_cents', 0))/100, 2) }}</p><p class="text-[#526474]">Final payout: ${{ number_format(((int) data_get($finalFinancial, 'caregiver_amount_cents', 0))/100, 2) }}</p>@if($report->last_error)<p class="mt-2 text-rose-700">{{ $report->last_error }}</p>@endif</div>
                    <div><p class="font-semibold text-[#17313F]">Links and actors</p><p class="mt-1 text-[#526474]">Caregiver: {{ $report->caregiver?->name }}</p><p class="text-[#526474]">Approved by: {{ $report->approvedBy?->name ?: 'Not approved' }}</p><p class="text-[#526474]">Booking: {{ $report->care_booking_id ? '#'.$report->care_booking_id : 'Not created' }}</p><p class="mt-2 text-[#526474]">Notifications: {{ $report->notificationDeliveries->count() }} attempt(s)</p>@foreach($report->notificationDeliveries as $delivery)<p class="text-xs text-[#6A7784]">{{ $delivery->event_key }} · {{ $delivery->channel }} · {{ $delivery->status }}</p>@endforeach @if($report->booking)<a href="{{ route('admin.requests.show', $report->booking->care_request_id) }}" wire:navigate class="mt-2 inline-block font-semibold text-[#0F5B52] underline">Open visit</a>@endif @if($report->supportTicket)<p class="mt-2 text-rose-700">Support #{{ $report->support_ticket_id }} · {{ $report->supportTicket->status }}</p>@endif</div>
                </article>
            @empty
                <p class="px-5 py-6 text-sm text-[#526474]">No completed extra visits have been reported for this plan.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-lg border border-[#D8D0C5] bg-white">
        <div class="border-b border-[#E7E0D8] px-5 py-4"><h2 class="font-display text-2xl font-semibold text-[#17313F]">Visits and payments</h2><p class="text-base text-[#526474]">Every row is a real booking. Payment retries affect only that visit.</p></div>
        <div class="overflow-x-auto"><table class="min-w-[1080px] w-full text-left text-sm"><thead class="bg-[#F5F2ED] text-[#526474]"><tr><th class="px-4 py-3">Booking</th><th class="px-4 py-3">When</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Payment</th><th class="px-4 py-3">Authorization</th><th class="px-4 py-3">Transfer</th><th class="px-4 py-3">Action</th></tr></thead><tbody class="divide-y divide-[#E7E0D8]">@foreach($bookings as $booking)<tr><td class="px-4 py-4 font-semibold">#{{ $booking->id }}<br><span class="font-normal text-[#526474]">{{ $booking->plan_visit_kind ?: 'regular' }} · v{{ $booking->plan_schedule_version ?: '?' }}</span>@if($booking->corrections->isNotEmpty())<br><span class="font-normal text-amber-700">{{ $booking->corrections->count() }} correction(s)</span>@endif</td><td class="px-4 py-4">{{ $booking->scheduled_start_at?->format('M j, Y g:i A') }}<br>to {{ $booking->scheduled_end_at?->format('g:i A') }}</td><td class="px-4 py-4">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</td><td class="px-4 py-4 font-semibold">{{ ucfirst(str_replace('_', ' ', $booking->payment?->status ?? 'none')) }}@if($booking->payment?->last_error)<br><span class="font-normal text-rose-700">{{ $booking->payment->last_error }}</span>@endif</td><td class="px-4 py-4">${{ number_format(($booking->payment?->amount_authorized_cents ?? 0)/100, 2) }}<br>{{ $booking->payment?->authorization_expires_at?->format('M j, g:i A') ?: 'Not authorized' }}</td><td class="px-4 py-4">{{ $booking->payment?->stripe_transfer_id ?: 'Not transferred' }}</td><td class="px-4 py-4"><a href="{{ route('admin.requests.show', $booking->care_request_id) }}" wire:navigate class="font-semibold text-[#0F5B52] underline">Request / support</a>@if($booking->payment_retryable)<button type="button" wire:click="retryPayment({{ $booking->id }})" wire:confirm="Retry authorization for this visit? The previous authorization will be reconciled first." class="ml-3 font-semibold text-[#4F5FAF] underline">Retry payment</button>@endif</td></tr>@endforeach</tbody></table></div>
        <div class="border-t border-[#E7E0D8] px-5 py-4">{{ $bookings->links() }}</div>
    </section>

    <section class="rounded-lg border border-[#D8D0C5] bg-white">
        <div class="border-b border-[#E7E0D8] px-5 py-4">
            <h2 class="font-display text-2xl font-semibold text-[#17313F]">Schedule history</h2>
            <p class="text-base text-[#526474]">Requested changes, decisions, effective dates, and the people involved.</p>
        </div>
        <div class="divide-y divide-[#E7E0D8]">
            @forelse($plan->scheduleChanges as $change)
                @php
                    $proposal = $change->proposed_schedule ?? [];
                    $days = collect(data_get($proposal, 'days', []))->map(fn ($day) => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $day] ?? '?')->join(', ');
                    $proposedLabel = $change->type === \App\Models\CarePlanScheduleChange::TYPE_EXTRA_VISIT
                        ? optional(\Illuminate\Support\Carbon::parse(data_get($proposal, 'start_at')))->format('M j, Y g:i A').' to '.optional(\Illuminate\Support\Carbon::parse(data_get($proposal, 'end_at')))->format('g:i A')
                        : $days.' '.substr((string) data_get($proposal, 'start_time'), 0, 5).' to '.substr((string) data_get($proposal, 'end_time'), 0, 5);
                @endphp
                <div class="grid gap-2 px-5 py-4 text-sm md:grid-cols-[150px_1fr_180px]">
                    <div><p class="font-semibold text-[#17313F]">{{ ucfirst(str_replace('_', ' ', $change->type)) }}</p><p class="text-[#526474]">Effective {{ $change->effective_on?->format('M j, Y') }}</p></div>
                    <div><p class="font-semibold text-[#17313F]">{{ $proposedLabel }}</p><p class="text-[#526474]">Requested by {{ $change->requestedBy?->name ?: 'System' }}{{ $change->note ? ': '.$change->note : '' }}</p></div>
                    <div><p class="font-semibold text-[#17313F]">{{ ucfirst($change->status) }}</p><p class="text-[#526474]">{{ $change->respondedBy ? 'By '.$change->respondedBy->name : 'Awaiting response' }}</p></div>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-[#526474]">No schedule changes recorded.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-lg border border-[#D8D0C5] bg-white">
        <div class="border-b border-[#E7E0D8] px-5 py-4">
            <h2 class="font-display text-2xl font-semibold text-[#17313F]">Operations audit</h2>
            <p class="text-base text-[#526474]">Plan-level overrides retain the operator and required reason.</p>
        </div>
        <div class="divide-y divide-[#E7E0D8]">
            @forelse($plan->events as $event)
                <div class="grid gap-2 px-5 py-4 text-sm md:grid-cols-[180px_1fr_190px]">
                    <p class="font-semibold text-[#17313F]">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</p>
                    <p class="text-[#324457]">{{ $event->reason ?: 'No reason recorded.' }}</p>
                    <p class="text-[#526474]">{{ $event->actor?->name ?: 'System' }}<br>{{ $event->created_at?->format('M j, Y g:i A') }}</p>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-[#526474]">No plan-level operations recorded.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-lg border border-[#D8D0C5] bg-white">
        <div class="border-b border-[#E7E0D8] px-5 py-4">
            <h2 class="font-display text-2xl font-semibold text-[#17313F]">Notification history</h2>
            <p class="text-base text-[#526474]">Delivery records linked to this exact regular-care plan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[760px] w-full text-left text-sm">
                <thead class="bg-[#F5F2ED] text-[#526474]"><tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Recipient</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Channel</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-[#E7E0D8]">
                    @forelse($notifications as $delivery)
                        <tr><td class="px-4 py-3">{{ ($delivery->sent_at ?: $delivery->created_at)?->format('M j, Y g:i A') }}</td><td class="px-4 py-3">{{ $delivery->user?->name }}</td><td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $delivery->event_key)) }}</td><td class="px-4 py-3">{{ strtoupper($delivery->channel) }}</td><td class="px-4 py-3">{{ ucfirst($delivery->status) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-[#526474]">No linked notifications recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg border border-amber-200 bg-amber-50 p-5">
        <h2 class="font-display text-xl font-semibold text-amber-950">Operations controls</h2><p class="mt-1 text-sm text-amber-900">A reason is required and the family and caregiver are notified.</p>
        <textarea wire:model="operationsReason" rows="2" class="mt-3 w-full rounded-md border-amber-300" placeholder="Required operational reason"></textarea><x-input-error :messages="$errors->get('operationsReason')" class="mt-2" />
        <div class="mt-3 flex flex-wrap gap-2">@if($plan->status === \App\Models\CarePlan::STATUS_PAUSED)<x-button color="green" wire:click="changeState('resume')" wire:confirm="Resume this plan?">Resume</x-button>@elseif($plan->isLive())<x-button color="amber" wire:click="changeState('pause')" wire:confirm="Pause and cancel future visits?">Pause</x-button>@endif @if($plan->isLive())<x-button color="red" wire:click="changeState('end')" wire:confirm="End and cancel all future visits?">End plan</x-button>@endif</div>
    </section>
</div>
