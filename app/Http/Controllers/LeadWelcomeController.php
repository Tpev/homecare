<?php

namespace App\Http\Controllers;

use App\Models\LeadWelcomeEmail;
use App\Services\FamilyAcquisition\LeadWelcomeService;
use Illuminate\Http\Request;

class LeadWelcomeController extends Controller
{
    public function start(Request $request, LeadWelcomeEmail $welcomeEmail, LeadWelcomeService $service)
    {
        session([LeadWelcomeService::SESSION_KEY => ['id' => $welcomeEmail->id, 'expires' => (int) $request->query('expires')]]);
        if ($request->user()) {
            if ($service->continueFor($request->user())) {
                return redirect()->route('family.requests.create');
            }

            return redirect()->route('dashboard');
        }

        return redirect()->route('register')->header('Referrer-Policy', 'no-referrer');
    }

    public function unsubscribe(Request $request, LeadWelcomeEmail $welcomeEmail, LeadWelcomeService $service)
    {
        if ($request->isMethod('post')) {
            if (! $welcomeEmail->unsubscribed_at) {
                $welcomeEmail->update(['unsubscribed_at' => now()]);
                $service->activity($welcomeEmail, 'Unsubscribed from welcome emails');
            }

            return view('lead-welcome-unsubscribe', ['done' => true]);
        }

        return view('lead-welcome-unsubscribe', ['done' => (bool) $welcomeEmail->unsubscribed_at]);
    }
}
