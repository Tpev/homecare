<?php

namespace App\Http\Controllers;

use App\Services\Analytics\PageViewTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class MarketingPagesController extends Controller
{
    public function landing(): View
    {
        return view('marketing.family');
    }

    public function family(): View
    {
        return view('marketing.family');
    }

    public function caregiver(Request $request, PageViewTracker $tracker): View|Response
    {
        $result = $tracker->trackCaregiverLandingView($request);

        $response = response()->view('marketing.caregiver');

        if ($result['should_set_cookie'] && $result['anon_id']) {
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

    public function agency(): View
    {
        return view('marketing.agency');
    }
}
