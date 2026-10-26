<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CompletedExtraVisitRequest;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Support\MarketplacePricing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CreateJohnCharlesOctober20Visit extends Command
{
    protected $signature = 'homecare:create-john-charles-oct20-visit
        {--apply : Create the completed visit, capture payment, and settle caregiver earnings}
        {--expected-charge= : Expected USD family charge in cents, required for apply}
        {--admin= : Administrator ID for the audit; defaults to Thibaud Peverelli - Admin}';

    protected $description = 'Preview or complete John and Charles October 20, 2026 visit, 11:30 AM-12:30 PM Raleigh time';

    public function handle(): int
    {
        $lock = null;
        try {
            if ($this->option('apply')) {
                $lock = Cache::lock('john-charles-2026-10-20-1130', 600);
                if (! $lock->get()) {
                    throw new RuntimeException('This visit is already being processed. Inspect the existing result before retrying.');
                }
            }
            $results = $this->createVisits((bool) $this->option('apply'));
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock?->release();
        }
    }

    private function createVisits(bool $charge): array
    {
        $check = static function (bool $ok, string $message): void {
            if (! $ok) {
                throw new RuntimeException($message);
            }
        };
        $admin = $this->option('admin') !== null
            ? User::findOrFail((int) $this->option('admin'))
            : User::where('role', 'admin')->where('name', 'Thibaud Peverelli - Admin')->sole();
        $check($admin->isAdministrator(), 'The audit actor must be an administrator.');
        $previousActor = auth()->user();
        auth()->setUser($admin);

        try {
            $pricing = app(MarketplacePricing::class);
            $trust = app(BookingTrustService::class);
            $payments = app(BookingPaymentService::class);
            $check($pricing->currentPricingEnabled(), 'Current pricing must be enabled.');
            $check(! $charge || ! config('services.stripe.bypass') || app()->environment('testing'),
                'Stripe bypass is enabled: refusing to report a simulated payment as a real charge.');
            $secret = (string) config('services.stripe.secret');
            $check(! $charge || app()->environment('testing') || (app()->environment('production')
                && (str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_'))),
                'Apply requires production with a live Stripe key.');
            $check(strtolower((string) config('services.stripe.currency')) === 'usd', 'This visit must be billed in USD.');
            $check(! $charge || (ctype_digit((string) $this->option('expected-charge')) && (int) $this->option('expected-charge') > 0),
                'Run the preview, then pass its charge_cents as --expected-charge.');

            // Commit the booking before contacting Stripe; payment failures must not erase it.
            $ids = DB::transaction(function () use ($admin, $pricing, $trust, $payments, $check, $charge): array {
                $family = User::lockForUpdate()->findOrFail(431);
                $charles = User::with('caregiverProfile')->lockForUpdate()->findOrFail(17);
                $account = FamilyAccount::lockForUpdate()->findOrFail(37);
                $template = CareRequest::with(['recipient', 'tasks'])->findOrFail(241);
                $check($family->role === 'family' && $family->name === 'John Grady Eberdt'
                    && $family->email === 'jeberdt@gmail.com', 'John #431 does not match.');
                $check($charles->role === 'caregiver' && $charles->name === 'Charles Petrini-Poli'
                    && $charles->email === 'charlespetrinipoli@gmail.com'
                    && $charles->caregiverProfile?->status === 'active', 'Charles #17 does not match or is inactive.');
                $check((int) $account->owner_user_id === 431 && $account->status === 'active'
                    && (int) $template->family_user_id === 431 && (int) $template->family_account_id === 37
                    && $template->request_type === 'one_time' && $template->title === 'One-time care support for Transportation'
                    && $template->city === 'Raleigh'
                    && $template->state === 'NC' && $template->recipient !== null,
                    'John account #37 or template request #241 does not match.');
                $ids = [];

                foreach (['2026-10-20'] as $date) {
                    $start = Carbon::parse($date.' 11:30:00', 'America/New_York')->setTimezone(config('app.timezone'));
                    $end = $start->copy()->addHour();
                    $check($end->isPast(), 'This command is only for completed historical shifts.');
                    $marker = 'Admin historical visit: John #431 / Charles #17 / '.$date.' 11:30-12:30 America/New_York.';
                    $matches = CareRequest::where('family_account_id', 37)
                        ->where('additional_info', $marker)->get();
                    $check($matches->count() <= 1, 'Duplicate recovery markers found for '.$date.'.');
                    $request = $matches->first();

                    if ($request) {
                        $booking = CareBooking::where('care_request_id', $request->id)->sole();
                        $check($request->status === 'filled' && (int) $request->family_user_id === 431
                            && $request->requested_start_at?->equalTo($start) && $request->requested_end_at?->equalTo($end)
                            && (int) $booking->family_user_id === 431 && (int) $booking->family_account_id === 37
                            && (int) $booking->caregiver_user_id === 17 && in_array($booking->status, ['completed', 'reviewed'], true)
                            && $booking->scheduled_start_at?->equalTo($start) && $booking->scheduled_end_at?->equalTo($end)
                            && $booking->started_at?->equalTo($start) && $booking->completed_at?->equalTo($end)
                            && (int) $booking->worked_minutes === 60 && (int) $booking->expected_minutes === 60
                            && $booking->application?->status === 'hired'
                            && (int) $booking->application?->care_request_id === (int) $request->id
                            && (int) $booking->application?->caregiver_user_id === 17,
                            'Previously created shift changed; inspect booking #'.$booking->id.' before continuing.');
                        $quote = $payments->quoteForWorkedMinutes($booking, 60);
                        $check(! $charge || (int) $this->option('expected-charge') === (int) $quote['total_charge_cents'],
                            'The expected charge does not match this booking. Run preview again and review the amount.');
                        if (! $charge) {
                            return $this->preview($admin, $quote) + ['request_id' => $request->id, 'booking_id' => $booking->id,
                                'payment' => $booking->payment?->only(['id', 'status', 'amount_captured_cents', 'stripe_payment_intent_id', 'stripe_transfer_id', 'last_error'])];
                        }
                        $ids[] = $booking->id;

                        continue;
                    }

                    $check(! CareRequest::where('family_account_id', 37)
                        ->where('requested_start_at', '<', $end)->where('requested_end_at', '>', $start)->exists(),
                        'John already has a request overlapping '.$date.'. Inspect it before creating another.');
                    $check(! CareBooking::where(fn ($q) => $q->where('family_account_id', 37)->orWhere('caregiver_user_id', 17))
                        ->where(fn ($q) => $q
                            ->where(fn ($w) => $w->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start))
                            ->orWhere(fn ($w) => $w->where('started_at', '<', $end)->where('completed_at', '>', $start)))
                        ->exists(), 'John or Charles already has an overlapping booking on '.$date.'.');
                    $check(! CompletedExtraVisitRequest::where(fn ($q) => $q->where('family_account_id', 37)->orWhere('caregiver_user_id', 17))
                        ->whereNotIn('status', ['withdrawn', 'superseded'])
                        ->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)->exists(),
                        'An extra-visit report already covers '.$date.'.');
                    $check(! CareBookingTimeCorrection::active()
                        ->whereHas('booking', fn ($q) => $q->where(fn ($w) => $w->where('family_account_id', 37)->orWhere('caregiver_user_id', 17)))
                        ->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)->exists(),
                        'A time correction already covers '.$date.'.');

                    $quote = $pricing->quoteForPair(37, 17, 60);
                    $check(! $charge || (int) $this->option('expected-charge') === (int) $quote['total_charge_cents'],
                        'The expected charge does not match current pricing. Run preview again and review the amount.');
                    if (! $charge) {
                        return $this->preview($admin, $quote) + ['request_id' => null, 'booking_id' => null];
                    }

                    $request = new CareRequest($template->only([
                        'title', 'scope_of_work', 'home_access_notes', 'address_line1', 'address_line2', 'city', 'state', 'zip', 'lat', 'lng',
                    ]));
                    $request->forceFill([
                        'family_user_id' => 431, 'family_account_id' => 37, 'created_by_user_id' => $admin->id,
                        'request_type' => 'one_time', 'status' => 'filled', 'is_private' => true,
                        'requested_start_at' => $start, 'requested_end_at' => $end,
                        'additional_info' => $marker, 'time_expectations' => 'One hour, 11:30 AM-12:30 PM Eastern.', 'first_hire_at' => now(),
                    ])->saveQuietly();
                    $recipient = $template->recipient->replicate();
                    $recipient->care_request_id = $request->id;
                    $recipient->saveQuietly();
                    $request->tasks()->sync($template->tasks->mapWithKeys(fn ($task) => [$task->id => ['task_note' => $task->pivot->task_note]])->all());
                    $request->load(['recipient', 'tasks']);

                    $quote = $pricing->quoteForPair(37, 17, 60);
                    $application = new CareRequestApplication([
                        'care_request_id' => $request->id, 'caregiver_user_id' => 17, 'status' => 'hired',
                        'proposed_rate' => $quote['hourly_rate'], 'cover_note' => 'Assigned by administrator for a reported completed visit.',
                    ]);
                    $application->saveQuietly();
                    CareRequestConversation::findOrCreateForApplication($application->setRelation('careRequest', $request), $admin->id);
                    $booking = CareBooking::create([
                        'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
                        'family_user_id' => 431, 'family_account_id' => 37, 'caregiver_user_id' => 17,
                        'status' => 'completed', 'scheduled_start_at' => $start, 'scheduled_end_at' => $end,
                        'started_at' => $start, 'completed_at' => $end, 'expected_minutes' => 60, 'worked_minutes' => 60,
                        'total_paused_seconds' => 0, 'check_in_source' => 'admin', 'check_out_source' => 'admin',
                        'check_in_note' => $marker, 'check_out_note' => '60 minutes recorded from the administrator request.',
                        'agreement_snapshot' => $trust->buildAgreementSnapshot($request, $application),
                    ]);
                    $booking->forceFill($pricing->currentSnapshotAttributes($booking))->saveQuietly();
                    $check((int) $payments->quoteForWorkedMinutes($booking, 60)['total_charge_cents'] === (int) $this->option('expected-charge'),
                        'Pricing changed after assigning the booking. No visit was saved.');
                    $trust->seedTaskChecks($booking, $request);
                    $trust->recordEvent($booking, $admin->id, 'admin', 'historical_visit_created_by_admin', [
                        'source' => 'john-charles-october-20-2026', 'template_request_id' => 241,
                        'local_date' => $date, 'timezone' => 'America/New_York', 'worked_minutes' => 60,
                        'reason' => 'Administrator requested creation, payment and completion of the October 20 one-hour visit.',
                    ]);
                    $ids[] = $booking->id;
                }

                return $ids;
            }, 3);

            if (! $charge) {
                return $ids;
            }

            $results = [];
            foreach ($ids as $id) {
                $booking = CareBooking::findOrFail($id);
                $check($booking->dispute_opened_at === null && ! $booking->timeCorrections()->active()->exists(),
                    'A dispute or active time correction needs review for booking #'.$id.'.');
                $quote = $payments->quoteForWorkedMinutes($booking, 60);
                $summary = [
                    'request_id' => $booking->care_request_id, 'booking_id' => $id,
                    'date' => $booking->scheduled_start_at->copy()->setTimezone('America/New_York')->toDateString(),
                    'time' => '11:30 AM-12:30 PM Eastern', 'charge_cents' => $quote['total_charge_cents'],
                    'currency' => config('services.stripe.currency'),
                ];
                $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $payment = $booking->payment;
                $check(! $payment || ((int) $payment->amount_refunded_cents === 0
                    && ! $payment->hasPrepaidVisitHold()
                    && ! in_array($payment->status, ['refunded', 'partially_refunded', 'cancelled'], true)),
                    'Payment was refunded or cancelled; inspect booking #'.$id.' manually.');
                if ($charge && ! ($payment && in_array($payment->status, ['captured', 'transferred', 'transfer_failed'], true))) {
                    try {
                        $trust->recordEvent($booking, $admin->id, 'admin', 'historical_visit_payment_requested', $summary);
                        $payments->authorizeForBooking($booking->fresh(), notify: false);
                        $payments->captureForBooking($booking->fresh(), notify: false);
                    } catch (Throwable $error) {
                        $this->line(json_encode(['booking_id' => $id, 'payment_error' => $error->getMessage(),
                            'payment' => $booking->payment()->first()?->only(['status', 'amount_captured_cents', 'stripe_payment_intent_id'])], JSON_THROW_ON_ERROR));
                        throw $error;
                    }
                }
                $payment = $booking->payment()->first();
                if ($charge) {
                    $check($payment !== null && in_array($payment->status, ['captured', 'transferred', 'transfer_failed'], true)
                        && (int) $payment->amount_captured_cents === (int) $quote['total_charge_cents']
                        && (int) $payment->overage_pending_cents === 0,
                        'Payment is not fully captured at the expected amount; inspect booking #'.$id.'.');
                }
                // Reuse the existing capture and ledger when only fee/transfer settlement is pending.
                if ($payment->status !== 'transferred' || $payment->fee_finalization_status !== 'finalized') {
                    $payment = app(BookingPaymentV2Service::class)->finalizeFeesAndTransfers($payment);
                }
                DB::transaction(function () use ($booking, $admin, $trust, $summary): void {
                    $locked = CareBooking::lockForUpdate()->findOrFail($booking->id);
                    if (! $locked->family_confirmed_at) {
                        // This field also closes system-approved visits; the audit records admin approval,
                        // without claiming John approved or Charles submitted a historical timesheet.
                        $locked->forceFill(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => null])->saveQuietly();
                        $trust->recordEvent($locked, $admin->id, 'admin', 'historical_visit_completed_and_paid_by_admin',
                            $summary + ['approval_basis' => 'administrator_instruction', 'source' => 'homecare:create-john-charles-oct20-visit']);
                    }
                });
                $trust->recomputeReliabilityForBooking($booking->fresh());
                $results[] = $summary + ['payment' => $payment?->only([
                    'id', 'status', 'amount_captured_cents', 'stripe_payment_intent_id', 'stripe_transfer_id',
                    'caregiver_gross_amount_cents', 'caregiver_amount_cents', 'stripe_processing_fee_cents', 'fee_finalization_status', 'last_error',
                ])];
                $this->line(json_encode(end($results), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $check($payment->status === 'transferred' && $payment->stripe_transfer_id && $payment->fee_finalization_status === 'finalized',
                    'The family payment was captured, but caregiver settlement is pending. Re-run this same command to reconcile; do not create another charge.');
            }

            return $results;
        } finally {
            $previousActor ? auth()->setUser($previousActor) : auth()->forgetUser();
        }
    }

    private function preview(User $admin, array $quote): array
    {
        return [
            'result' => 'preview_only', 'admin_id' => $admin->id,
            'family' => ['id' => 431, 'name' => 'John Grady Eberdt', 'account_id' => 37],
            'caregiver' => ['id' => 17, 'name' => 'Charles Petrini-Poli'],
            'date' => '2026-10-20', 'time' => '11:30 AM-12:30 PM', 'timezone' => 'America/New_York',
            'worked_minutes' => 60, 'currency' => 'usd', 'charge_cents' => $quote['total_charge_cents'],
            'caregiver_gross_cents' => $quote['caregiver_gross_amount_cents'],
            'template_request_id' => 241,
        ];
    }
}
