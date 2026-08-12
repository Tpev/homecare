<?php

namespace App\Livewire\Admin\Content;

use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Models\MediaAsset;
use App\Services\Content\BlogPostReadiness;
use App\Services\Content\BlogPostWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PostEditor extends Component
{
    public BlogPost $post;

    /** @var array<string,mixed> */
    public array $form = [];

    /** @var list<int|string> */
    public array $categoryIds = [];

    /** @var list<int|string> */
    public array $tagIds = [];

    /** @var list<int|string> */
    public array $relatedPostIds = [];

    /** @var list<array<string,mixed>> */
    public array $sources = [];

    public ?string $savedAt = null;

    public string $scheduleAt = '';

    public string $reviewNotes = '';

    public int $expectedEditVersion = 0;

    public function mount(BlogPost $blogPost): void
    {
        Gate::authorize('update', $blogPost);
        $this->post = $blogPost;
        $this->fillForm();
    }

    public function saveDraft(BlogPostWorkflow $workflow, bool $autosave = false): void
    {
        Gate::authorize('update', $this->post);
        $this->validate($this->rules());
        $this->post = $workflow->save(
            $this->post,
            array_merge($this->form, ['related_post_ids' => array_map('intval', $this->relatedPostIds)]),
            auth()->user(),
            array_map('intval', $this->categoryIds),
            array_map('intval', $this->tagIds),
            $this->sources,
            $autosave ? 'Autosaved' : 'Draft saved',
            $this->expectedEditVersion,
            ! $autosave,
        );
        $this->savedAt = now()->format('g:i:s A');
        $this->fillForm();

        if (! $autosave) {
            session()->flash('status', 'Draft saved.');
        }
    }

    public function autosave(BlogPostWorkflow $workflow): void
    {
        $this->saveDraft($workflow, true);
    }

    public function submitForReview(BlogPostWorkflow $workflow): void
    {
        $this->saveDraft($workflow, true);
        $this->post = $workflow->submitForReview($this->post, auth()->user());
        session()->flash('status', 'Article submitted for independent review.');
    }

    public function approveReview(BlogPostWorkflow $workflow): void
    {
        Gate::authorize('review', $this->post);
        $this->validate(['reviewNotes' => ['required', 'string', 'min:20', 'max:10000']]);
        $this->post = $workflow->approveReview($this->post, auth()->user(), $this->reviewNotes);
        $this->fillForm();
        session()->flash('status', 'Editorial review approved.');
    }

    public function publishNow(BlogPostWorkflow $workflow): void
    {
        Gate::authorize('publish', $this->post);
        $this->saveDraft($workflow, true);
        $this->post = $workflow->publish($this->post, auth()->user());
        $this->fillForm();
        session()->flash('status', 'Article published with an immutable live revision.');
    }

    public function schedule(BlogPostWorkflow $workflow): void
    {
        Gate::authorize('publish', $this->post);
        $this->validate(['scheduleAt' => ['required', 'date', 'after:now']]);
        $this->saveDraft($workflow, true);
        $this->post = $workflow->publish($this->post, auth()->user(), $this->scheduleAt);
        $this->fillForm();
        session()->flash('status', 'Publication scheduled.');
    }

    public function archive(BlogPostWorkflow $workflow): void
    {
        Gate::authorize('archive', $this->post);
        $this->post = $workflow->archive($this->post, auth()->user());
        $this->fillForm();
        session()->flash('status', 'Article archived.');
    }

    public function restoreRevision(int $revisionId, BlogPostWorkflow $workflow): void
    {
        Gate::authorize('update', $this->post);
        $revision = BlogPostRevision::query()->findOrFail($revisionId);
        $this->post = $workflow->restoreRevision($this->post, $revision, auth()->user());
        $this->fillForm();
        $this->dispatch('content-editor-load', document: $this->form['content_json']);
        session()->flash('status', 'Revision restored as a new working draft.');
    }

    public function addSource(): void
    {
        $this->sources[] = [
            'uuid' => (string) Str::uuid(),
            'title' => '', 'publisher' => '', 'url' => '', 'published_on' => '',
            'accessed_on' => now()->toDateString(), 'notes' => '',
        ];
    }

    public function removeSource(int $index): void
    {
        unset($this->sources[$index]);
        $this->sources = array_values($this->sources);
    }

    public function selectFeaturedMedia(int $assetId): void
    {
        Gate::authorize('update', $this->post);
        MediaAsset::query()->findOrFail($assetId);
        $this->form['featured_media_asset_id'] = $assetId;
    }

    public function render(BlogPostReadiness $readiness): View
    {
        $this->post->load(['author', 'reviewer', 'featuredMedia.variants', 'categories', 'tags', 'sources']);

        return view('livewire.admin.content.post-editor', [
            'authors' => ContentAuthor::active()->orderBy('name')->get(),
            'categories' => ContentCategory::active()->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => ContentTag::orderBy('name')->get(),
            'mediaAssets' => MediaAsset::query()->latest()->limit(24)->get(),
            'relatedCandidates' => BlogPost::query()->whereKeyNot($this->post->id)->orderBy('title')->limit(200)->get(['id', 'title', 'status']),
            'revisions' => $this->post->revisions()->with('actor')->limit(30)->get(),
            'readiness' => $readiness->inspect($this->post),
            'checklist' => BlogPostReadiness::checklist(),
            'independentReviewRequired' => (bool) config('content.publishing.require_independent_review'),
            'previewUrl' => URL::temporarySignedRoute('admin.content.posts.preview', now()->addHours(2), ['blogPost' => $this->post->id]),
        ]);
    }

    /** @return array<string,array<int,string>> */
    private function rules(): array
    {
        return [
            'form.title' => ['required', 'string', 'max:220'],
            'form.slug' => ['required', 'string', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'form.excerpt' => ['nullable', 'string', 'max:600'],
            'form.content_json' => ['required', 'array'],
            'form.author_id' => ['nullable', 'integer', 'exists:content_authors,id'],
            'form.featured_media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'form.seo_title' => ['nullable', 'string', 'max:220'],
            'form.meta_description' => ['nullable', 'string', 'max:320'],
            'form.canonical_url' => ['nullable', 'url', 'max:255'],
            'form.social_title' => ['nullable', 'string', 'max:220'],
            'form.social_description' => ['nullable', 'string', 'max:320'],
            'form.content_type' => ['required', 'in:guide,news,case-study,research,company-update'],
            'form.schema_type' => ['required', 'in:BlogPosting,Article,NewsArticle'],
            'form.editorial_checklist' => ['array'],
            'form.editorial_checklist.*' => ['boolean'],
            'form.locale' => ['required', 'string', 'max:16'],
            'form.review_notes' => ['nullable', 'string', 'max:10000'],
            'form.research_methodology' => ['nullable', 'string', 'max:20000'],
            'categoryIds' => ['array'],
            'categoryIds.*' => ['integer', 'exists:content_categories,id'],
            'tagIds' => ['array'],
            'tagIds.*' => ['integer', 'exists:content_tags,id'],
            'relatedPostIds' => ['array', 'max:12'],
            'relatedPostIds.*' => ['integer', 'exists:blog_posts,id'],
            'sources' => ['array', 'max:50'],
            'sources.*.uuid' => ['nullable', 'uuid', 'distinct'],
            'sources.*.title' => ['nullable', 'string', 'max:255'],
            'sources.*.publisher' => ['nullable', 'string', 'max:255'],
            'sources.*.url' => ['nullable', 'url:http,https', 'max:2048'],
            'sources.*.published_on' => ['nullable', 'date'],
            'sources.*.accessed_on' => ['nullable', 'date'],
            'sources.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function fillForm(): void
    {
        $this->post->load(['categories', 'tags', 'sources', 'relatedPosts']);
        $this->form = [
            'title' => $this->post->title,
            'slug' => $this->post->slug,
            'excerpt' => $this->post->excerpt ?? '',
            'content_json' => $this->post->content_json,
            'author_id' => $this->post->author_id,
            'featured_media_asset_id' => $this->post->featured_media_asset_id,
            'seo_title' => $this->post->seo_title ?? '',
            'meta_description' => $this->post->meta_description ?? '',
            'canonical_url' => $this->post->canonical_url ?? '',
            'social_title' => $this->post->social_title ?? '',
            'social_description' => $this->post->social_description ?? '',
            'schema_type' => $this->post->schema_type,
            'content_type' => $this->post->content_type,
            'locale' => $this->post->locale,
            'editorial_checklist' => array_merge(array_fill_keys(array_keys(BlogPostReadiness::checklist()), false), (array) $this->post->editorial_checklist),
            'review_notes' => $this->post->review_notes ?? '',
            'research_methodology' => $this->post->research_methodology ?? '',
        ];
        $this->categoryIds = $this->post->categories->modelKeys();
        $this->tagIds = $this->post->tags->modelKeys();
        $this->relatedPostIds = $this->post->relatedPosts->modelKeys();
        $this->sources = $this->post->sources->map(fn ($source): array => [
            'uuid' => $source->uuid,
            'title' => $source->title,
            'publisher' => $source->publisher ?? '',
            'url' => $source->url,
            'published_on' => $source->published_on?->toDateString() ?? '',
            'accessed_on' => $source->accessed_on?->toDateString() ?? '',
            'notes' => $source->notes ?? '',
        ])->values()->all();
        $this->expectedEditVersion = $this->post->edit_version;
        $this->reviewNotes = $this->post->review_notes ?? '';
        $this->scheduleAt = $this->post->scheduled_for?->format('Y-m-d\TH:i') ?? '';
    }
}
