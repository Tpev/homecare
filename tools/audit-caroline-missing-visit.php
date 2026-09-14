<?php

// Read-only. Run from the deployed application root:
// php tools/audit-caroline-missing-visit.php
// Or: php artisan tinker --execute="require 'tools/audit-caroline-missing-visit.php';"

use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Models\CarePlan;
use App\Models\CareRelationship;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CompletedExtraVisitRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\RegularCare\CompletedExtraVisitService;
use Carbon\Carbon;

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
    require __DIR__.'/../vendor/autoload.php';
    $application = require __DIR__.'/../bootstrap/app.php';
    $application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

(function (): void {
    $ticket = SupportTicket::with('opener:id,name,role')->findOrFail(59);
    $caregiver = $ticket->opener;
    if (! $caregiver || $caregiver->role !== 'caregiver'
        || strcasecmp(trim($caregiver->name), 'Caroline Petrini-Poli') !== 0) {
        throw new RuntimeException('Ticket #59 does not belong to caregiver Caroline Petrini-Poli in this database.');
    }
    $family = User::query()->findOrFail(431);
    if ($family->role !== 'family' || strcasecmp(trim($family->name), 'John Grady Eberdt') !== 0) {
        throw new RuntimeException('User #431 is not John Grady Eberdt in this database.');
    }

    $timezone = 'America/New_York';
    $appTimezone = (string) config('app.timezone', 'America/New_York');
    $start = Carbon::create(2026, 9, 13, 11, 0, 0, $timezone)->setTimezone($appTimezone);
    $end = Carbon::create(2026, 9, 13, 12, 30, 0, $timezone)->setTimezone($appTimezone);
    $dayStart = $start->copy()->setTimezone($timezone)->startOfDay()->setTimezone($appTimezone);
    $dayEnd = $dayStart->copy()->setTimezone($timezone)->addDay()->setTimezone($appTimezone);
    $format = fn ($date) => $date?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s P');
    $describeBooking = function (?CareBooking $booking) use ($format): ?array {
        if (! $booking) {
            return null;
        }

        return [
            ...$booking->only(['id', 'care_request_id', 'care_request_application_id', 'care_plan_id', 'family_user_id', 'caregiver_user_id', 'status']),
            'family_name' => $booking->family?->name,
            'caregiver_name' => $booking->caregiver?->name,
            'scheduled_local' => [$format($booking->scheduled_start_at), $format($booking->scheduled_end_at)],
            'recorded_local' => [$format($booking->started_at), $format($booking->completed_at)],
            'worked_minutes' => $booking->worked_minutes,
            'timesheet_submitted_local' => $format($booking->timesheet_submitted_at),
            'family_confirmed_local' => $format($booking->family_confirmed_at),
            'pricing' => $booking->only(['pricing_version', 'pricing_agreement_id', 'family_care_rate_cents', 'family_processing_fee_rate_cents', 'caregiver_gross_rate_cents']),
            'payment' => $booking->payment?->only(['id', 'status', 'currency', 'amount_authorized_cents', 'amount_captured_cents', 'amount_refunded_cents']),
        ];
    };

    // Include nearby requests, recurring requests, and any request with Caroline's application/invitation.
    $requestQuery = CareRequest::query()->where('family_user_id', $family->id)
        ->where(function ($query) use ($dayStart, $dayEnd, $caregiver, $ticket): void {
            $query->whereBetween('requested_start_at', [$dayStart->copy()->subDays(7), $dayEnd->copy()->addDays(7)])
                ->orWhereBetween('created_at', [$dayStart->copy()->subDays(7), $dayEnd->copy()->addDays(7)])
                ->orWhere('request_type', CareRequest::TYPE_RECURRING)
                ->orWhereHas('applications', fn ($q) => $q->where('caregiver_user_id', $caregiver->id))
                ->orWhereHas('invitations', fn ($q) => $q->where('caregiver_user_id', $caregiver->id));
            if ($ticket->care_request_id) {
                $query->orWhere('id', $ticket->care_request_id);
            }
        });
    $requestCount = (clone $requestQuery)->count();
    $requests = $requestQuery->with([
        'applications' => fn ($q) => $q->where(fn ($a) => $a->where('caregiver_user_id', $caregiver->id)
            ->orWhere('status', CareRequestApplication::STATUS_HIRED))->with('caregiver:id,name'),
        'invitations' => fn ($q) => $q->where('caregiver_user_id', $caregiver->id),
        'booking.caregiver:id,name', 'booking.family:id,name', 'booking.payment',
    ])->orderByDesc('id')->limit(50)->get();

    $bookings = CareBooking::query()->with(['family:id,name', 'caregiver:id,name', 'payment'])
        ->where('caregiver_user_id', $caregiver->id)
        ->where(function ($query) use ($dayStart, $dayEnd, $ticket): void {
            $query->where(fn ($q) => $q->where('scheduled_start_at', '<', $dayEnd)->where('scheduled_end_at', '>', $dayStart))
                ->orWhere(fn ($q) => $q->where('started_at', '<', $dayEnd)->where('completed_at', '>', $dayStart))
                ->orWhere(fn ($q) => $q->where('started_at', '>=', $dayStart)->where('started_at', '<', $dayEnd))
                ->orWhere(fn ($q) => $q->where('completed_at', '>=', $dayStart)->where('completed_at', '<', $dayEnd));
            if ($ticket->care_booking_id) {
                $query->orWhere('id', $ticket->care_booking_id);
            }
        })->orderBy('scheduled_start_at')->get();
    $plans = CarePlan::query()->where('family_user_id', $family->id)
        ->where('caregiver_user_id', $caregiver->id)->orderBy('id')->get();
    $reports = CompletedExtraVisitRequest::query()->where('caregiver_user_id', $caregiver->id)
        ->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)->get();
    $corrections = CareBookingTimeCorrection::query()
        ->whereHas('booking', fn ($q) => $q->where('caregiver_user_id', $caregiver->id))
        ->where('proposed_started_at', '<', $end)->where('proposed_completed_at', '>', $start)->get();

    echo json_encode([
        'environment' => app()->environment(),
        'app_timezone' => $appTimezone,
        'display_timezone' => $timezone,
        'ticket' => [
            ...$ticket->only(['id', 'subject', 'status', 'care_request_id', 'care_booking_id', 'counterparty_user_id']),
            'opened_local' => $format($ticket->created_at),
        ],
        'caregiver' => $caregiver->only(['id', 'name']),
        'family' => $family->only(['id', 'name']),
        'reported_local' => [$format($start), $format($end)],
        'elapsed_minutes_before_any_break' => 90,
        'date_assumption' => 'Sunday means September 13, 2026, based on the September 14 support ticket. The request/application match still needs review.',
        'matching_request_count' => $requestCount,
        'requests_truncated' => $requestCount > $requests->count(),
        'john_requests' => $requests->map(fn ($request) => [
            ...$request->only(['id', 'title', 'status', 'request_type', 'care_plan_id', 'family_account_id']),
            'created_local' => $format($request->created_at),
            'requested_local' => [$format($request->requested_start_at), $format($request->requested_end_at)],
            'first_hire_local' => $format($request->first_hire_at),
            'applications_caroline_or_hired' => $request->applications->map(fn ($a) => [
                ...$a->only(['id', 'caregiver_user_id', 'status', 'proposed_rate']),
                'caregiver_name' => $a->caregiver?->name,
            ])->all(),
            'caroline_invitations' => $request->invitations->map(fn ($i) => $i->only(['id', 'status', 'care_request_application_id']))->all(),
            'existing_booking' => $describeBooking($request->booking),
        ])->all(),
        'caroline_bookings_on_sunday' => $bookings->map($describeBooking)->all(),
        'john_caroline_relationships' => CareRelationship::query()->where('family_user_id', $family->id)
            ->where('caregiver_user_id', $caregiver->id)->get()
            ->map(fn ($r) => $r->only(['id', 'status', 'source_care_request_id', 'last_care_booking_id']))->all(),
        'john_caroline_plans' => $plans->map(fn ($plan) => [
            ...$plan->only(['id', 'status', 'care_relationship_id', 'source_care_request_id', 'source_care_booking_id', 'timezone', 'hourly_rate']),
            'completed_extra_visit_reporting_allowed' => app(CompletedExtraVisitService::class)->canReport($plan, $caregiver),
        ])->all(),
        'overlapping_extra_visit_reports' => $reports->map(fn ($r) => [
            ...$r->only(['id', 'status', 'family_user_id', 'care_plan_id', 'care_booking_id', 'proposed_worked_minutes']),
            'reported_local' => [$format($r->proposed_started_at), $format($r->proposed_completed_at)],
        ])->all(),
        'overlapping_time_corrections' => $corrections->map(fn ($c) => [
            ...$c->only(['id', 'status', 'care_booking_id', 'family_user_id', 'proposed_worked_minutes']),
            'reported_local' => [$format($c->proposed_started_at), $format($c->proposed_completed_at)],
        ])->all(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
})();
