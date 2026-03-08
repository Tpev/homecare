<div class="max-w-7xl mx-auto py-8 grid grid-cols-1 lg:grid-cols-4 gap-6">
    <x-card class="lg:col-span-1">
        <x-input label="ZIP" wire:model.live="zip" />
        <x-input type="number" label="Min rate" wire:model.live="rate_min" />
        <x-input type="number" label="Max rate" wire:model.live="rate_max" />

        <x-select.styled wire:model.live="skills" multiple label="Skills"
            :options="$skillOptions->map(fn($s)=>['label'=>$s->name,'value'=>$s->id])->values()->all()" />

        <x-select.styled wire:model.live="languages" multiple label="Languages"
            :options="$languageOptions->map(fn($l)=>['label'=>$l->name,'value'=>$l->id])->values()->all()" />

        <x-select.styled wire:model.live="sort" label="Sort"
            :options="[
                ['label'=>'Relevance','value'=>'relevance'],
                ['label'=>'Price low-high','value'=>'price_low'],
                ['label'=>'Price high-low','value'=>'price_high'],
                ['label'=>'Experience high-low','value'=>'experience'],
            ]" />
    </x-card>

    <div class="lg:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($caregivers as $c)
            <x-card>
                <h3 class="font-semibold">{{ $c->user->name }}</h3>
                <p class="text-sm text-slate-600">{{ $c->user->city }}, {{ $c->user->state }}</p>
                <p class="mt-2">${{ $c->hourly_rate }}/hr</p>
                <p class="text-sm">⭐ {{ number_format($c->average_rating, 1) }} ({{ $c->reviews_count }})</p>
                <a class="underline text-sm" href="{{ route('caregivers.show', $c->slug) }}">View profile</a>
            </x-card>
        @endforeach

        <div class="md:col-span-2">{{ $caregivers->links() }}</div>
    </div>
</div>
