<div class="hc-page space-y-6 py-8">
    @if (session('status'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Caregiver screening</h1>
        </div>
        <div class="flex flex-wrap gap-2" aria-label="Screening views">
            <button type="button" wire:click="$set('filter', 'open')" aria-pressed="{{ $filter === 'open' ? 'true' : 'false' }}" class="min-h-11 rounded-xl border px-4 py-2 text-sm font-semibold {{ $filter === 'open' ? 'border-teal-600 bg-teal-50 text-teal-900' : 'border-slate-300 bg-white text-slate-700' }}">Open reviews ({{ $counts->sum() }})</button>
            <button type="button" wire:click="$set('filter', 'suspended')" aria-pressed="{{ $filter === 'suspended' ? 'true' : 'false' }}" class="min-h-11 rounded-xl border px-4 py-2 text-sm font-semibold {{ $filter === 'suspended' ? 'border-rose-600 bg-rose-50 text-rose-900' : 'border-slate-300 bg-white text-slate-700' }}">Suspended ({{ $suspendedCount }})</button>
        </div>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
            <p class="font-semibold">The action could not be completed.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(16rem,0.8fr)_minmax(0,2fr)]">
        <aside class="overflow-hidden rounded-2xl border border-slate-200 bg-white" aria-label="Screening queue">
            <div class="space-y-3 border-b border-slate-200 p-4">
                <h2 class="font-semibold text-slate-900">{{ $filter === 'suspended' ? 'Suspended caregivers' : 'Review queue' }}</h2>
                <label for="screening-search" class="sr-only">Search caregiver name or email</label>
                <input id="screening-search" type="search" wire:model.live.debounce.350ms="query" placeholder="Name or email" class="min-h-11 w-full rounded-xl border-slate-300 text-sm">
                <label for="screening-filter" class="sr-only">Filter reviews</label>
                <select id="screening-filter" wire:model.live="filter" class="min-h-11 w-full rounded-xl border-slate-300 text-sm">
                    <option value="open">All open reviews ({{ $counts->sum() }})</option>
                    <option value="initial">New caregivers ({{ $counts->get('initial', 0) }})</option>
                    <option value="legacy">Legacy reviews ({{ $counts->get('legacy', 0) }})</option>
                    <option value="recurring">Six-month reviews ({{ $counts->get('recurring', 0) }})</option>
                    <option value="overdue">Overdue reviews</option>
                    <option value="potential_matches">Potential matches</option>
                    <option value="awaiting_investigation">Awaiting investigation</option>
                    <option value="suspended">Suspended ({{ $suspendedCount }})</option>
                    <option value="completed">Completed / history</option>
                </select>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($cases as $case)
                    <button type="button" wire:key="screening-case-{{ $case->id }}" wire:click="selectCase({{ $case->id }})" @if ($selectedId === $case->id) aria-current="true" @endif class="block w-full px-4 py-4 text-left transition {{ $selectedId === $case->id ? 'bg-teal-50 ring-1 ring-inset ring-teal-400' : 'hover:bg-slate-50' }}">
                        <span class="block font-semibold text-slate-900">{{ $case->profile?->user?->name ?? 'Unavailable caregiver' }}</span>
                        <span class="mt-0.5 block break-all text-xs text-slate-500">{{ $case->profile?->user?->email }}</span>
                        <span class="mt-2 flex flex-wrap gap-1.5 text-xs">
                            <span class="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-700">{{ ['initial' => 'New caregiver', 'legacy' => 'Legacy review', 'recurring' => 'Six-month review'][$case->kind] ?? $case->kind }}</span>
                            @if ($filter === 'suspended')
                                <span class="rounded-full bg-rose-100 px-2 py-1 font-medium text-rose-800">Suspended</span>
                            @else
                                <span class="rounded-full px-2 py-1 {{ $case->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">{{ str($case->status)->headline() }}</span>
                            @endif
                        </span>
                        @if ($filter === 'suspended')
                            <span class="mt-2 block text-xs text-slate-500">{{ $case->profile?->screening_suspended_at ? 'Suspended '.$case->profile->screening_suspended_at->format('M j, Y') : 'Account suspended' }} · {{ $case->status === 'completed' ? 'Review to restore' : 'Review open' }}</span>
                        @else
                            <span class="mt-2 block text-xs {{ $case->status !== 'completed' && $case->due_at?->isPast() ? 'font-semibold text-rose-700' : 'text-slate-500' }}">{{ $case->status !== 'completed' && $case->due_at?->isPast() ? 'Overdue · ' : 'Due ' }}{{ $case->due_at?->format('M j, Y') ?? 'Not scheduled' }}</span>
                        @endif
                        @if ($case->claimed_by && $case->claimed_until?->isFuture())
                            <span class="mt-1 block text-xs text-slate-500">Claimed by {{ $case->claimant?->name ?? 'an admin' }}</span>
                        @endif
                    </button>
                @empty
                    <p class="p-5 text-sm text-slate-500">{{ $filter === 'suspended' ? 'No suspended caregivers match this search.' : 'No reviews match this filter.' }}</p>
                @endforelse
            </div>
            @if ($cases->hasPages()) <div class="border-t border-slate-200 p-3">{{ $cases->links() }}</div> @endif
        </aside>

        <main class="min-w-0 space-y-5" aria-label="Selected screening review">
            @if ($selectedCase && $selectedCase->profile)
                @php
                    $profile = $selectedCase->profile;
                    $identity = $selectedCase->identity_snapshot ?? [];
                    $hasClaim = (int) $selectedCase->claimed_by === (int) auth()->id() && $selectedCase->claimed_until?->isFuture();
                    $claimedByOther = $selectedCase->claimed_by && (int) $selectedCase->claimed_by !== (int) auth()->id() && $selectedCase->claimed_until?->isFuture();
                    $completed = $selectedCase->status === 'completed';
                    $isSuspended = $profile->status === 'suspended' || $profile->screening_suspended_at;
                    $canDecide = $hasClaim && ! $completed && $hasCurrentIdentity && $selectedCase->search_status === 'completed' && $selectedCase->status !== 'awaiting_kyc';
                @endphp
                <section class="rounded-2xl border border-slate-200 bg-white p-5" wire:key="selected-screening-{{ $selectedCase->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Case #{{ $selectedCase->id }} · {{ ['initial' => 'New caregiver', 'legacy' => 'Legacy review', 'recurring' => 'Six-month review'][$selectedCase->kind] ?? $selectedCase->kind }}</p>
                            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $profile->user?->name }}</h2>
                            <p class="mt-1 break-all text-sm text-slate-500">{{ $profile->user?->email }}</p>
                        </div>
                        <a href="{{ route('admin.caregivers.screening.export', $selectedCase) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Export audit</a>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">Profile: {{ str($profile->status)->headline() }}</span>
                        @if ($isSuspended)
                            <span class="rounded-full bg-rose-100 px-3 py-1.5 font-semibold text-rose-800">New work suspended · login retained</span>
                        @elseif ($profile->screening_legacy_access && ! $profile->screening_initial_approved_at)
                            <span class="rounded-full bg-blue-100 px-3 py-1.5 text-blue-800">Legacy work access preserved</span>
                        @elseif ($profile->screening_initial_approved_at)
                            <span class="rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-800">Initial screening approved</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-3 py-1.5 text-amber-900">Initial approval required for new work</span>
                        @endif
                    </div>
                    @if ($isSuspended)
                        <p class="mt-3 text-sm text-slate-600">{{ $hasCurrentIdentity ? 'Review and approve to restore new work. The original suspension stays in the audit history.' : 'Current approved KYC is required before restoring this account.' }}</p>
                    @endif
                    @if (! $completed)
                        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                            <button type="button" wire:click="claim" wire:loading.attr="disabled" @disabled($claimedByOther || ! $hasCurrentIdentity) class="min-h-11 rounded-xl border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $hasClaim ? 'Renew review claim' : 'Claim review' }}</button>
                            <p class="text-xs text-slate-500">{{ $hasClaim ? 'Claimed by you until '.$selectedCase->claimed_until->format('g:i A') : ($claimedByOther ? 'Currently claimed by '.($selectedCase->claimant?->name ?? 'another admin').' until '.$selectedCase->claimed_until->format('g:i A') : 'Claim this case before searching or deciding.') }}</p>
                        </div>
                    @endif
                    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <h3 class="font-semibold text-slate-900">Didit-verified identity</h3>
                            @if (! $completed)
                                <button type="button" wire:click="search" wire:loading.attr="disabled" @disabled(! $hasClaim || ! $hasCurrentIdentity || $selectedCase->status === 'awaiting_kyc') class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50">{{ $selectedCase->searched_at ? 'Refresh name search' : 'Search names' }}</button>
                            @endif
                        </div>
                        @if ($identity)
                            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                <div><dt class="text-xs text-slate-500">Legal name</dt><dd class="font-medium text-slate-900">{{ $identity['full_name'] ?? trim(($identity['first_name'] ?? '').' '.($identity['last_name'] ?? '')) }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Date of birth</dt><dd>{{ $identity['date_of_birth'] ?? 'Not supplied' }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-3 text-sm text-amber-900">Verified identity unavailable.</p>
                        @endif
                        @if ($selectedCase->search_status === 'error')
                            <p role="alert" class="mt-3 text-sm font-medium text-rose-800">Search failed. Refresh the name search before deciding.</p>
                        @elseif ($selectedCase->search_status !== 'completed')
                            <p role="status" class="mt-3 text-sm text-amber-900">Name search {{ str($selectedCase->search_status)->replace('_', ' ') }}. Results are not ready.</p>
                        @endif
                    </div>
                    @include('livewire.admin.partials.screening-candidates')
                    @include('livewire.admin.partials.screening-decision')
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-slate-900">Profile & credentials</h2>
                        <button type="button" wire:click="toggleTopCaregiver({{ $profile->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-xl border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-900">{{ $profile->top_caregiver ? 'Remove Top Caregiver badge' : 'Set Top Caregiver badge' }}</button>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $profile->bio ?: 'No biography provided.' }}</p>
                    <p class="mt-3 text-xs text-slate-500">{{ $profile->years_experience ?? 'Unknown' }} years of experience · {{ $profile->service_area_zip ?: 'No service ZIP' }} · Platform rate ${{ number_format((float) $profile->resolvePlatformHourlyRate(), 2) }}/hr</p>
                    @include('livewire.admin.partials.caregiver-background-review', ['profile' => $profile])
                </section>
                @include('livewire.admin.partials.screening-history')
            @elseif ($selectedCase)
                <section class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-semibold text-slate-900">Archived case #{{ $selectedCase->id }}</h2>
                        <a href="{{ route('admin.caregivers.screening.export', $selectedCase) }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Export audit</a>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">The caregiver profile is no longer available. Screening evidence and completed decisions remain in the audit history.</p>
                    <p class="mt-3 text-sm text-slate-700">{{ str($selectedCase->status)->headline() }}{{ $selectedCase->outcome ? ' · '.str($selectedCase->outcome)->headline() : '' }}{{ $selectedCase->decision ? ' · '.str($selectedCase->decision)->headline() : '' }}</p>
                    @if ($selectedCase->note)<p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $selectedCase->note }}</p>@endif
                    @include('livewire.admin.partials.screening-record', ['record' => $selectedCase->identity_snapshot ?? [], 'depth' => 0])
                    @include('livewire.admin.partials.screening-candidates')
                </section>
                @include('livewire.admin.partials.screening-history')
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-16 text-center">
                    <h2 class="font-semibold text-slate-900">{{ $filter === 'suspended' ? 'No suspended caregiver selected' : 'Select a review to get started' }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ $filter === 'suspended' ? 'Choose a suspended caregiver to review their account. Past decisions remain in Completed / history.' : 'Choose a caregiver from the queue. Completed decisions are available in the history filter.' }}</p>
                </div>
            @endif
        </main>
    </div>
    @include('livewire.admin.partials.screening-booking-reviews')
</div>
