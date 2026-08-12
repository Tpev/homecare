<div class="min-h-screen bg-[#F7F3ED] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><p class="hc-brand-kicker">Content operations</p><h1 class="mt-2 font-display text-4xl font-semibold">Managed media library</h1><p class="mt-2 text-sm text-[#526474]">Owned assets, responsive WEBP renditions, accessibility metadata, credits, and licensing.</p></div><a href="{{ route('admin.content.posts.index') }}" wire:navigate class="hc-secondary-button">Back to articles</a></header>
        @if(session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>@endif

        <section class="hc-brand-card">
            <form wire:submit="store" class="grid gap-4 lg:grid-cols-3">
                <label class="lg:col-span-3"><span class="text-sm font-semibold">Image file</span><input type="file" wire:model="upload" accept="image/jpeg,image/png,image/webp,image/gif" class="mt-1 block w-full rounded-xl border border-[#D8D1C7] bg-white p-3 text-sm">@error('upload')<span class="text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                <label><span class="text-sm font-semibold">Alt text</span><input type="text" wire:model="altText" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm" placeholder="Describe the meaningful image content"></label>
                <label><span class="text-sm font-semibold">Credit</span><input type="text" wire:model="credit" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"></label>
                <label><span class="text-sm font-semibold">License</span><input type="text" wire:model="license" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm" placeholder="Owned, licensed, CC BY…"></label>
                <label class="lg:col-span-2"><span class="text-sm font-semibold">Caption</span><input type="text" wire:model="caption" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"></label>
                <label><span class="text-sm font-semibold">Source URL</span><input type="url" wire:model="sourceUrl" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"></label>
                <div class="lg:col-span-3"><button type="submit" class="hc-primary-button" wire:loading.attr="disabled">Upload and generate renditions</button></div>
            </form>
        </section>

        <section class="space-y-4">
            <input type="search" wire:model.blur="q" placeholder="Search filename, alt text, or caption…" class="min-h-11 w-full rounded-xl border border-[#D8D1C7] bg-white px-3 text-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($assets as $asset)
                    <article class="overflow-hidden rounded-2xl border border-[#DED6CA] bg-white shadow-sm" wire:key="media-{{ $asset->id }}">
                        <img src="{{ $asset->variantUrl('medium') }}" alt="{{ $asset->alt_text }}" class="aspect-[4/3] w-full object-cover">
                        <div class="space-y-2 p-4 text-sm"><p class="truncate font-semibold">{{ $asset->original_filename }}</p><p class="text-xs text-[#526474]">{{ $asset->width }}×{{ $asset->height }} · {{ number_format($asset->size_bytes/1024) }} KB · {{ $asset->variants->count() }} rendition(s)</p><p class="text-xs {{ $asset->alt_text ? 'text-[#526474]' : 'font-semibold text-rose-700' }}">{{ $asset->alt_text ?: 'Missing alt text' }}</p><div class="flex gap-3"><button type="button" wire:click="edit({{ $asset->id }})" class="font-semibold text-[#0F5B52] underline">Edit metadata</button>@can('delete',$asset)<button type="button" wire:click="delete({{ $asset->id }})" wire:confirm="Permanently delete this unused asset and its renditions?" class="text-rose-700 underline">Delete</button>@endcan</div></div>
                    </article>
                @endforeach
            </div>
            {{ $assets->links() }}
        </section>

        @if ($editingId)
            <div class="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 p-4" role="dialog" aria-modal="true">
                <form wire:submit="updateMetadata" class="w-full max-w-xl space-y-3 rounded-3xl bg-white p-6 shadow-2xl"><h2 class="font-display text-2xl font-semibold">Media metadata</h2><label class="block text-sm font-semibold">Alt text<input type="text" wire:model="altText" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"></label><label class="block text-sm font-semibold">Caption<textarea wire:model="caption" rows="2" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2"></textarea></label><div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold">Credit<input type="text" wire:model="credit" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"></label><label class="text-sm font-semibold">License<input type="text" wire:model="license" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"></label></div><label class="block text-sm font-semibold">Source URL<input type="url" wire:model="sourceUrl" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3"></label><div class="flex justify-end gap-2"><button type="button" wire:click="cancelEdit" class="hc-secondary-button">Cancel</button><button type="submit" class="hc-primary-button">Save metadata</button></div></form>
            </div>
        @endif
    </div>
</div>
