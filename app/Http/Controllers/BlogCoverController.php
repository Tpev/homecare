<?php

namespace App\Http\Controllers;

use App\Services\Content\BlogContentService;
use Illuminate\Http\Response;

class BlogCoverController extends Controller
{
    public function __invoke(string $blogSlug, BlogContentService $blogs): Response
    {
        $binary = $blogs->coverBinaryForSlug($blogSlug);

        if (! $binary) {
            return response('', 404);
        }

        return response($binary['content'], 200, [
            'Content-Type' => $binary['mime'],
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
