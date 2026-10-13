<?php

/** Add independent synthetic edge-state fixtures without modifying core captures. */
use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! $app->environment('playwright') || config('database.default') !== 'sqlite'
    || ! str_ends_with(strtolower($database), '/find-care-audit.sqlite')) {
    throw new RuntimeException('Extra audit fixtures require the isolated playwright audit database.');
}
$manifestPath = dirname(__DIR__).'/fixture-manifest.json';
$core = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
if (isset($core['requests']['one_time']['single_applicant'])) {
    throw new RuntimeException('Extra fixture keys already exist; refusing to create duplicate fixtures.');
}

$extras = DB::transaction(function () use ($core): array {
    $family = User::findOrFail($core['familyId']);
    $sofia = User::findOrFail($core['caregivers']['sofia']['id']);
    $fourth = $sofia->replicate(['email', 'remember_token']);
    $fourth->setRelations([]);
    $fourth->fill(['name' => 'Oliver Chen', 'email' => 'oliver.find-care-audit@example.com', 'phone' => '+1 919 555 0164']);
    $fourth->save();
    $profile = $sofia->caregiverProfile->replicate(['user_id', 'slug']);
    $profile->setRelations([]);
    $profile->fill(['user_id' => $fourth->id, 'slug' => 'oliver-find-care-audit', 'years_experience' => 4,
        'bio' => 'Friendly and dependable companionship, everyday meal preparation, and help around the home.',
        'average_rating' => 4.8, 'reviews_count' => 12]);
    $profile->save();
    $profile->skills()->sync($sofia->caregiverProfile->skills->pluck('id'));
    $profile->languages()->sync($sofia->caregiverProfile->languages->pluck('id'));
    foreach ($sofia->caregiverProfile->availabilities as $availability) {
        $copy = $availability->replicate();
        $copy->setRelations([]);
        $profile->availabilities()->save($copy);
    }

    $cloneRequest = function (int $sourceId, string $stage): CareRequest {
        $source = CareRequest::with(['recipient', 'thirdPartyContact', 'tasks'])->findOrFail($sourceId);
        $request = $source->replicate(['care_plan_id']);
        $request->setRelations([]);
        $request->title = preg_replace('/ — .+$/u', '', $source->title).' — '.ucwords(str_replace('_', ' ', $stage));
        $request->save();
        foreach (['recipient', 'thirdPartyContact'] as $relation) {
            if ($source->{$relation}) {
                $copy = $source->{$relation}->replicate();
                $copy->setRelations([]);
                $request->{$relation}()->save($copy);
            }
        }
        $request->tasks()->sync($source->tasks->mapWithKeys(fn ($task) => [$task->id => ['task_note' => $task->pivot->task_note]])->all());
        return $request;
    };
    $extras = ['requests' => [], 'bookings' => [], 'extraPlans' => [], 'timeCorrections' => [],
        'caregivers' => ['oliver' => ['id' => $fourth->id, 'email' => $fourth->email, 'name' => $fourth->name]]];

    foreach (['one_time', 'recurring'] as $variation) {
        foreach (['single_applicant', 'many_applicants', 'private_waiting', 'paused', 'payment_attention', 'time_correction'] as $stage) {
            $sourceStage = match ($stage) {
                'paused' => 'in_progress', 'payment_attention' => 'scheduled', 'time_correction' => 'timesheet_review', default => 'waiting',
            };
            $sourceRequest = CareRequest::findOrFail($core['requests'][$variation][$sourceStage]);
            $request = $cloneRequest($sourceRequest->id, $stage);
            $extras['requests'][$variation][$stage] = $request->id;
            if ($stage === 'private_waiting') {
                $request->update(['is_private' => true]);
                continue;
            }
            if (in_array($stage, ['single_applicant', 'many_applicants'])) {
                $caregiverIds = $stage === 'single_applicant' ? [$sofia->id]
                    : [$sofia->id, $core['caregivers']['james']['id'], $core['caregivers']['aisha']['id'], $fourth->id];
                foreach ($caregiverIds as $index => $caregiverId) {
                    CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiverId,
                        'status' => $index === 0 ? 'shortlisted' : 'applied', 'proposed_rate' => 30,
                        'cover_note' => 'I am available for your schedule and would love to help Ellie with her everyday routine.']);
                }
                $request->update(['first_applicant_at' => now()->subHours(2), 'first_shortlist_at' => now()->subHour()]);
                continue;
            }

            $sourceBooking = $sourceRequest->booking;
            $application = $sourceBooking->application->replicate();
            $application->setRelations([]);
            $application->care_request_id = $request->id;
            $application->save();
            $plan = null;
            if ($variation === 'recurring') {
                $plan = $sourceBooking->carePlan->replicate(['next_booking_id', 'source_care_booking_id']);
                $plan->setRelations([]);
                $plan->fill(['source_care_request_id' => $request->id, 'status' => $stage === 'payment_attention' ? 'payment_attention' : 'active',
                    'payment_status' => $stage === 'payment_attention' ? 'action_required' : 'authorized',
                    'title' => 'Recurring care for Ellie — '.ucwords(str_replace('_', ' ', $stage)),
                    'last_error' => $stage === 'payment_attention' ? 'Your card needs attention before this visit.' : null]);
                $plan->save();
                $request->update(['care_plan_id' => $plan->id, 'request_type' => 'one_time', 'is_system_generated' => true]);
                $extras['extraPlans'][$stage] = $plan->id;
            }
            $booking = $sourceBooking->replicate(['financial_reference', 'occurrence_key']);
            $booking->setRelations([]);
            $booking->fill(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
                'care_plan_id' => $plan?->id, 'occurrence_key' => $plan ? 'audit-extra-'.$plan->id.'-'.$stage : null,
                'agreement_snapshot' => app(BookingTrustService::class)->buildAgreementSnapshot($request->fresh(['recipient', 'tasks']), $application)]);
            if ($stage === 'paused') $booking->fill(['status' => CareBooking::STATUS_PAUSED, 'paused_at' => now()->subMinutes(10), 'total_paused_seconds' => 0]);
            $booking->save();
            $extras['bookings'][$variation][$stage] = $booking->id;
            if ($plan) $plan->update(['source_care_booking_id' => $booking->id, 'next_booking_id' => $stage === 'payment_attention' ? $booking->id : null]);
            $payment = $sourceBooking->payment->replicate();
            $payment->setRelations([]);
            $payment->fill(['care_booking_id' => $booking->id, 'financial_reference' => $booking->financial_reference]);
            if ($stage === 'payment_attention') $payment->fill(['status' => 'failed', 'failed_at' => now()->subHour(),
                'authorized_at' => null, 'authorization_expires_at' => null,
                'last_error' => 'Your card was declined. Update your payment method to keep this visit on track.']);
            $payment->save();
            foreach (['taskChecks', 'events'] as $relation) {
                foreach ($sourceBooking->{$relation} as $record) {
                    $copy = $record->replicate();
                    $copy->setRelations([]);
                    $booking->{$relation}()->save($copy);
                }
            }
            if ($stage === 'paused') {
                $booking->events()->create(['actor_user_id' => $sofia->id, 'actor_role' => 'caregiver',
                    'event_type' => 'shift_paused_by_caregiver', 'happened_at' => now()->subMinutes(10),
                    'payload' => ['source' => 'find_care_screen_audit']]);
            }
            if ($stage === 'time_correction') {
                $end = $booking->completed_at->copy()->subMinutes(15);
                $correction = CareBookingTimeCorrection::create([
                    'client_request_id' => (string) Str::uuid(), 'care_booking_id' => $booking->id,
                    'requester_user_id' => $sofia->id, 'family_user_id' => $family->id, 'version' => 1,
                    'status' => CareBookingTimeCorrection::STATUS_PENDING_FAMILY,
                    'reason_code' => CareBookingTimeCorrection::REASON_FORGOT_END,
                    'explanation' => 'I finished helping Ellie at the time below but forgot to end the visit in the app until 15 minutes later.',
                    'proposed_started_at' => $booking->started_at, 'proposed_completed_at' => $end,
                    'proposed_break_minutes' => 0, 'proposed_worked_minutes' => 105,
                    'original_snapshot' => ['booking' => [
                        'status' => $booking->status, 'scheduled_start_at' => $booking->scheduled_start_at->toIso8601String(),
                        'scheduled_end_at' => $booking->scheduled_end_at->toIso8601String(),
                        'started_at' => $booking->started_at->toIso8601String(), 'completed_at' => $booking->completed_at->toIso8601String(),
                        'worked_minutes' => 120, 'check_in_lat' => 35.7796, 'check_in_lng' => -78.6382,
                        'check_out_lat' => 35.7797, 'check_out_lng' => -78.6383,
                    ], 'payment' => ['status' => $payment->status, 'amount_captured_cents' => 0, 'amount_authorized_cents' => 6200]],
                    'financial_preview' => ['timezone' => config('app.timezone'), 'worked_minutes' => 105,
                        'target_charge_cents' => 5425, 'caregiver_amount_cents' => 4537,
                        'family_care_amount_cents' => 5250, 'family_processing_fee_cents' => 175,
                        'hourly_rate' => 30, 'platform_fee_percent' => 0], 'submitted_at' => now()->subMinutes(30),
                ]);
                $extras['timeCorrections'][$variation] = $correction->id;
            }
        }
    }
    return $extras;
});
echo json_encode($extras, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
