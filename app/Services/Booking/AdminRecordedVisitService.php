<?php

namespace App\Services\Booking;

use App\Exceptions\Payments\PaymentException;
use App\Models\AdminRecordedVisit;
use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingTimeCorrection;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CompletedExtraVisitRequest;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingPaymentV2Service;
use App\Support\MarketplacePricing;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminRecordedVisitService
{
    public function __construct(
        private readonly MarketplacePricing $pricing,
        private readonly BookingTrustService $trust,
        private readonly BookingPaymentService $payments,
        private readonly BookingPaymentV2Service $paymentsV2,
    ) {}

    public function preview(User $admin, array $input, bool $lock = false): array
    {
        $this->authorize($admin);
        $data = $this->normalize($input);
        [$family, $caregiver] = $this->participants($data);
        $this->assertNoOverlap($data, $lock);
        $quote = $this->pricing->quoteForPair($family->id, $caregiver->id, $data['worked_minutes']);
        $this->ensure($this->pricing->currentPricingEnabled(), 'Current marketplace pricing must be enabled.');
        $this->ensure($quote['total_charge_cents'] >= 100, 'The family charge must be at least $1.00.');
        $mode = $this->paymentMode();
        $context = ['owner_id' => $family->owner_user_id, 'family' => $family->owner->name,
            'family_email' => $family->owner->email, 'caregiver' => $caregiver->name, 'payment_mode' => $mode];

        return ['input' => $data, 'quote' => $quote, ...$context,
            'fingerprint' => $this->fingerprint([$data, $quote, $context]),
            'start_label' => CarbonImmutable::parse($data['started_at'], config('app.timezone'))->setTimezone($data['timezone'])->format('D, M j, Y · g:i A T (P)'),
            'end_label' => CarbonImmutable::parse($data['completed_at'], config('app.timezone'))->setTimezone($data['timezone'])->format('D, M j, Y · g:i A T (P)'),
        ];
    }

    /** Persist the visit before contacting Stripe so any failure can resume the same booking. */
    public function create(User $admin, array $input, string $token, string $expectedFingerprint): AdminRecordedVisit
    {
        $this->authorize($admin);
        Validator::make(['token' => $token], ['token' => ['required', 'uuid']])->validate();
        $data = $this->normalize($input);
        $hash = $this->fingerprint($data);

        return DB::transaction(function () use ($admin, $input, $data, $hash, $token, $expectedFingerprint): AdminRecordedVisit {
            [$family] = $this->participants($data, lock: true);
            $existing = AdminRecordedVisit::where('request_token', $token)->lockForUpdate()->first();
            if ($existing) {
                $this->ensure(hash_equals($existing->input_hash, $hash), 'This submission already created a visit with different details. Open its saved record.');

                return $existing;
            }
            $preview = $this->preview($admin, $input, lock: true);
            $this->ensure(hash_equals($preview['fingerprint'], $expectedFingerprint), 'The visit or pricing changed. Review the charge again before creating it.');

            $request = new CareRequest;
            $request->forceFill([
                'family_user_id' => $family->owner_user_id, 'family_account_id' => $family->id, 'created_by_user_id' => $admin->id,
                'request_type' => CareRequest::TYPE_ONE_TIME, 'status' => CareRequest::STATUS_FILLED, 'is_private' => true,
                'is_system_generated' => false, 'title' => $data['title'], 'scope_of_work' => $data['scope_of_work'],
                'address_line1' => $data['address_line1'], 'address_line2' => $data['address_line2'], 'city' => $data['city'],
                'state' => $data['state'], 'zip' => $data['zip'], 'requested_start_at' => $data['started_at'],
                'requested_end_at' => $data['completed_at'], 'first_hire_at' => now(),
                'time_expectations' => $data['worked_minutes'].' worked minutes; '.$data['break_minutes'].' unpaid break minutes. Time zone: '.$data['timezone'].'.',
            ])->saveQuietly();
            CareRecipient::create(['care_request_id' => $request->id, 'full_name' => $data['recipient_name'],
                'relationship_to_family' => $data['relationship'], 'recipient_is_requester' => strcasecmp($data['relationship'], 'Self') === 0]);
            $request->tasks()->sync($data['task_ids']);
            $request->load(['recipient', 'tasks']);
            $application = new CareRequestApplication(['care_request_id' => $request->id, 'caregiver_user_id' => $data['caregiver_user_id'],
                'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => $preview['quote']['hourly_rate'],
                'cover_note' => 'Assigned by an administrator for a reported completed visit.']);
            $application->saveQuietly();
            CareRequestConversation::findOrCreateForApplication($application->setRelation('careRequest', $request), $admin->id);
            $booking = CareBooking::create([
                'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
                'family_user_id' => $family->owner_user_id, 'family_account_id' => $family->id, 'caregiver_user_id' => $data['caregiver_user_id'],
                'status' => CareBooking::STATUS_COMPLETED, 'scheduled_start_at' => $data['started_at'], 'scheduled_end_at' => $data['completed_at'],
                'started_at' => $data['started_at'], 'completed_at' => $data['completed_at'],
                'expected_minutes' => $data['worked_minutes'], 'worked_minutes' => $data['worked_minutes'],
                'total_paused_seconds' => $data['break_minutes'] * 60, 'check_in_source' => 'admin', 'check_out_source' => 'admin',
                'check_in_note' => 'Past visit recorded by an administrator.', 'check_out_note' => $data['reason'],
                'agreement_snapshot' => $this->trust->buildAgreementSnapshot($request, $application),
            ]);
            $booking->forceFill($this->pricing->currentSnapshotAttributes($booking))->saveQuietly();
            $quote = $this->pricing->quoteForCurrentBooking($booking, $data['worked_minutes']);
            $this->ensure($this->fingerprint($quote) === $this->fingerprint($preview['quote']), 'The saved booking has different pricing. Review the charge again; no visit was saved.');
            $this->trust->seedTaskChecks($booking, $request);
            $visit = AdminRecordedVisit::create(['request_token' => $token, 'input_hash' => $hash, 'created_by_user_id' => $admin->id,
                'care_booking_id' => $booking->id, 'input' => $data + ['owner_user_id' => (int) $family->owner_user_id, 'care_request_id' => $request->id],
                'quote' => $quote, 'payment_mode' => $preview['payment_mode']]);
            $this->trust->recordEvent($booking, $admin->id, 'admin', 'historical_visit_created_by_admin', [
                'recorded_visit_id' => $visit->id, 'source' => 'admin_record_past_visit', 'reason' => $data['reason'],
                'timezone' => $data['timezone'], 'source_request_id' => $data['source_request_id'],
                'worked_minutes' => $data['worked_minutes'], 'break_minutes' => $data['break_minutes'],
                'approval_basis' => 'administrator_instruction', 'reviewed_charge_cents' => $quote['total_charge_cents'],
            ]);

            return $visit;
        }, 3);
    }

    /** Retry only this saved visit. Provider idempotency and the existing ledger own money movement. */
    public function processPayment(User $admin, AdminRecordedVisit $visit): AdminRecordedVisit
    {
        $this->authorize($admin);
        $lock = Cache::lock('admin-recorded-visit:'.$visit->id, 600);
        $this->ensure($lock->get(), 'This visit is already processing. Refresh its status in a moment.');
        try {
            $visit->refresh();
            $this->ensure($visit->payment_mode === $this->paymentMode(), 'The payment environment changed. Review this visit before retrying.');
            $booking = $visit->booking()->with(['payment', 'careRequest', 'application'])->firstOrFail();
            $this->assertUnchanged($visit, $booking);
            $payment = $booking->payment;
            $this->ensure(! $payment || (! $payment->hasPrepaidVisitHold() && ! $payment->amount_refunded_cents
                && ! in_array($payment->status, ['refunded', 'partially_refunded', 'cancelled'], true)), 'This payment has a hold, cancellation or refund. Open the request to review it.');
            $this->ensure(! app(\App\Services\CareHours\PrepaidBookingFundingService::class)->isPrepaid($booking),
                'This booking now uses care hours. Open the request to review its funding.');
            $quote = $this->pricing->quoteForCurrentBooking($booking, $booking->worked_minutes);
            $this->ensure($this->fingerprint($quote) === $this->fingerprint($visit->quote) && ! $this->pricing->hasUnappliedAgreement($booking), 'Pricing changed after this visit was recorded. Review the request before payment.');

            if (! $payment || ! in_array($payment->status, ['captured', 'transferred', 'transfer_failed'], true)) {
                // Card authentication must happen in the family's existing billing flow.
                $this->ensure(! $payment || ! in_array($payment->status, ['authorization_required', 'reauth_required'], true),
                    'The family needs to confirm their card in Billing & Payments. Refresh this visit after confirmation.');
                $this->trust->recordEvent($booking, $admin->id, 'admin', 'historical_visit_payment_requested',
                    ['recorded_visit_id' => $visit->id, 'charge_cents' => $visit->quote['total_charge_cents']]);
                $this->payments->authorizeForBooking($booking->fresh(), notify: false);
                $this->payments->captureForBooking($booking->fresh(), notify: false);
            }
            $payment = $booking->payment()->firstOrFail();
            $this->ensure(in_array($payment->status, ['captured', 'transferred', 'transfer_failed'], true)
                && (int) $payment->amount_captured_cents === (int) $visit->quote['total_charge_cents'] && (int) $payment->overage_pending_cents === 0,
                'Payment is not fully captured at the reviewed amount. Open Payments to inspect it.');
            $this->closeApproval($visit, $admin);
            if ($payment->status !== 'transferred' || $payment->fee_finalization_status !== 'finalized') {
                $payment = $this->paymentsV2->finalizeFeesAndTransfers($payment);
            }
            $visit->update(['last_error' => $payment->status === 'transferred' && $payment->fee_finalization_status === 'finalized'
                ? null : 'The family payment is captured. Caregiver earnings are waiting for fee finalization or transfer. You can retry settlement here.']);
        } catch (ValidationException $e) {
            $visit->update(['last_error' => implode(' ', $e->validator->errors()->all())]);
        } catch (PaymentException $e) {
            $visit->update(['last_error' => $e->userMessage]);
        } catch (Throwable $e) {
            report($e);
            $visit->update(['last_error' => 'Processing was interrupted. The visit is saved. Refresh the payment status before retrying.']);
        } finally {
            $lock->release();
        }

        return $visit->fresh();
    }

    private function closeApproval(AdminRecordedVisit $visit, User $admin): void
    {
        DB::transaction(function () use ($visit, $admin): void {
            $booking = CareBooking::lockForUpdate()->findOrFail($visit->care_booking_id);
            $this->assertUnchanged($visit, $booking);
            if (! $booking->family_confirmed_at) {
                // Shared approval-complete field, with administrator attribution in the event.
                // Do not invent family approval, a caregiver submission, or terms acceptance.
                $booking->forceFill(['family_confirmed_at' => now(), 'family_confirmed_by_user_id' => null])->saveQuietly();
                $this->trust->recordEvent($booking, $admin->id, 'admin', 'historical_visit_completed_and_paid_by_admin',
                    ['recorded_visit_id' => $visit->id, 'approval_basis' => 'administrator_instruction', 'charge_cents' => $visit->quote['total_charge_cents']]);
                $this->trust->recomputeReliabilityForBooking($booking);
            }
        });
    }

    private function assertUnchanged(AdminRecordedVisit $visit, CareBooking $booking): void
    {
        $data = $visit->input;
        $this->ensure(in_array($booking->status, ['completed', 'reviewed'], true)
            && (int) $booking->family_account_id === $data['family_account_id'] && (int) $booking->caregiver_user_id === $data['caregiver_user_id']
            && (int) $booking->family_user_id === $data['owner_user_id'] && (int) $booking->care_request_id === $data['care_request_id']
            && (int) $booking->careRequest?->family_user_id === $data['owner_user_id']
            && (int) $booking->careRequest?->family_account_id === $data['family_account_id'] && $booking->careRequest?->status === 'filled'
            && (int) $booking->application?->care_request_id === $data['care_request_id']
            && (int) $booking->application?->caregiver_user_id === $data['caregiver_user_id'] && $booking->application?->status === 'hired'
            && FamilyAccount::whereKey($data['family_account_id'])->where('owner_user_id', $data['owner_user_id'])->where('status', 'active')->exists()
            && $booking->scheduled_start_at?->format('Y-m-d H:i:s') === $data['started_at']
            && $booking->scheduled_end_at?->format('Y-m-d H:i:s') === $data['completed_at']
            && (int) $booking->expected_minutes === $data['worked_minutes']
            && $booking->started_at?->format('Y-m-d H:i:s') === $data['started_at'] && $booking->completed_at?->format('Y-m-d H:i:s') === $data['completed_at']
            && (int) $booking->worked_minutes === $data['worked_minutes'] && (int) $booking->total_paused_seconds === $data['break_minutes'] * 60
            && ! $booking->dispute_opened_at && ! $booking->cancelled_at && ! $booking->timeCorrections()->active()->exists()
            && ! CareBookingCorrection::where('care_booking_id', $booking->id)->exists(),
            'This booking has changed or needs review. Open the request before attempting payment.');
    }

    public function normalize(array $input): array
    {
        $rules = [
            'family_account_id' => ['required', 'integer', 'min:1'], 'caregiver_user_id' => ['required', 'integer', 'min:1'],
            'source_request_id' => ['nullable', 'integer', 'min:1'], 'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'], 'timezone' => ['required', 'timezone'],
            'start_occurrence' => ['required', Rule::in(['first', 'second'])],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'], 'break_minutes' => ['required', 'integer', 'min:0', 'lt:form.duration_minutes'],
            'recipient_name' => ['required', 'string', 'max:255'], 'relationship' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'], 'scope_of_work' => ['nullable', 'string', 'max:5000'],
            'address_line1' => ['required', 'string', 'max:255'], 'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'], 'state' => ['required', 'regex:/^[A-Za-z]{2}$/'], 'zip' => ['required', 'string', 'max:15'],
            'task_ids' => ['present', 'array', 'max:50'], 'task_ids.*' => ['integer', 'distinct', 'exists:care_tasks,id'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ];
        $input = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $input);
        $data = Validator::make(['form' => $input], collect($rules)->mapWithKeys(fn ($rule, $key) => ['form.'.$key => $rule])->all())->validate()['form'];
        // Normalize field order and types so replay hashes cannot change with JSON key order.
        $data = array_replace(array_fill_keys(array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '*')), null), $data);
        foreach (['family_account_id', 'caregiver_user_id', 'duration_minutes', 'break_minutes'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        $data['source_request_id'] = $data['source_request_id'] ? (int) $data['source_request_id'] : null;
        $data['task_ids'] = array_map('intval', $data['task_ids']);
        sort($data['task_ids']);
        $data['state'] = strtoupper($data['state']);
        $data['address_line2'] = $data['address_line2'] ?: null;
        $data['scope_of_work'] = $data['scope_of_work'] ?: null;
        $local = $data['date'].' '.$data['start_time'];
        $naive = CarbonImmutable::createFromFormat('!Y-m-d H:i', $local, 'UTC');
        $zone = new DateTimeZone($data['timezone']);
        $offsets = collect($zone->getTransitions($naive->timestamp - 86400, $naive->timestamp + 86400))->pluck('offset')->unique();
        $candidates = $offsets->map(fn ($offset) => $naive->subSeconds($offset))->filter(fn ($time) => $time->setTimezone($zone)->format('Y-m-d H:i') === $local)->sortBy(fn ($time) => $time->timestamp)->values();
        $this->ensure($candidates->isNotEmpty(), 'This local start time does not exist because of daylight saving. Choose a valid time.', 'form.start_time');
        $start = $data['start_occurrence'] === 'second' ? $candidates->last() : $candidates->first();
        $end = $start->addMinutes($data['duration_minutes']);
        $this->ensure($end->isPast(), 'The entire visit must be in the past.', 'form.date');
        $data['started_at'] = $start->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $data['completed_at'] = $end->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        // Existing booking columns store local datetimes without an offset. Never silently
        // turn the second occurrence of a repeated hour into its first occurrence.
        $this->ensure(CarbonImmutable::parse($data['started_at'], config('app.timezone'))->equalTo($start)
            && CarbonImmutable::parse($data['completed_at'], config('app.timezone'))->equalTo($end),
            'The booking system cannot store this repeated daylight-saving time unambiguously. This visit needs a time-zone correction before it can be recorded.', 'form.start_time');
        $data['worked_minutes'] = $data['duration_minutes'] - $data['break_minutes'];

        return $data;
    }

    private function participants(array $data, bool $lock = false): array
    {
        $family = FamilyAccount::with('owner')->when($lock, fn ($q) => $q->lockForUpdate())->find($data['family_account_id']);
        $caregiver = User::with('caregiverProfile')->when($lock, fn ($q) => $q->lockForUpdate())->find($data['caregiver_user_id']);
        $this->ensure($family?->status === 'active' && $family->owner?->role === 'family', 'Select an active family account.', 'form.family_account_id');
        $this->ensure($caregiver?->role === 'caregiver' && $caregiver->caregiverProfile?->status === 'active', 'Select an active caregiver.', 'form.caregiver_user_id');
        $this->ensure(! $data['source_request_id'] || CareRequest::where('family_account_id', $family->id)->whereKey($data['source_request_id'])->exists(),
            'The source request must belong to the selected family.', 'form.source_request_id');

        return [$family, $caregiver];
    }

    private function assertNoOverlap(array $data, bool $lock = false): void
    {
        $start = $data['started_at'];
        $end = $data['completed_at'];
        $booking = CareBooking::where(fn ($q) => $q->where('family_account_id', $data['family_account_id'])->orWhere('caregiver_user_id', $data['caregiver_user_id']))
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start))
                ->orWhere(fn ($w) => $w->where('started_at', '<', $end)->where(fn ($t) => $t->whereNull('completed_at')->orWhere('completed_at', '>', $start))))
            ->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $this->ensure(! $booking, 'An overlapping booking already exists'.($booking ? ' on request #'.$booking->care_request_id : '').'. Open that request to correct it instead of creating another.');
        $request = CareRequest::where('family_account_id', $data['family_account_id'])->where('requested_start_at', '<', $end)->where('requested_end_at', '>', $start)
            ->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $this->ensure(! $request, 'An overlapping request already exists'.($request ? ' (#'.$request->id.')' : '').'. Review that request first.');
        $extra = CompletedExtraVisitRequest::where(fn ($q) => $q->where('family_account_id', $data['family_account_id'])->orWhere('caregiver_user_id', $data['caregiver_user_id']))
            ->whereNotIn('status', ['withdrawn', 'superseded'])->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)
            ->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $correction = CareBookingTimeCorrection::active()->whereHas('booking', fn ($q) => $q->where(fn ($w) => $w->where('family_account_id', $data['family_account_id'])->orWhere('caregiver_user_id', $data['caregiver_user_id'])))
            ->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)
            ->when($lock, fn ($q) => $q->lockForUpdate())->first();
        $this->ensure(! $extra && ! $correction, 'An extra-visit report or time correction already covers these hours. Review it first.');
    }

    public function paymentMode(): string
    {
        $bypass = (bool) config('services.stripe.bypass');
        $secret = (string) config('services.stripe.secret');
        $live = str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_');
        $this->ensure(! app()->environment('production') || (! $bypass && $live), 'Production payments require live Stripe configuration with bypass disabled.');
        $this->ensure(app()->environment('production') || ! $live, 'Live payments can only be processed in production.');
        $this->ensure(strtolower((string) config('services.stripe.currency')) === 'usd', 'This tool currently supports USD billing.');

        return $bypass ? 'simulated' : ($live ? 'live' : 'test');
    }

    private function fingerprint(array $value): string
    {
        // MySQL JSON can reorder object keys; JSON also normalizes 0.0 to 0.
        $canonicalize = function (array $items) use (&$canonicalize): array {
            if (! array_is_list($items)) {
                ksort($items);
            }

            return array_map(fn ($item) => is_array($item) ? $canonicalize($item) : $item, $items);
        };
        $value = $canonicalize($value);

        return hash_hmac('sha256', json_encode($value, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function authorize(User $admin): void
    {
        abort_unless($admin->fresh()?->isAdministrator(), 403);
    }

    private function ensure(bool $condition, string $message, string $field = 'visit'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
