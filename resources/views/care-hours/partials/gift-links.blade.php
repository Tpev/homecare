<div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
    @if(app(\App\Services\CareHours\CareHourAccess::class)->allows(auth()->user()))
        <a href="{{ route('care-hours.claim') }}" class="inline-flex min-h-11 items-center font-semibold underline">Have a gift code?</a>
    @endif
    @if(app(\App\Services\CareHours\CareHourAccess::class)->canBuyGift(auth()->user()))
        <a href="{{ route('care-hours.gifts.index') }}" class="inline-flex min-h-11 items-center font-semibold underline">Buy a gift of care</a>
    @endif
</div>
