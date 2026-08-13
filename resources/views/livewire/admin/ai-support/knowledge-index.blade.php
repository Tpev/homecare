<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('admin.ai-support.index') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; AI Support overview</a>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Knowledge base</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">All product truth used by intelligent support. Drafts never enter retrieval; published versions are immutable.</p>
        </div>
        <a href="{{ route('admin.ai-support.knowledge.create') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white">Create draft</a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([['Published', $publishedCount], ['Drafts', $draftCount], ['Paused', $pausedCount], ['Review overdue', $overdueCount]] as [$label, $count])
            <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-extrabold text-slate-950">{{ $count }}</p></div>
        @endforeach
    </div>

    <x-card>
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px]">
            <label class="text-sm font-semibold text-slate-800">Search all entries
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Stable ID, title, or answer" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
            </label>
            <label class="text-sm font-semibold text-slate-800">Lifecycle
                <select wire:model.live="status" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"><option value="">All</option>@foreach(['draft','in_review','approved','published','paused','superseded','withdrawn','deleted'] as $value)<option value="{{ $value }}">{{ str($value)->replace('_',' ')->headline() }}</option>@endforeach</select>
            </label>
            <label class="text-sm font-semibold text-slate-800">Role
                <select wire:model.live="role" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base"><option value="">All</option><option value="family">Family</option><option value="caregiver">Caregiver</option></select>
            </label>
        </div>
    </x-card>

    <div class="space-y-3">
        @forelse($entries as $entry)
            @php $working = $entry->workingVersion; @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" wire:key="kb-entry-{{ $entry->id }}">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2"><p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">{{ $entry->stable_id }}</p>@if($working)<x-badge :text="strtoupper($working->status)" :color="$working->status === 'published' ? 'green' : ($working->status === 'paused' ? 'yellow' : 'blue')" />@endif @if($entry->deleted_at)<x-badge text="ENTRY DELETED" color="red" />@endif</div>
                        <h2 class="mt-2 text-lg font-bold text-slate-950">{{ $working?->title ?: 'Content removed; tombstone retained' }}</h2>
                        @if($working)<p class="mt-1 text-sm text-slate-600">Version {{ $working->version_number }} · {{ str($working->type)->replace('_',' ')->headline() }} · {{ implode(', ', array_map(fn($role) => str($role)->headline(), $working->roles ?? [])) }}</p><p class="mt-1 text-sm {{ $working->review_by?->isPast() ? 'font-semibold text-rose-700' : 'text-slate-600' }}">Review by {{ $working->review_by?->format('M j, Y') ?: 'not set' }}</p>@endif
                    </div>
                    @if(! $entry->deleted_at)<a href="{{ route('admin.ai-support.knowledge.edit', $entry) }}" wire:navigate class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-800">Open entry</a>@endif
                </div>
            </article>
        @empty
            <x-card><p class="text-sm text-slate-600">No knowledge entries match these filters.</p></x-card>
        @endforelse
    </div>
    {{ $entries->links() }}
</div>
