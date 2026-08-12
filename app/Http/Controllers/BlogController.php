<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use App\Models\UrlRedirect;
use App\Services\Content\PublicBlogPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    public function index(Request $request, PublicBlogPresenter $presenter): View
    {
        $posts = BlogPost::published()
            ->with('publishedRevision')
            ->latest('first_published_at')
            ->paginate(12);
        $presented = $presenter->presentMany($posts->items());

        $allCategories = ContentCategory::query()->orderBy('sort_order')->orderBy('name')->get();
        $categoryCounts = Cache::remember('content.public-category-counts', now()->addMinutes(15), function () use ($allCategories): array {
            $destinations = $allCategories->mapWithKeys(fn (ContentCategory $category): array => [
                $category->id => (int) ($category->merged_into_id ?: $category->id),
            ]);
            $counts = [];
            BlogPost::published()->with('publishedRevision:id,snapshot')->select(['id', 'published_revision_id'])->each(function (BlogPost $post) use (&$counts, $destinations): void {
                foreach (array_unique((array) data_get($post->publishedRevision?->snapshot, 'category_ids', [])) as $categoryId) {
                    $destinationId = (int) ($destinations[(int) $categoryId] ?? $categoryId);
                    $counts[$destinationId] = ($counts[$destinationId] ?? 0) + 1;
                }
            });

            return $counts;
        });
        $categories = $allCategories->filter(function (ContentCategory $category) use ($categoryCounts): bool {
            $category->setAttribute('posts_count', $categoryCounts[$category->id] ?? 0);

            return $category->is_active && $category->merged_into_id === null && $category->posts_count > 0;
        });

        return view('marketing.blog-index', [
            'posts' => $posts,
            'presentedPosts' => $presented,
            'featured' => $presented->first(),
            'categories' => $categories,
        ]);
    }

    public function show(string $blogSlug, PublicBlogPresenter $presenter): View|RedirectResponse
    {
        $post = BlogPost::published()
            ->with('publishedRevision')
            ->whereHas('publishedRevision', fn ($query) => $query->where('snapshot->slug', $blogSlug))
            ->first();
        if (! $post) {
            $redirect = UrlRedirect::active()->where('source_path', '/blog/'.$blogSlug)->first();
            if ($redirect) {
                return redirect($redirect->destination_path, $redirect->status_code);
            }
            abort(404);
        }

        $presented = $presenter->present($post);
        $categoryIds = $presented['categories']->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $relatedIds = array_slice($presented['related_post_ids'], 0, 4);
        $relatedModels = BlogPost::published()->with('publishedRevision')->whereKey($relatedIds)->get()
            ->sortBy(function (BlogPost $related) use ($relatedIds): int {
                $position = array_search($related->id, $relatedIds, true);

                return $position === false ? PHP_INT_MAX : $position;
            })->values();
        if ($relatedModels->count() < 4 && $categoryIds !== []) {
            $fallback = BlogPost::published()
                ->with('publishedRevision')
                ->whereNotIn('id', $relatedModels->modelKeys())
                ->whereKeyNot($post->id)
                ->publishedInAnyCategory($categoryIds)
                ->latest('first_published_at')
                ->limit(4 - $relatedModels->count())
                ->get();
            $relatedModels = $relatedModels->concat($fallback)->values();
        }

        return view('marketing.blog-show', [
            'post' => $presented,
            'relatedPosts' => $presenter->presentMany($relatedModels),
        ]);
    }

    public function category(string $category, PublicBlogPresenter $presenter): View|RedirectResponse
    {
        $categorySlug = $category;
        $category = ContentCategory::query()->where('slug', $categorySlug)->first();
        if (! $category) {
            return $this->redirectOrAbort('/blog/category/'.$categorySlug);
        }
        if ($category->merged_into_id) {
            return redirect()->route('blog.category', $category->mergedInto()->firstOrFail(), 301);
        }
        abort_unless($category->is_active || BlogPost::published()->publishedInCategory($category->id)->exists(), 404);
        $posts = BlogPost::published()->publishedInCategory($category->id)->with('publishedRevision')->latest('first_published_at')->paginate(12);

        return view('marketing.blog-hub', [
            'hubType' => 'Category', 'hub' => $category, 'posts' => $posts,
            'presentedPosts' => $presenter->presentMany($posts->items()),
        ]);
    }

    public function tag(string $tag, PublicBlogPresenter $presenter): View|RedirectResponse
    {
        $tagSlug = $tag;
        $tag = ContentTag::query()->where('slug', $tagSlug)->first();
        if (! $tag) {
            return $this->redirectOrAbort('/blog/topic/'.$tagSlug);
        }
        if ($tag->merged_into_id) {
            return redirect()->route('blog.tag', $tag->mergedInto()->firstOrFail(), 301);
        }
        $posts = BlogPost::published()->publishedWithTag($tag->id)->with('publishedRevision')->latest('first_published_at')->paginate(12);

        return view('marketing.blog-hub', [
            'hubType' => 'Topic', 'hub' => $tag, 'posts' => $posts,
            'presentedPosts' => $presenter->presentMany($posts->items()),
        ]);
    }

    public function author(string $author, PublicBlogPresenter $presenter): View|RedirectResponse
    {
        $authorSlug = $author;
        $author = ContentAuthor::query()->where('slug', $authorSlug)->first();
        if (! $author) {
            return $this->redirectOrAbort('/blog/authors/'.$authorSlug);
        }
        abort_unless(BlogPost::published()->publishedAttributedToAuthor($author->id)->exists(), 404);
        $posts = BlogPost::published()->publishedAttributedToAuthor($author->id)->with('publishedRevision')->latest('first_published_at')->paginate(12);

        return view('marketing.blog-author', [
            'author' => $author->load('avatar.variants'), 'posts' => $posts,
            'presentedPosts' => $presenter->presentMany($posts->items()),
        ]);
    }

    private function redirectOrAbort(string $path): RedirectResponse
    {
        $redirect = UrlRedirect::active()->where('source_path', $path)->first();
        if ($redirect) {
            return redirect($redirect->destination_path, $redirect->status_code);
        }

        abort(404);
    }
}
