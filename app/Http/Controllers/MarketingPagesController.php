<?php

namespace App\Http\Controllers;

class MarketingPagesController extends Controller
{
    public function landing()   { return view('marketing.landing'); }
    public function family()    { return view('marketing.family'); }
    public function caregiver() { return view('marketing.caregiver'); }
    public function agency()    { return view('marketing.agency'); }
}