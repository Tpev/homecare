<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\User;
use App\Services\Payments\DonFamilyPricingService;
use App\Services\Payments\DonLegacyPricingService;
use App\Support\MarketplacePricing;
use Illuminate\Console\Command;

class RepairDonFamilyPricing extends Command
{
    protected $signature = 'homecare:repair-don-family-pricing
        {--booking=322 : Don booking to verify and include in the repair}
        {--apply : Register account-wide pricing and repair uncharged snapshots}
        {--family= : Family account ID verified in the preview}
        {--admin= : Administrator user ID for the audit}';

    protected $description = 'Apply Don’s $15.75 total / $15 caregiver rates to every caregiver without moving money';

    public function handle(DonFamilyPricingService $repair, DonLegacyPricingService $legacy, MarketplacePricing $pricing): int
    {
        $source = CareBooking::with(['family', 'caregiver'])->findOrFail((int) $this->option('booking'));
        $legacy->assertSource($source);
        $this->info('Family: '.$source->family->name.'; account #'.$source->family_account_id.'. All caregivers.');
        $rows = [];
        foreach ($repair->candidates($source) as $booking) {
            $minutes = max(1, (int) ($booking->worked_minutes ?: $booking->expected_minutes ?: 60));
            $proposed = clone $booking;
            $proposed->forceFill($legacy->agreedRates());
            $quote = $pricing->quoteForCurrentBooking($proposed, $minutes);
            $rows[] = [$booking->id, $booking->caregiver?->name, $minutes,
                number_format($quote['total_charge_cents'] / 100, 2), number_format($quote['caregiver_amount_cents'] / 100, 2),
                number_format(($booking->payment?->amount_authorized_cents ?? 0) / 100, 2), $repair->blockedReason($booking) ?: 'Eligible'];
        }
        $this->table(['Booking', 'Caregiver', 'Minutes', 'Family USD', 'Caregiver USD', 'Existing hold USD', 'Repair'], $rows);
        if (! $this->option('apply')) {
            $this->info('Preview only. No records changed or Stripe calls made.');
            return self::SUCCESS;
        }
        if (! $this->option('admin') || ! $this->option('family')) {
            $this->error('--apply requires --admin and the --family account from the preview.');
            return self::FAILURE;
        }
        $result = $repair->apply($source, User::findOrFail((int) $this->option('admin')), (int) $this->option('family'));
        $this->info('Account-wide agreement #'.$result['agreement']->id.'. Repaired: '.(implode(', ', $result['repaired']) ?: 'none; already correct or blocked'));
        foreach ($result['skipped'] as $id => $reason) {
            $this->warn('Booking #'.$id.' skipped: '.$reason);
        }
        $this->line('Existing card holds are unchanged. Final capture uses approved minutes at $15.75/hour; caregiver receives $15/hour. No visit completion, notification, refund or ticket resolution performed.');
        return $result['skipped'] ? self::FAILURE : self::SUCCESS;
    }
}
