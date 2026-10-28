<?php

namespace App\Http\Controllers\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\CareHourRecipientLink;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Support\CareHourGiftClaimIntent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class GiftClaimController extends Controller
{
    public function __construct(private readonly CareHourGiftService $gifts, private readonly CareHourIdentityService $identities) {}

    public function index(Request $request): View
    {
        $accounts = $this->identities->accountsFor($request->user());
        $draft = $request->session()->get('care_hours.gift_claim');
        $selectedAccount = $draft ? $accounts->firstWhere('id', $draft['account_id']) : null;
        if ($draft && (! $selectedAccount || (int) ($draft['actor_id'] ?? 0) !== (int) $request->user()->id)) {
            $request->session()->forget('care_hours.gift_claim');
            $draft = null;
        }

        return view('care-hours.gifts.claim', [
            'accounts' => $accounts->where('active', true), 'draft' => $draft, 'selectedAccount' => $selectedAccount,
            'giftIntent' => CareHourGiftClaimIntent::summary(),
            'profiles' => $this->identities->profilesFor($request->user())->reject(fn ($profile) => $accounts->contains('care_recipient_profile_id', $profile->id)),
            'recipients' => $this->identities->recipientsFor($request->user())->filter(fn ($recipient) => ! $recipient->care_recipient_profile_id
                && ! CareHourRecipientLink::where('care_recipient_id', $recipient->id)->exists()),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $guided = CareHourGiftClaimIntent::active();
        $code = $guided ? CareHourGiftClaimIntent::code() : $request->input('code');
        // A gift code is a bearer secret. Never put it in Laravel's flashed old input.
        $request->request->remove('code');
        $request->query->remove('code');
        $request->session()->forget('care_hours.gift_claim');
        $input = Validator::make(['code' => $code, 'recipient' => $request->input('recipient')], [
            'code' => ['required', 'string', 'max:80'],
            'recipient' => ['required', $guided ? 'regex:/^(self|(account|profile|recipient):[1-9][0-9]*)$/' : 'regex:/^(account|profile|recipient):[1-9][0-9]*$/'],
        ])->validate();
        try {
            $account = $input['recipient'] === 'self'
                ? $this->gifts->resolveSelfClaimAccount($input['code'], $request->user())
                : $this->gifts->resolveClaimAccount($input['code'], $request->user(), $input['recipient']);
            $quote = $this->gifts->previewForClaim($input['code'], $account, $request->user());
        } catch (PaymentException|ApiErrorException) {
            return redirect()->route('family.care-hours.claim')->withErrors(['gift_code' => 'We could not confirm this gift’s payment. Please try again shortly. Your code has not been used.']);
        }
        $request->session()->put('care_hours.gift_claim', [
            'encrypted_code' => Crypt::encryptString($input['code']), 'account_id' => $account->id,
            'quote' => $quote, 'nonce' => Str::random(40), 'guided' => $guided,
            'for_self' => $input['recipient'] === 'self', 'actor_id' => $request->user()->id,
        ]);

        return redirect()->route('family.care-hours.claim');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $input = $request->validate(['confirm' => ['accepted'], 'nonce' => ['required', 'string', 'size:40']]);
        $draft = $request->session()->get('care_hours.gift_claim');
        abort_unless($draft && hash_equals($draft['nonce'], $input['nonce']), 422);
        abort_unless((int) ($draft['actor_id'] ?? 0) === (int) $request->user()->id, 403);
        $account = CareHourAccount::findOrFail($draft['account_id']);
        $this->identities->assertAccess($account, $request->user());
        try {
            $purchase = $this->gifts->claim(Crypt::decryptString($draft['encrypted_code']), $account, $request->user(), $draft['quote']['quote_key']);
        } catch (PaymentException|ApiErrorException) {
            return redirect()->route('family.care-hours.claim')->withErrors(['gift_code' => 'We could not confirm this gift’s payment. Please try again shortly.']);
        }
        $request->session()->forget('care_hours.gift_claim');
        if ($draft['guided'] ?? false) {
            CareHourGiftClaimIntent::clear();

            return redirect()->route('family.care-hours.claim.ready', $purchase->public_id);
        }

        return redirect()->route('family.care-hours.show', $account)->with('status',
            CareHourAccount::formatMinutes($purchase->total_minutes).' added for '.$account->display_name.'.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget('care_hours.gift_claim');

        return redirect()->route('family.care-hours.claim');
    }

    public function ready(Request $request, CareHourPurchase $purchase): View|RedirectResponse
    {
        abort_unless($purchase->gift?->claimed_at && $purchase->care_hour_account_id, 404);
        $account = CareHourAccount::findOrFail($purchase->care_hour_account_id);
        $this->identities->assertAccess($account, $request->user());

        if ($purchase->status !== 'issued' || $purchase->correction_state !== null || $purchase->amount_reversed_cents > 0
            || ($purchase->dispute_id && $purchase->dispute_status !== 'won') || ! $account->active) {
            return redirect()->route('family.care-hours.show', $account)
                ->withErrors(['gift_code' => 'This gift needs review. Check your current care-hours balance below.']);
        }

        return view('care-hours.gifts.ready', ['purchase' => $purchase, 'account' => $account]);
    }
}
