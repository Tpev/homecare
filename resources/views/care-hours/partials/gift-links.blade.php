<div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
    <a href="{{ route('care-hours.claim') }}" class="inline-flex min-h-11 items-center font-semibold underline">Have a gift code?</a>
    @if(config('care_hours.enabled') && config('care_hours.sales_enabled') && config('care_hours.gifts_enabled'))
        <a href="{{ route('care-hours.gifts.index') }}" class="inline-flex min-h-11 items-center font-semibold underline">Buy a gift of care</a>
    @endif
</div>
