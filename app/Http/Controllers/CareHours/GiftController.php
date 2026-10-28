<?php

namespace App\Http\Controllers\CareHours;

use App\Http\Controllers\Controller;
use App\Models\CareHourPurchase;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourPurchaseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GiftController extends Controller
{
    use ReadsCareHourPayment;

    public function __construct(private readonly CareHourGiftService $gifts) {}

    public function index(Request $request): View
    {
        abort_unless(config('care_hours.enabled') && config('care_hours.sales_enabled') && config('care_hours.gifts_enabled'), 404);

        return view('care-hours.gifts.buy', [
            'buyer' => $this->buyer($request), 'packages' => $this->gifts->catalog(),
            'verificationPending' => $request->session()->has('care_hours.gift_verification'),
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function requestCode(Request $request): RedirectResponse
    {
        $input = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email:rfc', 'max:254']]);
        $verification = $this->gifts->requestVerification($input['email'], $input['name']);
        $request->session()->forget('care_hours.gift_buyer');
        $request->session()->put('care_hours.gift_verification', $verification->id);

        return redirect()->route('care-hours.gifts.index')->with('status', 'Check your email for a verification code.');
    }

    public function confirmCode(Request $request): RedirectResponse
    {
        $input = $request->validate(['code' => ['required', 'digits:6']]);
        $id = (string) $request->session()->get('care_hours.gift_verification', '');
        abort_unless($id !== '', 422);
        $token = $this->gifts->verify($id, $input['code']);
        $request->session()->put('care_hours.gift_buyer', $token);
        $request->session()->forget('care_hours.gift_verification');

        return redirect()->route('care-hours.gifts.index')->with('status', 'Email verified. Choose your gift.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'hours' => ['required', 'integer', 'in:5,10,20,40'], 'quotes' => ['required', 'array'],
            'quotes.*' => ['required', 'string', 'size:64'], 'request_key' => ['required', 'uuid'], 'terms' => ['accepted'],
        ]);
        $purchase = $this->gifts->start((string) $request->session()->get('care_hours.gift_buyer', ''),
            (int) $input['hours'], (string) ($input['quotes'][$input['hours']] ?? ''), $input['request_key'],
            url('/care-hours/gifts/receipts/{PURCHASE_ID}').'?checkout=success',
            url('/care-hours/gifts/receipts/{PURCHASE_ID}').'?checkout=cancel');

        return $purchase->checkout_url && $purchase->status === 'pending'
            ? redirect()->away($purchase->checkout_url)
            : redirect()->route('care-hours.gifts.receipt', $purchase->public_id);
    }

    public function receipt(Request $request, CareHourPurchase $purchase, CareHourPurchaseService $purchases): View
    {
        abort_unless($purchase->gift()->exists(), 404);
        try {
            $this->gifts->authorizeReceipt($purchase, $request->user(), $request->session()->get('care_hours.gift_buyer'));
        } catch (AuthorizationException) {
            return view('care-hours.gifts.receipt-access', [
                'purchaseId' => $purchase->public_id,
                'verificationPending' => $request->session()->has('care_hours.gift_receipt_verification.'.$purchase->id),
            ]);
        }
        $purchase = $this->readPayment($purchase, $purchases);

        return view('care-hours.gifts.receipt', [
            'purchase' => $purchase,
            'giftCode' => $this->gifts->codeForBuyer($purchase, $request->user(), $request->session()->get('care_hours.gift_buyer')),
        ]);
    }

    public function receiptCode(Request $request, CareHourPurchase $purchase): RedirectResponse
    {
        abort_unless($purchase->gift()->exists(), 404);
        $input = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $id = (string) Str::uuid();
        try {
            $verification = $this->gifts->requestReceiptVerification($purchase, $input['email']);
            $id = $verification->id;
        } catch (ValidationException) {
            // Receipt recovery must not disclose which email paid for this gift.
        }
        $request->session()->put('care_hours.gift_receipt_verification.'.$purchase->id, $id);

        return back()->with('status', 'If this email matches the purchase, a verification code is on its way.');
    }

    public function receiptConfirm(Request $request, CareHourPurchase $purchase): RedirectResponse
    {
        abort_unless($purchase->gift()->exists(), 404);
        $input = $request->validate(['code' => ['required', 'digits:6']]);
        $id = (string) $request->session()->get('care_hours.gift_receipt_verification.'.$purchase->id, '');
        abort_unless($id !== '', 422);
        $token = $this->gifts->verifyReceipt($purchase, $id, $input['code']);
        $request->session()->put('care_hours.gift_buyer', $token);
        $request->session()->forget('care_hours.gift_receipt_verification.'.$purchase->id);

        return redirect()->route('care-hours.gifts.receipt', $purchase->public_id);
    }

    private function buyer(Request $request): ?array
    {
        $token = (string) $request->session()->get('care_hours.gift_buyer', '');
        if ($token === '') {
            return null;
        }
        try {
            return $this->gifts->verifiedBuyer($token);
        } catch (AuthorizationException|ValidationException) {
            $request->session()->forget('care_hours.gift_buyer');

            return null;
        }
    }
}
