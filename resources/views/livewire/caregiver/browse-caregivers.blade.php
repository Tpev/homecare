<div class="hc-page py-8 space-y-6">
    @if (!empty($prelaunchMode))
        <x-alert color="yellow">
            Caregiver marketplace is in pre-launch mode. Profiles are not publicly available yet.
        </x-alert>
    @endif

    <section class="hc-hero">
        <p class="text-xs uppercase tracking-[0.2em] text-[#CFC6F7]">Find Caregivers</p>
        <h1 class="mt-2 text-2xl md:text-3xl font-display font-semibold text-[#FFF8F0]">Find trusted non-medical caregivers</h1>
        <p class="mt-2 text-sm text-[#CFC6F7]">Filter by trust badges, skills, language, and experience to shortlist faster.</p>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
        <aside class="h-fit rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm lg:sticky lg:top-20">
            <form wire:submit.prevent="applyFilters" class="grid gap-4 sm:grid-cols-2 lg:block lg:space-y-4">
                <div>
                    <label for="caregiver-search" class="block text-sm font-medium text-[#324457]">Search name, city, bio</label>
                    <input
                        id="caregiver-search"
                        type="search"
                        placeholder="Example: Raleigh companionship"
                        autocomplete="off"
                        wire:model="search"
                        class="mt-1 block min-h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 py-2 text-base text-[#17313F] shadow-sm transition placeholder:text-[#8A98A8] focus:border-[#4F6FAF] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/20"
                    >
                </div>

                <div>
                    <label for="caregiver-zip" class="block text-sm font-medium text-[#324457]">ZIP</label>
                    <input
                        id="caregiver-zip"
                        type="text"
                        inputmode="numeric"
                        autocomplete="postal-code"
                        wire:model="zip"
                        class="mt-1 block min-h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 py-2 text-base text-[#17313F] shadow-sm transition placeholder:text-[#8A98A8] focus:border-[#4F6FAF] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/20"
                    >
                </div>

                <div>
                    <p class="text-sm font-medium text-[#324457]">Skills</p>
                    <div class="mt-2 grid max-h-40 gap-1 overflow-y-auto rounded-xl border border-[#DED6CA] bg-[#FFFCF8] p-2 sm:grid-cols-2 lg:grid-cols-1">
                        @foreach ($skillOptions as $skill)
                            <label class="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-[#324457] hover:bg-white" wire:key="caregiver-skill-filter-{{ $skill->id }}">
                                <input type="checkbox" value="{{ $skill->id }}" wire:model="skills" class="rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]">
                                <span>{{ $skill->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-[#324457]">Languages</p>
                    <div class="mt-2 grid max-h-36 gap-1 overflow-y-auto rounded-xl border border-[#DED6CA] bg-[#FFFCF8] p-2 sm:grid-cols-2 lg:grid-cols-1">
                        @foreach ($languageOptions as $language)
                            <label class="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-[#324457] hover:bg-white" wire:key="caregiver-language-filter-{{ $language->id }}">
                                <input type="checkbox" value="{{ $language->id }}" wire:model="languages" class="rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]">
                                <span>{{ $language->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-native-select-field
                    label="Trust badges"
                    wire:model="trust"
                    :options="[
                        ['label'=>'All caregivers','value'=>'all'],
                        ['label'=>'Verified only','value'=>'verified'],
                        ['label'=>'Top Caregiver only','value'=>'top'],
                    ]"
                />

                <x-native-select-field
                    label="Sort"
                    wire:model="sort"
                    :options="[
                        ['label'=>'Relevance','value'=>'relevance'],
                        ['label'=>'Top caregivers first','value'=>'top'],
                        ['label'=>'Reliability high-low','value'=>'reliability'],
                        ['label'=>'Experience high-low','value'=>'experience'],
                    ]"
                />

                <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row lg:col-span-1 lg:flex-col">
                    <button type="submit" class="min-h-11 w-full rounded-xl bg-[#0F3D3E] px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30">
                        Search caregivers
                    </button>

                    <button type="button" wire:click="clearFilters" class="min-h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 py-2 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB]">
                        Clear filters
                    </button>
                </div>
            </form>
        </aside>

        <section class="space-y-4 min-w-0">
            <div class="flex items-center justify-between">
                <p class="text-sm text-[#607080]">{{ $caregivers->total() }} caregiver(s) found</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($caregivers as $c)
                    @php
                        $photoUrl = $c->profile_photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->profile_photo_path) : null;
                        $nameParts = preg_split('/\s+/', trim((string) $c->user->name));
                        $initials = collect($nameParts)->filter()->map(fn($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
                    @endphp

                    <article class="rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}" alt="{{ $c->user->name }}" class="h-14 w-14 rounded-full object-cover border border-[#E4DDD3]">
                            @else
                                <div class="h-14 w-14 rounded-full bg-[#EAF6F6] text-[#0F3D3E] font-semibold flex items-center justify-center border border-[#BDD4F7]">
                                    {{ $initials }}
                                </div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <h3 class="font-display text-lg font-semibold text-[#17313F] truncate">{{ $c->user->name }}</h3>
                                <p class="text-sm text-[#607080]">{{ $c->user->city }}, {{ $c->user->state }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[#607080]">
                                    @if ((float) $c->average_rating > 0 && (int) $c->reviews_count > 0)
                                        <span class="inline-flex items-center gap-1 font-medium text-[#17313F]">
                                            <svg viewBox="0 0 20 20" class="h-4 w-4 text-amber-400" fill="currentColor" aria-hidden="true">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81H7.03a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                            {{ number_format((float) $c->average_rating, 1) }}
                                        </span>
                                        <span>{{ (int) $c->reviews_count }} review{{ (int) $c->reviews_count === 1 ? '' : 's' }}</span>
                                    @else
                                        <span class="text-[#7B8794]">No reviews yet</span>
                                    @endif
                                </div>
                                @if (! is_null($c->invite_response_rate))
                                    <p class="mt-1 text-xs text-[#7B8794]">Response score {{ number_format((float) $c->invite_response_rate, 0) }}%</p>
                                @endif
                                <p class="mt-1 text-xs text-[#7B8794]">Reliability {{ number_format((float) $c->reliability_score, 0) }}%</p>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-[#4B5B6B]">{{ \Illuminate\Support\Str::limit((string) $c->bio, 140) }}</p>

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
                                <span class="inline-flex rounded-full bg-[#F0E9E1] px-3 py-1 text-xs text-[#4B5B6B]">{{ $skill->name }}</span>
                            @endforeach
                            @if ($c->skills->count() > 3)
                                <span class="inline-flex rounded-full bg-[#F0E9E1] px-3 py-1 text-xs text-[#7B8794]">+{{ $c->skills->count() - 3 }} more</span>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <p class="text-xs text-[#7B8794]">{{ $c->is_accepting_new_clients ? 'Accepting new clients' : 'Currently not accepting new clients' }}</p>
                            <a class="hc-link whitespace-nowrap" href="{{ route('caregivers.show', $c->slug) }}" wire:navigate>View full profile</a>
                        </div>
                    </article>
                @empty
                    <div class="md:col-span-2 rounded-2xl border border-[#E4DDD3] bg-white p-6 shadow-sm">
                        <p class="text-sm text-[#607080]">
                            {{ !empty($prelaunchMode) ? 'Caregiver profiles will appear here when launch opens.' : 'No caregiver matches these filters yet.' }}
                        </p>
                    </div>
                @endforelse
            </div>

            <div>{{ $caregivers->links() }}</div>
        </section>
    </div>
</div>

