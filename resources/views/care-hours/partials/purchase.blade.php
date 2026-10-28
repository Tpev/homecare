@php
    $issued = (bool) $purchase->issued_at || $purchase->status === 'issued';
    $failed = in_array($purchase->status, ['failed', 'expired', 'cancelled'], true);
    $held = in_array($purchase->status, ['held', 'recovery', 'disputed', 'reversed', 'refunded', 'needs_review'], true);
    $corrected = $purchase->correction_state === 'succeeded';
    $receiptRecipientName = $purchase->contributionLink?->display_name ?? $purchase->account?->display_name ?? 'your chosen recipient';
@endphp
<h1 class="font-display text-3xl font-semibold text-[#23483F]">{{ $corrected ? 'Billing correction completed' : ($held ? 'Purchase needs attention' : ($issued ? 'Your care hours are ready' : ($failed ? 'Payment not completed' : 'Payment processing'))) }}</h1>
@include('care-hours.partials.messages')
<p class="text-[#485E53]">@if($corrected)This invalid payment was reversed. No care hours were issued.@elseif($purchase->correction_state === 'pending')A correction of this invalid payment is being confirmed. No care hours were issued.@elseif($held)This purchase is being reviewed. Contact LoLo support for help.@elseif($issued){{ \App\Models\CareHourAccount::formatMinutes($purchase->total_minutes) }} added for {{ $receiptRecipientName }}.@elseif($failed)No hours were added for this payment attempt.@elseWe are confirming your payment. Hours become available after it succeeds. You can safely return to this receipt; do not pay again.@endif</p>
<section class="hc-surface p-5"><h2 class="font-semibold">Purchase receipt</h2><dl class="mt-4 space-y-3 text-sm">
    <div class="flex justify-between gap-4"><dt>Reference</dt><dd class="break-all text-right">{{ $purchase->public_id }}</dd></div>
    <div class="flex justify-between gap-4"><dt>Paid hours</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($purchase->paid_minutes) }}</dd></div>
    @if($purchase->bonus_minutes > 0)<div class="flex justify-between gap-4"><dt>Free welcome hours</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($purchase->bonus_minutes) }}</dd></div>@endif
    <div class="flex justify-between gap-4"><dt>Total care time</dt><dd>{{ \App\Models\CareHourAccount::formatMinutes($purchase->total_minutes) }}</dd></div>
    <div class="flex justify-between gap-4 border-t border-[#D8E1D7] pt-3 font-semibold"><dt>{{ $corrected ? 'Payment reversed' : ($issued ? 'Paid' : 'Purchase total') }}</dt><dd>${{ number_format($purchase->amount_cents / 100, 2) }} {{ strtoupper($purchase->currency) }}</dd></div>
</dl></section>
@unless($issued || $failed || $held)<div class="flex flex-wrap gap-3">@if($purchase->checkout_url && ! $purchase->paid_at)<a href="{{ $purchase->checkout_url }}" class="hc-primary-button">Continue payment</a>@endif<a href="{{ request()->url() }}" class="hc-secondary-button">Check payment status</a></div>@endunless
<p class="text-sm text-[#485E53]">Non-refundable · No expiry. Paid and free hours follow the same rules. Each hour covers eligible care and the hourly platform fee, even if standard prices increase.</p>
