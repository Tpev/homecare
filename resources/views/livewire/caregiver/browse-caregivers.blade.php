<div class="hc-page py-8 space-y-6">
    <section class="hc-hero">
        <p class="text-xs uppercase tracking-[0.2em] text-cyan-100">Find Caregivers</p>
        <h1 class="mt-2 text-2xl md:text-3xl font-display font-semibold">Find trusted non-medical caregivers</h1>
        <p class="mt-2 text-sm text-cyan-100">Filter by trust badges, skills, language, and rate to shortlist faster.</p>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)] gap-6">
        <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm h-fit xl:sticky xl:top-20 space-y-4">
            <x-input label="Search name, city, bio" placeholder="Example: Raleigh companionship" wire:model.live.debounce.300ms="search" />
            <x-input label="ZIP" wire:model.live="zip" />

            <div class="grid grid-cols-2 gap-3">
                <x-input type="number" label="Min rate" wire:model.live="rate_min" />
                <x-input type="number" label="Max rate" wire:model.live="rate_max" />
            </div>

            <x-select.styled wire:model.live="skills" multiple label="Skills"
                :options="$skillOptions->map(fn($s)=>['label'=>$s->name,'value'=>$s->id])->values()->all()" />

            <x-select.styled wire:model.live="languages" multiple label="Languages"
                :options="$languageOptions->map(fn($l)=>['label'=>$l->name,'value'=>$l->id])->values()->all()" />

            <x-select.styled wire:model.live="trust" label="Trust badges"
                :options="[
                    ['label'=>'All caregivers','value'=>'all'],
                    ['label'=>'Verified only','value'=>'verified'],
                    ['label'=>'Top Caregiver only','value'=>'top'],
                ]" />

            <x-select.styled wire:model.live="sort" label="Sort"
                :options="[
                    ['label'=>'Relevance','value'=>'relevance'],
                    ['label'=>'Top caregivers first','value'=>'top'],
                    ['label'=>'Reliability high-low','value'=>'reliability'],
                    ['label'=>'Price low-high','value'=>'price_low'],
                    ['label'=>'Price high-low','value'=>'price_high'],
                    ['label'=>'Experience high-low','value'=>'experience'],
                ]" />
        </aside>

        <section class="space-y-4 min-w-0">
            <div class="flex items-center justify-between">
                <p class="text-sm text-slate-600">{{ $caregivers->total() }} caregiver(s) found</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($caregivers as $c)
                    @php
                        $photoUrl = $c->profile_photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->profile_photo_path) : null;
                        $nameParts = preg_split('/\s+/', trim((string) $c->user->name));
                        $initials = collect($nameParts)->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                    @endphp

                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}" alt="{{ $c->user->name }}" class="h-14 w-14 rounded-full object-cover border border-slate-200">
                            @else
                                <div class="h-14 w-14 rounded-full bg-cyan-100 text-cyan-700 font-semibold flex items-center justify-center border border-cyan-200">
                                    {{ $initials }}
                                </div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <h3 class="font-display text-lg font-semibold text-slate-900 truncate">{{ $c->user->name }}</h3>
                                <p class="text-sm text-slate-600">{{ $c->user->city }}, {{ $c->user->state }}</p>
                        <div class="mt-2 flex items-center gap-3 text-sm">
                            <p class="font-semibold text-slate-900">${{ number_format((float) $c->hourly_rate, 2) }}/hr</p>
                            <p class="text-slate-600">⭐ {{ number_format((float) $c->average_rating, 1) }} ({{ $c->reviews_count }})</p>
                        </div>
                        @if (! is_null($c->invite_response_rate))
                            <p class="mt-1 text-xs text-slate-500">Response score {{ number_format((float) $c->invite_response_rate, 0) }}%</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-500">Reliability {{ number_format((float) $c->reliability_score, 0) }}%</p>
                    </div>
                </div>

                        <p class="mt-3 text-sm text-slate-700">{{ \Illuminate\Support\Str::limit((string) $c->bio, 140) }}</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($c->hasIdentityVerifiedBadge())
                                <x-badge color="cyan" text="Identity verified" />
                            @endif
                            @if ($c->hasBackgroundCheckBadge())
                                <x-badge color="green" text="Background check" />
                            @endif
                            @if ($c->hasTopCaregiverBadge())
                                <x-badge color="amber" text="Top Caregiver" />
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($c->skills->take(3) as $skill)
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ $skill->name }}</span>
                            @endforeach
                            @if ($c->skills->count() > 3)
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500">+{{ $c->skills->count() - 3 }} more</span>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">{{ $c->is_accepting_new_clients ? 'Accepting new clients' : 'Currently not accepting new clients' }}</p>
                            <a class="hc-link whitespace-nowrap" href="{{ route('caregivers.show', $c->slug) }}" wire:navigate>View full profile</a>
                        </div>
                    </article>
                @empty
                    <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-sm text-slate-600">No caregiver matches these filters yet.</p>
                    </div>
                @endforelse
            </div>

            <div>{{ $caregivers->links() }}</div>
        </section>
    </div>
</div>
