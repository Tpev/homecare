@if(app(\App\Services\CareHours\CareHourNavigation::class)->visible(auth()->user()))
    <a href="{{ route('family.care-hours.index') }}" wire:navigate {{ $attributes->class(['inline-flex min-h-11 items-center text-sm font-semibold text-[#485E53] underline underline-offset-4']) }}>Prepaid care hours</a>
@endif
