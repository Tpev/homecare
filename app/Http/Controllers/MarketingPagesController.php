<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Services\Analytics\PageViewTracker;
use App\Support\CaregiverPrelaunch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

class MarketingPagesController extends Controller
{
    private const FAMILY_VARIANTS = ['a', 'b', 'c', 'd', 'e'];

    public function landing(Request $request, PageViewTracker $tracker): View|Response
    {
        return $this->trackedLandingResponse(
            request: $request,
            tracker: $tracker,
            view: 'marketing.landing',
            event: 'family',
            data: [
                'featuredCaregivers' => $this->featuredCaregivers(),
            ],
        );
    }

    public function family(Request $request, PageViewTracker $tracker): View|Response
    {
        return $this->trackedLandingResponse(
            request: $request,
            tracker: $tracker,
            view: 'marketing.family',
            event: 'family'
        );
    }

    public function getCare(Request $request, PageViewTracker $tracker): View|Response
    {
        return $this->trackedLandingResponse(
            request: $request,
            tracker: $tracker,
            view: 'marketing.family-callback',
            event: 'family'
        );
    }

    public function familyVariant(Request $request, PageViewTracker $tracker, string $variant): View|Response
    {
        abort_unless(in_array($variant, self::FAMILY_VARIANTS, true), 404);

        return $this->trackedLandingResponse(
            request: $request,
            tracker: $tracker,
            view: "marketing.family-variants.{$variant}",
            event: 'family'
        );
    }

    public function caregiver(Request $request, PageViewTracker $tracker): View|Response
    {
        return $this->trackedLandingResponse(
            request: $request,
            tracker: $tracker,
            view: 'marketing.caregiver',
            event: 'caregiver'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function trackedLandingResponse(Request $request, PageViewTracker $tracker, string $view, string $event, array $data = []): View|Response
    {
        $result = $event === 'caregiver'
            ? $tracker->trackCaregiverLandingView($request)
            : $tracker->trackFamilyLandingView($request);

        $response = response()->view($view, $data);

        if (($result['should_set_cookie'] ?? false) && ! empty($result['anon_id'])) {
            $response->cookie(
                Cookie::make(
                    (string) config('analytics.anon_cookie_name', 'hc_anon_id'),
                    $result['anon_id'],
                    (int) config('analytics.anon_cookie_days', 1825) * 24 * 60,
                    '/',
                    null,
                    app()->environment('production'),
                    true,
                    false,
                    'lax'
                )
            );
        }

        return $response;
    }

    /**
     * Use the same minimum public-profile requirements as caregiver search.
     * Draft, incomplete, or pre-launch profiles must never leak onto marketing pages.
     *
     * @return Collection<int, CaregiverProfile>
     */
    private function featuredCaregivers(): Collection
    {
        if (CaregiverPrelaunch::enabled()) {
            return collect();
        }

        return CaregiverProfile::query()
            ->with(['user', 'skills', 'languages', 'availabilities'])
            ->where('status', 'active')
            ->where('is_accepting_new_clients', true)
            ->whereNotNull('slug')
            ->whereNotNull('bio')
            ->whereNotNull('platform_hourly_rate')
            ->whereNotNull('years_experience')
            ->whereNotNull('service_area_zip')
            ->whereNotNull('service_radius_miles')
            ->whereHas('skills')
            ->whereHas('languages')
            ->whereHas('availabilities')
            ->orderByDesc('top_caregiver')
            ->orderByDesc('average_rating')
            ->orderByDesc('reviews_count')
            ->orderByDesc('reliability_score')
            ->limit(3)
            ->get();
    }
}
