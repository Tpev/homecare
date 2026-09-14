<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\CompletedExtraVisitRequest;
use App\Models\FamilyAccount;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Booking\CareBookingTimeCorrectionService;
use App\Support\MarketplacePricing;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecoverCarolineSundayVisit extends Command
{
    public const CLIENT_REQUEST_ID = '8c205d3a-6b6c-4231-a591-937a16f9bd08';

    protected $signature = 'homecare:recover-caroline-sunday-visit
        {--apply : Restore the booking and create a pending family time review}
        {--admin= : Administrator user ID to record in the recovery audit}';

    protected $description = 'Preview or recover Caroline’s September 13 visit for John Grady Eberdt, without charging or notifying anyone';

    public function handle(CareBookingTimeCorrectionService $corrections, BookingTrustService $trust, MarketplacePricing $pricing): int
    {
        try {
            $admin = null;
            if ($this->option('apply')) {
                $admin = User::query()->find((int) $this->option('admin'));
                $this->ensure($admin?->isAdministrator() === true, '--apply requires --admin with a valid administrator user ID.');
            }

            $result = $this->option('apply')
                ? DB::transaction(fn () => $this->recover($corrections, $trust, $pricing, $admin), 3)
                : $this->recover($corrections, $trust, $pricing);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->info($result['result'] === 'already_recovered'
                ? 'Recovery already exists. No further changes were made.'
                : ($this->option('apply')
                ? 'Recovery recorded. John must review the reported hours and total in his account. No charge or notification was sent by this command.'
                : 'Preview only. No records changed. Use --apply --admin=YOUR_ADMIN_ID to save this recovery.'));

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
    }

    private function recover(CareBookingTimeCorrectionService $corrections, BookingTrustService $trust, MarketplacePricing $pricing, ?User $admin = null): array
    {
        $lock = $admin !== null;
        $ticket = SupportTicket::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail(59);
        $request = CareRequest::query()->with(['family', 'tasks', 'recipient'])
            ->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail(173);
        $application = CareRequestApplication::query()->with('caregiver.caregiverProfile')
            ->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail(196);
        $invitation = CareRequestInvitation::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail(81);
        $account = FamilyAccount::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail(37);
        $family = $request->family;
        $caregiver = $application->caregiver;
        $this->ensure((int) $family?->id === 431 && $family->role === 'family'
            && strcasecmp((string) $family->email, 'jeberdt@gmail.com') === 0
            && (int) $request->family_account_id === 37, 'Request #173 must belong to John Grady Eberdt (jeberdt@gmail.com), family account #37.');
        $this->ensure((int) $account->owner_user_id === 431 && $account->status === FamilyAccount::STATUS_ACTIVE,
            'Family account #37 must be active and owned by John Grady Eberdt #431.');
        $this->ensure((int) $caregiver?->id === 256 && $caregiver->role === 'caregiver'
            && strcasecmp(trim($caregiver->name), 'Caroline Petrini-Poli') === 0
            && (int) $application->care_request_id === 173, 'Application #196 must link Caroline #256 to request #173.');
        $this->ensure((int) $ticket->opener_user_id === 256, 'Ticket #59 must belong to Caroline #256.');
        $this->ensure((int) $invitation->care_request_id === 173 && (int) $invitation->caregiver_user_id === 256
            && (int) $invitation->care_request_application_id === 196 && (int) $invitation->family_user_id === 431
            && $invitation->status === CareRequestInvitation::STATUS_ACCEPTED, 'Invitation #81 must be Caroline’s accepted invitation from John for request #173.');

        $existing = CareBookingTimeCorrection::query()->with('booking')
            ->where('client_request_id', self::CLIENT_REQUEST_ID)->first();
        if ($existing) {
            $this->ensure((int) $existing->booking?->care_request_id === 173
                && (int) $existing->booking?->caregiver_user_id === 256
                && (int) $existing->booking?->family_user_id === 431
                && (int) $existing->requester_user_id === 256
                && (int) $existing->support_ticket_id === 59
                && (int) $ticket->care_request_id === 173
                && (int) $ticket->care_booking_id === (int) $existing->care_booking_id,
                'The previous recovery no longer matches these records. Inspect it before making another change.');

            return ['result' => 'already_recovered', ...$this->result($existing)];
        }

        $this->ensure($ticket->status === SupportTicket::STATUS_OPEN && (int) $ticket->care_request_id === 102
            && ! $ticket->care_booking_id && ! $ticket->counterparty_user_id
            && in_array((int) $ticket->family_account_id, [0, 32], true), 'Ticket #59 changed since the audit. Refresh the audit before recovering it.');
        $oldRequest = CareRequest::query()->findOrFail(102);
        $this->ensure((int) $oldRequest->family_user_id === 406 && (int) $oldRequest->family_account_id === 32,
            'The original ticket link is no longer John Murray’s request #102. Review the link before changing it.');
        $this->ensure($request->status === CareRequest::STATUS_CANCELLED && $request->request_type === CareRequest::TYPE_ONE_TIME
            && ! $request->care_plan_id && ! $request->first_hire_at
            && $application->status === CareRequestApplication::STATUS_NOT_SELECTED
            && (float) $application->proposed_rate === 30.0, 'Request #173 or application #196 changed since the audit.');
        $this->ensure($request->requested_start_at?->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s') === '2026-09-13 10:00:00'
            && $request->requested_end_at?->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s') === '2026-09-13 12:00:00', 'Request #173 no longer has the audited Sunday schedule.');
        $this->ensure(! CareBooking::query()->where('care_request_id', 173)->exists(), 'Request #173 already has a booking. Use its normal time-correction flow.');
        $this->ensure(! $request->applications()->where('status', CareRequestApplication::STATUS_HIRED)->exists(), 'Request #173 already has a hired caregiver.');
        $this->ensure(! $ticket->timeCorrection()->exists(), 'Ticket #59 already has a time correction.');

        $input = [
            'started_at' => '2026-09-13T11:00', 'completed_at' => '2026-09-13T12:30', 'break_minutes' => 0,
            'reason_code' => CareBookingTimeCorrection::REASON_APP_OR_GPS,
            'explanation' => 'Caroline reported that she provided care for John Grady Eberdt on Sunday, September 13, from 11 AM to 12:30 PM but could not start the visit. LoLo Care entered this report from support ticket #59 after restoring the missing booking. John’s approval is still required.',
            'confirmed' => true,
        ];
        $start = Carbon::parse('2026-09-13 11:00:00', 'America/New_York')->setTimezone(config('app.timezone'));
        $end = $start->copy()->addMinutes(90);
        $this->ensure(! CompletedExtraVisitRequest::query()->where('caregiver_user_id', 256)
            ->whereNotIn('status', [CompletedExtraVisitRequest::STATUS_WITHDRAWN, CompletedExtraVisitRequest::STATUS_SUPERSEDED])
            ->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)->exists(), 'Caroline already has a report covering these hours.');
        $this->ensure(! CareBookingTimeCorrection::query()->active()
            ->whereHas('booking', fn ($q) => $q->where('caregiver_user_id', 256))
            ->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)->exists(), 'Caroline already has a time correction covering these hours.');

        $before = [
            'ticket' => $ticket->only(['id', 'care_request_id', 'care_booking_id', 'family_account_id', 'family_visibility', 'counterparty_user_id', 'status']),
            'request' => $request->only(['id', 'status', 'first_hire_at']),
            'application' => $application->only(['id', 'status', 'proposed_rate']),
        ];
        $booking = new CareBooking([
            'care_request_id' => 173, 'care_request_application_id' => 196,
            'family_account_id' => 37, 'family_user_id' => 431, 'caregiver_user_id' => 256,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 120,
            'agreement_snapshot' => $trust->buildAgreementSnapshot($request, $application),
        ]);
        $booking->forceFill($pricing->currentSnapshotAttributes($booking));
        $booking->setRelation('application', $application)->setRelation('family', $family)->setRelation('caregiver', $caregiver);
        $this->ensure($corrections->timezoneFor($booking) === 'America/New_York', 'The visit time zone must match the audited America/New_York time zone.');
        $preview = $corrections->preview($booking, $input);

        if (! $admin) {
            return [
                'result' => 'preview', 'before' => $before,
                'correct_family' => $family->only(['id', 'name', 'email']),
                'caregiver' => $caregiver->only(['id', 'name']),
                'changes' => ['Restore request #173 to filled and application #196 to hired.', 'Create the missing booking with its original 10 AM–noon schedule.', 'Record 11 AM–12:30 PM as a pending family time correction.', 'Relink ticket #59 from John Murray’s request #102 to this booking under John Grady Eberdt’s account.'],
                'time_and_price_for_family_review' => $preview,
                'administrators' => User::query()->where('role', 'admin')->get(['id', 'name'])->toArray(),
            ];
        }

        // Preserve the historical recovery in the audit instead of emitting fresh hire notifications.
        $request->forceFill(['status' => CareRequest::STATUS_FILLED, 'first_hire_at' => now()])->saveQuietly();
        $application->forceFill(['status' => CareRequestApplication::STATUS_HIRED])->saveQuietly();
        $booking->save();
        // Re-evaluate any pricing agreement whose applicability depends on the newly allocated booking ID.
        $booking->forceFill($pricing->currentSnapshotAttributes($booking))->saveQuietly();
        $trust->seedTaskChecks($booking, $request);
        $ticket->forceFill([
            'care_request_id' => 173, 'care_booking_id' => $booking->id,
            'family_account_id' => 37, 'counterparty_user_id' => 431,
        ])->save();
        $correction = $corrections->submit($booking, $caregiver, $input, self::CLIENT_REQUEST_ID, supportAdmin: $admin);
        $correction->forceFill(['support_ticket_id' => 59])->save();
        $audit = ['before' => $before, 'care_booking_id' => $booking->id, 'time_correction_id' => $correction->id, 'reported_started_at' => $input['started_at'], 'reported_completed_at' => $input['completed_at'], 'family_approval_pending' => true];
        $trust->recordEvent($booking, $admin->id, 'admin', 'missing_visit_recovered_from_support', $audit);
        SupportTicketActivity::query()->create([
            'support_ticket_id' => 59, 'actor_user_id' => $admin->id,
            'action' => 'missing_visit_recovered', 'metadata' => $audit, 'created_at' => now(),
        ]);

        return ['result' => 'recovered', ...$this->result($correction)];
    }

    private function result(CareBookingTimeCorrection $correction): array
    {
        return [
            'care_booking_id' => $correction->care_booking_id,
            'time_correction_id' => $correction->id, 'status' => $correction->status,
            'worked_minutes_for_review' => $correction->proposed_worked_minutes,
            'family_review_url' => route('family.requests.show', ['careRequest' => 173, 'tab' => 'shift']).'#time-correction-review-'.$correction->id,
            'time_and_price_for_family_review' => $correction->financial_preview,
        ];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['recovery' => $message]);
        }
    }
}
