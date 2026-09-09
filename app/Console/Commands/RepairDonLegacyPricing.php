<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\DonLegacyPricingService;
use App\Support\MarketplacePricing;
use Illuminate\Console\Command;

class RepairDonLegacyPricing extends Command
{
    protected $signature = 'homecare:repair-don-pricing
        {--booking=159 : First booking ID to repair, also identifying Don and the agreed caregiver}
        {--apply : Save the agreement and repair eligible uncaptured booking snapshots}
        {--admin= : Administrator user ID recorded in the audit}
        {--caregiver= : Caregiver user ID verified in the preview}';

    protected $description = 'Preview or restore Don’s $15.75 family / $15 caregiver agreement without moving money';

    public function handle(DonLegacyPricingService $repair, BookingPaymentService $payments, MarketplacePricing $pricing): int
    {
        $source = CareBooking::query()->with(['family', 'caregiver'])->findOrFail((int) $this->option('booking'));
        $repair->assertSource($source);
        $this->info('Family: '.$source->family->name.'; caregiver: '.$source->caregiver->name.' (user #'.$source->caregiver_user_id.')');
        $this->line('Don pays $15.75/hour total; caregiver receives $15/hour; LoLo pays processing costs.');
        $this->line('Scope: booking #'.$source->id.' and higher IDs only. Lower booking IDs are excluded, even when scheduled in the future.');
        $rows = [];
        foreach ($repair->affectedBookings($source) as $booking) {
            $minutes = max(1, (int) ($booking->worked_minutes ?: $booking->expected_minutes ?: 60));
            $before = $payments->quoteForWorkedMinutes($booking, $minutes);
            $proposed = clone $booking;
            $proposed->forceFill(array_merge($repair->agreedRates(), ['pricing_version' => $pricing->currentVersion()]));
            $after = $pricing->quoteForCurrentBooking($proposed, $minutes);
            $rows[] = [$booking->id, $minutes, '$'.number_format($before['total_charge_cents'] / 100, 2),
                '$'.number_format($after['total_charge_cents'] / 100, 2), '$'.number_format($after['caregiver_amount_cents'] / 100, 2),
                $repair->blockedReason($booking) ?: 'Eligible'];
        }
        $this->table(['Booking', 'Minutes', 'Current total', 'Correct total', 'Caregiver receives', 'Repair status'], $rows);
        $historical = CareBooking::query()->where('family_account_id', $source->family_account_id)
            ->where('caregiver_user_id', $source->caregiver_user_id)->whereKeyNot($source->id)
            ->where('id', '>=', $source->id)
            ->where('scheduled_start_at', '<', now())->where('status', '!=', CareBooking::STATUS_CANCELLED)
            ->pluck('id')->all();
        if ($historical) {
            $this->warn('Other historical visits to audit separately: '.implode(', ', $historical));
        }
        if (! $this->option('apply')) {
            $this->info('Preview only. No records changed and no Stripe calls made.');

            return self::SUCCESS;
        }
        if (! $this->option('admin') || ! $this->option('caregiver')) {
            $this->error('--apply requires --admin and the --caregiver ID verified above.');

            return self::FAILURE;
        }
        $result = $repair->apply($source, User::query()->findOrFail((int) $this->option('admin')), (int) $this->option('caregiver'));
        $this->info('Agreement #'.$result['agreement']->id.' saved. Repaired bookings: '.(implode(', ', $result['repaired']) ?: 'none (already correct or blocked)'));
        foreach ($result['skipped'] as $id => $reason) {
            $this->warn('Booking #'.$id.' skipped: '.$reason);
        }
        $this->line('No payment, payout, visit-time, or ticket-status action was performed. Review ticket #52’s corrected preview before completing billing.');

        return $result['skipped'] ? self::FAILURE : self::SUCCESS;
    }
}
