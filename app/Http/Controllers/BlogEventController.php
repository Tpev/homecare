<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogEventController extends Controller
{
    public function __invoke(Request $request, BlogPost $blogPost): JsonResponse
    {
        abort_unless(BlogPost::published()->whereKey($blogPost->id)->exists(), 404);
        if (preg_match('/(?:bot|crawler|spider|slurp|headlesschrome|lighthouse)/i', (string) $request->userAgent())) {
            return response()->json(['recorded' => false], 202);
        }

        $data = $request->validate([
            'event' => ['required', Rule::in(['page_view', 'read_50', 'read_complete', 'cta_click', 'source_click'])],
            'href' => ['nullable', 'string', 'max:1000'],
            'placement' => ['nullable', 'string', 'max:100'],
            'utm_source' => ['nullable', 'string', 'max:100'],
        ]);
        $visitorToken = (string) ($request->header('X-Content-Visitor') ?: $request->cookie('lolo_content_visitor'));
        if (! preg_match('/^[A-Za-z0-9-]{16,100}$/', $visitorToken)) {
            $visitorToken = (string) Str::uuid();
        }
        $sessionHash = hash_hmac('sha256', $visitorToken, (string) config('app.key'));
        $href = trim((string) ($data['href'] ?? ''));
        $hrefHost = $href !== '' ? parse_url($href, PHP_URL_HOST) : null;
        $hrefPath = $href !== '' ? parse_url($href, PHP_URL_PATH) : null;
        $dedupeKey = hash_hmac('sha256', implode('|', [
            $blogPost->id,
            $data['event'],
            (string) ($data['placement'] ?? ''),
            (string) $hrefHost,
            (string) $hrefPath,
            now()->toDateString(),
            $sessionHash,
        ]), (string) config('app.key'));

        $blogPost->events()->firstOrCreate(['dedupe_key' => $dedupeKey], [
            'event' => $data['event'],
            'session_hash' => $sessionHash,
            'user_id' => $request->user()?->id,
            'metadata' => array_filter([
                'href_host' => $hrefHost,
                'href_path' => $hrefPath,
                'placement' => $data['placement'] ?? null,
                'referrer_host' => parse_url((string) $request->headers->get('referer'), PHP_URL_HOST),
                'utm_source' => $data['utm_source'] ?? null,
            ]),
            'occurred_at' => now(),
        ]);

        if ($data['event'] === 'cta_click') {
            $request->session()->put('content_attribution', [
                'blog_post_id' => $blogPost->id,
                'placement' => $data['placement'] ?? null,
                'clicked_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json(['recorded' => true], 202)->cookie(
            'lolo_content_visitor',
            $visitorToken,
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );
    }
}
