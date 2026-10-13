<?php

use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\CareRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = realpath(getcwd().'/.tmp/find-care-screen-audit-2026-09-08/find-care-audit.sqlite');
$actual = realpath((string) config('database.connections.sqlite.database'));
if (! $app->environment('playwright') || config('database.default') !== 'sqlite' || ! $expected || $actual !== $expected) {
    throw new RuntimeException('Only the isolated September 8 screenshot database may receive synthetic plan states.');
}
$manifestPath = getcwd().'/docs/product/find-care-screen-audit-2026-09-08/fixture-manifest.json';
$manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$template = CarePlan::with(['relationship', 'sourceCareRequest.tasks', 'sourceCareRequest.recipient', 'sourceCareRequest.thirdPartyContact', 'sourceCareBooking.application', 'sourceCareBooking.payment'])->findOrFail($manifest['plans']['pending']);
$seeded = DB::transaction(function () use ($template, $manifest) {
    $result = [];
    foreach (['countered', 'declined', 'cancelled'] as $status) {
        $fixtureKey = 'redesign-plan-'.$status;
        $existing = CarePlan::where('title', 'Recurring care for Ellie — '.ucfirst($status))->get()
            ->first(fn ($plan) => data_get($plan->metadata, 'fixture_key') === $fixtureKey);
        if ($existing) {
            $result[$status] = $existing->id;
            continue;
        }
        $start = now('America/New_York')->addWeek()->startOfDay();
        $source = $template->sourceCareRequest->replicate();
        $source->forceFill(['care_plan_id' => null, 'status' => $status === 'countered' ? 'filled' : 'cancelled']);
        $source->save();
        foreach ($template->sourceCareRequest->tasks as $task) {
            $source->tasks()->attach($task->id, ['task_note' => $task->pivot->task_note]);
        }
        if ($template->sourceCareRequest->thirdPartyContact) {
            $contact = $template->sourceCareRequest->thirdPartyContact->replicate();
            $source->thirdPartyContact()->save($contact);
        }
        $relationship = $template->relationship->replicate();
        $relationship->forceFill([
            'source_care_request_id' => $source->id, 'last_care_request_id' => $source->id,
            'last_care_booking_id' => null, 'last_visit_at' => null,
            'status' => $status === 'countered' ? 'active' : 'ended',
            'metadata' => ['synthetic_audit_fixture' => true, 'fixture_key' => $fixtureKey],
        ])->save();
        $plan = $template->replicate();
        $plan->forceFill([
            'care_relationship_id' => $relationship->id, 'source_care_request_id' => $source->id,
            'source_care_booking_id' => null, 'next_booking_id' => null,
            'status' => $status, 'title' => 'Recurring care for Ellie — '.ucfirst($status),
            'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addMonths(3)->toDateString(),
            'offered_at' => now()->subDay(), 'responded_at' => now()->subHours(2),
            'accepted_at' => null, 'activated_at' => null, 'paused_at' => null,
            'pause_starts_on' => null, 'resumes_on' => null,
            'declined_at' => $status === 'declined' ? now()->subHours(2) : null,
            'ended_at' => $status === 'cancelled' ? now()->subHours(2) : null,
            'expires_at' => $status === 'countered' ? now()->addDays(2) : null,
            'counter_schedule_days' => $status === 'countered' ? [2, 4] : null,
            'counter_schedule_start_time' => $status === 'countered' ? '09:00' : null,
            'counter_schedule_end_time' => $status === 'countered' ? '11:00' : null,
            'counter_schedule_slots' => $status === 'countered' ? [
                ['day' => 2, 'start_time' => '09:00', 'end_time' => '11:00'],
                ['day' => 4, 'start_time' => '13:00', 'end_time' => '15:30'],
            ] : null,
            'counter_starts_on' => $status === 'countered' ? $start->toDateString() : null,
            'counter_note' => $status === 'countered' ? 'I can help on Tuesday mornings and Thursday afternoons. Would those times work for Ellie?' : null,
            'caregiver_note' => $status === 'declined' ? 'I cannot commit to the requested weekly schedule.' : null,
            'payment_status' => CarePlan::PAYMENT_UNCHECKED,
            'last_generated_at' => null, 'last_error' => null,
            'metadata' => ['synthetic_audit_fixture' => true, 'fixture_key' => $fixtureKey, 'home_access_notes' => 'Ring apartment 2B at the main entrance.'],
        ])->save();
        $source->forceFill(['care_plan_id' => $plan->id])->save();
        CarePlanEvent::create([
            'care_plan_id' => $plan->id, 'actor_user_id' => $status === 'cancelled' ? $plan->family_user_id : $plan->caregiver_user_id,
            'event_type' => 'offer_'.$status,
            'reason' => $status === 'cancelled' ? 'The family cancelled the offer before any visits were booked.' : ($status === 'declined' ? 'The caregiver was unavailable for this weekly schedule.' : 'The caregiver proposed different weekly times.'),
            'payload' => ['source' => 'find_care_redesign_audit', 'synthetic' => true],
        ]);
        $result[$status] = $plan->id;
    }
    return $result;
});
// A known-caregiver offer is sent from prior booked care in production. Keep
// that historical source booking separate from generated recurring visits.
DB::transaction(function () use ($template, $seeded) {
    foreach ($seeded as $id) {
        $plan = CarePlan::with(['sourceCareRequest', 'relationship'])->findOrFail($id);
        if (! $plan->sourceCareRequest->recipient && $template->sourceCareRequest->recipient) {
            $plan->sourceCareRequest->recipient()->save($template->sourceCareRequest->recipient->replicate());
        }
        if ($plan->source_care_booking_id) continue;
        $source = $plan->sourceCareRequest;
        $application = $template->sourceCareBooking->application->replicate();
        $application->forceFill(['care_request_id' => $source->id])->save();
        $booking = $template->sourceCareBooking->replicate();
        $agreement = $booking->agreement_snapshot ?? [];
        $agreement['care_request_id'] = $source->id;
        $agreement['application_id'] = $application->id;
        $booking->forceFill(['care_request_id' => $source->id, 'care_request_application_id' => $application->id,
            'financial_reference' => null, 'agreement_snapshot' => $agreement,
            'care_plan_id' => null, 'occurrence_key' => null, 'plan_visit_kind' => null, 'plan_schedule_version' => null])->save();
        if ($template->sourceCareBooking->payment) {
            $payment = $template->sourceCareBooking->payment->replicate();
            $payment->forceFill(['care_booking_id' => $booking->id, 'financial_reference' => $booking->financial_reference])->save();
        }
        $source->forceFill(['status' => $template->sourceCareRequest->status])->save();
        $plan->forceFill(['source_care_booking_id' => $booking->id])->save();
        $plan->relationship->forceFill(['last_care_booking_id' => $booking->id, 'last_visit_at' => $booking->completed_at])->save();
    }
});
$manifest['newPlans'] = $seeded;
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo json_encode($seeded, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
