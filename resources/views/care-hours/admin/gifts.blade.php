<x-app-layout><div class="hc-page space-y-6 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 class="font-display text-3xl font-semibold">Care-hour gifts</h1><a href="{{ route('admin.care-hours.index') }}" class="hc-secondary-button">Recipient balances</a></div>
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
                @if($purchase->gift->claimed_at && $purchase->status !== 'issued')<p class="text-sm font-semibold text-amber-800">Payment status: {{ ucfirst(str_replace('_', ' ', $purchase->status)) }}</p>@endif
                @if($purchase->account)<a href="{{ route('admin.care-hours.show', $purchase->account) }}" class="text-sm font-semibold underline">{{ $purchase->account->display_name }} · {{ \App\Models\CareHourAccount::formatMinutes($purchase->total_minutes) }} added</a>@endif
                <p class="text-sm text-slate-600">Processing fee: {{ $purchase->processing_fee_cents === null ? 'Awaiting confirmation' : '$'.number_format($purchase->processing_fee_cents / 100, 2) }}</p>
                @if($check = $lastChecks->get($purchase->id))<p class="text-sm text-slate-600">Last check: {{ \Illuminate\Support\Carbon::parse($check->created_at)->format('M j, Y g:i A') }} · {{ $check->reason }}</p>@endif
                <details><summary class="cursor-pointer text-sm font-semibold">Payment review</summary><form action="{{ route('admin.care-hours.gifts.recheck', $purchase->public_id) }}" method="POST" class="mt-3 flex flex-wrap gap-3">@csrf<label class="sr-only" for="gift-reason-{{ $purchase->id }}">Reason for payment check</label><input id="gift-reason-{{ $purchase->id }}" name="reason" required minlength="5" maxlength="500" placeholder="Reason for this check" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300"><button type="submit" class="hc-secondary-button">Recheck gift payment</button></form></details>
            </article>
        @empty<p class="hc-surface p-5">No gifts match these filters.</p>@endforelse
    </div>{{ $gifts->links() }}
</div></x-app-layout>
