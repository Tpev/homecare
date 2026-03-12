<?php

namespace App\Http\Controllers;

use App\Services\Content\BlogContentService;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function index(BlogContentService $blogs): View
    {
        $posts = $blogs->all();

        return view('marketing.blog-index', [
            'posts' => $posts,
        ]);
    }

    public function show(string $blogSlug, BlogContentService $blogs): View
    {
        $post = $blogs->findBySlug($blogSlug);
        abort_unless($post !== null, 404);

        return view('marketing.blog-show', [
            'post' => $post,
            'allPosts' => $blogs->all(),
        ]);
    }
}
