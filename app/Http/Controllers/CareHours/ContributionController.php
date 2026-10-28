<?php

namespace App\Http\Controllers\CareHours;

use App\Http\Controllers\Controller;
use App\Models\CareHourPurchase;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourPurchaseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContributionController extends Controller
{
    use ReadsCareHourPayment;

    public function __construct(
        private readonly CareHourContributionService $contributions,
        private readonly CareHourPurchaseService $purchases,
    ) {}

    public function show(Request $request, string $token): View|\Illuminate\Http\Response
    {
        abort_unless(app(\App\Services\CareHours\CareHourAccess::class)->allows($request->user()), 404);
        if (! config('care_hours.enabled') || ! config('care_hours.sales_enabled')) {
            return response()->view('care-hours.public.unavailable', [], 410);
        }
        try {
            $link = $this->contributions->resolve($token);
        } catch (ValidationException $exception) {
            return response()->view('care-hours.public.unavailable', [], 410);
        }
        $verifiedToken = $request->session()->get('care_hours.verified.'.$link->id);
        $buyer = null;
        if ($verifiedToken) {
            try {
                $buyer = $this->contributions->verifiedBuyer($link, $verifiedToken);
            } catch (AuthorizationException|ValidationException|HttpException $exception) {
                $request->session()->forget('care_hours.verified.'.$link->id);
            }
        }

        return view('care-hours.public.contribute', [
            'link' => $link, 'token' => $token, 'buyer' => $buyer,
            'verificationPending' => $request->session()->has('care_hours.verification.'.$link->id),
            'packages' => collect([5, 10, 20, 40])->map(fn ($hours) => $this->purchases->quote($link->account, $hours)),
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function requestCode(Request $request, string $token): RedirectResponse
    {
        $link = $this->contributions->resolve($token);
        $input = $request->validate(['email' => ['required', 'email:rfc', 'max:254'], 'name' => ['required', 'string', 'max:100']]);
        $verification = $this->contributions->requestVerification($link, $input['email'], $input['name']);
        $request->session()->put('care_hours.verification.'.$link->id, $verification->id);

        return redirect()->route('care-hours.contribute', $token)->with('status', 'We emailed a verification code. Enter it below to continue.');
    }

    public function confirmCode(Request $request, string $token): RedirectResponse
    {
        $link = $this->contributions->resolve($token);
        $input = $request->validate(['code' => ['required', 'digits:6']]);
        $verificationId = (string) $request->session()->get('care_hours.verification.'.$link->id, '');
        abort_unless($verificationId !== '', 422);
        $verifiedToken = $this->contributions->verify($link, $verificationId, $input['code']);
        $request->session()->put('care_hours.verified.'.$link->id, $verifiedToken);
        $request->session()->forget('care_hours.verification.'.$link->id);

        return redirect()->route('care-hours.contribute', $token)->with('status', 'Email verified. Choose your care package.');
    }

    public function checkout(Request $request, string $token): RedirectResponse
    {
        $link = $this->contributions->resolve($token);
        $input = $request->validate([
            'hours' => ['required', 'integer', 'in:5,10,20,40'],
            'quotes' => ['required', 'array'],
            'quotes.*' => ['required', 'string', 'size:64'],
            'request_key' => ['required', 'uuid'],
            'terms' => ['accepted'],
        ]);
        $verifiedToken = (string) $request->session()->get('care_hours.verified.'.$link->id, '');
        $purchase = $this->purchases->startContribution(
            $link, $verifiedToken, (int) $input['hours'], (string) ($input['quotes'][$input['hours']] ?? ''), $input['request_key'],
            url('/care-hours/receipts/{PURCHASE_ID}').'?checkout=success',
            url('/care-hours/receipts/{PURCHASE_ID}').'?checkout=cancel',
        );

        return $purchase->checkout_url && $purchase->status !== 'issued'
            ? redirect()->away($purchase->checkout_url)
            : redirect()->route('care-hours.receipt', $purchase->public_id);
    }

    public function receipt(Request $request, CareHourPurchase $purchase): View|RedirectResponse
    {
        if ($purchase->gift()->exists()) {
            return redirect()->route('care-hours.gifts.receipt', $purchase->public_id);
        }
        if (! $this->canReadReceipt($request, $purchase)) {
            return view('care-hours.public.receipt-verification', [
                'purchaseId' => $purchase->public_id,
                'verificationPending' => $request->session()->has('care_hours.receipt_verification.'.$purchase->id),
                'canVerifyEmail' => (bool) $purchase->care_hour_contribution_link_id,
            ]);
        }

        return view('care-hours.public.receipt', ['purchase' => $this->readPayment($purchase, $this->purchases)]);
    }

    public function receiptCode(Request $request, CareHourPurchase $purchase): RedirectResponse
    {
        abort_if($purchase->gift()->exists(), 404);
        $input = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $verificationId = (string) Str::uuid();
        try {
            $verification = $this->contributions->requestReceiptVerification($purchase, $input['email']);
            if ($verification) {
                $verificationId = $verification->id;
            }
        } catch (ValidationException|AuthorizationException $exception) {
            // Receipt access never discloses which email purchased this package.
        }
        $request->session()->put('care_hours.receipt_verification.'.$purchase->id, $verificationId);

        return back()->with('status', 'If this email matches the purchase, a verification code is on its way.');
    }

    public function receiptConfirm(Request $request, CareHourPurchase $purchase): RedirectResponse
    {
        abort_if($purchase->gift()->exists(), 404);
        $input = $request->validate(['code' => ['required', 'digits:6']]);
        $verificationId = (string) $request->session()->get('care_hours.receipt_verification.'.$purchase->id, '');
        abort_unless($verificationId !== '', 422);
        $token = $this->contributions->verifyReceipt($purchase, $verificationId, $input['code']);
        $request->session()->put('care_hours.verified.'.$purchase->care_hour_contribution_link_id, $token);
        $request->session()->forget('care_hours.receipt_verification.'.$purchase->id);

        return redirect()->route('care-hours.receipt', $purchase->public_id);
    }

    private function canReadReceipt(Request $request, CareHourPurchase $purchase): bool
    {
        try {
            $this->purchases->authorizeReceipt($purchase, $request->user(), $request->session()->get('care_hours.verified.'.$purchase->care_hour_contribution_link_id));

            return true;
        } catch (AuthorizationException|HttpException $exception) {
            if ($exception instanceof HttpException && $exception->getStatusCode() !== 403) {
                throw $exception;
            }

            return false;
        }
    }
}
