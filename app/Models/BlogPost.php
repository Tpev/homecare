<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_IN_REVIEW => 'In review',
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'status', 'content_json', 'body_html', 'plain_text',
        'table_of_contents', 'author_id', 'reviewer_id', 'featured_media_asset_id',
        'created_by_user_id', 'updated_by_user_id', 'submitted_by_user_id', 'last_edited_by_user_id',
        'reviewed_by_user_id', 'published_by_user_id',
        'submitted_for_review_at', 'reviewed_at', 'scheduled_for', 'first_published_at',
        'last_published_at', 'content_review_due_at', 'seo_title', 'meta_description',
        'canonical_url', 'social_title', 'social_description', 'robots_index',
        'robots_directives', 'schema_type', 'content_type', 'locale', 'revision_number', 'edit_version',
        'published_revision_id',
        'word_count', 'read_minutes', 'editorial_checklist', 'review_notes',
        'research_methodology', 'source_import',
    ];

    protected $casts = [
        'content_json' => 'array',
        'table_of_contents' => 'array',
        'editorial_checklist' => 'array',
        'robots_index' => 'boolean',
        'revision_number' => 'integer',
        'edit_version' => 'integer',
        'word_count' => 'integer',
        'read_minutes' => 'integer',
        'submitted_for_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'first_published_at' => 'datetime',
        'last_published_at' => 'datetime',
        'content_review_due_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_revision_id')
            ->where('status', '!=', self::STATUS_ARCHIVED)
            ->where('robots_index', true)
            ->whereNotNull('first_published_at')
            ->where('first_published_at', '<=', now());
    }

    public function scopeDueForReview(Builder $query): Builder
    {
        return $query->whereNotNull('content_review_due_at')->where('content_review_due_at', '<=', now());
    }

    public function scopePublishedInCategory(Builder $query, int $categoryId): Builder
    {
        $categoryIds = ContentCategory::query()
            ->whereKey($categoryId)
            ->orWhere('merged_into_id', $categoryId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $query->publishedInAnyCategory($categoryIds ?: [$categoryId]);
    }

    /** @param list<int> $categoryIds */
    public function scopePublishedInAnyCategory(Builder $query, array $categoryIds): Builder
    {
        return $query->whereHas('publishedRevision', function (Builder $revision) use ($categoryIds): void {
            $revision->where(function (Builder $json) use ($categoryIds): void {
                foreach ($categoryIds as $categoryId) {
                    $json->orWhereJsonContains('snapshot->category_ids', (int) $categoryId);
                }
            });
        });
    }

    public function scopePublishedWithTag(Builder $query, int $tagId): Builder
    {
        $tagIds = ContentTag::query()
            ->whereKey($tagId)
            ->orWhere('merged_into_id', $tagId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $query->whereHas('publishedRevision', function (Builder $revision) use ($tagIds, $tagId): void {
            $revision->where(function (Builder $json) use ($tagIds, $tagId): void {
                foreach ($tagIds ?: [$tagId] as $id) {
                    $json->orWhereJsonContains('snapshot->tag_ids', (int) $id);
                }
            });
        });
    }

    public function scopePublishedByAuthor(Builder $query, int $authorId): Builder
    {
        return $query->whereHas('publishedRevision', fn (Builder $revision) => $revision->where('snapshot->author_id', $authorId));
    }

    public function scopePublishedAttributedToAuthor(Builder $query, int $authorId): Builder
    {
        return $query->whereHas('publishedRevision', fn (Builder $revision) => $revision
            ->where(fn (Builder $attribution) => $attribution
                ->where('snapshot->author_id', $authorId)
                ->orWhere('snapshot->reviewer_id', $authorId)));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(ContentAuthor::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(ContentAuthor::class, 'reviewer_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'featured_media_asset_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ContentCategory::class, 'blog_post_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContentTag::class, 'blog_post_tag');
    }

    public function relatedPosts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blog_post_related', 'blog_post_id', 'related_blog_post_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BlogPostRevision::class)->latest('revision_number');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(BlogPostRevision::class, 'published_revision_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(BlogPostSource::class)->orderBy('position')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BlogPostEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function canonicalPath(): string
    {
        return '/blog/'.$this->slug;
    }
}
