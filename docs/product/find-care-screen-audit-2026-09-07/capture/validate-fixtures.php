<?php
/** Read-only fixture semantics check; performs no mutations. */
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! $app->environment('playwright') || config('database.default') !== 'sqlite'
    || ! str_ends_with(strtolower($database), '/find-care-audit.sqlite')) {
    throw new RuntimeException('Validation requires the isolated audit database.');
}
$manifest = json_decode(file_get_contents(dirname(__DIR__).'/fixture-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$result = ['applicantCounts' => [], 'lifecycle' => [], 'plans' => []];
foreach (['one_time', 'recurring'] as $variation) {
    foreach (['waiting', 'invitations', 'applicants', 'single_applicant', 'many_applicants', 'private_waiting'] as $stage) {
        $request = App\Models\CareRequest::findOrFail($manifest['requests'][$variation][$stage]);
        $result['applicantCounts'][$variation][$stage] = ['applications' => $request->applications()->count(),
            'invitations' => $request->invitations()->count(), 'is_private' => $request->is_private];
    }
    foreach (['scheduled', 'in_progress', 'after_shift', 'timesheet_review', 'leave_review', 'closed', 'cancelled', 'cancelled_booking', 'paused', 'payment_attention', 'time_correction'] as $stage) {
        $request = App\Models\CareRequest::with(['booking.payment', 'reviews'])->findOrFail($manifest['requests'][$variation][$stage]);
        $booking = $request->booking;
        $result['lifecycle'][$variation][$stage] = ['request_id' => $request->id, 'request_status' => $request->status,
            'request_type' => $request->request_type, 'care_plan_id' => $request->care_plan_id,
            'system_generated' => $request->is_system_generated,
            'booking_status' => $booking?->status, 'submitted' => (bool) $booking?->timesheet_submitted_at,
            'family_confirmed' => (bool) $booking?->family_confirmed_at, 'payment_status' => $booking?->payment?->status,
            'family_review_count' => $request->reviews->where('reviewer_user_id', $manifest['familyId'])->count(),
            'total_reviews' => $request->reviews->count()];
    }
}
foreach ($manifest['plans'] as $stage => $id) {
    $plan = App\Models\CarePlan::findOrFail($id);
    $result['plans'][$stage] = ['id' => $id, 'status' => $plan->status, 'payment_status' => $plan->payment_status,
        'source_request_id' => $plan->source_care_request_id, 'source_booking_id' => $plan->source_care_booking_id,
        'next_booking_id' => $plan->next_booking_id, 'generated_booking_count' => $plan->generatedBookings()->count()];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
