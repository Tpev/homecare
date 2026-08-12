<?php

namespace App\Http\Controllers\Api\Content\V1;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Models\MediaAsset;
use App\Services\Content\BlogPostReadiness;
use App\Services\Content\BlogPostWorkflow;
use App\Services\Content\ContentApiIdempotency;
use App\Services\Content\ContentApiPresenter;
use App\Services\Content\MediaAssetManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function __construct(
        private readonly BlogPostWorkflow $workflow,
        private readonly BlogPostReadiness $readiness,
        private readonly MediaAssetManager $media,
        private readonly ContentApiPresenter $presenter,
        private readonly ContentApiIdempotency $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', BlogPost::class);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(BlogPost::STATUSES))],
            'search' => ['nullable', 'string', 'max:220'],
            'author_id' => ['nullable', 'integer', 'exists:content_authors,id'],
            'category_id' => ['nullable', 'integer', 'exists:content_categories,id'],
            'tag_id' => ['nullable', 'integer', 'exists:content_tags,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = BlogPost::query()
            ->with(['author', 'reviewer', 'featuredMedia.variants', 'categories', 'tags', 'sources', 'publishedRevision'])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(fn (Builder $matching): Builder => $matching
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('excerpt', 'like', '%'.$search.'%'));
            })
            ->when($validated['author_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('author_id', $id))
            ->when($validated['category_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('categories', fn (Builder $categories): Builder => $categories->whereKey($id)))
            ->when($validated['tag_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('tags', fn (Builder $tags): Builder => $tags->whereKey($id)))
            ->latest('updated_at')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return response()->json([
            'data' => $posts->getCollection()->map(fn (BlogPost $post): array => $this->presenter->article($post, false))->all(),
            'links' => $this->paginationLinks($posts),
            'meta' => $this->paginationMeta($posts),
        ]);
    }

    public function show(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('view', $article);

        return response()->json(['data' => $this->presenter->article($article)]);
    }

    public function options(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', BlogPost::class);
        $validated = $request->validate([
            'include' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:220'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $include = isset($validated['include'])
            ? array_values(array_unique(array_filter(explode(',', $validated['include']))))
            : ['authors', 'categories', 'tags', 'media'];
        $unknown = array_diff($include, ['authors', 'categories', 'tags', 'media']);
        if ($include === [] || $unknown !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'include' => 'Include only authors, categories, tags, and media.',
            ]);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);
        $search = (string) ($validated['search'] ?? '');
        $data = [];
        $meta = [];

        if (in_array('authors', $include, true)) {
            $items = ContentAuthor::query()->active()
                ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')->paginate($perPage, ['*'], 'page', $page);
            $data['authors'] = $items->getCollection()->map(fn (ContentAuthor $author): ?array => $this->presenter->author($author))->all();
            $meta['authors'] = $this->paginationMeta($items);
        }
        if (in_array('categories', $include, true)) {
            $items = ContentCategory::query()->where('is_active', true)
                ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('sort_order')->orderBy('name')->paginate($perPage, ['*'], 'page', $page);
            $data['categories'] = $items->getCollection()->map(fn (ContentCategory $category): array => $this->presenter->category($category))->all();
            $meta['categories'] = $this->paginationMeta($items);
        }
        if (in_array('tags', $include, true)) {
            $items = ContentTag::query()
                ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')->paginate($perPage, ['*'], 'page', $page);
            $data['tags'] = $items->getCollection()->map(fn (ContentTag $tag): array => $this->presenter->tag($tag))->all();
            $meta['tags'] = $this->paginationMeta($items);
        }
        if (in_array('media', $include, true)) {
            $items = MediaAsset::query()->with('variants')
                ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $matching): Builder => $matching
                    ->where('original_filename', 'like', '%'.$search.'%')
                    ->orWhere('alt_text', 'like', '%'.$search.'%')
                    ->orWhere('caption', 'like', '%'.$search.'%')))
                ->latest()->paginate($perPage, ['*'], 'page', $page);
            $data['media'] = $items->getCollection()->map(fn (MediaAsset $asset): array => $this->presenter->media($asset))->all();
            $meta['media'] = $this->paginationMeta($items);
        }

        return response()->json(['data' => $data, 'meta' => [
            'contract_version' => (int) config('content_api.contract_version'),
            ...$meta,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('create', BlogPost::class);
        $validated = $request->validate($this->articleRules(false));

        return $this->idempotency->execute($request, function () use ($request, $validated): JsonResponse {
            $post = DB::transaction(function () use ($request, $validated): BlogPost {
                $post = $this->workflow->createDraft($request->user(), $validated['title']);
                [$attributes, $categories, $tags, $sources] = $this->workflowInput($post, $validated, false);

                return $this->workflow->save(
                    $post,
                    $attributes,
                    $request->user(),
                    $categories,
                    $tags,
                    $sources,
                    'Draft created through Content API',
                    $post->edit_version,
                );
            });
            $request->attributes->set('content_api_blog_post_id', $post->id);

            return response()->json(['data' => $this->presenter->article($post)], 201);
        });
    }

    public function update(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('update', $article);
        $validated = $request->validate($this->articleRules(true));

        return $this->idempotency->execute($request, function () use ($request, $article, $validated): JsonResponse {
            [$attributes, $categories, $tags, $sources] = $this->workflowInput($article, $validated, true);
            $article = $this->workflow->save(
                $article,
                $attributes,
                $request->user(),
                $categories,
                $tags,
                $sources,
                'Updated through Content API',
                (int) $validated['edit_version'],
            );

            return response()->json(['data' => $this->presenter->article($article)]);
        }, $article);
    }

    public function uploadMedia(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('view', $article);
        Gate::forUser($request->user())->authorize('create', MediaAsset::class);
        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) ceil(config('content_api.max_media_bytes') / 1024)],
            'alt_text' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'edit_version' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->idempotency->execute($request, function () use ($request, $validated): JsonResponse {
            $asset = $this->media->storeUpload($validated['file'], $request->user(), Arr::only($validated, [
                'alt_text', 'caption', 'credit', 'license', 'source_url',
            ]));

            return response()->json(['data' => $this->presenter->media($asset)], 201);
        }, $article);
    }

    public function preview(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('view', $article);
        $expiresAt = now()->addMinutes((int) config('content_api.preview_ttl_minutes'));

        return response()->json(['data' => [
            'article_id' => $article->id,
            'url' => URL::temporarySignedRoute('admin.content.posts.preview', $expiresAt, ['blogPost' => $article->id]),
            'expires_at' => $expiresAt->toIso8601String(),
            'authentication_required' => true,
        ]]);
    }

    public function audit(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('view', $article);

        return $this->idempotency->execute($request, fn (): JsonResponse => response()->json(['data' => [
            'article_id' => $article->id,
            'edit_version' => $article->edit_version,
            'readiness' => $this->readiness->inspect($article),
            'audited_at' => now()->toIso8601String(),
            'actor_user_id' => $request->user()->id,
        ]]), $article);
    }

    public function submit(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('update', $article);
        $validated = $request->validate(['edit_version' => ['required', 'integer', 'min:0']]);

        return $this->idempotency->execute($request, function () use ($request, $article, $validated): JsonResponse {
            $article = $this->workflow->submitForReview($article, $request->user(), (int) $validated['edit_version']);

            return response()->json(['data' => $this->presenter->article($article)]);
        }, $article);
    }

    public function schedule(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('publish', $article);
        $validated = $request->validate([
            'edit_version' => ['required', 'integer', 'min:0'],
            'scheduled_for' => [
                'required',
                'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/',
                'date',
                'after:now',
            ],
        ]);

        return $this->idempotency->execute($request, function () use ($request, $article, $validated): JsonResponse {
            $article = $this->workflow->publish($article, $request->user(), $validated['scheduled_for'], (int) $validated['edit_version']);

            return response()->json(['data' => $this->presenter->article($article)]);
        }, $article);
    }

    public function publish(Request $request, string $post): JsonResponse
    {
        $article = $this->resolvePost($request, $post);
        Gate::forUser($request->user())->authorize('publish', $article);
        $validated = $request->validate(['edit_version' => ['required', 'integer', 'min:0']]);

        return $this->idempotency->execute($request, function () use ($request, $article, $validated): JsonResponse {
            $article = $this->workflow->publish($article, $request->user(), null, (int) $validated['edit_version']);

            return response()->json(['data' => $this->presenter->article($article)]);
        }, $article);
    }

    /** @return array<string,array<int,mixed>> */
    private function articleRules(bool $updating): array
    {
        $required = $updating ? ['sometimes'] : ['required'];

        return [
            'edit_version' => $updating ? ['required', 'integer', 'min:0'] : ['prohibited'],
            'title' => [...$required, 'string', 'max:220'],
            'slug' => ['sometimes', 'string', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:600'],
            'content_json' => ['sometimes', 'array'],
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:content_authors,id'],
            'featured_media_asset_id' => ['sometimes', 'nullable', 'integer', 'exists:media_assets,id'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:220'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'canonical_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'social_title' => ['sometimes', 'nullable', 'string', 'max:220'],
            'social_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'content_type' => ['sometimes', Rule::in(['guide', 'news', 'case-study', 'research', 'company-update'])],
            'schema_type' => ['sometimes', Rule::in(['BlogPosting', 'Article', 'NewsArticle'])],
            'locale' => ['sometimes', 'string', 'max:16'],
            'editorial_checklist' => ['sometimes', 'array'],
            'editorial_checklist.*' => ['boolean'],
            'review_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'research_methodology' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'category_ids' => ['sometimes', 'array', 'max:30'],
            'category_ids.*' => ['integer', 'distinct', 'exists:content_categories,id'],
            'tag_ids' => ['sometimes', 'array', 'max:50'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:content_tags,id'],
            'related_post_ids' => ['sometimes', 'array', 'max:12'],
            'related_post_ids.*' => ['integer', 'distinct', 'exists:blog_posts,id'],
            'sources' => ['sometimes', 'array', 'max:50'],
            'sources.*.uuid' => ['required', 'uuid', 'distinct'],
            'sources.*.title' => ['required', 'string', 'max:255'],
            'sources.*.publisher' => ['nullable', 'string', 'max:255'],
            'sources.*.url' => ['required', 'url:http,https', 'max:2048'],
            'sources.*.published_on' => ['nullable', 'date', 'before:tomorrow'],
            'sources.*.accessed_on' => ['nullable', 'date', 'before:tomorrow'],
            'sources.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array{0:array<string,mixed>,1:list<int>,2:list<int>,3:list<array<string,mixed>>} */
    private function workflowInput(BlogPost $post, array $validated, bool $preserveMissing): array
    {
        $post->loadMissing(['categories', 'tags', 'sources', 'relatedPosts']);
        $attributes = Arr::except($validated, ['edit_version', 'category_ids', 'tag_ids', 'sources']);
        if ($preserveMissing && ! array_key_exists('related_post_ids', $validated)) {
            $attributes['related_post_ids'] = $post->relatedPosts->modelKeys();
        }

        return [
            $attributes,
            array_key_exists('category_ids', $validated) ? $validated['category_ids'] : ($preserveMissing ? $post->categories->modelKeys() : []),
            array_key_exists('tag_ids', $validated) ? $validated['tag_ids'] : ($preserveMissing ? $post->tags->modelKeys() : []),
            array_key_exists('sources', $validated) ? $validated['sources'] : ($preserveMissing ? $post->sources->map->only([
                'uuid', 'title', 'publisher', 'url', 'published_on', 'accessed_on', 'notes',
            ])->values()->all() : []),
        ];
    }

    private function resolvePost(Request $request, string $identifier): BlogPost
    {
        $post = BlogPost::query()
            ->where(fn (Builder $query): Builder => is_numeric($identifier)
                ? $query->whereKey((int) $identifier)
                : $query->where('slug', $identifier))
            ->firstOrFail();
        $request->attributes->set('content_api_blog_post_id', $post->id);

        return $post;
    }

    /** @return array<string,mixed> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    /** @return array<string,?string> */
    private function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return ['first' => $paginator->url(1), 'last' => $paginator->url($paginator->lastPage()), 'prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()];
    }
}
