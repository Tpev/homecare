<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Models\MediaAsset;

class ContentApiPresenter
{
    public function __construct(private readonly BlogPostReadiness $readiness) {}

    /** @return array<string,mixed> */
    public function article(BlogPost $post, bool $detailed = true): array
    {
        $post->loadMissing([
            'author', 'reviewer', 'featuredMedia.variants', 'categories', 'tags', 'sources',
            'relatedPosts:id,title,slug,status', 'publishedRevision:id,revision_number,created_at',
        ]);
        $inspection = $this->readiness->inspect($post);

        $data = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'excerpt' => $post->excerpt,
            'edit_version' => $post->edit_version,
            'revision_number' => $post->revision_number,
            'word_count' => $post->word_count,
            'read_minutes' => $post->read_minutes,
            'scheduled_for' => $post->scheduled_for?->toIso8601String(),
            'first_published_at' => $post->first_published_at?->toIso8601String(),
            'last_published_at' => $post->last_published_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'readiness' => $inspection,
            'author' => $this->author($post->author),
            'reviewer' => $this->author($post->reviewer),
            'categories' => $post->categories->map(fn (ContentCategory $category): array => $this->category($category))->values()->all(),
            'tags' => $post->tags->map(fn (ContentTag $tag): array => $this->tag($tag))->values()->all(),
            'featured_media' => $post->featuredMedia ? $this->media($post->featuredMedia) : null,
        ];

        if (! $detailed) {
            return $data;
        }

        return array_merge($data, [
            'content_json' => $post->content_json,
            'body_html' => $post->body_html,
            'plain_text' => $post->plain_text,
            'table_of_contents' => $post->table_of_contents ?? [],
            'seo' => [
                'title' => $post->seo_title,
                'meta_description' => $post->meta_description,
                'canonical_url' => $post->canonical_url,
                'social_title' => $post->social_title,
                'social_description' => $post->social_description,
                'schema_type' => $post->schema_type,
                'content_type' => $post->content_type,
                'locale' => $post->locale,
            ],
            'editorial_checklist' => $post->editorial_checklist ?? [],
            'review_notes' => $post->review_notes,
            'research_methodology' => $post->research_methodology,
            'sources' => $post->sources->map(fn ($source): array => [
                'uuid' => $source->uuid,
                'position' => $source->position,
                'title' => $source->title,
                'publisher' => $source->publisher,
                'url' => $source->url,
                'published_on' => $source->published_on?->toDateString(),
                'accessed_on' => $source->accessed_on?->toDateString(),
                'notes' => $source->notes,
            ])->values()->all(),
            'related_posts' => $post->relatedPosts->map(fn (BlogPost $related): array => [
                'id' => $related->id,
                'title' => $related->title,
                'slug' => $related->slug,
                'status' => $related->status,
            ])->values()->all(),
            'workflow' => [
                'submitted_for_review_at' => $post->submitted_for_review_at?->toIso8601String(),
                'reviewed_at' => $post->reviewed_at?->toIso8601String(),
                'content_review_due_at' => $post->content_review_due_at?->toIso8601String(),
                'published_revision' => $post->publishedRevision ? [
                    'id' => $post->publishedRevision->id,
                    'number' => $post->publishedRevision->revision_number,
                    'created_at' => $post->publishedRevision->created_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /** @return array<string,mixed> */
    public function media(MediaAsset $asset): array
    {
        $asset->loadMissing('variants');

        return [
            'id' => $asset->id,
            'filename' => $asset->original_filename,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'credit' => $asset->credit,
            'license' => $asset->license,
            'source_url' => $asset->source_url,
            'url' => $asset->url,
            'variants' => $asset->variants->map(fn ($variant): array => [
                'name' => $variant->variant,
                'mime_type' => $variant->mime_type,
                'size_bytes' => $variant->size_bytes,
                'width' => $variant->width,
                'height' => $variant->height,
                'url' => $asset->variantUrl($variant->variant),
            ])->values()->all(),
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function author(?ContentAuthor $author): ?array
    {
        return $author ? [
            'id' => $author->id,
            'name' => $author->name,
            'slug' => $author->slug,
            'headline' => $author->headline,
            'bio' => $author->bio,
            'credentials' => $author->credentials,
            'profile_url' => $author->profile_url,
            'is_active' => $author->is_active,
        ] : null;
    }

    /** @return array<string,mixed> */
    public function category(ContentCategory $category): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'is_active' => $category->is_active];
    }

    /** @return array<string,mixed> */
    public function tag(ContentTag $tag): array
    {
        return ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug];
    }
}
