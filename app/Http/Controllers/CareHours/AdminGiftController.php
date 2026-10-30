<?php

namespace App\Http\Controllers\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\CareHourAllocation;
use App\Models\CareHourLot;
use App\Models\CareHourPurchase;
use App\Models\User;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Services\CareHours\CareHourPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class AdminGiftController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $state = in_array($request->query('state'), ['unclaimed', 'claimed', 'attention'], true) ? $request->query('state') : 'all';
        $gifts = CareHourPurchase::query()->whereHas('gift')->with(['gift', 'account.familyAccount.owner'])
            ->when($state === 'unclaimed', fn ($query) => $query->where('status', 'paid')->whereHas('gift', fn ($gift) => $gift->whereNull('claimed_at')->whereNotNull('ready_at')))
            ->when($state === 'claimed', fn ($query) => $query->whereHas('gift', fn ($gift) => $gift->whereNotNull('claimed_at')))
            ->when($state === 'attention', fn ($query) => $query->where(fn ($issues) => $issues->whereIn('status', ['held', 'recovery'])
                ->orWhere(fn ($error) => $error->whereNotNull('last_error')->where('last_error', '!=', ''))))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('public_id', $search)->orWhere('buyer_email', $search)))
            ->latest()->paginate(25)->withQueryString();
        $lastChecks = DB::table('care_hour_gift_admin_checks')
            ->whereIn('id', fn ($query) => $query->from('care_hour_gift_admin_checks')->selectRaw('MAX(id)')
                ->whereIn('care_hour_purchase_id', $gifts->pluck('id'))->groupBy('care_hour_purchase_id'))
            ->get()->keyBy('care_hour_purchase_id');

        return view('care-hours.admin.gifts', compact('gifts', 'search', 'state', 'lastChecks'));
    }

    public function show(CareHourPurchase $purchase): View
    {
        // Read a consistent gift snapshot without reconciling a payment, acquiring
        // financial row locks or writing an audit event just for opening the page.
        $detail = DB::transaction(function () use ($purchase): array {
            $purchase = CareHourPurchase::query()->whereKey($purchase->id)->with([
                'gift:id,care_hour_purchase_id,welcome_bonus_minutes,ready_at,claimed_by_user_id,claimed_at',
                'account.familyAccount.owner',
            ])->firstOrFail();
            abort_unless($purchase->gift, 404);

            $claimant = $purchase->gift->claimed_at && $purchase->gift->claimed_by_user_id
                ? User::query()->select('id', 'name', 'email')->find($purchase->gift->claimed_by_user_id) : null;
            $lot = CareHourLot::query()->where('care_hour_purchase_id', $purchase->id)->with('account')
                ->withSum(['allocations as reserved' => fn ($query) => $query->where('status', 'reserved')], 'minutes')
                ->withSum(['allocations as used' => fn ($query) => $query->where('status', 'used')], 'minutes')->first();
            $balance = ['issued_minutes' => (int) ($lot?->total_minutes ?? 0), 'available_minutes' => 0,
                'reserved_minutes' => (int) ($lot?->reserved ?? 0), 'used_minutes' => (int) ($lot?->used ?? 0),
                'held_minutes' => 0, 'reversed_minutes' => 0];
            $free = $balance['issued_minutes'] - $balance['reserved_minutes'] - $balance['used_minutes'];
            $issue = null;
            if ($free < 0 || ($lot?->status === 'reversed' && $balance['reserved_minutes'] + $balance['used_minutes'] > 0)) {
                $issue = 'This gift’s hour records need review.';
            } elseif ($purchase->gift->claimed_at && ! $lot) {
                $issue = 'This claimed gift has no credited hours record. Review its payment.';
            }
            if ($lot) {
                $key = match (true) {
                    $lot->status === 'reversed' => 'reversed_minutes',
                    $lot->status === 'held', ! $lot->account?->active => 'held_minutes',
                    default => 'available_minutes',
                };
                $balance[$key] = max(0, $free);
            }

            // Keep returned allocations in the visit list. They are historical
            // returns, not extra current credit: the same minutes can be reused.
            $visits = CareHourAllocation::query()->where('care_hour_lot_id', $lot?->id ?? 0)
                ->select('care_booking_id')
                ->selectRaw("SUM(CASE WHEN status = 'reserved' THEN minutes ELSE 0 END) AS reserved_minutes")
                ->selectRaw("SUM(CASE WHEN status = 'used' THEN minutes ELSE 0 END) AS used_minutes")
                ->selectRaw("SUM(CASE WHEN status = 'released' THEN minutes ELSE 0 END) AS returned_minutes")
                ->selectRaw('MAX(updated_at) AS last_activity_at')
                ->with(['booking.careRequest.recipient', 'booking.caregiver', 'booking.carePlan'])
                ->groupBy('care_booking_id')->orderByDesc('last_activity_at')->orderByDesc('care_booking_id')
                ->paginate(15, ['*'], 'visits')->withQueryString()
                ->through(fn (CareHourAllocation $row): array => [
                    'booking_id' => (int) $row->care_booking_id, 'booking' => $row->booking,
                    'reserved_minutes' => (int) $row->reserved_minutes, 'used_minutes' => (int) $row->used_minutes,
                    'returned_minutes' => (int) $row->returned_minutes,
                    'timezone' => $row->booking ? app(CareBookingTimeCorrectionService::class)->timezoneFor($row->booking)
                        : (string) config('app.timezone', 'America/New_York'),
                ]);

            return ['purchase' => $purchase, 'claimant' => $claimant, 'balance' => $balance,
                'lot_status' => $lot?->status, 'balance_issue' => $issue, 'visits' => $visits,
                'timezone' => (string) config('app.timezone', 'America/New_York'),
                'lastCheck' => DB::table('care_hour_gift_admin_checks')->where('care_hour_purchase_id', $purchase->id)->latest('id')->first()];
        });

        return view('care-hours.admin.gifts', compact('detail'));
    }

    public function recheck(Request $request, CareHourPurchase $purchase, CareHourPurchaseService $purchases): RedirectResponse
    {
        abort_unless($purchase->gift()->exists(), 404);
        $input = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::table('care_hour_gift_admin_checks')->insert([
            'care_hour_purchase_id' => $purchase->id, 'actor_user_id' => $request->user()->id,
            'reason' => $input['reason'], 'created_at' => now(),
        ]);
        try {
            $purchases->refresh($purchase);
        } catch (PaymentException|ApiErrorException) {
            return back()->withErrors(['payment' => 'The provider could not confirm this gift payment. The check is recorded; try again later.']);
        }

        return back()->with('status', 'Gift payment checked. Only confirmed provider results can change its status.');
    }
}
