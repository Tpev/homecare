<?php

namespace App\Services\FamilyAcquisition;

use App\Models\PageViewEvent;
use App\Services\Analytics\PageViewTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FamilySignupAttribution
{
    public const SESSION_KEY = 'family_signup_attribution';

    private const UTM_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function capture(Request $request): void
    {
        if (! $request->isMethod('GET') || ! $request->routeIs('register') || ! $request->hasSession()) {
            return;
        }

        $values = ['url' => $request->url(), 'referrer' => $request->headers->get('referer')];
        foreach (self::UTM_FIELDS as $field) {
            $values[$field] = $request->query($field);
        }

        // The registration page is usually reached after an internal homepage link.
        $referrerHost = is_string($values['referrer']) ? parse_url($values['referrer'], PHP_URL_HOST) : null;
        $hasCampaign = collect(self::UTM_FIELDS)->contains(fn ($field) => is_string($values[$field]) && trim($values[$field]) !== '');
        if (! $hasCampaign && (! $referrerHost || $this->host($referrerHost) === $this->host($request->getHost()))) {
            $anonId = $request->cookie((string) config('analytics.anon_cookie_name', 'hc_anon_id'));
            $landing = is_string($anonId) && Str::isUuid($anonId)
                ? PageViewEvent::query()->where('anon_id', $anonId)
                    ->where('event_name', PageViewTracker::FAMILY_LANDING_EVENT)
                    ->where('created_at', '>=', now()->subDays(30))->latest('id')->first()
                : null;
            if ($landing) {
                $values = $landing->only(['url', 'referrer', ...self::UTM_FIELDS]);
            } elseif ($this->current() !== []) {
                return;
            }
        }

        $referrerHost = is_string($values['referrer']) ? parse_url($values['referrer'], PHP_URL_HOST) : null;
        if ($referrerHost && $this->host($referrerHost) === $this->host($request->getHost())) {
            $values['referrer'] = null;
        }
        $values = array_map(fn ($value) => is_string($value) ? mb_substr(trim($value), 0, 255) : null, $values);
        $request->session()->put(self::SESSION_KEY, [...array_filter($values), 'captured_at' => now()->timestamp]);
    }

    public function current(): array
    {
        $values = session(self::SESSION_KEY, []);

        return is_array($values) && ($values['captured_at'] ?? 0) >= now()->subDays(30)->timestamp ? $values : [];
    }

    private function host(string $host): string
    {
        return preg_replace('/^www\./', '', strtolower($host));
    }
}
