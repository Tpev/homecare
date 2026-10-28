<details class="mt-3 rounded-xl border border-slate-200 p-3">
    <summary class="cursor-pointer text-sm font-semibold">Payment details and recovery</summary>
    @if($settlement)
        <p class="mt-3 text-sm">Caregiver gross ${{ number_format($settlement->caregiver_gross_cents / 100, 2) }} ·
            Allocated package fee {{ $settlement->package_fee_cents === null ? 'pending' : '$'.number_format($settlement->package_fee_cents / 100, 2) }} ·
            Visit payment fee {{ $settlement->card_fee_cents === null ? 'pending' : '$'.number_format($settlement->card_fee_cents / 100, 2) }}</p>
    @endif
    <p class="mt-3 text-xs text-slate-600">Latest 25 operations. Use these references to reconcile the original provider event; never replace an uncertain payment.</p>
    <div class="mt-3 divide-y">
        @forelse($funding->operations as $operation)
            <div class="space-y-1 py-3 text-sm">
                <p class="font-semibold">{{ ucfirst($operation->kind) }} · ${{ number_format($operation->amount_cents / 100, 2) }} · {{ ucfirst(str_replace('_', ' ', $operation->status)) }}</p>
                <p class="break-all text-xs">Operation {{ $operation->public_id }}</p>
                @foreach(['Payment' => $operation->stripe_payment_intent_id, 'Transfer' => $operation->stripe_transfer_id, 'Refund' => $operation->stripe_refund_id, 'Source charge' => $operation->source_charge_id ?: $operation->stripe_charge_id] as $label => $reference)
                    @if($reference)<p class="break-all text-xs">{{ $label }}: {{ $reference }}</p>@endif
                @endforeach
                @if($operation->last_error)<p class="text-amber-900">{{ $operation->last_error }}</p>@endif
            </div>
        @empty
            <p class="py-2 text-sm text-slate-600">No separate payment operations.</p>
        @endforelse
    </div>
    @if($settlement || $funding->status === 'released')
        <form action="{{ route('admin.care-hours.visit.recheck', [$account, $funding]) }}" method="POST" class="mt-3 flex flex-wrap gap-3">
            @csrf
            <label class="sr-only" for="visit-recheck-{{ $funding->id }}">Reason for visit recheck</label>
            <input id="visit-recheck-{{ $funding->id }}" name="reason" required minlength="5" maxlength="500" placeholder="Reason for this check" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300">
            <button class="hc-secondary-button" type="submit">Recheck visit payments</button>
        </form>
    @endif
</details>
