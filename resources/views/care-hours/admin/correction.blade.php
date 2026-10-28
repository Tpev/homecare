@if($purchase->status === 'recovery' && $purchase->paid_at && ! $purchase->issued_at && $purchase->stripe_charge_id && in_array($purchase->correction_state, [null, 'failed'], true))
    <details class="rounded-lg border border-amber-300 bg-amber-50 p-3">
        <summary class="cursor-pointer text-sm font-semibold text-amber-950">Correct an invalid paid purchase</summary>
        <p class="mt-2 text-sm text-amber-950">Only for a payment that cannot be fulfilled and has never issued care hours. Valid unused packages cannot be refunded.</p>
        <form action="{{ route('admin.care-hours.correct', [$account, $purchase]) }}" method="POST" class="mt-3 space-y-3">@csrf
            <label class="block text-sm font-semibold" for="correction-reason-{{ $purchase->id }}">Reason and support reference</label>
            <textarea id="correction-reason-{{ $purchase->id }}" name="reason" required minlength="10" maxlength="1000" rows="2" class="w-full rounded-lg border-amber-300"></textarea>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I verified that this paid purchase is invalid and no hours were issued.</span></label>
            <button type="submit" class="hc-secondary-button">{{ $purchase->correction_state === 'failed' ? 'Retry failed correction' : 'Reverse invalid purchase' }}</button>
        </form>
    </details>
@endif
@if($purchase->correction_state)<p class="text-sm text-slate-600">Billing correction: {{ $purchase->correction_state }}@if($purchase->correction_refund_id) · {{ $purchase->correction_refund_id }}@endif</p>@endif
@if($purchase->correction_state === 'pending')<p class="text-sm text-amber-900">The correction result is pending. Use “Recheck provider status” to reconcile the existing request.</p>@endif
