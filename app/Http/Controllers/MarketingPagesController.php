<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class MarketingPagesController extends Controller
{
    public function landing(): RedirectResponse
    {
        return redirect()->route('landing.caregiver');
    }

    public function family(): RedirectResponse
    {
        return redirect()->route('landing.caregiver');
    }

    public function caregiver() { return view('marketing.caregiver'); }

    public function agency(): RedirectResponse
    {
        return redirect()->route('landing.caregiver');
    }
}
