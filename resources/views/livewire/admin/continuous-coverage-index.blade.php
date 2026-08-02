<div class="hc-page space-y-6 py-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-[#0F6B5B]">Coverage operations</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-[#17313F]">Continuous Coverage</h1>
            <p class="mt-1 max-w-3xl text-base text-[#526474]">Read-only operational visibility for gaps, replacements, booking linkage, notification delivery, and payment attention. Families approve caregivers and caregivers choose whether to participate.</p>
        </div>
        <label>
            <span class="sr-only">Plan filter</span>
            <select wire:model.live="status" class="min-h-12 rounded-xl border-[#BFC8CE]">
                <option value="attention">Needs attention</option>
                <option value="all">All plans</option>
            </select>
        </label>
    </header>

    <section class="rounded-3xl border border-[#D8D0C5] bg-white p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="font-display text-2xl font-semibold">Shift exceptions</h2><p class="text-sm text-[#607080]">Future gaps and recoverable booking or payment issues. No action here changes a caregiver assignment.</p></div>
            <span class="text-sm font-semibold text-[#526474]">{{ $attentionShifts->total() }} total</span>
        </div>
        <div class="mt-4 space-y-3">
            @forelse($attentionShifts as $shift)
                @php
                    $operationalError = data_get($shift->metadata, 'last_operational_error');
                    $releasedPayment = data_get($shift->metadata, 'released_booking_payment_attention');
                    $label = $operationalError && !$shift->care_booking_id
                        ? 'Booking linkage failed'
                        : ($releasedPayment ? 'Released booking payment review' : ucfirst(str_replace('_', ' ', $shift->status)));
                @endphp
                <article class="grid gap-3 rounded-2xl border border-[#E7E0D8] p-4 lg:grid-cols-[1.3fr_1fr_1fr_1fr] lg:items-center">
                    <div><p class="font-semibold">{{ $shift->plan->title }}</p><p class="text-sm text-[#526474]">Shift #{{ $shift->id }} · {{ $shift->scheduled_start_at->copy()->setTimezone($shift->plan->timezone)->format('D, M j · g:i A T') }}</p></div>
                    <div><p class="text-xs text-[#7B8794]">Family</p><p class="font-semibold">{{ $shift->plan->family?->name }}</p></div>
                    <div><p class="text-xs text-[#7B8794]">Operational state</p><p class="font-semibold text-amber-800">{{ $label }}</p>@if($operationalError)<p class="text-xs text-[#7B8794]">Last error: {{ $operationalError }}</p>@endif</div>
                    <div><p class="text-xs text-[#7B8794]">Linked visit</p><p class="font-semibold">{{ $shift->care_booking_id ? '#'.$shift->care_booking_id : 'Not linked' }}</p>@if($releasedPayment)<p class="text-xs text-rose-700">Prior visit #{{ data_get($releasedPayment, 'care_booking_id') }} · {{ str_replace('_', ' ', (string) data_get($releasedPayment, 'payment_status')) }}</p>@endif</div>
                </article>
            @empty
                <p class="rounded-2xl bg-[#F7F2EA] p-6 text-center text-[#607080]">No current shift exceptions.</p>
            @endforelse
        </div>
        @if($attentionShifts->hasPages())
            <div class="mt-5">{{ $attentionShifts->links() }}</div>
        @endif
    </section>

    <section class="rounded-3xl border border-[#D8D0C5] bg-white p-5">
        <h2 class="font-display text-2xl font-semibold">Open replacement cases</h2>
        <div class="mt-4 space-y-3">
            @forelse($cases as $case)
                <article class="grid gap-3 rounded-2xl border border-[#E7E0D8] p-4 lg:grid-cols-[1.2fr_1fr_1fr_auto] lg:items-center">
                    <div><p class="font-semibold">{{ $case->shift->plan->title }}</p><p class="text-sm text-[#526474]">{{ $case->shift->scheduled_start_at->copy()->setTimezone($case->shift->plan->timezone)->format('D, M j · g:i A') }}</p></div>
                    <div><p class="text-xs text-[#7B8794]">Family</p><p class="font-semibold">{{ $case->shift->plan->family?->name }}</p></div>
                    <div><p class="text-xs text-[#7B8794]">Status</p><p class="font-semibold {{ $case->status==='unresolved'?'text-rose-700':'text-amber-800' }}">{{ ucfirst(str_replace('_',' ',$case->status)) }}</p><p class="text-xs text-[#7B8794]">Original: {{ $case->originalCaregiver?->name ?: 'Unknown' }}</p></div>
                    <span class="rounded-full bg-[#F7F2EA] px-3 py-1 text-xs font-semibold">Case #{{ $case->id }}</span>
                </article>
            @empty
                <p class="rounded-2xl bg-[#F7F2EA] p-6 text-center text-[#607080]">No open replacement cases.</p>
            @endforelse
        </div>
        @if($cases->hasPages())
            <div class="mt-5">{{ $cases->links() }}</div>
        @endif
    </section>

    <section class="rounded-3xl border border-[#D8D0C5] bg-white p-5">
        <h2 class="font-display text-2xl font-semibold">Notification failures</h2>
        <p class="mt-1 text-sm text-[#607080]">Coverage-specific delivery failures only. Message contents and private care details are not displayed here.</p>
        <div class="mt-4 space-y-3">
            @forelse($notificationFailures as $delivery)
                <article class="grid gap-2 rounded-2xl border border-rose-200 bg-rose-50 p-4 sm:grid-cols-[1.4fr_1fr_auto] sm:items-center">
                    <div><p class="font-semibold text-rose-950">{{ ucfirst(str_replace('_', ' ', $delivery->event_key)) }}</p><p class="text-sm text-rose-800">{{ strtoupper($delivery->channel) }} · {{ $delivery->status }}</p></div>
                    <div><p class="text-xs text-rose-700">Recipient</p><p class="font-semibold text-rose-950">{{ $delivery->user?->name ?: 'Unavailable user' }}</p></div>
                    <time class="text-sm text-rose-800" datetime="{{ $delivery->created_at?->toIso8601String() }}">{{ $delivery->created_at?->diffForHumans() }}</time>
                </article>
            @empty
                <p class="rounded-2xl bg-[#F7F2EA] p-6 text-center text-[#607080]">No Continuous Coverage notification failures.</p>
            @endforelse
        </div>
        @if($notificationFailures->hasPages())
            <div class="mt-5">{{ $notificationFailures->links() }}</div>
        @endif
    </section>

    <section class="overflow-hidden rounded-3xl border border-[#D8D0C5] bg-white">
        <div class="hidden grid-cols-[80px_1.4fr_1fr_1fr_1fr] gap-3 border-b border-[#E7E0D8] bg-[#F5F2ED] px-5 py-3 text-sm font-semibold text-[#526474] lg:grid"><span>Plan</span><span>Family</span><span>Coverage</span><span>Open gaps</span><span>Payment</span></div>
        <div class="divide-y divide-[#E7E0D8]">
            @forelse($plans as $plan)
                <article class="grid gap-3 px-5 py-4 lg:grid-cols-[80px_1.4fr_1fr_1fr_1fr] lg:items-center"><span class="font-semibold">#{{ $plan->id }}</span><div><p class="font-semibold">{{ $plan->family?->name }}</p><p class="text-sm text-[#526474]">{{ $plan->family?->email }}</p></div><div><p class="font-semibold">{{ $plan->title }}</p><p class="text-sm text-[#526474]">{{ $plan->coverage_pattern==='24_7'?'24/7':ucfirst($plan->coverage_pattern) }}</p></div><p class="font-semibold {{ $plan->uncovered_count?'text-rose-700':'text-emerald-700' }}">{{ $plan->uncovered_count }}</p><p class="font-semibold {{ $plan->payment_attention_count?'text-amber-800':'text-[#526474]' }}">{{ $plan->payment_attention_count }} attention</p></article>
            @empty
                <p class="px-5 py-10 text-center text-[#526474]">No plans matched.</p>
            @endforelse
        </div>
    </section>
    @if($plans->hasPages())
        {{ $plans->links() }}
    @endif

    <section class="rounded-3xl border border-[#D8D0C5] bg-white p-5">
        <h2 class="font-display text-2xl font-semibold">Recent audit history</h2>
        <p class="mt-1 text-sm text-[#607080]">Privacy-safe domain events for investigation and retry planning.</p>
        <div class="mt-4 space-y-2">
            @forelse($auditEvents as $event)
                <article class="grid gap-2 rounded-xl border border-[#E7E0D8] px-4 py-3 sm:grid-cols-[1.4fr_1fr_auto] sm:items-center">
                    <div><p class="font-semibold">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</p><p class="text-sm text-[#607080]">{{ $event->plan?->title }}{{ $event->shift ? ' · Shift #'.$event->shift->id : '' }}</p></div>
                    <p class="text-sm text-[#526474]">{{ $event->actor?->name ?: 'System' }}</p>
                    <time class="text-sm text-[#607080]" datetime="{{ $event->happened_at?->toIso8601String() }}">{{ $event->happened_at?->diffForHumans() }}</time>
                </article>
            @empty
                <p class="rounded-2xl bg-[#F7F2EA] p-6 text-center text-[#607080]">No Continuous Coverage audit events yet.</p>
            @endforelse
        </div>
        @if($auditEvents->hasPages())
            <div class="mt-5">{{ $auditEvents->links() }}</div>
        @endif
    </section>
</div>
