<?php

namespace App\Http\Controllers;

use App\Services\Analytics\PageViewTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class MarketingPagesController extends Controller
{
    public function landing(Request $request, PageViewTracker $tracker): View|Response
    {
        return $this->trackedLandingResponse(
            request: $request,
            tracker: $tracker,
            view: 'marketing.family',
            event: 'family'
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

    public function caregiver(Request $request, PageViewTracker $tracker): View|Response
    {
        return $this->trackedLandingResponse(
            request: $request,
            tracker: $tracker,
            view: 'marketing.caregiver',
            event: 'caregiver'
        );
    }

    public function agency(): View
    {
        return view('marketing.agency');
    }

    private function trackedLandingResponse(Request $request, PageViewTracker $tracker, string $view, string $event): View|Response
    {
        $result = $event === 'caregiver'
            ? $tracker->trackCaregiverLandingView($request)
            : $tracker->trackFamilyLandingView($request);

        $response = response()->view($view);

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
}
