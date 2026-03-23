<?php

namespace App\Services\Analytics;

use App\Models\PageViewEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageViewTracker
{
    public const CAREGIVER_LANDING_EVENT = 'caregiver_landing_view';
    public const FAMILY_LANDING_EVENT = 'family_landing_view';

    /**
     * @return array{tracked: bool, should_set_cookie: bool, anon_id: ?string}
     */
    public function trackCaregiverLandingView(Request $request): array
    {
        return $this->trackLandingView($request, self::CAREGIVER_LANDING_EVENT);
    }

    /**
     * @return array{tracked: bool, should_set_cookie: bool, anon_id: ?string}
     */
    public function trackFamilyLandingView(Request $request): array
    {
        return $this->trackLandingView($request, self::FAMILY_LANDING_EVENT);
    }

    /**
     * @return array{tracked: bool, should_set_cookie: bool, anon_id: ?string}
     */
    private function trackLandingView(Request $request, string $eventName): array
    {
        if ($this->shouldSkip($request)) {
            return [
                'tracked' => false,
                'should_set_cookie' => false,
                'anon_id' => null,
            ];
        }

        $user = $request->user();
        $anonId = $request->cookie((string) config('analytics.anon_cookie_name', 'hc_anon_id'));
        $shouldSetCookie = false;

        if (! $user) {
            if (! is_string($anonId) || ! Str::isUuid($anonId)) {
                $anonId = (string) Str::uuid();
                $shouldSetCookie = true;
            }
        } else {
            $anonId = null;
        }

        PageViewEvent::query()->create([
            'event_name' => $eventName,
            'user_id' => $user?->id,
            'anon_id' => $anonId,
            'url' => (string) $request->fullUrl(),
            'referrer' => $this->truncate($request->headers->get('referer'), 2048),
            'utm_source' => $this->truncate($request->query('utm_source'), 255),
            'utm_medium' => $this->truncate($request->query('utm_medium'), 255),
            'utm_campaign' => $this->truncate($request->query('utm_campaign'), 255),
            'utm_term' => $this->truncate($request->query('utm_term'), 255),
            'utm_content' => $this->truncate($request->query('utm_content'), 255),
            'ip_hash' => $this->hashValue($request->ip()),
            'user_agent_hash' => $this->hashValue($request->userAgent()),
        ]);

        return [
            'tracked' => true,
            'should_set_cookie' => $shouldSetCookie,
            'anon_id' => $anonId,
        ];
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->is('up')) {
            return true;
        }

        $ua = strtolower((string) $request->userAgent());

        if ($ua === '') {
            return true;
        }

        $botMarkers = [
            'bot',
            'crawl',
            'crawler',
            'spider',
            'slurp',
            'headless',
            'pingdom',
            'uptime',
            'monitor',
            'statuscake',
            'curl',
            'wget',
            'python-requests',
        ];

        foreach ($botMarkers as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function hashValue(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $pepper = (string) config('app.key', '');

        return hash('sha256', $value.'|'.$pepper);
    }

    private function truncate(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, $limit, '');
    }
}
