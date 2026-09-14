<?php

// Read-only. From the application root, run:
// php artisan tinker --execute="require 'tools/audit-tammy-visit-overlap.php';"
// Or, without Tinker: php tools/audit-tammy-visit-overlap.php

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
    require __DIR__.'/../vendor/autoload.php';
    $application = require __DIR__.'/../bootstrap/app.php';
    $application->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

(function (): void {
    $ticket = \App\Models\SupportTicket::with('opener:id,name,role')->findOrFail(58);
    $caregiver = $ticket->opener;
    if (! $caregiver || $caregiver->role !== 'caregiver'
        || strcasecmp(trim($caregiver->name), 'Tammy Antonelli') !== 0) {
        throw new \RuntimeException('Ticket #58 does not belong to caregiver Tammy Antonelli in this database.');
    }

    $timezone = 'America/New_York';
    $appTimezone = (string) config('app.timezone', 'America/New_York');
    // Match CareBookingTimeCorrectionService::parseLocalDate, including its seconds handling.
    $parse = fn (string $value) => \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $value, $timezone)->setTimezone($appTimezone);
    $start = $parse('2026-09-14T11:46');
    $end = $parse('2026-09-14T17:18');
    $dayStart = $start->copy()->setTimezone($timezone)->startOfDay()->setTimezone($appTimezone);
    $dayEnd = $dayStart->copy()->setTimezone($timezone)->addDay()->setTimezone($appTimezone);

    $base = fn () => \App\Models\CareBooking::query()->where('caregiver_user_id', $caregiver->id);
    $format = fn ($date) => $date?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s P');
    $describe = function ($booking) use ($format): array {
        $recorded = $booking->started_at !== null && $booking->completed_at !== null;

        return [
            'booking_id' => $booking->id,
            'care_request_id' => $booking->care_request_id,
            'care_plan_id' => $booking->care_plan_id,
            'family_user_id' => $booking->family_user_id,
            'family_name' => $booking->family?->name,
            'status' => $booking->status,
            'scheduled_local' => [$format($booking->scheduled_start_at), $format($booking->scheduled_end_at)],
            'recorded_local' => [$format($booking->started_at), $format($booking->completed_at)],
            'raw_database_times' => collect(['scheduled_start_at', 'scheduled_end_at', 'started_at', 'completed_at'])
                ->mapWithKeys(fn ($field) => [$field => $booking->getRawOriginal($field)])->all(),
            'overlap_check_uses' => $recorded ? 'recorded times' : 'scheduled times (at least one recorded time is missing)',
            'worked_minutes' => $booking->worked_minutes,
            'total_paused_seconds' => $booking->total_paused_seconds,
        ];
    };

    // List all her visits scheduled or recorded on the screenshot date, plus the ticket-linked visit.
    // Do not guess which booking is being edited if the ticket has no booking link.
    $visits = $base()->with('family:id,name')->where(function ($query) use ($dayStart, $dayEnd, $ticket): void {
        $query->where(fn ($q) => $q->where('scheduled_start_at', '<', $dayEnd)->where('scheduled_end_at', '>', $dayStart))
            ->orWhere(fn ($q) => $q->where('started_at', '<', $dayEnd)->where('completed_at', '>', $dayStart));
        if ($ticket->care_booking_id) {
            $query->orWhere('id', $ticket->care_booking_id);
        }
    })->orderBy('scheduled_start_at')->get();

    $results = $visits->map(function ($visit) use ($base, $describe, $start, $end): array {
        // Same predicate as validateTimeRange; no date/family restriction on conflicting visits.
        $conflicts = $base()->with('family:id,name')
            ->whereKeyNot($visit->id)
            ->where('status', '!=', \App\Models\CareBooking::STATUS_CANCELLED)
            ->where(function ($query) use ($start, $end): void {
                $query->where(function ($recorded) use ($start, $end): void {
                    $recorded->whereNotNull('started_at')->whereNotNull('completed_at')
                        ->where('started_at', '<', $end)->where('completed_at', '>', $start);
                })->orWhere(function ($scheduled) use ($start, $end): void {
                    $scheduled->where(fn ($q) => $q->whereNull('started_at')->orWhereNull('completed_at'))
                        ->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start);
                });
            })->orderBy('scheduled_start_at')->get();

        return [
            'if_editing_this_visit' => $describe($visit),
            'overlap_error_would_trigger' => $conflicts->isNotEmpty(),
            'blocking_visits' => $conflicts->map($describe)->all(),
        ];
    });

    echo json_encode([
        'environment' => app()->environment(),
        'app_timezone' => $appTimezone,
        'display_timezone' => $timezone,
        'ticket_id' => $ticket->id,
        'ticket_booking_id' => $ticket->care_booking_id,
        'ticket_care_request_id' => $ticket->care_request_id,
        'caregiver' => ['id' => $caregiver->id, 'name' => $caregiver->name],
        'requested_local' => [$format($start), $format($end)],
        'query_bounds_in_app_timezone' => [$start->toDateTimeString(), $end->toDateTimeString()],
        'screenshot_scheduled_local' => ['2026-09-14 11:45', '2026-09-14 15:45'],
        'note' => 'Each result tests the requested hours as if editing that visit. Match the ticket booking ID or the screenshot schedule. This audits only the overlap rule.',
        'visits' => $results->all(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
})();
