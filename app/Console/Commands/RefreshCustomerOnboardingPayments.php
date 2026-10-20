<?php

namespace App\Console\Commands;

use App\Models\FamilyAccount;
use App\Services\CustomerOnboarding\FamilyPaymentSetupService;
use App\Services\Payments\StripeClient;
use Illuminate\Console\Command;
use Throwable;

class RefreshCustomerOnboardingPayments extends Command
{
    protected $signature = 'customer-onboarding:refresh-payments {--limit=100 : Maximum accounts to refresh}';

    protected $description = 'Refresh unknown or stale family payment summaries for customer onboarding';

    public function handle(FamilyPaymentSetupService $setup, StripeClient $stripe): int
    {
        if ($stripe->isBypass()) {
            $this->warn('Stripe bypass is enabled; no payment snapshots were changed.');

            return self::SUCCESS;
        }
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $accounts = FamilyAccount::query()->with('owner')->where('status', FamilyAccount::STATUS_ACTIVE)
            ->where(fn ($query) => $query->where(fn ($account) => $account->whereNotNull('stripe_customer_id')->where('stripe_customer_id', '!=', ''))
                ->orWhereHas('owner', fn ($owner) => $owner->whereNotNull('stripe_customer_id')->where('stripe_customer_id', '!=', '')))
            ->where(fn ($query) => $query->whereNull('payment_method_checked_at')
                ->orWhere('payment_method_checked_at', '<=', now()->subDay()))
            ->orderBy('payment_method_refresh_attempted_at')->orderBy('payment_method_checked_at')->orderBy('id')->limit($limit)->get();
        $failed = 0;
        foreach ($accounts as $account) {
            try {
                $setup->summaryForAccount($account, true);
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->warn("Could not refresh family account {$account->id}; its last known summary was preserved.");
            }
        }
        $this->info(($accounts->count() - $failed).' payment summaries refreshed.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
