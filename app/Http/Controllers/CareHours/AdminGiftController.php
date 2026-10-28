<?php

namespace App\Http\Controllers\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\CareHourPurchase;
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
        $gifts = CareHourPurchase::query()->whereHas('gift')->with(['gift', 'account'])
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
