<?php

namespace App\Http\Controllers\CareHours;

use App\Http\Controllers\Controller;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingTimeCorrection;
use App\Models\CareHourAccount;
use App\Models\CareHourContributionLink;
use App\Models\CareHourPurchase;
use App\Models\CareHourRecipientLink;
use App\Models\CarePlan;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\CareHours\CareHourAccess;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourEligibilityService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\CareHourScheduleChangeFundingService;
use App\Services\CareHours\PrepaidBookingFundingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FamilyCareHoursController extends Controller
{
    use ReadsCareHourPayment;

    public function __construct(
        private readonly CareHourIdentityService $identities,
        private readonly CareHourLedgerService $ledger,
        private readonly CareHourPurchaseService $purchases,
        private readonly CareHourContributionService $contributions,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        abort_unless(app(\App\Services\CareHours\CareHourNavigation::class)->visible($request->user()), 404);
        $accounts = $this->identities->accountsFor($request->user());
        if ($accounts->count() === 1 && ! $request->boolean('choose')) {
            return redirect()->route('family.care-hours.show', $accounts->first());
        }

        return view('care-hours.family.index', [
            'accounts' => $accounts,
            'balances' => $accounts->mapWithKeys(fn ($account) => [$account->id => $this->ledger->balance($account)]),
            'recipients' => app(CareHourAccess::class)->canEnroll($request->user()) ? $this->identities->recipientsFor($request->user())
                ->filter(fn ($recipient) => ! $recipient->care_recipient_profile_id && ! CareHourRecipientLink::where('care_recipient_id', $recipient->id)->exists()) : collect(),
            'profiles' => app(CareHourAccess::class)->canEnroll($request->user()) ? $this->identities->profilesFor($request->user())
                ->reject(fn ($profile) => $accounts->contains('care_recipient_profile_id', $profile->id)) : collect(),
        ]);
    }

    public function enroll(Request $request): RedirectResponse
    {
        abort_unless(app(CareHourAccess::class)->canEnroll($request->user()), 403);
        $accounts = $this->identities->accountsFor($request->user());
        $input = $request->validate([
            'recipient' => ['required', 'regex:/^(profile|recipient):[1-9][0-9]*$/'],
            'balance_target' => [$accounts->isNotEmpty() ? 'required' : 'nullable', 'regex:/^(new|[1-9][0-9]*)$/'],
        ]);
        [$type, $id] = explode(':', $input['recipient']);
        if (filled($input['balance_target'] ?? null) && $input['balance_target'] !== 'new') {
            $account = $accounts->firstWhere('id', (int) $input['balance_target']);
            abort_unless($account, 403);
            if ($type === 'profile') {
                throw ValidationException::withMessages(['recipient' => 'This care profile is a separate person. Choose their own care balance.']);
            }
            $this->identities->linkRecipient($account, CareRecipient::query()->findOrFail($id), $request->user());

            return redirect()->route('family.care-hours.show', $account)->with('status', 'This care request is now linked to the existing care balance.');
        }
        $account = $type === 'profile'
            ? $this->identities->enrollProfile(CareRecipientProfile::query()->findOrFail($id), $request->user())
            : $this->identities->enroll(CareRecipient::query()->findOrFail($id), $request->user());

        return redirect()->route('family.care-hours.show', $account);
    }

    public function show(Request $request, CareHourAccount $account): View
    {
        $this->identities->assertAccess($account, $request->user());

        abort_unless(app(CareHourAccess::class)->allows($request->user()) || $account->lots()->exists() || $account->purchases()->exists(), 404);

        return view('care-hours.family.show', [
            'account' => $account,
            'accounts' => $this->identities->accountsFor($request->user()),
            'balance' => $this->ledger->balance($account),
            'history' => $this->ledger->history($account)->where('type', '!=', 'processing_fee')->paginate(15),
            'purchases' => CareHourPurchase::query()->where('care_hour_account_id', $account->id)->latest()->limit(10)->get(),
            'isOwner' => $this->identities->isOwner($account, $request->user()),
            'links' => CareHourContributionLink::query()->where('care_hour_account_id', $account->id)->latest()->get(),
            'eligibilityReason' => app(CareHourEligibilityService::class)->reasonFor($account),
        ]);
    }

    public function packages(Request $request, CareHourAccount $account): View
    {
        $this->identities->assertAccess($account, $request->user());
        abort_unless(app(CareHourAccess::class)->canBuy($request->user()) && $account->active, 403);
        $reason = app(CareHourEligibilityService::class)->reasonFor($account);

        return view('care-hours.family.packages', [
            'account' => $account,
            'packages' => $reason ? collect() : collect([5, 10, 20, 40])->map(fn ($hours) => $this->purchases->quote($account, $hours)),
            'eligibilityReason' => $reason,
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function checkout(Request $request, CareHourAccount $account): RedirectResponse
    {
        $this->identities->assertAccess($account, $request->user());
        $input = $request->validate([
            'hours' => ['required', 'integer', 'in:5,10,20,40'],
            'quotes' => ['required', 'array'],
            'quotes.*' => ['required', 'string', 'size:64'],
            'request_key' => ['required', 'uuid'],
            'terms' => ['accepted'],
        ]);
        $purchase = $this->purchases->start(
            $account, (int) $input['hours'], $request->user(), (string) ($input['quotes'][$input['hours']] ?? ''), $input['request_key'],
            url('/family/care-hours/purchases/{PURCHASE_ID}').'?checkout=success',
            url('/family/care-hours/purchases/{PURCHASE_ID}').'?checkout=cancel',
        );

        return $purchase->checkout_url && $purchase->status !== 'issued'
            ? redirect()->away($purchase->checkout_url)
            : redirect()->route('family.care-hours.purchase', $purchase->public_id);
    }

    public function purchase(Request $request, CareHourPurchase $purchase): View|RedirectResponse
    {
        if ($purchase->gift()->exists()) {
            return redirect()->route('care-hours.gifts.receipt', $purchase->public_id);
        }
        $this->purchases->authorizeReceipt($purchase, $request->user(), null);
        $purchase = $this->readPayment($purchase, $this->purchases);

        return view('care-hours.family.purchase', ['purchase' => $purchase]);
    }

    public function createLink(Request $request, CareHourAccount $account): RedirectResponse
    {
        abort_unless(app(CareHourAccess::class)->canContribute($request->user()), 403);
        $this->identities->assertAccess($account, $request->user());
        abort_unless($this->identities->isOwner($account, $request->user()), 403);
        $result = $this->contributions->createLink($account, $request->user());

        return back()->with('contribution_url', route('care-hours.contribute', $result['token']))
            ->with('status', 'Your private link is ready. Share it only with the people you choose.');
    }

    public function revokeLink(Request $request, CareHourAccount $account, CareHourContributionLink $link): RedirectResponse
    {
        $this->identities->assertAccess($account, $request->user());
        abort_unless((int) $link->care_hour_account_id === (int) $account->id, 404);
        $this->contributions->revoke($link, $request->user());

        return back()->with('status', 'This link is disabled. Completed contributions remain available.');
    }

    public function planFunding(Request $request, CarePlan $plan, PrepaidBookingFundingService $funding): RedirectResponse
    {
        $input = $request->validate(['account_id' => ['required_without:card', 'nullable', 'integer'], 'confirm' => ['accepted'], 'card' => ['nullable', 'boolean']]);
        if ($request->boolean('card')) {
            $funding->disablePlan($plan, $request->user());

            return redirect()->route('family.care.show', ['carePlan' => $plan->id, 'tab' => 'details'])->with('status', 'Future unprepared visits will use the existing card flow. Already funded visits remain unchanged.');
        }
        $account = CareHourAccount::query()->findOrFail($input['account_id']);
        $this->identities->assertAccess($account, $request->user());
        $funding->selectForPlan($plan, $account, $request->user());

        return redirect()->route('family.care.show', ['carePlan' => $plan->id, 'tab' => 'details'])
            ->with('status', 'Care hours will cover future visits that have not already had payment prepared.');
    }

    public function shortfall(Request $request, CareBooking $booking, PrepaidBookingFundingService $funding): RedirectResponse
    {
        $selection = $funding->forBooking($booking);
        abort_unless($selection, 404);
        $this->identities->assertAccess($selection->account, $request->user());
        $returnUrl = route('family.requests.show', $booking->care_request_id);
        try {
            $result = $funding->createShortfallCheckout($booking, $request->user(), route('family.care-hours.shortfall.return', $booking), $returnUrl);
        } catch (\App\Exceptions\Payments\PaymentException $exception) {
            return redirect()->to($returnUrl)->withErrors(['billing' => $exception->userMessage]);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            report($exception);

            return redirect()->to($returnUrl)->withErrors(['billing' => 'We could not confirm the payment page just now. Please try again shortly.']);
        }

        return redirect()->away($result['url']);
    }

    public function coverage(Request $request, CareBooking $booking, PrepaidBookingFundingService $funding): RedirectResponse
    {
        $selection = $funding->forBooking($booking);
        abort_unless($selection, 404);
        $this->identities->assertAccess($selection->account, $request->user());
        try {
            $funding->refreshCoverage($booking, $request->user());
            $corrections = app(CareBookingTimeCorrectionService::class);
            $correction = $corrections->activeForBooking($booking);
            if ($correction?->status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED) {
                $corrections->resumeApproved($correction, $request->user());
            }
        } catch (\App\Exceptions\Payments\PaymentException $exception) {
            return redirect()->route('family.requests.show', $booking->care_request_id)->withErrors(['billing' => $exception->userMessage]);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            report($exception);

            return redirect()->route('family.requests.show', $booking->care_request_id)->withErrors(['billing' => 'We could not check payment just now. Your existing coverage is saved. Please check again shortly.']);
        }

        return redirect()->route('family.requests.show', $booking->care_request_id)->with('status', 'Care-hour coverage updated.');
    }

    public function shortfallReturn(Request $request, CareBooking $booking, PrepaidBookingFundingService $funding): RedirectResponse
    {
        // A return URL is only a prompt to read the provider; it never proves payment.
        return $this->coverage($request, $booking, $funding);
    }

    public function scheduleChangePayment(Request $request, CareBookingChangeRequest $change, CareHourScheduleChangeFundingService $changes): RedirectResponse
    {
        $returnUrl = route('family.requests.show', $change->booking->care_request_id);
        try {
            $result = $changes->createCheckout($change, $request->user(), route('family.care-hours.change.return', $change), $returnUrl);
        } catch (\App\Exceptions\Payments\PaymentException $exception) {
            return redirect()->to($returnUrl)->withErrors(['billing' => $exception->userMessage]);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            report($exception);

            return redirect()->to($returnUrl)->withErrors(['billing' => 'We could not open payment just now. Your current visit is unchanged. Please try again shortly.']);
        }

        return redirect()->away($result['url']);
    }

    public function scheduleChangeReturn(Request $request, CareBookingChangeRequest $change, PrepaidBookingFundingService $funding): RedirectResponse
    {
        return $this->coverage($request, $change->booking, $funding);
    }
}
