<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;

class BlogPostPreviewController extends Controller
{
    public function __invoke(BlogPost $blogPost): View
    {
        $blogPost->load(['author', 'reviewer', 'featuredMedia.variants', 'categories', 'tags', 'sources']);

        return view('marketing.blog-preview', ['post' => $blogPost]);
    }
}
