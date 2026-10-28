<?php

namespace App\Http\Controllers\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Services\CareHours\CareHourGiftService;
use App\Support\CareHourGiftClaimIntent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class GiftClaimEntryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (CareHourGiftClaimIntent::active()) {
            if (! $request->user()) {
                return redirect()->route('register');
            }
            if ($request->user()->role === 'family') {
                return redirect()->route('family.care-hours.claim');
            }
        }

        return view('care-hours.gifts.enter-code');
    }

    public function start(Request $request, CareHourGiftService $gifts): RedirectResponse
    {
        $code = $request->input('code');
        $request->request->remove('code');
        $request->query->remove('code');
        CareHourGiftClaimIntent::clear();
        $request->session()->forget('care_hours.gift_claim');
        $validated = Validator::make(['code' => $code], ['code' => ['required', 'string', 'max:80']])->validate();
        if ($request->user() && $request->user()->role !== 'family') {
            return redirect()->route('care-hours.claim')->withErrors(['gift_code' => 'Use a family account to claim your gift.']);
        }
        try {
            $summary = $gifts->inspectForClaim($validated['code']);
        } catch (PaymentException|ApiErrorException) {
            return redirect()->route('care-hours.claim')->withErrors(['gift_code' => 'We could not check this gift just now. Please try again shortly.']);
        }
        CareHourGiftClaimIntent::put($validated['code'], $summary);

        return redirect()->route($request->user() ? 'family.care-hours.claim' : 'register');
    }

    public function reset(Request $request): RedirectResponse
    {
        CareHourGiftClaimIntent::clear();
        $request->session()->forget('care_hours.gift_claim');

        return redirect()->route('care-hours.claim');
    }
}
