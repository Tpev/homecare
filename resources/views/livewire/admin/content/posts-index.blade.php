<div class="min-h-screen bg-[#F7F3ED] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="hc-brand-kicker">Content operations</p>
                <h1 class="mt-2 font-display text-4xl font-semibold text-[#17313F]">Editorial command center</h1>
                <p class="mt-2 max-w-3xl text-sm text-[#526474]">Plan, review, publish, measure, and refresh trustworthy content without touching deployed files.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.content.media.index') }}" wire:navigate class="hc-secondary-button">Media library</a>
                @if(auth()->user()?->canPublishContent())<a href="{{ route('admin.content.settings') }}" wire:navigate class="hc-secondary-button">Authors & taxonomy</a>@endif
                <button type="button" wire:click="createPost" class="hc-primary-button">New article</button>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8" aria-label="Content performance summary">
            @foreach ([
                ['Drafts', $metrics['drafts']], ['In review', $metrics['review']], ['Scheduled', $metrics['scheduled']],
                ['Published', $metrics['published']], ['Review due', $metrics['due_review']],
                ['30d daily visitors', $metrics['views_30d']], ['30d CTA', $metrics['cta_30d']],
                ['30d AI referrals', $metrics['ai_referrals_30d']],
            ] as [$label, $value])
                <div class="hc-metric-card">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#6A7784]">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold text-[#17313F]">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </section>

        <section class="hc-brand-card space-y-4">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px]">
                <label>
                    <span class="sr-only">Search articles</span>
                    <input type="search" wire:model.blur="q" placeholder="Search title, permalink, or article text…" class="min-h-11 w-full rounded-xl border border-[#D8D1C7] bg-white px-3 text-sm">
                </label>
                <select wire:model.live="status" class="min-h-11 rounded-xl border border-[#D8D1C7] bg-white px-3 text-sm">
                    <option value="all">All workflows</option>
                    @foreach (\App\Models\BlogPost::STATUSES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="sort" class="min-h-11 rounded-xl border border-[#D8D1C7] bg-white px-3 text-sm">
                    <option value="updated_at">Recently edited</option>
                    <option value="first_published_at">Publication date</option>
                    <option value="title">Title</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-[#E2DCD3] bg-white">
                <table class="min-w-[980px] w-full text-left text-sm">
                    <thead class="bg-[#F5F2ED] text-xs uppercase tracking-[0.12em] text-[#6A7784]">
                        <tr><th class="px-4 py-3">Article</th><th class="px-4 py-3">Workflow</th><th class="px-4 py-3">Trust</th><th class="px-4 py-3">30-day performance</th><th class="px-4 py-3">Live revision</th><th class="px-4 py-3">Updated</th><th class="px-4 py-3">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[#EEE8E0]">
                        @forelse ($posts as $post)
                            <tr wire:key="post-{{ $post->id }}">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($post->featuredMedia)
                                            <img src="{{ $post->featuredMedia->variantUrl('small') }}" alt="" class="h-12 w-16 rounded-lg object-cover">
                                        @else
                                            <div class="grid h-12 w-16 place-items-center rounded-lg bg-[#EFEAE3] text-[10px] text-[#6A7784]">No image</div>
                                        @endif
                                        <div><p class="font-semibold text-[#17313F]">{{ $post->title }}</p><p class="mt-1 text-xs text-[#6A7784]">/blog/{{ $post->slug }}</p></div>
                                    </div>
                                </td>
                                <td class="px-4 py-4"><span class="hc-muted-chip">{{ \App\Models\BlogPost::STATUSES[$post->status] ?? $post->status }}</span></td>
                                <td class="px-4 py-4 text-xs text-[#526474]">{{ $post->author?->name ?? 'No author' }}<br>{{ $post->sources_count }} source(s) · {{ $post->revisions_count }} revision(s)</td>
                                <td class="px-4 py-4 text-xs text-[#526474]">
                                    {{ number_format($post->views_30d_count) }} views · {{ number_format($post->reads_30d_count) }} completed<br>
                                    {{ number_format($post->cta_30d_count) }} CTA · {{ number_format($post->signups_30d_count) }} signup(s)
                                </td>
                                <td class="px-4 py-4 text-xs text-[#526474]">{{ $post->published_revision_id ? '#'.$post->publishedRevision?->revision_number : 'Not live' }}<br>{{ $post->first_published_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-4 text-xs text-[#526474]">{{ $post->updated_at->diffForHumans() }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex gap-2">
                                        <a href="{{ route('admin.content.posts.edit', $post->id) }}" wire:navigate class="font-semibold text-[#0F5B52] underline">Edit</a>
                                        @can('archive', $post)
                                            @if ($post->status !== \App\Models\BlogPost::STATUS_ARCHIVED)
                                                <button type="button" wire:click="archive({{ $post->id }})" wire:confirm="Archive this article and remove it from public discovery?" class="text-rose-700 underline">Archive</button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-12 text-center text-[#6A7784]">No articles match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $posts->links() }}
        </section>
    </div>
</div>
