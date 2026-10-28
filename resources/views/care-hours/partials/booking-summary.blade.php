@if($prepaidBookingSummary)
    @php($careSettlement = $prepaidBookingSummary['settlement'] ?? null)
    @php($carePaymentUnderReview = $prepaidBookingSummary['payment_under_review'] ?? false)
    <section class="rounded-xl border border-[#D8E1D7] bg-[#F2F8F4] p-4 text-sm" aria-label="Care-hour payment">
        <p class="font-semibold">{{ $carePaymentUnderReview ? 'Payment under review' : ($careSettlement ? 'Paid with care hours' : ($prepaidBookingSummary['status'] === 'released' ? 'Reserved hours returned' : 'Using care hours')) }}</p>
        @if($carePaymentUnderReview)
            <p class="mt-1">LoLo needs to review this payment.</p>
            <a href="{{ route('support.index') }}" wire:navigate class="hc-care-text-link mt-3 inline-block">Contact support</a>
        @else
        @if($careSettlement)
            <p class="mt-1">{{ \App\Models\CareHourAccount::formatMinutes($careSettlement['prepaid_minutes']) }} used · {{ \App\Models\CareHourAccount::formatMinutes($prepaidBookingSummary['available_minutes']) }} still available.</p>
            @if($careSettlement['card_paid_cents'] > 0)<p class="mt-1">${{ number_format($careSettlement['card_paid_cents'] / 100, 2) }} paid separately for uncovered time.</p>@endif
            @if($careSettlement['amount_due_cents'] > 0)<p class="mt-2 font-semibold">${{ number_format($careSettlement['amount_due_cents'] / 100, 2) }} still due for additional care.</p><form action="{{ route('family.care-hours.shortfall', $booking) }}" method="POST" class="mt-3">@csrf<button type="submit" class="hc-primary-button">Pay ${{ number_format($careSettlement['amount_due_cents'] / 100, 2) }}</button></form>@endif
        @elseif($prepaidBookingSummary['status'] !== 'released')<p class="mt-1">{{ \App\Models\CareHourAccount::formatMinutes($prepaidBookingSummary['reserved_minutes']) }} reserved for this visit.</p>@endif
        @if(! $careSettlement && ! $prepaidBookingSummary['ready'] && $prepaidBookingSummary['shortfall_cents'] > 0 && $prepaidBookingSummary['status'] !== 'released')
            <p class="mt-2">{{ \App\Models\CareHourAccount::formatMinutes($prepaidBookingSummary['shortfall_minutes']) }} still needs payment · ${{ number_format($prepaidBookingSummary['shortfall_cents'] / 100, 2) }}.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <form action="{{ route('family.care-hours.shortfall', $booking) }}" method="POST">@csrf<button type="submit" class="hc-primary-button">Pay ${{ number_format($prepaidBookingSummary['shortfall_cents'] / 100, 2) }}</button></form>
                @if(config('care_hours.enabled') && config('care_hours.sales_enabled'))<a href="{{ route('family.care-hours.packages', $prepaidBookingSummary['account_id']) }}" wire:navigate class="hc-secondary-button">Buy more hours</a>@endif
                <form action="{{ route('family.care-hours.coverage', $booking) }}" method="POST">@csrf<button type="submit" class="hc-care-text-link">Check coverage</button></form>
            </div><p class="mt-2 text-xs">You can pay this amount without saving a card.</p>
        @elseif(! $careSettlement && $prepaidBookingSummary['ready'] && $prepaidBookingSummary['status'] !== 'released')
            <p class="mt-1">Scheduled care is covered.@if($prepaidBookingSummary['shortfall_cents'] > 0) Includes a separate ${{ number_format($prepaidBookingSummary['shortfall_cents'] / 100, 2) }} payment for uncovered time.@endif</p>
        @endif
        @endif
    </section>
@endif
