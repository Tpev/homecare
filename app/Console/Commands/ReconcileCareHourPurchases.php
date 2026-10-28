<?php

namespace App\Console\Commands;

use App\Models\CareHourPurchase;
use App\Services\CareHours\CareHourPurchaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReconcileCareHourPurchases extends Command
{
    protected $signature = 'homecare:reconcile-care-hour-purchases {--limit=100}';

    protected $description = 'Recover accepted care-hour purchases and deliver their receipts without changing legacy card payments.';

    public function handle(CareHourPurchaseService $service): int
    {
        if (! Schema::hasTable('care_hour_purchases')) {
            return self::SUCCESS;
        }
        $limit = max(1, min(500, (int) $this->option('limit')));
        $failures = 0;
        $purchases = CareHourPurchase::query()->where(fn ($query) => $query->whereIn('status', ['pending', 'paid', 'issued', 'held'])->orWhere('correction_state', 'pending'))
            ->where(fn ($query) => $query->whereNull('last_reconciled_at')->orWhere('last_reconciled_at', '<', now()->subMinutes(15)))
            ->orderBy('last_reconciled_at')->orderBy('id')->limit($limit)->get();
        foreach ($purchases as $purchase) {
            try {
                if ($purchase->correction_state === 'pending') {
                    $service->reconcileCorrection($purchase);
                    $purchase->forceFill(['last_reconciled_at' => now()])->save();

                    continue;
                }
                if (! $purchase->stripe_checkout_session_id) {
                    $purchase = $service->startCheckout($purchase);
                }
                $service->refresh($purchase);
                $purchase->forceFill(['last_reconciled_at' => now()])->save();
            } catch (Throwable $exception) {
                $failures++;
                $purchase->forceFill(['last_reconciled_at' => now(), 'last_error' => 'Payment reconciliation is pending; retry required.'])->save();
                report($exception);
            }
        }
        $sent = $service->dispatchNotices($limit);
        $this->info($purchases->count().' care-hour purchases checked; '.$sent.' notices delivered; '.$failures.' need retry.');

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
