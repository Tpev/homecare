<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Models\MediaAsset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PublicBlogPresenter
{
    /** @var array<int,ContentAuthor|null> */
    private array $authorCache = [];

    /** @var array<int,MediaAsset|null> */
    private array $mediaCache = [];

    /** @var array<int,ContentCategory|null> */
    private array $categoryCache = [];

    /** @var array<int,ContentTag|null> */
    private array $tagCache = [];

    /** @return array<string,mixed> */
    public function present(BlogPost $post, bool $workingDraft = false): array
    {
        $snapshot = $workingDraft
            ? app(BlogPostWorkflow::class)->snapshot($post)
            : (array) $post->publishedRevision?->snapshot;

        if ($snapshot === []) {
            $snapshot = app(BlogPostWorkflow::class)->snapshot($post);
        }

        $author = $this->author((int) ($snapshot['author_id'] ?? 0));
        $reviewer = $this->author((int) ($snapshot['reviewer_id'] ?? 0));
        $media = $this->media((int) ($snapshot['featured_media_asset_id'] ?? 0));
        $categories = collect((array) ($snapshot['category_ids'] ?? []))
            ->map(fn ($id): ?ContentCategory => $this->category((int) $id))
            ->filter()->unique('id')->sortBy('sort_order')->values();
        $tags = collect((array) ($snapshot['tag_ids'] ?? []))
            ->map(fn ($id): ?ContentTag => $this->tag((int) $id))
            ->filter()->unique('id')->sortBy('name')->values();
        $sources = collect((array) ($snapshot['sources'] ?? []))->values();
        $faqs = $this->extractFaqs((array) ($snapshot['content_json'] ?? []));

        $modifiedAt = $post->publishedRevision?->created_at;
        if ($post->last_published_at && (! $modifiedAt || $post->last_published_at->gt($modifiedAt))) {
            $modifiedAt = $post->last_published_at;
        }

        return [
            'model' => $post,
            'id' => $post->id,
            'title' => (string) ($snapshot['title'] ?? $post->title),
            'slug' => (string) ($snapshot['slug'] ?? $post->slug),
            'path' => '/blog/'.(string) ($snapshot['slug'] ?? $post->slug),
            'url' => route('blog.show', ['blogSlug' => (string) ($snapshot['slug'] ?? $post->slug)]),
            'excerpt' => (string) ($snapshot['excerpt'] ?? ''),
            'body_html' => (string) ($snapshot['body_html'] ?? ''),
            'plain_text' => (string) ($snapshot['plain_text'] ?? ''),
            'table_of_contents' => (array) ($snapshot['table_of_contents'] ?? []),
            'word_count' => (int) ($snapshot['word_count'] ?? 0),
            'read_minutes' => (int) ($snapshot['read_minutes'] ?? 1),
            'author' => $author,
            'reviewer' => $reviewer,
            'featured_media' => $media,
            'categories' => $categories,
            'tags' => $tags,
            'sources' => $sources,
            'related_post_ids' => array_values(array_map('intval', (array) ($snapshot['related_post_ids'] ?? []))),
            'faqs' => $faqs,
            'research_methodology' => (string) ($snapshot['research_methodology'] ?? ''),
            'seo_title' => (string) (($snapshot['seo_title'] ?? null) ?: ($snapshot['title'] ?? $post->title)),
            'meta_description' => (string) (($snapshot['meta_description'] ?? null) ?: ($snapshot['excerpt'] ?? '')),
            'social_title' => (string) (($snapshot['social_title'] ?? null) ?: (($snapshot['seo_title'] ?? null) ?: ($snapshot['title'] ?? $post->title))),
            'social_description' => (string) (($snapshot['social_description'] ?? null) ?: (($snapshot['meta_description'] ?? null) ?: ($snapshot['excerpt'] ?? ''))),
            'canonical_url' => (string) (($snapshot['canonical_url'] ?? null) ?: route('blog.show', ['blogSlug' => (string) ($snapshot['slug'] ?? $post->slug)])),
            'schema_type' => (string) ($snapshot['schema_type'] ?? 'BlogPosting'),
            'content_type' => (string) ($snapshot['content_type'] ?? 'guide'),
            'robots_directives' => $workingDraft ? 'noindex,nofollow,noarchive' : $post->robots_directives,
            'published_at' => $post->first_published_at,
            'modified_at' => $modifiedAt,
            'reviewed_at' => ! empty($snapshot['reviewed_at']) ? Carbon::parse($snapshot['reviewed_at']) : null,
        ];
    }

    /** @return Collection<int,array<string,mixed>> */
    public function presentMany(iterable $posts): Collection
    {
        $posts = collect($posts);
        $this->prime($posts);

        return $posts->map(fn (BlogPost $post): array => $this->present($post));
    }

    /** @param Collection<int,BlogPost> $posts */
    private function prime(Collection $posts): void
    {
        $snapshots = $posts->map(fn (BlogPost $post): array => (array) $post->publishedRevision?->snapshot);
        $authorIds = $snapshots->flatMap(fn (array $snapshot): array => [$snapshot['author_id'] ?? null, $snapshot['reviewer_id'] ?? null])->filter()->unique()->values();
        $mediaIds = $snapshots->pluck('featured_media_asset_id')->filter()->unique()->values();
        $categoryIds = $snapshots->flatMap(fn (array $snapshot): array => (array) ($snapshot['category_ids'] ?? []))->filter()->unique()->values();
        $tagIds = $snapshots->flatMap(fn (array $snapshot): array => (array) ($snapshot['tag_ids'] ?? []))->filter()->unique()->values();

        ContentAuthor::query()->with('avatar.variants')->whereIn('id', $authorIds)->get()->each(fn (ContentAuthor $author) => $this->authorCache[$author->id] = $author);
        MediaAsset::query()->with('variants')->whereIn('id', $mediaIds)->get()->each(fn (MediaAsset $media) => $this->mediaCache[$media->id] = $media);
        if ($categoryIds->isNotEmpty()) {
            ContentCategory::query()->get()->each(fn (ContentCategory $category) => $this->categoryCache[$category->id] = $category);
        }
        if ($tagIds->isNotEmpty()) {
            ContentTag::query()->get()->each(fn (ContentTag $tag) => $this->tagCache[$tag->id] = $tag);
        }
    }

    private function author(int $id): ?ContentAuthor
    {
        if ($id < 1) {
            return null;
        }
        if (! array_key_exists($id, $this->authorCache)) {
            $this->authorCache[$id] = ContentAuthor::query()->with('avatar.variants')->find($id);
        }

        return $this->authorCache[$id];
    }

    private function media(int $id): ?MediaAsset
    {
        if ($id < 1) {
            return null;
        }
        if (! array_key_exists($id, $this->mediaCache)) {
            $this->mediaCache[$id] = MediaAsset::query()->with('variants')->find($id);
        }

        return $this->mediaCache[$id];
    }

    private function category(int $id): ?ContentCategory
    {
        if ($id < 1) {
            return null;
        }
        if (! array_key_exists($id, $this->categoryCache)) {
            $this->categoryCache[$id] = ContentCategory::query()->find($id);
        }

        $category = $this->categoryCache[$id];

        return $category?->merged_into_id ? $this->category((int) $category->merged_into_id) : $category;
    }

    private function tag(int $id): ?ContentTag
    {
        if ($id < 1) {
            return null;
        }
        if (! array_key_exists($id, $this->tagCache)) {
            $this->tagCache[$id] = ContentTag::query()->find($id);
        }

        $tag = $this->tagCache[$id];

        return $tag?->merged_into_id ? $this->tag((int) $tag->merged_into_id) : $tag;
    }

    /** @return list<array{question:string,answer:string}> */
    private function extractFaqs(array $node): array
    {
        $faqs = [];
        $walk = function (array $current) use (&$walk, &$faqs): void {
            if (($current['type'] ?? null) === 'faqItem') {
                $answer = trim($this->nodeText($current));
                $faqs[] = [
                    'question' => trim((string) data_get($current, 'attrs.question', 'Question')),
                    'answer' => $answer,
                ];
            }
            foreach ((array) ($current['content'] ?? []) as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($node);

        return $faqs;
    }

    private function nodeText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }

        return trim(implode(' ', array_map(fn (array $child): string => $this->nodeText($child), (array) ($node['content'] ?? []))));
    }
}
