<form action="{{ $checkoutUrl }}" method="POST" class="space-y-5">
    @csrf
    <input type="hidden" name="request_key" value="{{ $requestKey }}">
    <fieldset><legend class="sr-only">Choose a care package</legend><div class="grid gap-3 sm:grid-cols-2">
        @foreach($packages as $package)
            <input type="hidden" name="quotes[{{ $package['paid_hours'] }}]" value="{{ $package['quote_key'] }}">
            <label class="hc-surface flex cursor-pointer items-start gap-3 p-5 has-[:checked]:border-emerald-800 has-[:checked]:ring-1 has-[:checked]:ring-emerald-800">
                <input type="radio" name="hours" value="{{ $package['paid_hours'] }}" required @checked((int) old('hours') === $package['paid_hours']) class="mt-1 border-[#D8E1D7] text-emerald-800">
                <span class="flex-1"><span class="block font-display text-2xl font-semibold">{{ $package['paid_hours'] }} hours</span>@if($package['bonus_minutes'] > 0)<span class="block text-sm font-semibold text-emerald-800">+ {{ \App\Models\CareHourAccount::formatMinutes($package['bonus_minutes']) }} free</span>@endif<span class="mt-3 block text-2xl font-semibold">${{ number_format($package['amount_cents'] / 100, 0) }}</span><span class="mt-1 block text-sm text-[#485E53]">{{ \App\Models\CareHourAccount::formatMinutes($package['total_minutes']) }} of care in total</span></span>
            </label>
        @endforeach
    </div></fieldset>
    @if(collect($packages)->contains(fn ($package) => $package['bonus_minutes'] > 0))<p class="text-sm text-[#485E53]">The welcome bonus applies once per care recipient, on their first 10-, 20-, or 40-hour package. A 5-hour purchase keeps them eligible.</p>@endif
    @if(collect($packages)->contains(fn ($package) => $package['welcome_pending'] ?? false))<p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-950">A welcome-bonus purchase is already processing for this person. Wait for its result before buying another package.</p>@endif
    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-[#D8E1D7]"><span>I agree this purchase is non-refundable. Paid and free hours never expire.</span></label>
    <button type="submit" class="hc-primary-button w-full sm:w-auto">Continue to payment</button>
</form>
<p class="text-sm text-[#485E53]">Care hours cover standard individual visits, including the hourly platform fee, even if prices increase. Shared care, continuous coverage and custom-price arrangements are excluded. Cancelled visits and no-shows return reserved hours to the care balance.</p>
