<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Support\MarketplacePricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HireCharlesForRequest241 extends Command
{
    protected $signature = 'homecare:hire-charles-request-241
        {--apply : Save this specific admin hire}
        {--admin= : Administrator user ID for the audit}';

    protected $description = 'Preview or hire Charles #17 for John’s transportation request #241, preserving its existing timing';

    public function handle(BookingTrustService $trust, MarketplacePricing $pricing): int
    {
        try {
            $apply = (bool) $this->option('apply');
            $admin = $apply ? User::query()->find((int) $this->option('admin')) : null;
            $this->ensure(! $apply || $admin?->isAdministrator(), '--apply requires a valid --admin user ID.');
            $result = DB::transaction(fn () => $this->hire($trust, $pricing, $admin), 3);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
    }

    private function hire(BookingTrustService $trust, MarketplacePricing $pricing, ?User $admin): array
    {
        $request = CareRequest::query()->lockForUpdate()->with(['family', 'recipient', 'tasks'])->findOrFail(241);
        $caregiver = User::query()->lockForUpdate()->with('caregiverProfile')->findOrFail(17);
        $account = FamilyAccount::query()->lockForUpdate()->findOrFail(37);
        $this->ensure((int) $request->family_user_id === 431 && (int) $request->family_account_id === 37
            && $request->family?->role === 'family' && $request->family->email === 'jeberdt@gmail.com'
            && (int) $account->owner_user_id === 431 && $account->status === 'active',
            'Request #241 must belong to John Grady Eberdt #431 and his active family account #37.');
        $this->ensure($caregiver->role === 'caregiver' && $caregiver->email === 'charlespetrinipoli@gmail.com'
            && $caregiver->name === 'Charles Petrini-Poli' && $caregiver->caregiverProfile?->status === 'active',
            'User #17 must be Charles Petrini-Poli with an active caregiver profile.');
        $this->ensure($request->title === 'One-time care support for Transportation'
            && $request->request_type === CareRequest::TYPE_ONE_TIME && ! $request->care_plan_id
            && $request->city === 'Raleigh' && $request->state === 'NC' && $request->zip === '27609'
            && $request->created_at->copy()->setTimezone('America/New_York')->toDateString() === '2026-09-28',
            'Request #241 no longer matches the specified September 28 transportation request.');

        $bookings = CareBooking::query()->where('care_request_id', 241)->lockForUpdate()->get();
        if ($bookings->isNotEmpty()) {
            $booking = $bookings->first();
            $this->ensure($bookings->count() === 1 && (int) $booking->caregiver_user_id === 17
                && (int) $booking->family_user_id === 431 && (int) $booking->family_account_id === 37
                && $booking->status !== CareBooking::STATUS_CANCELLED && $request->status === CareRequest::STATUS_FILLED
                && (int) $booking->application?->care_request_id === 241
                && (int) $booking->application?->caregiver_user_id === 17
                && $booking->application?->status === CareRequestApplication::STATUS_HIRED,
                'An existing booking does not match this hire. No records changed.');

            return ['result' => 'already_hired', 'booking_id' => $booking->id, 'application_id' => $booking->care_request_application_id];
        }

        $this->ensure($request->status === CareRequest::STATUS_OPEN, 'Request #241 must still be open.');
        $applications = $request->applications()->lockForUpdate()->get();
        $this->ensure(! $applications->contains('status', CareRequestApplication::STATUS_HIRED),
            'This request already has a hired application. Inspect it before creating a booking.');
        $charlesApplications = $applications->where('caregiver_user_id', 17);
        $this->ensure($charlesApplications->count() <= 1, 'Charles has duplicate applications. Inspect them before hiring.');
        $application = $charlesApplications->first();
        $this->ensure(! $application || in_array($application->status, [
            CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED,
        ], true), 'Charles’s existing application is not available to hire. No records changed.');
        $conversation = $request->conversations()->where('caregiver_user_id', 17)->lockForUpdate()->first();
        $this->ensure(! $conversation || ((int) $conversation->family_user_id === 431
            && (int) $conversation->family_account_id === 37
            && (! $conversation->care_request_application_id
                || ($application && (int) $conversation->care_request_application_id === (int) $application->id))),
            'Charles’s existing conversation has conflicting ownership or application links. No records changed.');
        $rate = $application ? (float) $application->proposed_rate
            : $pricing->hourlyRateForRequest($request, (float) $caregiver->caregiverProfile->platform_hourly_rate);
        $this->ensure($rate > 0, 'Charles must have a valid existing platform rate.');
        $otherApplications = $applications->filter(fn ($item) => (int) $item->caregiver_user_id !== 17
            && in_array($item->status, [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED], true));
        $beforeApplications = $applications->map(fn ($item) => $item->only(['id', 'caregiver_user_id', 'status']))->values()->all();
        $summary = [
            'request_id' => 241, 'family' => $request->family->only(['id', 'name']),
            'caregiver' => $caregiver->only(['id', 'name']), 'proposed_rate' => $rate,
            'other_applications_to_mark_not_selected' => $otherApplications->pluck('id')->values()->all(),
            'scheduled_start_at' => $request->requested_start_at?->toIso8601String(),
            'scheduled_end_at' => $request->requested_end_at?->toIso8601String(),
            'timing_preserved' => true, 'payment_initiated' => false, 'notifications_sent' => false,
        ];
        if (! $admin) {
            return ['result' => 'preview', ...$summary,
                'administrators' => User::query()->where('role', 'admin')->get(['id', 'name'])->toArray(),
                'changes' => ['Create or reuse Charles’s application, mark it hired, and create the scheduled booking.',
                    'Mark other applied or shortlisted applications not selected; preserve their records and messages.',
                    'Mark request #241 filled and create its conversation and task checklist.',
                    'Record the administrator; retain existing timing and leave payment and personal terms acceptance untouched.'],
            ];
        }

        if (! $request->requested_end_at || $request->requested_end_at->isFuture()) {
            app(\App\Services\Screening\CaregiverWorkEligibility::class)->assertCanStartNewWork($caregiver->caregiverProfile);
        }

        // This is an admin assignment, not an application or acceptance authored by Charles or John.
        $application ??= new CareRequestApplication([
            'care_request_id' => 241, 'caregiver_user_id' => 17,
            'status' => CareRequestApplication::STATUS_HIRED, 'proposed_rate' => $rate,
            'cover_note' => 'Assigned by an administrator for request #241 on September 28, 2026.',
        ]);
        $application->status = CareRequestApplication::STATUS_HIRED;
        $application->saveQuietly();
        if ($otherApplications->isNotEmpty()) {
            $request->applications()->whereIn('id', $otherApplications->pluck('id'))
                ->whereIn('status', [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED])
                ->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);
        }
        $application->setRelation('careRequest', $request);
        CareRequestConversation::findOrCreateForApplication($application, $admin->id);
        $start = $request->requested_start_at;
        $end = $request->requested_end_at;
        $booking = CareBooking::query()->create([
            'care_request_id' => 241, 'care_request_application_id' => $application->id,
            'family_account_id' => 37, 'family_user_id' => 431, 'caregiver_user_id' => 17,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $start, 'scheduled_end_at' => $end,
            'expected_minutes' => $start && $end ? max(0, (int) $start->diffInMinutes($end, false)) : null,
            'agreement_snapshot' => $trust->buildAgreementSnapshot($request, $application),
        ]);
        // Re-evaluate pricing agreements that depend on the assigned booking ID.
        $booking->forceFill($pricing->currentSnapshotAttributes($booking))->saveQuietly();
        $trust->seedTaskChecks($booking, $request);
        $request->forceFill(['status' => CareRequest::STATUS_FILLED, 'first_hire_at' => $request->first_hire_at ?: now()])->saveQuietly();
        $trust->recordEvent($booking, $admin->id, 'admin', 'booking_hired_by_admin', [
            'application_id' => $application->id, 'source' => 'homecare:hire-charles-request-241',
            'reason' => 'Administrator requested Charles be hired for John on request #241; preserve existing timing.',
            'previous_request_status' => CareRequest::STATUS_OPEN, 'payment_initiated' => false,
            'previous_applications' => $beforeApplications,
        ]);

        return ['result' => 'hired', 'booking_id' => $booking->id, 'application_id' => $application->id, ...$summary];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['hire' => $message]);
        }
    }
}
