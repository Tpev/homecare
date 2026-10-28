<?php

namespace App\Http\Controllers\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\CareHourAccount;
use App\Models\CareHourBookingFunding;
use App\Models\CareHourPurchase;
use App\Models\CareHourSettlement;
use App\Models\FamilyAccountActivityLog;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\CareHours\PrepaidBookingSettlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class AdminCareHoursController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $attentionOnly = $request->boolean('attention');
        $pendingSettlements = $this->settlementIssues();
        $fundingIssues = $this->fundingIssues();
        $accounts = CareHourAccount::query()->with('familyAccount.owner')
            ->withSum('ledgerEntries as available_minutes', 'available_delta')
            ->withCount(['purchases as purchase_attention_count' => fn ($query) => $this->purchaseIssues($query)])
            ->selectSub((clone $fundingIssues)->selectRaw('count(*)')->whereColumn('care_hour_account_id', 'care_hour_accounts.id'), 'funding_attention_count')
            ->selectSub((clone $pendingSettlements)->selectRaw('count(*)')->whereColumn('care_hour_booking_fundings.care_hour_account_id', 'care_hour_accounts.id'), 'settlement_attention_count')
            ->when($attentionOnly, fn ($query) => $query->where(fn ($issues) => $issues
                ->whereHas('purchases', fn ($purchase) => $this->purchaseIssues($purchase))
                ->orWhereIn('id', (clone $fundingIssues)->select('care_hour_account_id'))
                ->orWhereIn('id', (clone $pendingSettlements)->select('care_hour_booking_fundings.care_hour_account_id'))))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('display_name', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('id', ctype_digit($search) ? (int) $search : 0)
                    ->orWhereHas('familyAccount.owner', fn ($owner) => $owner->where('email', $search));
            }))->latest()->paginate(25)->withQueryString();

        return view('care-hours.admin.index', compact('accounts', 'search', 'attentionOnly'));
    }

    public function show(CareHourAccount $account, CareHourLedgerService $ledger): View
    {
        $fundings = CareHourBookingFunding::query()->with(['booking', 'operations' => fn ($query) => $query->latest()->limit(25)])
            ->where('care_hour_account_id', $account->id)->latest()->paginate(20, ['*'], 'visits')->withQueryString();

        return view('care-hours.admin.show', [
            'account' => $account->load('familyAccount.owner'),
            'balance' => $ledger->balance($account),
            'history' => $ledger->history($account)->paginate(30, ['*'], 'activity')->withQueryString(),
            'purchases' => CareHourPurchase::query()->where('care_hour_account_id', $account->id)->latest()->paginate(20, ['*'], 'purchases')->withQueryString(),
            'fundings' => $fundings,
            'settlements' => CareHourSettlement::query()->whereIn('care_hour_booking_funding_id', $fundings->pluck('id'))->get(),
            'audit' => FamilyAccountActivityLog::query()->where('family_account_id', $account->family_account_id)
                ->where('action', 'like', 'care_hours_%')->latest()->limit(20)->get(),
        ]);
    }

    public function recheck(Request $request, CareHourAccount $account, CareHourPurchase $purchase, CareHourPurchaseService $purchases): RedirectResponse
    {
        abort_unless((int) $purchase->care_hour_account_id === (int) $account->id, 404);
        $input = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => $account->family_account_id,
            'actor_user_id' => $request->user()->id,
            'action' => 'care_hours_purchase_recheck',
            'metadata' => ['account_id' => $account->id, 'purchase_id' => $purchase->id, 'reason' => $input['reason']],
        ]);
        try {
            if ($purchase->correction_state === 'pending') {
                $purchases->reconcileCorrection($purchase);
            } else {
                $purchases->refresh($purchase);
            }
        } catch (PaymentException|ApiErrorException $exception) {
            report($exception);

            return back()->withErrors(['payment' => 'The provider could not confirm this payment. The audit and existing records are preserved; retry this check later.']);
        }

        return back()->with('status', 'Payment status checked. Only confirmed provider results can change issued hours.');
    }

    public function correct(Request $request, CareHourAccount $account, CareHourPurchase $purchase, CareHourPurchaseService $purchases): RedirectResponse
    {
        abort_unless((int) $purchase->care_hour_account_id === (int) $account->id, 404);
        $input = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000'], 'confirm' => ['accepted']]);
        try {
            $purchases->correctUnfulfillablePurchase($purchase, $request->user(), $input['reason']);
        } catch (PaymentException|ApiErrorException $exception) {
            report($exception);

            return back()->withErrors(['payment' => 'The provider could not confirm this correction. Recheck its recorded status before requesting another attempt.']);
        }

        return back()->with('status', 'Invalid-purchase correction requested. Provider confirmation determines its final result.');
    }

    public function recheckVisit(Request $request, CareHourAccount $account, CareHourBookingFunding $funding, PrepaidBookingFundingService $coverage, PrepaidBookingSettlementService $settlements): RedirectResponse
    {
        abort_unless((int) $funding->care_hour_account_id === (int) $account->id, 404);
        $input = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        FamilyAccountActivityLog::create([
            'family_account_id' => $account->family_account_id, 'actor_user_id' => $request->user()->id,
            'action' => 'care_hours_visit_recheck', 'metadata' => ['account_id' => $account->id,
                'funding_id' => $funding->id, 'booking_id' => $funding->care_booking_id, 'reason' => $input['reason']],
        ]);
        try {
            $settlement = CareHourSettlement::where('care_hour_booking_funding_id', $funding->id)->first();
            if ($settlement) {
                $settlements->reconcile($settlement);
            } elseif ($funding->status === 'released') {
                $coverage->reconcileReleased($funding->id);
            }
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment' => 'This visit still needs payment review. Its records and this check are saved.']);
        }

        return back()->with('status', 'Recorded visit payments checked. Review any remaining attention status below.');
    }

    private function settlementIssues(): Builder
    {
        return CareHourSettlement::query()->join('care_hour_booking_fundings', 'care_hour_booking_fundings.id', '=', 'care_hour_settlements.care_hour_booking_funding_id')
            ->where(fn ($issues) => $issues->whereIn('care_hour_settlements.status', ['pending', 'reconciliation_pending', 'payment_attention', 'partially_settled', 'fee_pending', 'funding_on_hold', 'payout_pending'])
                ->orWhereColumn('care_hour_settlements.paid_cents', '<', 'care_hour_settlements.caregiver_net_cents'));
    }

    private function purchaseIssues(Builder $query): Builder
    {
        return $query->where(fn ($issues) => $issues->whereIn('status', ['held', 'recovery'])
            ->orWhere(fn ($unissued) => $unissued->whereIn('status', ['pending', 'paid'])->whereNull('issued_at')
                ->whereNotNull('last_error')->where('last_error', '!=', '')));
    }

    private function fundingIssues(): Builder
    {
        return CareHourBookingFunding::query()->where(function (Builder $issues) {
            $issues->where('status', 'payment_attention')->orWhere(function (Builder $released) {
                $released->where(fn ($ended) => $ended->where('status', 'released')
                    ->orWhereHas('booking', fn ($booking) => $booking->where('status', 'cancelled')))
                    ->whereHas('operations', function (Builder $operations) {
                        $operations->where('kind', 'shortfall')->where(function (Builder $unresolved) {
                            $unresolved->where('status', 'authorized')
                                ->orWhere(fn ($captured) => $captured->where('status', 'captured')->whereColumn('captured_cents', '>', 'refunded_cents'))
                                ->orWhere(fn ($checkout) => $checkout->whereIn('status', ['pending', 'checkout', 'expired'])
                                    ->where(fn ($attention) => $attention
                                        ->where(fn ($error) => $error->whereNotNull('last_error')->where('last_error', '!=', ''))
                                        ->orWhere(fn ($error) => $error->whereNotNull('care_hour_booking_fundings.last_error')->where('care_hour_booking_fundings.last_error', '!=', ''))
                                        ->orWhere('created_at', '<', now()->subHours(23))));
                        });
                    });
            });
        });
    }
}
