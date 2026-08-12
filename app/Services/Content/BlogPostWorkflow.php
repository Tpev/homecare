<?php

namespace App\Services\Content;

use App\Jobs\SubmitIndexNowUrls;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\ContentAuthor;
use App\Models\UrlRedirect;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BlogPostWorkflow
{
    public function __construct(
        private readonly TiptapDocumentRenderer $renderer,
        private readonly BlogPostReadiness $readiness,
    ) {}

    public function createDraft(User $actor, ?string $title = null): BlogPost
    {
        if (! $actor->isContentTeamMember()) {
            throw ValidationException::withMessages(['authorization' => 'A content-team account is required to create an article.']);
        }

        $baseTitle = trim((string) $title) ?: 'Untitled article';
        $slug = $this->uniqueSlug($baseTitle);
        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];
        $rendered = $this->renderer->render($content);

        return DB::transaction(function () use ($actor, $baseTitle, $slug, $content, $rendered): BlogPost {
            $post = BlogPost::query()->create([
                'title' => $baseTitle,
                'slug' => $slug,
                'status' => BlogPost::STATUS_DRAFT,
                'content_json' => $content,
                'body_html' => $rendered['html'],
                'plain_text' => $rendered['plain_text'],
                'table_of_contents' => $rendered['table_of_contents'],
                'word_count' => $rendered['word_count'],
                'read_minutes' => $rendered['read_minutes'],
                'robots_index' => false,
                'robots_directives' => 'noindex,nofollow',
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
                'last_edited_by_user_id' => $actor->id,
            ]);

            $this->recordRevision($post, $actor, 'Draft created');

            return $post->fresh();
        });
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @param  list<int>  $categoryIds
     * @param  list<int>  $tagIds
     * @param  list<array<string,mixed>>  $sources
     */
    public function save(
        BlogPost $post,
        array $attributes,
        User $actor,
        array $categoryIds = [],
        array $tagIds = [],
        array $sources = [],
        string $summary = 'Draft saved',
        ?int $expectedEditVersion = null,
        bool $recordPermanentRevision = true,
    ): BlogPost {
        $this->assertCanEdit($post, $actor);

        $sources = $this->normalizeSources($sources);

        return DB::transaction(function () use ($post, $attributes, $actor, $categoryIds, $tagIds, $sources, $summary, $expectedEditVersion, $recordPermanentRevision): BlogPost {
            $post = BlogPost::query()->lockForUpdate()->findOrFail($post->id);
            if ($expectedEditVersion !== null && $post->edit_version !== $expectedEditVersion) {
                throw ValidationException::withMessages([
                    'conflict' => 'This article changed in another editor. Reload before saving so newer work is not overwritten.',
                ]);
            }

            $before = $this->snapshot($post);

            $content = (array) ($attributes['content_json'] ?? $post->content_json);
            $rendered = $this->renderer->render($content, $sources);
            $slug = Str::slug((string) ($attributes['slug'] ?? $post->slug));
            if ($slug === '') {
                throw ValidationException::withMessages(['slug' => 'Enter a valid permalink.']);
            }

            $slugConflict = BlogPost::withTrashed()
                ->where('slug', $slug)
                ->whereKeyNot($post->id)
                ->exists();
            if ($slugConflict) {
                throw ValidationException::withMessages(['slug' => 'That permalink is already in use.']);
            }

            $post->fill(array_intersect_key($attributes, array_flip([
                'title', 'excerpt', 'author_id', 'reviewer_id', 'featured_media_asset_id',
                'seo_title', 'meta_description', 'canonical_url', 'social_title',
                'social_description', 'schema_type', 'content_type', 'locale',
                'editorial_checklist', 'review_notes', 'research_methodology',
                'source_import',
            ])));
            $post->forceFill([
                'slug' => $slug,
                'content_json' => $content,
                'body_html' => $rendered['html'],
                'plain_text' => $rendered['plain_text'],
                'table_of_contents' => $rendered['table_of_contents'],
                'word_count' => $rendered['word_count'],
                'read_minutes' => $rendered['read_minutes'],
                'updated_by_user_id' => $actor->id,
            ])->save();

            $post->categories()->sync(array_values(array_unique(array_map('intval', $categoryIds))));
            $post->tags()->sync(array_values(array_unique(array_map('intval', $tagIds))));
            $relatedIds = collect((array) ($attributes['related_post_ids'] ?? []))
                ->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id > 0 && $id !== $post->id)->unique()->values();
            $post->relatedPosts()->sync($relatedIds->mapWithKeys(fn (int $id, int $position): array => [$id => ['sort_order' => $position]])->all());
            $this->syncSources($post, $sources);
            $post->refresh();

            $after = $this->snapshot($post);
            if ($this->snapshotHash($before) !== $this->snapshotHash($after)) {
                if ($post->reviewed_at !== null
                    || $post->submitted_for_review_at !== null
                    || in_array($post->status, [BlogPost::STATUS_IN_REVIEW, BlogPost::STATUS_SCHEDULED], true)
                    || $post->published_revision_id !== null
                ) {
                    $post->forceFill([
                        'status' => BlogPost::STATUS_DRAFT,
                        'submitted_for_review_at' => null,
                        'submitted_by_user_id' => null,
                        'reviewed_at' => null,
                        'reviewed_by_user_id' => null,
                        'reviewer_id' => null,
                        'scheduled_for' => null,
                    ])->save();
                }

                $post->forceFill([
                    'edit_version' => $post->edit_version + 1,
                    'last_edited_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ])->save();

                if ($recordPermanentRevision) {
                    $this->recordRevision($post, $actor, $summary);
                }
            }

            return $post->fresh(['author', 'reviewer', 'featuredMedia.variants', 'categories', 'tags', 'sources']);
        });
    }

    public function submitForReview(BlogPost $post, User $actor): BlogPost
    {
        $this->assertCanEdit($post, $actor);

        return DB::transaction(function () use ($post, $actor): BlogPost {
            $post = BlogPost::query()->lockForUpdate()->findOrFail($post->id);
            $inspection = $this->readiness->inspect($post);
            $reviewIssue = 'Complete an independent editorial review.';
            $blocking = array_values(array_filter($inspection['issues'], fn (string $issue): bool => $issue !== $reviewIssue));
            if ($blocking !== []) {
                throw ValidationException::withMessages(['publish' => $blocking]);
            }

            $post->forceFill([
                'status' => BlogPost::STATUS_IN_REVIEW,
                'submitted_for_review_at' => now(),
                'submitted_by_user_id' => $actor->id,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'reviewer_id' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();
            $this->recordRevision($post, $actor, 'Submitted for editorial review');

            return $post->fresh();
        });
    }

    public function approveReview(BlogPost $post, User $reviewer, ?string $notes = null): BlogPost
    {
        if (! $reviewer->canReviewContent()) {
            throw ValidationException::withMessages(['authorization' => 'An editor or publisher account is required to approve review.']);
        }

        return DB::transaction(function () use ($post, $reviewer, $notes): BlogPost {
            $post = BlogPost::query()->lockForUpdate()->with('author')->findOrFail($post->id);
            if ($post->status !== BlogPost::STATUS_IN_REVIEW || ! $post->submitted_for_review_at || ! $post->submitted_by_user_id) {
                throw ValidationException::withMessages(['review' => 'Submit the article for review before approving it.']);
            }

            $conflictingUserIds = array_filter([
                $post->author?->user_id,
                $post->last_edited_by_user_id,
                $post->submitted_by_user_id,
            ]);
            if (in_array((int) $reviewer->id, array_map('intval', $conflictingUserIds), true)) {
                throw ValidationException::withMessages([
                    'review' => 'Independent review must be completed by someone other than the author, last editor, or submitter.',
                ]);
            }

            if (mb_strlen(trim((string) $notes)) < 20) {
                throw ValidationException::withMessages(['review' => 'Record substantive review notes before approval.']);
            }

            $profile = $this->authorProfileFor($reviewer);
            if (mb_strlen(trim((string) $profile->bio)) < 60 || trim((string) $profile->credentials) === '') {
                throw ValidationException::withMessages(['review' => 'Complete the reviewer bio and visible credentials before approval.']);
            }
            $post->forceFill([
                'reviewer_id' => $profile->id,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'updated_by_user_id' => $reviewer->id,
            ])->save();
            $this->recordRevision($post, $reviewer, 'Editorial review approved');

            return $post->fresh(['reviewer']);
        });
    }

    public function publish(BlogPost $post, User $publisher, mixed $when = null): BlogPost
    {
        if (! $publisher->canPublishContent()) {
            throw ValidationException::withMessages(['authorization' => 'A publisher account is required to publish content.']);
        }

        $publishAt = $when ? now()->parse($when) : now();
        if ($publishAt->isFuture()) {
            return DB::transaction(function () use ($post, $publisher, $publishAt): BlogPost {
                $post = BlogPost::query()->lockForUpdate()->findOrFail($post->id);
                $inspection = $this->readiness->inspect($post);
                if (! $inspection['ready']) {
                    throw ValidationException::withMessages(['publish' => $inspection['issues']]);
                }

                $post->forceFill([
                    'status' => BlogPost::STATUS_SCHEDULED,
                    'scheduled_for' => $publishAt,
                    'published_by_user_id' => $publisher->id,
                    'updated_by_user_id' => $publisher->id,
                ])->save();
                $this->recordRevision($post, $publisher, 'Publication scheduled for '.$publishAt->toIso8601String());

                return $post->fresh();
            });
        }

        return $this->publishNow($post, $publisher);
    }

    public function publishNow(BlogPost $post, User $publisher, bool $scheduledExecution = false): BlogPost
    {
        if (! $publisher->canPublishContent()) {
            throw ValidationException::withMessages(['authorization' => 'A publisher account is required to publish content.']);
        }

        return DB::transaction(function () use ($post, $publisher, $scheduledExecution): BlogPost {
            $post = BlogPost::query()->lockForUpdate()->findOrFail($post->id);
            if ($scheduledExecution && ($post->status !== BlogPost::STATUS_SCHEDULED || ! $post->scheduled_for?->lte(now()))) {
                throw ValidationException::withMessages(['publish' => 'This article is no longer due for scheduled publication.']);
            }
            if (! in_array($post->status, [BlogPost::STATUS_IN_REVIEW, BlogPost::STATUS_SCHEDULED, BlogPost::STATUS_PUBLISHED], true)) {
                throw ValidationException::withMessages(['publish' => 'Submit and approve the working draft before publication.']);
            }

            $inspection = $this->readiness->inspect($post);
            if (! $inspection['ready']) {
                throw ValidationException::withMessages(['publish' => $inspection['issues']]);
            }

            $previousPublicSlug = (string) data_get($post->publishedRevision?->snapshot, 'slug', '');
            $revision = $this->recordRevision($post, $publisher, 'Published');
            $now = now();

            $post->forceFill([
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_revision_id' => $revision->id,
                'first_published_at' => $post->first_published_at ?? $now,
                'last_published_at' => $now,
                'scheduled_for' => null,
                'published_by_user_id' => $publisher->id,
                'robots_index' => true,
                'robots_directives' => 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1',
                'content_review_due_at' => $now->copy()->addMonths(6),
                'updated_by_user_id' => $publisher->id,
            ])->save();

            if ($previousPublicSlug !== '' && $previousPublicSlug !== $post->slug) {
                UrlRedirect::query()->updateOrCreate(
                    ['source_path' => '/blog/'.$previousPublicSlug],
                    [
                        'destination_path' => '/blog/'.$post->slug,
                        'status_code' => 301,
                        'is_active' => true,
                        'created_by_user_id' => $publisher->id,
                    ]
                );
            }

            $urls = [
                route('blog.show', ['blogSlug' => $post->slug]),
                route('blog.index'),
                route('blog.feed'),
                route('sitemap.xml'),
            ];
            if ($previousPublicSlug !== '' && $previousPublicSlug !== $post->slug) {
                $urls[] = route('blog.show', ['blogSlug' => $previousPublicSlug]);
            }
            SubmitIndexNowUrls::dispatch($urls)->afterCommit();
            Cache::forget('content.public-category-counts');

            return $post->fresh(['publishedRevision']);
        });
    }

    public function archive(BlogPost $post, User $actor): BlogPost
    {
        if (! $actor->canPublishContent()) {
            throw ValidationException::withMessages(['authorization' => 'A publisher account is required to archive content.']);
        }

        $publicSlug = (string) data_get($post->publishedRevision?->snapshot, 'slug', $post->slug);
        $post->forceFill([
            'status' => BlogPost::STATUS_ARCHIVED,
            'robots_index' => false,
            'robots_directives' => 'noindex,nofollow',
            'updated_by_user_id' => $actor->id,
        ])->save();
        $this->recordRevision($post, $actor, 'Archived and removed from search');
        SubmitIndexNowUrls::dispatch([
            route('blog.show', ['blogSlug' => $publicSlug]),
            route('blog.index'),
            route('sitemap.xml'),
        ])->afterCommit();
        Cache::forget('content.public-category-counts');

        return $post->fresh();
    }

    public function restoreRevision(BlogPost $post, BlogPostRevision $revision, User $actor): BlogPost
    {
        $this->assertCanEdit($post, $actor);
        abort_unless((int) $revision->blog_post_id === (int) $post->id, 404);
        $snapshot = $revision->snapshot;

        return $this->save(
            $post,
            $snapshot,
            $actor,
            (array) ($snapshot['category_ids'] ?? []),
            (array) ($snapshot['tag_ids'] ?? []),
            (array) ($snapshot['sources'] ?? []),
            'Restored revision '.$revision->revision_number,
        );
    }

    public function recordRevision(BlogPost $post, ?User $actor, string $summary): BlogPostRevision
    {
        $post->loadMissing(['categories', 'tags', 'sources', 'relatedPosts']);
        $snapshot = $this->snapshot($post);
        $latest = $post->revisions()->first();

        if ($latest && $this->snapshotHash((array) $latest->snapshot) === $this->snapshotHash($snapshot)) {
            return $latest;
        }

        $number = ((int) $post->revision_number) + 1;
        $revision = $post->revisions()->create([
            'revision_number' => $number,
            'snapshot' => $snapshot,
            'actor_user_id' => $actor?->id,
            'change_summary' => Str::limit(trim($summary), 255, ''),
        ]);
        $post->forceFill(['revision_number' => $number])->saveQuietly();

        return $revision;
    }

    /** @return array<string,mixed> */
    public function snapshot(BlogPost $post): array
    {
        $post->loadMissing(['categories', 'tags', 'sources']);

        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content_json' => $post->content_json,
            'body_html' => $post->body_html,
            'plain_text' => $post->plain_text,
            'table_of_contents' => $post->table_of_contents,
            'author_id' => $post->author_id,
            'reviewer_id' => $post->reviewer_id,
            'reviewed_at' => $post->reviewed_at?->toIso8601String(),
            'featured_media_asset_id' => $post->featured_media_asset_id,
            'seo_title' => $post->seo_title,
            'meta_description' => $post->meta_description,
            'canonical_url' => $post->canonical_url,
            'social_title' => $post->social_title,
            'social_description' => $post->social_description,
            'schema_type' => $post->schema_type,
            'content_type' => $post->content_type,
            'locale' => $post->locale,
            'word_count' => $post->word_count,
            'read_minutes' => $post->read_minutes,
            'editorial_checklist' => $post->editorial_checklist,
            'review_notes' => $post->review_notes,
            'research_methodology' => $post->research_methodology,
            'category_ids' => $post->categories->modelKeys(),
            'tag_ids' => $post->tags->modelKeys(),
            'related_post_ids' => $post->relatedPosts->modelKeys(),
            'sources' => $post->sources->map->only([
                'uuid', 'position', 'title', 'publisher', 'url', 'published_on', 'accessed_on', 'notes',
            ])->values()->all(),
        ];
    }

    /** @param list<array<string,mixed>> $sources */
    private function syncSources(BlogPost $post, array $sources): void
    {
        $kept = [];
        foreach ($sources as $position => $source) {
            if (trim((string) ($source['title'] ?? '')) === '' || trim((string) ($source['url'] ?? '')) === '') {
                continue;
            }

            $uuid = (string) $source['uuid'];
            $kept[] = $uuid;
            $post->sources()->updateOrCreate(['uuid' => $uuid], [
                'position' => $position,
                'title' => trim((string) $source['title']),
                'publisher' => trim((string) ($source['publisher'] ?? '')) ?: null,
                'url' => trim((string) $source['url']),
                'published_on' => ($source['published_on'] ?? null) ?: null,
                'accessed_on' => ($source['accessed_on'] ?? null) ?: now()->toDateString(),
                'notes' => trim((string) ($source['notes'] ?? '')) ?: null,
            ]);
        }

        $delete = $post->sources()->getQuery();
        if ($kept !== []) {
            $delete->whereNotIn('uuid', $kept);
        }
        $delete->delete();
    }

    /** @param list<array<string,mixed>> $sources @return list<array<string,mixed>> */
    private function normalizeSources(array $sources): array
    {
        return collect($sources)->values()->map(function (array $source, int $position): array {
            $uuid = (string) ($source['uuid'] ?? '');
            $source['uuid'] = Str::isUuid($uuid) ? $uuid : (string) Str::uuid();
            $source['position'] = $position;

            return $source;
        })->all();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'untitled-article';
        $slug = $base;
        $suffix = 2;
        while (BlogPost::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function authorProfileFor(User $user): ContentAuthor
    {
        return ContentAuthor::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name,
                'slug' => $this->uniqueAuthorSlug($user->name),
                'headline' => 'LoLo Care editorial reviewer',
                'is_active' => true,
            ]
        );
    }

    private function uniqueAuthorSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'lolo-editor';
        $slug = $base;
        $suffix = 2;
        while (ContentAuthor::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function assertCanEdit(BlogPost $post, User $actor): void
    {
        $canEdit = $actor->canReviewContent()
            || ($actor->content_role === 'author' && (int) $post->created_by_user_id === (int) $actor->id);
        if (! $canEdit) {
            throw ValidationException::withMessages(['authorization' => 'You cannot edit this article.']);
        }
    }

    private function snapshotHash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }
}
