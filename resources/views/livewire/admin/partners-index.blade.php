<div class="hc-page py-7 sm:py-10" x-data x-on:partner-selected.window="$nextTick(() => { const panel = document.getElementById('partner-detail'); if (panel) { panel.focus({ preventScroll: true }); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); } })">
    <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#66746D]">Partner network</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Partner directory</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-[#66746D]">Know your partners, the services they offer, and the communities they cover.</p>
        </div>
        <div x-data="{ copied: false, copyFailed: false }" class="shrink-0">
            <button type="button" x-on:click="copyFailed = false; navigator.clipboard ? navigator.clipboard.writeText(@js(route('partner.register'))).then(() => { copied = true; setTimeout(() => copied = false, 3000); }).catch(() => copyFailed = true) : copyFailed = true" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#23483F] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#19392F] focus:outline-none focus:ring-2 focus:ring-[#23483F] focus:ring-offset-2">
                <span x-text="copied ? 'Signup link copied' : 'Copy partner signup link'">Copy partner signup link</span>
            </button>
            <p class="sr-only" role="status" x-text="copied ? 'Partner signup link copied to clipboard.' : ''"></p>
            <div x-cloak x-show="copyFailed" class="mt-2 max-w-sm text-xs text-[#66746D]">
                Copy this link:
                <input readonly aria-label="Partner signup link" value="{{ route('partner.register') }}" class="mt-1 w-full rounded-lg border-[#D9CEC0] bg-white text-xs" x-on:focus="$el.select()">
            </div>
        </div>
    </header>

    <div class="mt-7 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="All partner counts">
        @foreach(['all' => 'All partners', ...$statusOptions] as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')" aria-pressed="{{ $status === $value ? 'true' : 'false' }}" class="rounded-2xl border px-4 py-4 text-left transition focus:outline-none focus:ring-2 focus:ring-[#23483F] focus:ring-offset-2 {{ $status === $value ? 'border-[#23483F] bg-[#EAF0E9]' : 'border-[#E3D6C5] bg-white/80 hover:border-[#93A99D]' }}">
                <span class="block text-xs font-semibold text-[#66746D]">{{ $label }}</span>
                <span class="mt-1 block text-2xl font-semibold tabular-nums text-[#23483F]">{{ number_format($value === 'all' ? $totalPartners : ($counts[$value] ?? 0)) }}</span>
            </button>
        @endforeach
    </div>

    <section class="mt-6 rounded-2xl border border-[#E3D6C5] bg-white/85 p-4 sm:p-5" aria-label="Filter partners">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr]">
            <div>
                <label for="partner-search" class="mb-2 block text-xs font-semibold text-[#23483F]">Search partners</label>
                <input id="partner-search" type="search" wire:model.live.debounce.300ms="q" maxlength="200" placeholder="Business, contact, city, county or ZIP" class="min-h-11 w-full rounded-xl border-[#D9CEC0] bg-[#FFFDFA] text-sm placeholder:text-[#7A847D] focus:border-[#23483F] focus:ring-[#23483F]">
            </div>
            <div>
                <label for="partner-category" class="mb-2 block text-xs font-semibold text-[#23483F]">Service category</label>
                <select id="partner-category" wire:model.live="category" class="min-h-11 w-full rounded-xl border-[#D9CEC0] bg-[#FFFDFA] text-sm focus:border-[#23483F] focus:ring-[#23483F]">
                    <option value="all">All services</option>
                    @foreach($categoryOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="partner-state" class="mb-2 block text-xs font-semibold text-[#23483F]">Coverage state</label>
                <select id="partner-state" wire:model.live="state" class="min-h-11 w-full rounded-xl border-[#D9CEC0] bg-[#FFFDFA] text-sm focus:border-[#23483F] focus:ring-[#23483F]">
                    <option value="all">All states</option>
                    @foreach($stateOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>
        @if($q !== '' || $category !== 'all' || $state !== 'all' || $status !== 'all')
            <button type="button" wire:click="clearFilters" class="mt-3 min-h-9 text-xs font-semibold text-[#23483F] underline underline-offset-4">Clear filters</button>
        @endif
    </section>

    <div class="mt-6 grid items-start gap-6 {{ $selectedPartner ? 'xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]' : '' }}">
        <section class="min-w-0" aria-label="Partners">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-[#23483F]">{{ number_format($partners->total()) }} {{ $partners->total() === 1 ? 'partner' : 'partners' }}{{ $q !== '' || $category !== 'all' || $state !== 'all' || $status !== 'all' ? ' matching your filters' : '' }}</p>
                <span wire:loading.delay wire:target="q,category,state,status,clearFilters" class="text-xs text-[#66746D]" role="status">Updating...</span>
            </div>
            <div class="grid gap-3 {{ $selectedPartner ? '' : 'lg:grid-cols-2' }}">
                @forelse($partners as $partner)
                    @php($statusStyle = match ($partner->status) { 'approved' => 'bg-[#E6F1E8] text-[#28553B]', 'paused' => 'bg-[#F0EAE3] text-[#6B6257]', default => 'bg-[#FFF0DE] text-[#8A5425]' })
                    <button type="button" wire:key="partner-{{ $partner->id }}" wire:click="selectPartner({{ $partner->id }})" aria-label="View {{ $partner->business_name }}" aria-expanded="{{ $selectedId === $partner->id ? 'true' : 'false' }}" aria-controls="partner-detail" class="min-w-0 rounded-2xl border bg-white p-5 text-left shadow-sm transition hover:border-[#93A99D] focus:outline-none focus:ring-2 focus:ring-[#23483F] focus:ring-offset-2 {{ $selectedId === $partner->id ? 'border-[#23483F] ring-1 ring-[#23483F]' : 'border-[#E3D6C5]' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="break-words text-xl font-semibold text-[#23483F]">{{ $partner->business_name }}</h2>
                                <p class="mt-1 break-words text-sm text-[#66746D]">{{ $partner->contact_name }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusStyle }}">{{ $partner->statusLabel() }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach($partner->categoryLabels() as $label)
                                <span class="rounded-lg bg-[#F4F1EA] px-2.5 py-1 text-xs font-medium text-[#43594F]">{{ $label }}</span>
                            @endforeach
                        </div>
                        <p class="mt-4 break-words text-sm leading-6 text-[#66746D]">{{ $partner->coverageSummary() }}</p>
                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-[#F0E9DF] pt-3 text-xs">
                            <span class="text-[#66746D]">Joined {{ $partner->submitted_at?->format('M j, Y') ?? $partner->created_at?->format('M j, Y') }}</span>
                            <span class="font-semibold text-[#23483F]">View profile <span aria-hidden="true">&rarr;</span></span>
                        </div>
                    </button>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#D9CEC0] bg-white/70 px-6 py-14 text-center lg:col-span-2">
                        <h2 class="text-2xl font-semibold">{{ $totalPartners ? 'No partners match these filters' : 'Your partner network starts here' }}</h2>
                        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[#66746D]">{{ $totalPartners ? 'Try another service or location, or clear your filters to see everyone.' : 'Share your partner signup link. New registrations will appear here with their services, coverage and contact details.' }}</p>
                        @if($totalPartners)<button type="button" wire:click="clearFilters" class="mt-5 min-h-11 rounded-xl border border-[#D9CEC0] bg-white px-5 py-2 text-sm font-semibold text-[#23483F]">Clear filters</button>@endif
                    </div>
                @endforelse
            </div>
            <div class="mt-5">{{ $partners->links() }}</div>
        </section>

        @if($selectedPartner)
            <aside id="partner-detail" tabindex="-1" aria-labelledby="partner-detail-title" class="min-w-0 scroll-mt-24 rounded-2xl border border-[#D9CEC0] bg-[#FFFDFA] shadow-sm focus:outline-none focus:ring-2 focus:ring-[#23483F]" wire:key="partner-detail-{{ $selectedPartner->id }}">
                <div class="flex items-start justify-between gap-4 border-b border-[#E8DED1] p-5 sm:p-6">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#66746D]">Partner profile</p>
                        <h2 id="partner-detail-title" class="mt-2 break-words text-2xl font-semibold">{{ $selectedPartner->business_name }}</h2>
                        @php($website = $selectedPartner->website)
                        @if($website && in_array(strtolower((string) parse_url($website, PHP_URL_SCHEME)), ['http', 'https'], true))
                            <a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block break-all text-sm font-medium text-[#23483F] underline underline-offset-4">{{ parse_url($website, PHP_URL_HOST) ?: 'Visit website' }} <span aria-hidden="true">&nearr;</span><span class="sr-only"> (opens in a new tab)</span></a>
                        @endif
                    </div>
                    <button type="button" wire:click="closePartner" aria-label="Close partner profile" class="flex size-10 shrink-0 items-center justify-center rounded-full border border-[#E3D6C5] text-xl text-[#66746D] hover:bg-[#F4F1EA] focus:outline-none focus:ring-2 focus:ring-[#23483F]">&times;</button>
                </div>
                <div class="space-y-6 p-5 sm:p-6">
                    @if($selectedPartner->description)
                        <p class="whitespace-pre-line break-words text-sm leading-6 text-[#56665D]">{{ $selectedPartner->description }}</p>
                    @endif
                    <section aria-label="Main contact">
                        <h3 class="text-sm font-semibold">Main contact</h3>
                        <p class="mt-2 break-words text-sm text-[#56665D]">{{ $selectedPartner->contact_name }}</p>
                        <a href="mailto:{{ $selectedPartner->contact_email }}" class="mt-1 block break-all text-sm text-[#23483F] underline underline-offset-4">{{ $selectedPartner->contact_email }}</a>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $selectedPartner->contact_phone) }}" class="mt-2 block text-sm text-[#23483F] underline underline-offset-4">{{ $selectedPartner->contact_phone }}</a>
                    </section>
                    <section aria-label="Services and coverage">
                        <h3 class="text-sm font-semibold">Services</h3>
                        <div class="mt-2 flex flex-wrap gap-1.5">@foreach($selectedPartner->categoryLabels() as $label)<span class="rounded-lg bg-[#EAF0E9] px-2.5 py-1 text-xs font-medium text-[#23483F]">{{ $label }}</span>@endforeach</div>
                        <h3 class="mt-5 text-sm font-semibold">Coverage</h3>
                        <p class="mt-2 break-words text-sm leading-6 text-[#56665D]">{{ implode(', ', $selectedPartner->coverageStateLabels()) }}</p>
                        <p class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-[#56665D]">{{ $selectedPartner->coverage_type === 'entire_states' ? 'Entire selected states' : $selectedPartner->coverage_areas }}</p>
                    </section>
                    @if($selectedPartner->customer_notes)
                        <section aria-label="Customers served">
                            <h3 class="text-sm font-semibold">Customers served</h3>
                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-[#56665D]">{{ $selectedPartner->customer_notes }}</p>
                        </section>
                    @endif
                    <form wire:submit="savePartner" class="space-y-4 border-t border-[#E8DED1] pt-5">
                        <div>
                            <label for="partner-review-status" class="mb-2 block text-sm font-semibold text-[#23483F]">Partner status</label>
                            <select id="partner-review-status" wire:model="partnerStatus" class="min-h-11 w-full rounded-xl border-[#D9CEC0] bg-white text-sm focus:border-[#23483F] focus:ring-[#23483F]">
                                @foreach($statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                            <x-input-error :messages="$errors->get('partnerStatus')" class="mt-2" />
                        </div>
                        <div>
                            <label for="partner-internal-notes" class="mb-2 block text-sm font-semibold text-[#23483F]">Internal notes</label>
                            <textarea id="partner-internal-notes" wire:model="internalNotes" rows="4" maxlength="5000" placeholder="Conversation notes, next steps, or details for your team" aria-describedby="partner-notes-help" class="w-full rounded-xl border-[#D9CEC0] bg-white text-sm placeholder:text-[#7A847D] focus:border-[#23483F] focus:ring-[#23483F]"></textarea>
                            <p id="partner-notes-help" class="mt-1 text-xs text-[#66746D]">Visible only to admins.</p>
                            <x-input-error :messages="$errors->get('internalNotes')" class="mt-2" />
                        </div>
                        @if(session('partnerSaved'))<p role="status" class="rounded-xl bg-[#E6F1E8] px-3 py-2 text-sm text-[#28553B]">{{ session('partnerSaved') }}</p>@endif
                        <button type="submit" wire:loading.attr="disabled" wire:target="savePartner" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-[#23483F] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#19392F] focus:outline-none focus:ring-2 focus:ring-[#23483F] focus:ring-offset-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="savePartner">Save changes</span>
                            <span wire:loading wire:target="savePartner">Saving...</span>
                        </button>
                    </form>
                </div>
            </aside>
        @endif
    </div>
</div>
