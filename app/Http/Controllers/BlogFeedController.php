<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\Content\PublicBlogPresenter;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class BlogFeedController extends Controller
{
    public function __invoke(PublicBlogPresenter $presenter): Response
    {
        $posts = BlogPost::published()->with('publishedRevision')->latest('last_published_at')->limit(50)->get();
        $feedUpdatedAt = Carbon::createFromTimestamp(filemtime(resource_path('views/marketing/blog-feed.blade.php')) ?: 0);
        $xml = view('marketing.blog-feed', [
            'posts' => $presenter->presentMany($posts),
            'feedUpdatedAt' => $feedUpdatedAt,
        ])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/atom+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }
}
