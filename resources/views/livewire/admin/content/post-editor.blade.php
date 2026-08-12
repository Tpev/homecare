<div class="min-h-screen bg-[#F7F3ED] px-3 py-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1500px] space-y-4">
        <header class="sticky top-0 z-30 flex flex-col gap-3 rounded-2xl border border-[#DED6CA] bg-white/95 p-4 shadow-sm backdrop-blur lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('admin.content.posts.index') }}" wire:navigate class="shrink-0 text-sm font-semibold text-[#0F5B52] underline">← Content</a>
                <div class="min-w-0"><p class="truncate font-semibold text-[#17313F]">{{ $post->title }}</p><p class="text-xs text-[#6A7784]">{{ \App\Models\BlogPost::STATUSES[$post->status] ?? $post->status }} · revision {{ $post->revision_number }} @if($post->published_revision_id) · live revision {{ $post->publishedRevision?->revision_number }} @endif</p></div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($savedAt)<span class="text-xs text-[#6A7784]">Saved {{ $savedAt }}</span>@endif
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="hc-secondary-button">Preview</a>
                <button type="button" wire:click="saveDraft" class="hc-secondary-button">Save draft</button>
                @if($independentReviewRequired)<button type="button" wire:click="submitForReview" class="hc-primary-button">Submit for review</button>@endif
            </div>
        </header>

        @if (session('status'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>@endif
        @error('publish')<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }}</div>@enderror
        @error('review')<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }}</div>@enderror
        @error('conflict')<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }} <button type="button" onclick="window.location.reload()" class="font-semibold underline">Reload article</button></div>@enderror

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_390px]">
            <main class="space-y-4">
                <section class="hc-brand-card space-y-4">
                    <label class="block"><span class="text-sm font-semibold text-[#17313F]">Article title</span><input type="text" wire:model.blur="form.title" class="mt-1 min-h-12 w-full rounded-xl border border-[#D8D1C7] px-4 text-xl font-semibold">@error('form.title')<span class="text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
                        <label class="block"><span class="text-sm font-semibold">Permalink</span><div class="mt-1 flex min-h-11 items-center rounded-xl border border-[#D8D1C7] bg-white px-3"><span class="text-sm text-[#8A928C]">/blog/</span><input type="text" wire:model.blur="form.slug" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0"></div>@error('form.slug')<span class="text-xs text-rose-700">{{ $message }}</span>@enderror</label>
                        <label class="block"><span class="text-sm font-semibold">Content type</span><select wire:model.blur="form.content_type" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"><option value="guide">Guide</option><option value="research">Original research</option><option value="case-study">Case study</option><option value="news">News</option><option value="company-update">Company update</option></select></label>
                    </div>
                    <label class="block"><span class="text-sm font-semibold">Reader-focused excerpt</span><textarea wire:model.blur="form.excerpt" rows="3" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2 text-sm" placeholder="Tell readers exactly what they will learn and why it matters."></textarea><span class="text-xs text-[#6A7784]">{{ mb_strlen((string)($form['excerpt'] ?? '')) }}/600</span></label>
                </section>

                <section class="overflow-hidden rounded-[1.5rem] border border-[#D8D1C7] bg-white shadow-sm" x-data="contentEditor({ initialDocument: @js($form['content_json']) })">
                    <div class="sticky top-[88px] z-20 flex flex-wrap items-center gap-1 border-b border-[#E5DED5] bg-[#FCFAF7] p-2" role="toolbar" aria-label="Article formatting">
                        @foreach ([['bold','B'],['italic','I'],['underline','U'],['strike','S']] as [$command,$label])
                            <button type="button" @click="command('{{ $command }}')" :class="active('{{ $command }}') ? 'bg-[#0F5B52] text-white' : 'bg-white text-[#17313F]'" class="min-h-9 min-w-9 rounded-lg border border-[#D8D1C7] px-2 text-sm font-semibold">{{ $label }}</button>
                        @endforeach
                        <button type="button" @click="setLink" class="content-editor-toolbar-button">Link</button>
                        <span class="mx-1 h-6 w-px bg-[#D8D1C7]"></span>
                        <button type="button" @click="command('paragraph')" class="content-editor-toolbar-button">Text</button>
                        <button type="button" @click="command('heading2')" class="content-editor-toolbar-button">H2</button>
                        <button type="button" @click="command('heading3')" class="content-editor-toolbar-button">H3</button>
                        <button type="button" @click="command('heading4')" class="content-editor-toolbar-button">H4</button>
                        <button type="button" @click="command('bulletList')" class="content-editor-toolbar-button">Bullets</button>
                        <button type="button" @click="command('orderedList')" class="content-editor-toolbar-button">Steps</button>
                        <button type="button" @click="command('blockquote')" class="content-editor-toolbar-button">Quote</button>
                        <button type="button" @click="command('table')" class="content-editor-toolbar-button">Table</button>
                        <button type="button" @click="command('addRow')" class="content-editor-toolbar-button">Row +</button>
                        <button type="button" @click="command('addColumn')" class="content-editor-toolbar-button">Column +</button>
                        <button type="button" @click="command('deleteTable')" class="content-editor-toolbar-button">Delete table</button>
                        <button type="button" @click="command('horizontalRule')" class="content-editor-toolbar-button">Divider</button>
                        <button type="button" @click="insertCallout" class="content-editor-toolbar-button">Callout</button>
                        <button type="button" @click="insertFaq" class="content-editor-toolbar-button">FAQ</button>
                        <button type="button" @click="insertCitation" class="content-editor-toolbar-button">Citation</button>
                        <button type="button" @click="insertCta" class="content-editor-toolbar-button">CTA</button>
                        <span class="mx-1 h-6 w-px bg-[#D8D1C7]"></span>
                        <button type="button" @click="command('undo')" class="content-editor-toolbar-button">Undo</button>
                        <button type="button" @click="command('redo')" class="content-editor-toolbar-button">Redo</button>
                        <span class="ml-auto text-xs text-[#6A7784]" x-text="saveState"></span>
                    </div>
                    <div wire:ignore class="min-h-[650px] bg-white"><div x-ref="surface"></div></div>
                    <div class="border-t border-[#E5DED5] bg-[#FCFAF7] px-4 py-2 text-xs text-[#6A7784]">{{ number_format($post->word_count) }} words · {{ $post->read_minutes }} min read · H1 is supplied by the page template</div>
                </section>

                <section class="hc-brand-card space-y-4">
                    <div class="flex items-center justify-between"><div><h2 class="font-display text-2xl font-semibold">Sources and citations</h2><p class="text-sm text-[#526474]">Choose a displayed source number; citations retain a stable source identity if the list changes.</p></div><button type="button" wire:click="addSource" class="hc-secondary-button">Add source</button></div>
                    @forelse ($sources as $index => $source)
                        <div class="rounded-2xl border border-[#E2DCD3] bg-[#FCFAF7] p-4" wire:key="source-{{ $source['uuid'] ?? $index }}" data-editor-source-key="{{ $source['uuid'] ?? '' }}">
                            <div class="mb-3 flex items-center justify-between"><p class="font-semibold">Source {{ $index + 1 }}</p><button type="button" wire:click="removeSource({{ $index }})" class="text-sm text-rose-700 underline">Remove</button></div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <input type="text" wire:model.blur="sources.{{ $index }}.title" placeholder="Source title" class="min-h-11 rounded-xl border border-[#D8D1C7] px-3 text-sm">
                                <input type="text" wire:model.blur="sources.{{ $index }}.publisher" placeholder="Publisher / institution" class="min-h-11 rounded-xl border border-[#D8D1C7] px-3 text-sm">
                                <input type="url" wire:model.blur="sources.{{ $index }}.url" placeholder="https://…" class="min-h-11 rounded-xl border border-[#D8D1C7] px-3 text-sm md:col-span-2">
                                <label class="text-xs text-[#526474]">Published<input type="date" wire:model.blur="sources.{{ $index }}.published_on" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"></label>
                                <label class="text-xs text-[#526474]">Accessed<input type="date" wire:model.blur="sources.{{ $index }}.accessed_on" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"></label>
                                <textarea wire:model.blur="sources.{{ $index }}.notes" rows="2" placeholder="Which claims does this source support?" class="rounded-xl border border-[#D8D1C7] px-3 py-2 text-sm md:col-span-2"></textarea>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">No sources yet. Guides cannot be published without verifiable sourcing.</div>
                    @endforelse
                </section>

                <section class="hc-brand-card space-y-3">
                    <div><h2 class="font-display text-2xl font-semibold">Related content</h2><p class="text-sm text-[#526474]">Curated links are saved into the immutable publication revision. If none are selected, the public page uses category-based recommendations.</p></div>
                    <select wire:model="relatedPostIds" multiple size="8" class="w-full rounded-xl border border-[#D8D1C7] px-3 py-2 text-sm">
                        @foreach($relatedCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->title }} · {{ $candidate->status }}</option>@endforeach
                    </select>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="hc-brand-card space-y-4">
                    <div class="flex items-center justify-between"><h2 class="font-display text-xl font-semibold">Publishing gate</h2><span class="{{ $readiness['ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }} rounded-full px-2.5 py-1 text-xs font-semibold">{{ $readiness['ready'] ? 'Ready' : count($readiness['issues']).' issue(s)' }}</span></div>
                    @if ($readiness['issues'])<ul class="space-y-1 text-xs text-rose-800">@foreach($readiness['issues'] as $issue)<li>• {{ $issue }}</li>@endforeach</ul>@endif
                    @if ($readiness['warnings'])<ul class="space-y-1 text-xs text-amber-800">@foreach($readiness['warnings'] as $warning)<li>• {{ $warning }}</li>@endforeach</ul>@endif
                    <div class="space-y-2 border-t border-[#E5DED5] pt-3">
                        @foreach ($checklist as $key => $label)
                            <label class="flex items-start gap-2 text-xs text-[#394B57]"><input type="checkbox" wire:model.blur="form.editorial_checklist.{{ $key }}" class="mt-0.5 rounded border-[#B9B0A5] text-[#0F5B52]"> <span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                    @if($independentReviewRequired)
                    @can('review', $post)
                        @if($post->status === \App\Models\BlogPost::STATUS_IN_REVIEW)
                        <textarea wire:model.blur="reviewNotes" rows="3" placeholder="Review notes and qualifications…" class="w-full rounded-xl border border-[#D8D1C7] px-3 py-2 text-sm"></textarea>
                        <button type="button" wire:click="approveReview" class="hc-secondary-button w-full">Approve independent review</button>
                        @endif
                    @endcan
                    @endif
                    @can('publish', $post)
                        <button type="button" wire:click="publishNow" wire:confirm="Publish the current ready revision now?" class="hc-primary-button w-full" @disabled(! $readiness['ready'])>Publish now</button>
                        <div class="flex gap-2"><input type="datetime-local" wire:model="scheduleAt" class="min-h-11 min-w-0 flex-1 rounded-xl border border-[#D8D1C7] px-2 text-xs"><button type="button" wire:click="schedule" class="hc-secondary-button">Schedule</button></div>
                    @endcan
                </section>

                <section class="hc-brand-card space-y-3">
                    <h2 class="font-display text-xl font-semibold">Ownership and topics</h2>
                    <label class="block text-sm font-semibold">Public author<select wire:model.blur="form.author_id" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"><option value="">Choose author</option>@foreach($authors as $author)<option value="{{ $author->id }}">{{ $author->name }}{{ $author->credentials ? ' · '.$author->credentials : '' }}</option>@endforeach</select></label>
                    <fieldset><legend class="text-sm font-semibold">Categories</legend><div class="mt-2 grid gap-2">@foreach($categories as $category)<label class="flex gap-2 text-sm"><input type="checkbox" wire:model.blur="categoryIds" value="{{ $category->id }}" class="rounded border-[#B9B0A5] text-[#0F5B52]">{{ $category->name }}</label>@endforeach</div></fieldset>
                    <fieldset><legend class="text-sm font-semibold">Tags</legend><div class="mt-2 flex flex-wrap gap-2">@foreach($tags as $tag)<label class="rounded-full border border-[#D8D1C7] px-2.5 py-1 text-xs"><input type="checkbox" wire:model.blur="tagIds" value="{{ $tag->id }}" class="mr-1 rounded border-[#B9B0A5] text-[#0F5B52]">{{ $tag->name }}</label>@endforeach</div></fieldset>
                </section>

                <section class="hc-brand-card space-y-3">
                    <div class="flex items-center justify-between"><h2 class="font-display text-xl font-semibold">Managed media</h2><a href="{{ route('admin.content.media.index') }}" target="_blank" class="text-xs font-semibold text-[#0F5B52] underline">Library</a></div>
                    @if ($post->featuredMedia)<img src="{{ $post->featuredMedia->variantUrl('medium') }}" alt="{{ $post->featuredMedia->alt_text }}" class="aspect-[16/9] w-full rounded-xl object-cover"><p class="text-xs text-[#526474]">Featured: {{ $post->featuredMedia->alt_text ?: 'Missing alt text' }}</p>@endif
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($mediaAssets as $asset)
                            <div class="group relative" wire:key="editor-media-{{ $asset->id }}">
                                <img src="{{ $asset->variantUrl('small') }}" alt="{{ $asset->alt_text }}" class="aspect-square w-full rounded-lg object-cover">
                                <div class="absolute inset-0 hidden flex-col justify-end gap-1 rounded-lg bg-black/65 p-1.5 group-hover:flex">
                                    <button type="button" wire:click="selectFeaturedMedia({{ $asset->id }})" class="rounded bg-white px-1 py-1 text-[10px] font-semibold">Feature</button>
                                    <button type="button" @click="window.dispatchEvent(new CustomEvent('content-editor-insert-image', { detail: { id: {{ $asset->id }}, url: @js($asset->variantUrl('large')), alt: @js($asset->alt_text), caption: @js($asset->caption) } }))" class="rounded bg-[#0F5B52] px-1 py-1 text-[10px] font-semibold text-white">Insert</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="hc-brand-card space-y-3">
                    <h2 class="font-display text-xl font-semibold">SEO and AI preview</h2>
                    <label class="block text-sm font-semibold">SEO title<input type="text" wire:model.blur="form.seo_title" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"><span class="text-xs text-[#6A7784]">{{ mb_strlen((string)($form['seo_title'] ?? '')) }}/65</span></label>
                    <label class="block text-sm font-semibold">Meta description<textarea wire:model.blur="form.meta_description" rows="3" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2 text-sm"></textarea><span class="text-xs text-[#6A7784]">{{ mb_strlen((string)($form['meta_description'] ?? '')) }}/165</span></label>
                    <label class="block text-sm font-semibold">Canonical override<input type="url" wire:model.blur="form.canonical_url" placeholder="Leave blank for article URL" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"></label>
                    <label class="block text-sm font-semibold">Social title<input type="text" wire:model.blur="form.social_title" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"></label>
                    <label class="block text-sm font-semibold">Social description<textarea wire:model.blur="form.social_description" rows="2" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2 text-sm"></textarea></label>
                    <label class="block text-sm font-semibold">Schema type<select wire:model.blur="form.schema_type" class="mt-1 min-h-11 w-full rounded-xl border border-[#D8D1C7] px-3 text-sm"><option>BlogPosting</option><option>Article</option><option>NewsArticle</option></select></label>
                    <label class="block text-sm font-semibold">Research methodology<textarea wire:model.blur="form.research_methodology" rows="3" class="mt-1 w-full rounded-xl border border-[#D8D1C7] px-3 py-2 text-sm" placeholder="Explain how original data, interviews, or estimates were produced."></textarea></label>
                </section>

                <section class="hc-brand-card space-y-3">
                    <h2 class="font-display text-xl font-semibold">Revision history</h2>
                    <div class="max-h-80 space-y-2 overflow-y-auto">
                        @foreach ($revisions as $revision)
                            <div class="rounded-xl border border-[#E5DED5] p-3 text-xs">
                                <div class="flex justify-between gap-2"><strong>#{{ $revision->revision_number }} · {{ $revision->change_summary }}</strong><span>{{ $revision->created_at?->diffForHumans() }}</span></div>
                                <p class="mt-1 text-[#6A7784]">{{ $revision->actor?->name ?? 'System import' }}</p>
                                @if ((int)$revision->id !== (int)$post->published_revision_id)<button type="button" wire:click="restoreRevision({{ $revision->id }})" wire:confirm="Restore this snapshot as a new working draft?" class="mt-2 font-semibold text-[#0F5B52] underline">Restore as draft</button>@else<span class="mt-2 inline-block rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">Live revision</span>@endif
                            </div>
                        @endforeach
                    </div>
                    @can('archive', $post)<button type="button" wire:click="archive" wire:confirm="Archive this article?" class="text-sm font-semibold text-rose-700 underline">Archive article</button>@endcan
                </section>
            </aside>
        </div>
    </div>
</div>
