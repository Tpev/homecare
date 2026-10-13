<?php
/** Correct only the two authorized synthetic after-shift submission timestamps. */
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! $app->environment('playwright') || config('database.default') !== 'sqlite'
    || ! str_ends_with(strtolower($database), '/find-care-audit.sqlite')) {
    throw new RuntimeException('Repair requires the isolated audit database.');
}
$manifest = json_decode(file_get_contents(dirname(__DIR__).'/fixture-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$result = Illuminate\Support\Facades\DB::transaction(function () use ($manifest): array {
    $updated = [];
    foreach (['one_time', 'recurring'] as $variation) {
        $booking = App\Models\CareBooking::findOrFail($manifest['bookings'][$variation]['after_shift']);
        if ((int) $booking->care_request_id !== (int) $manifest['requests'][$variation]['after_shift']
            || (int) $booking->family_user_id !== (int) $manifest['familyId']
            || $booking->status !== 'completed' || ! $booking->completed_at || $booking->family_confirmed_at) {
            throw new RuntimeException('Unexpected after-shift fixture; refusing mutation.');
        }
        $booking->update(['timesheet_submitted_at' => $booking->completed_at]);
        $updated[$variation] = ['booking_id' => $booking->id, 'request_id' => $booking->care_request_id,
            'timesheet_submitted_at' => $booking->timesheet_submitted_at->toIso8601String()];
    }
    return $updated;
});
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
