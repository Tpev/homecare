<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        $privatePaths = [
            '/admin/', '/caregiver/', '/family/', '/care-requests/', '/dashboard', '/messages',
            '/notifications/', '/profile', '/support', '/access', '/login', '/register',
            '/forgot-password', '/reset-password', '/email/', '/livewire/', '/webhooks/',
        ];
        $groups = [];
        foreach (['*', 'OAI-SearchBot', 'ChatGPT-User', 'GPTBot', 'PerplexityBot', 'ClaudeBot'] as $agent) {
            $groups[] = 'User-agent: '.$agent;
            $groups[] = 'Allow: /';
            foreach ($privatePaths as $path) {
                $groups[] = 'Disallow: '.$path;
            }
            $groups[] = '';
        }
        $groups[] = 'Sitemap: '.route('sitemap.xml');

        return response(implode("\n", $groups)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
