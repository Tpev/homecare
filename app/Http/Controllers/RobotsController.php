<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        $appUrl = rtrim(config('app.url'), '/');
        $sitemapUrl = $appUrl !== '' ? $appUrl.'/sitemap.xml' : '/sitemap.xml';

        return response(
            "User-agent: *\nAllow: /\n\nSitemap: {$sitemapUrl}\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
