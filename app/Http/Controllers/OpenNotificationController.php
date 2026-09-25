<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OpenNotificationController extends Controller
{
    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $url = (string) data_get($item->data, 'url', '');
        if (str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            $url = '';
        }
        $target = parse_url($url);
        $app = parse_url(url('/'));
        $local = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $sameOrigin = is_array($target) && isset($target['host'])
            && ($target['scheme'] ?? '') === ($app['scheme'] ?? '')
            && $target['host'] === ($app['host'] ?? '')
            && ($target['port'] ?? null) === ($app['port'] ?? null);

        $item->markAsRead();

        return redirect($url !== '' && ($local || $sameOrigin) ? $url : route('dashboard'));
    }
}
