@props(['lead'])

@php($additionalServices = $lead->additionalServices())

@if ($additionalServices !== [])
    <section {{ $attributes->class(['min-w-0 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4']) }}>
        <h3 class="text-sm font-bold text-slate-950">Additional services of interest</h3>
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($additionalServices as $service)
                <li class="max-w-full whitespace-normal break-words rounded-xl border border-emerald-100 bg-white px-3 py-2 text-sm text-emerald-900">{{ $service }}</li>
            @endforeach
        </ul>
    </section>
@endif
