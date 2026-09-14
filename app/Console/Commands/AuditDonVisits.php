<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\CarePricingAgreement;
use App\Services\Payments\DonLegacyPricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AuditDonVisits extends Command
{
    protected $signature = 'homecare:audit-don-visits
        {--booking=159 : Existing Don visit identifying the family and agreed caregiver}
        {--from= : First visit date (YYYY-MM-DD), defaults to seven days ago}
        {--to= : Last visit date (YYYY-MM-DD), defaults to fourteen days ahead}';

    protected $description = 'Read-only report of Don visits, possible overlaps, pricing, and recorded payments, including IDs below the repair cutoff';

    public function handle(DonLegacyPricingService $repair): int
    {
        $source = CareBooking::query()->with(['family', 'caregiver'])->findOrFail((int) $this->option('booking'));
        $repair->assertSource($source);
        $dates = [
            'from' => $this->option('from') ?: now()->subDays(7)->toDateString(),
            'to' => $this->option('to') ?: now()->addDays(14)->toDateString(),
        ];
        Validator::make($dates, [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();
        $from = Carbon::parse($dates['from'])->startOfDay();
        $to = Carbon::parse($dates['to'])->endOfDay();
        $bookings = CareBooking::query()
            ->where(function ($query) use ($source): void {
                $query->where('family_account_id', $source->family_account_id)
                    ->orWhere('family_user_id', $source->family_user_id);
            })
            ->where('scheduled_start_at', '<=', $to)
            ->where('scheduled_end_at', '>=', $from)
            ->with(['caregiver:id,name', 'carePlan:id,status,source_care_request_id', 'payment'])
            ->orderBy('scheduled_start_at')->orderBy('id')->get();
        $agreement = CarePricingAgreement::query()
            ->where('family_account_id', $source->family_account_id)
            ->where('caregiver_user_id', $source->caregiver_user_id)->first();

        $this->info('Family: '.$source->family->name.'; agreed caregiver: '.$source->caregiver->name.' (user #'.$source->caregiver_user_id.')');
        $this->line('Dates: '.$dates['from'].' through '.$dates['to'].'; timezone: '.config('app.timezone').'. Includes all booking IDs and caregivers for this family.');
        $this->line($agreement
            ? 'Agreement #'.$agreement->id.': '.($agreement->active ? 'active' : 'inactive').'; pricing cutoff: booking #'.$agreement->source_booking_id.'.'
            : 'No registered agreement found for this family and caregiver.');

        $rows = [];
        $paymentRows = [];
        foreach ($bookings as $booking) {
            $overlaps = $bookings->filter(fn (CareBooking $other): bool => $other->id !== $booking->id
                && $other->caregiver_user_id === $booking->caregiver_user_id
                && $booking->status !== CareBooking::STATUS_CANCELLED && $other->status !== CareBooking::STATUS_CANCELLED
                && $other->scheduled_start_at->lt($booking->scheduled_end_at)
                && $other->scheduled_end_at->gt($booking->scheduled_start_at))->pluck('id')->all();
            $rate = $booking->family_care_rate_cents !== null && $booking->family_processing_fee_rate_cents !== null
                ? $this->money($booking->family_care_rate_cents + $booking->family_processing_fee_rate_cents)
                : 'Legacy / inspect';
            $rows[] = [$booking->id, $booking->scheduled_start_at->format('Y-m-d H:i'), $booking->scheduled_end_at->format('H:i'),
                $booking->caregiver?->name.' (#'.$booking->caregiver_user_id.')', $booking->care_request_id,
                $booking->carePlan ? '#'.$booking->carePlan->id.' '.$booking->carePlan->status.' (source #'.$booking->carePlan->source_care_request_id.')' : '-',
                $booking->status, $rate, $booking->pricing_agreement_id ?: '-', implode(', ', $overlaps) ?: '-'];
            $payment = $booking->payment;
            $paymentRows[] = [$booking->id, $payment?->status ?: 'No record', strtoupper($payment?->currency ?? ''),
                $this->money($payment?->amount_authorized_cents), $this->money($payment?->amount_captured_cents),
                $this->money($payment?->amount_refunded_cents), $payment?->stripe_payment_intent_id ?: '-'];
        }
        $this->table(['Booking', 'Start', 'End', 'Caregiver', 'Request', 'Plan', 'Visit status', 'Family/hr', 'Agreement', 'Possible overlaps'], $rows);
        $this->table(['Booking', 'Payment status', 'Currency', 'Authorized', 'Captured', 'Refunded', 'PaymentIntent'], $paymentRows);
        $this->info('Read-only audit complete. No database changes, Stripe calls, or notifications. Payment values reflect local records; an authorization is not proof of a completed charge.');

        return self::SUCCESS;
    }

    private function money(mixed $cents): string
    {
        return $cents === null ? '-' : number_format((int) $cents / 100, 2, '.', '');
    }
}
