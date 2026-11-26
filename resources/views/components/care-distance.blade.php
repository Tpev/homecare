@props(['miles' => null])
<span {{ $attributes->class(['inline-flex rounded-full bg-[#E8F2EC] px-3 py-1 text-xs font-semibold text-[#23483F]']) }}>
    {{ $miles === null ? 'Distance unavailable' : ($miles < 1 ? 'Less than 1 mile away' : 'About '.number_format($miles, 0).' '.\Illuminate\Support\Str::plural('mile', (int) round($miles)).' away') }}
</span>
