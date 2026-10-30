<x-app-layout><div class="hc-page space-y-6 py-8">
    @isset($detail)
        @php
            $purchase = $detail['purchase'];
            $gift = $purchase->gift;
            $claimant = $detail['claimant'];
            $balance = $detail['balance'];
            $visits = $detail['visits'];
            $family = $purchase->account?->familyAccount;
            $claimed = (bool) $gift->claimed_at;
            $claimState = $claimed ? 'Claimed' : ($purchase->status === 'paid' && $gift->ready_at ? 'Awaiting claim' : ucfirst(str_replace('_', ' ', $purchase->status)));
            $remainingMinutes = $balance['available_minutes'] + $balance['reserved_minutes'] + $balance['held_minutes'];
            $formatTime = fn ($time) => $time?->copy()->timezone($detail['timezone'])->format('M j, Y g:i:s A T');
        @endphp
        <a href="{{ route('admin.care-hours.gifts.index') }}" class="hc-care-text-link">← Gift purchases</a>
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-[#485E53]">Gift details</p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-[#23483F]">{{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }} of care</h1>
                <p class="mt-2 text-[#485E53]">${{ number_format($purchase->amount_cents / 100, 2) }} {{ strtoupper($purchase->currency) }} · {{ $purchase->buyer_name }}</p>
                <p class="mt-2 break-all text-xs text-slate-500">{{ $purchase->public_id }}</p>
            </div>
            <span class="rounded-full bg-[#EAF0E5] px-4 py-2 text-sm font-semibold text-[#23483F]">{{ $claimState }}</span>
        </header>
        @include('care-hours.partials.messages')

        @if($detail['balance_issue'])
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950" role="status">{{ $detail['balance_issue'] }}</p>
        @elseif($detail['lot_status'] === 'reversed')
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">{{ \App\Models\CareHourAccount::formatMinutes($balance['reversed_minutes']) }} were reversed. These hours are no longer available.</p>
        @elseif(in_array($purchase->status, ['held', 'recovery', 'correction_pending', 'corrected'], true))
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">Payment status: {{ ucfirst(str_replace('_', ' ', $purchase->status)) }}. Review the payment before confirming that this gift can be used.</p>
        @endif

        <section aria-labelledby="gift-hours-heading" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="gift-hours-heading" class="font-display text-xl font-semibold">Hours from this gift</h2>
                    <p class="mt-1 text-sm text-[#485E53]">
                        {{ \App\Models\CareHourAccount::formatMinutes($balance['issued_minutes']) }} credited
                        @if($claimed)
                            · {{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }} paid + {{ \App\Models\CareHourAccount::formatMinutes($purchase->bonus_minutes) }} welcome bonus
                        @endif
                    </p>
                </div>
                @if($claimed)
                    <p class="text-sm text-[#485E53]"><strong class="text-[#23483F]">{{ \App\Models\CareHourAccount::formatMinutes($remainingMinutes) }} remaining</strong> · includes reserved and held hours</p>
                @elseif($purchase->status === 'paid' && $gift->ready_at)
                    <p class="text-sm text-[#485E53]"><strong class="text-[#23483F]">{{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }} awaiting claim</strong></p>
                @else
                    <p class="text-sm text-[#485E53]"><strong class="text-[#23483F]">Not credited yet</strong></p>
                @endif
            </div>
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach(['available_minutes' => 'Available', 'reserved_minutes' => 'Reserved', 'used_minutes' => 'Used', 'held_minutes' => 'On hold'] as $key => $label)
                    <div class="hc-surface p-5">
                        <dt class="text-sm text-[#485E53]">{{ $label }}</dt>
                        <dd class="mt-2 font-display text-3xl font-semibold text-[#23483F]">{{ \App\Models\CareHourAccount::formatMinutes($balance[$key]) }}</dd>
                    </div>
                @endforeach
            </dl>
            @unless($claimed)<p class="text-sm text-[#485E53]">No hours have been credited yet. Any welcome bonus is confirmed when the gift is claimed.</p>@endunless
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="hc-surface p-5 sm:p-6" aria-labelledby="gift-buyer-heading">
                <h2 id="gift-buyer-heading" class="font-display text-xl font-semibold">Buyer</h2>
                <p class="mt-4 font-semibold">{{ $purchase->buyer_name }}</p>
                <p class="mt-1 break-all text-sm text-[#485E53]">{{ $purchase->buyer_email }}</p>
                <dl class="mt-5 space-y-3 border-t border-[#D8E1D7] pt-4 text-sm">
                    <div><dt class="text-[#485E53]">Purchased</dt><dd class="mt-1">{{ $formatTime($purchase->created_at) }}</dd></div>
                    <div><dt class="text-[#485E53]">Payment confirmed</dt><dd class="mt-1">{{ $formatTime($purchase->paid_at) ?: 'Not confirmed' }}</dd></div>
                </dl>
            </section>
            <section class="hc-surface p-5 sm:p-6" aria-labelledby="gift-claim-heading">
                <h2 id="gift-claim-heading" class="font-display text-xl font-semibold">Claim</h2>
                @if($claimed)
                    <dl class="mt-4 space-y-4 text-sm">
                        <div>
                            <dt class="text-[#485E53]">Claimed by</dt>
                            <dd class="mt-1 font-semibold">{{ $claimant?->name ?: ($gift->claimed_by_user_id ? 'User #'.$gift->claimed_by_user_id.' (account unavailable)' : 'Account unavailable') }}</dd>
                            @if($claimant)<dd class="mt-1 break-all text-[#485E53]">{{ $claimant->email }}</dd>@endif
                        </div>
                        <div><dt class="text-[#485E53]">Claimed at</dt><dd class="mt-1 font-semibold"><time datetime="{{ $gift->claimed_at->copy()->timezone($detail['timezone'])->toIso8601String() }}">{{ $formatTime($gift->claimed_at) }}</time></dd><dd class="mt-1 text-xs text-slate-500">{{ $detail['timezone'] }}</dd></div>
                        <div class="border-t border-[#D8E1D7] pt-4">
                            <dt class="text-[#485E53]">Receiving family account</dt>
                            @if($family)
                                <dd class="mt-1"><a href="{{ route('admin.care-hours.show', $purchase->account) }}" class="font-semibold underline">{{ $family->owner?->name ?: 'Family account' }} · Family #{{ $family->id }}</a></dd>
                                @if($family->owner)<dd class="mt-1 break-all text-[#485E53]">{{ $family->owner->email }}</dd>@endif
                            @else
                                <dd class="mt-1">Family account unavailable</dd>
                            @endif
                        </div>
                    </dl>
                @else
                    <p class="mt-4 font-semibold">Not claimed</p>
                    <p class="mt-2 text-sm text-[#485E53]">No recipient is recorded until this gift is claimed.</p>
                    <p class="mt-2 text-sm text-[#485E53]">The buyer shares the private code. When claimed, the hours go into the signed-in person’s family balance.</p>
                @endif
            </section>
        </div>

        <section class="hc-surface p-5 sm:p-6" aria-labelledby="gift-visits-heading">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h2 id="gift-visits-heading" class="font-display text-xl font-semibold">Visits funded by this gift</h2><p class="mt-1 text-sm text-[#485E53]">Only hours from this gift are counted here.</p></div>
                <span class="text-sm text-[#485E53]">{{ $visits->total() }} {{ $visits->total() === 1 ? 'visit' : 'visits' }}</span>
            </div>
            <div class="mt-4 divide-y divide-[#D8E1D7]">
                @forelse($visits as $visit)
                    @php
                        $booking = $visit['booking'];
                        $recipientName = $booking?->careRequest?->recipient?->full_name ?: data_get($booking?->carePlan?->recipient_snapshot, 'full_name', 'Care recipient unavailable');
                    @endphp
                    <article class="space-y-4 py-5" aria-label="Visit {{ $visit['booking_id'] }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold">Visit #{{ $visit['booking_id'] }} · {{ $recipientName }}</h3>
                                @if($booking?->scheduled_start_at)
                                    <p class="mt-1 text-sm text-[#485E53]">{{ $booking->scheduled_start_at->copy()->timezone($visit['timezone'])->format('M j, Y g:i A T') }}@if($booking->scheduled_end_at) – {{ $booking->scheduled_end_at->copy()->timezone($visit['timezone'])->format('M j, Y g:i A T') }}@endif</p>
                                @else
                                    <p class="mt-1 text-sm text-[#485E53]">Visit date unavailable</p>
                                @endif
                                <p class="mt-1 text-sm text-[#485E53]">Caregiver: {{ $booking?->caregiver?->name ?: 'Unavailable' }}</p>
                            </div>
                            <span class="rounded-full bg-[#F2F3ED] px-3 py-1 text-xs font-semibold">{{ $booking ? ucfirst(str_replace('_', ' ', $booking->status)) : 'Record unavailable' }}</span>
                        </div>
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                                @foreach(['used_minutes' => 'Used', 'reserved_minutes' => 'Reserved', 'returned_minutes' => 'Returned over time'] as $key => $label)
                                    <div><dt class="text-[#485E53]">{{ $label }}</dt><dd class="mt-1 font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($visit[$key]) }}</dd></div>
                                @endforeach
                            </dl>
                            @if($booking?->careRequest)<a href="{{ route('admin.requests.show', $booking->care_request_id) }}" class="hc-secondary-button">View care request</a>@endif
                        </div>
                    </article>
                @empty
                    <p class="py-6 text-sm text-[#485E53]">No visits have used or reserved this gift’s hours.</p>
                @endforelse
            </div>
            @if($visits->total() > 0)<p class="border-t border-[#D8E1D7] pt-4 text-xs text-[#485E53]">Returned hours are a history of cancellations and corrections. The same hours can be returned more than once; they are not extra credit.</p>@endif
            <div class="mt-4">{{ $visits->links() }}</div>
        </section>

        <details class="hc-surface p-5 sm:p-6">
            <summary class="cursor-pointer font-semibold">Payment details and review</summary>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-[#485E53]">Payment status</dt><dd class="mt-1 font-semibold">{{ ucfirst(str_replace('_', ' ', $purchase->status)) }}</dd></div>
                <div><dt class="text-[#485E53]">Processing fee</dt><dd class="mt-1 font-semibold">{{ $purchase->processing_fee_cents === null ? 'Awaiting confirmation' : '$'.number_format($purchase->processing_fee_cents / 100, 2).' '.strtoupper($purchase->currency) }}</dd></div>
            </dl>
            @if($check = $detail['lastCheck'])<p class="mt-4 text-sm text-[#485E53]">Last admin check: {{ \Illuminate\Support\Carbon::parse($check->created_at)->timezone($detail['timezone'])->format('M j, Y g:i:s A T') }} · {{ $check->reason }}</p>@endif
            <form action="{{ route('admin.care-hours.gifts.recheck', $purchase->public_id) }}" method="POST" class="mt-5 space-y-3 border-t border-[#D8E1D7] pt-4">
                @csrf
                <label for="gift-review-reason" class="block text-sm font-semibold">Reason for payment check</label>
                <input id="gift-review-reason" name="reason" required minlength="5" maxlength="500" placeholder="Include the support reference" class="min-h-12 w-full rounded-xl border-slate-300">
                <p class="text-xs text-[#485E53]">This records your review and reconciles the payment with the provider.</p>
                <button type="submit" class="hc-secondary-button">Recheck gift payment</button>
            </form>
        </details>
    @else
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 class="font-display text-3xl font-semibold">Care-hour gifts</h1><a href="{{ route('admin.care-hours.index') }}" class="hc-secondary-button">Family balances</a></div>
    @include('care-hours.partials.messages')
    <form method="GET" class="flex flex-wrap gap-3">
        <label class="sr-only" for="gift-search">Purchase reference or buyer email</label><input id="gift-search" name="search" value="{{ $search }}" placeholder="Purchase reference or buyer email" class="min-h-12 min-w-0 flex-1 rounded-xl border-slate-300">
        <label class="sr-only" for="gift-state">Gift status</label><select id="gift-state" name="state" class="min-h-12 rounded-xl border-slate-300">@foreach(['all' => 'All gifts', 'unclaimed' => 'Awaiting claim', 'claimed' => 'Claimed', 'attention' => 'Needs attention'] as $value => $label)<option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>@endforeach</select>
        <button type="submit" class="hc-primary-button">Search</button>
    </form>
    <div class="space-y-4">
        @forelse($gifts as $purchase)
            <article class="hc-surface space-y-4 p-5">
                <div class="flex flex-wrap justify-between gap-4"><div><h2 class="font-semibold">{{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }} gift · ${{ number_format($purchase->amount_cents / 100, 2) }}</h2><p class="mt-1 text-sm">{{ $purchase->buyer_name }} · {{ $purchase->buyer_email }}</p></div><p class="font-semibold">{{ $purchase->gift->claimed_at ? 'Claimed' : ($purchase->status === 'paid' && $purchase->gift->ready_at ? 'Awaiting claim' : ucfirst(str_replace('_', ' ', $purchase->status))) }}</p></div>
                <p class="break-all text-xs text-slate-600">{{ $purchase->public_id }} · {{ $purchase->created_at->format('M j, Y g:i A') }}</p>
                <a href="{{ route('admin.care-hours.gifts.show', $purchase->public_id) }}" class="hc-secondary-button">View gift</a>
                @if($purchase->gift->claimed_at && $purchase->status !== 'issued')<p class="text-sm font-semibold text-amber-800">Payment status: {{ ucfirst(str_replace('_', ' ', $purchase->status)) }}</p>@endif
                @if($purchase->account)<a href="{{ route('admin.care-hours.show', $purchase->account) }}" class="text-sm font-semibold underline">{{ $purchase->account->familyAccount?->owner?->name ?? 'Family account' }} · {{ \App\Models\CareHourAccount::formatMinutes($purchase->total_minutes) }} added</a>@endif
                <p class="text-sm text-slate-600">Processing fee: {{ $purchase->processing_fee_cents === null ? 'Awaiting confirmation' : '$'.number_format($purchase->processing_fee_cents / 100, 2) }}</p>
                @if($check = $lastChecks->get($purchase->id))<p class="text-sm text-slate-600">Last check: {{ \Illuminate\Support\Carbon::parse($check->created_at)->format('M j, Y g:i A') }} · {{ $check->reason }}</p>@endif
                <details><summary class="cursor-pointer text-sm font-semibold">Payment review</summary><form action="{{ route('admin.care-hours.gifts.recheck', $purchase->public_id) }}" method="POST" class="mt-3 flex flex-wrap gap-3">@csrf<label class="sr-only" for="gift-reason-{{ $purchase->id }}">Reason for payment check</label><input id="gift-reason-{{ $purchase->id }}" name="reason" required minlength="5" maxlength="500" placeholder="Reason for this check" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300"><button type="submit" class="hc-secondary-button">Recheck gift payment</button></form></details>
            </article>
        @empty<p class="hc-surface p-5">No gifts match these filters.</p>@endforelse
    </div>{{ $gifts->links() }}
    @endisset
</div></x-app-layout>
