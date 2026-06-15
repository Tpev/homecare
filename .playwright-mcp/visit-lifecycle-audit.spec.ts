import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const familyEmail = 'don.lifecycle@example.com';
const caregiverEmail = 'caroline.lifecycle@example.com';
const password = 'password';
const screenshotDir = path.join(process.cwd(), '.playwright-mcp', 'visit-lifecycle');

function phpEval(code: string): string {
  return execFileSync('php', ['-r', code], {
    cwd: process.cwd(),
    encoding: 'utf8',
    env: {
      ...process.env,
      APPDATA: path.join(process.cwd(), '.tmp', 'appdata'),
    },
  });
}

async function login(page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel(/email/i).fill(email);
  await page.getByLabel(/password/i).fill(password);
  await page.getByRole('button', { name: /log in|login|sign in/i }).click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).not.toContainText('These credentials do not match');
}

async function capture(page, name: string): Promise<void> {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(screenshotDir, `${name}-desktop.png`), fullPage: false });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(screenshotDir, `${name}-mobile.png`), fullPage: false });
}

test('family and caregiver visit lifecycle screens are readable', async ({ page }) => {
  fs.mkdirSync(screenshotDir, { recursive: true });

  const bootstrap = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$password = Illuminate\Support\Facades\Hash::make('password');
$family = App\Models\User::updateOrCreate(
  ['email' => 'don.lifecycle@example.com'],
  ['name' => 'Don Johnson', 'role' => 'family', 'password' => $password, 'email_verified_at' => now(), 'city' => 'Durham', 'state' => 'NC']
);
$caregiver = App\Models\User::updateOrCreate(
  ['email' => 'caroline.lifecycle@example.com'],
  ['name' => 'Caroline Petrini-Poli', 'role' => 'caregiver', 'password' => $password, 'email_verified_at' => now(), 'city' => 'Durham', 'state' => 'NC']
);
$profile = App\Models\CaregiverProfile::firstOrNew(['user_id' => $caregiver->id]);
$profile->fill([
  'status' => 'active',
  'bio' => str_repeat('Calm and reliable caregiver. ', 4),
  'platform_hourly_rate' => 30,
  'years_experience' => 5,
  'service_area_zip' => '27703',
  'service_radius_miles' => 25,
  'insurance_status' => App\Models\CaregiverProfile::INSURANCE_NO,
  'identity_verification_status' => 'approved',
  'identity_verified_at' => now(),
  'is_accepting_new_clients' => true,
]);
$profile->save();

App\Models\CareRequest::query()->where('title', 'like', 'Lifecycle audit %')->delete();
$taskA = App\Models\CareTask::firstOrCreate(['name' => 'Companionship']);
$taskB = App\Models\CareTask::firstOrCreate(['name' => 'Meal preparation']);

$make = function (string $suffix, string $status, Illuminate\Support\Carbon $start, Illuminate\Support\Carbon $end, array $bookingExtra = []) use ($family, $caregiver, $taskA, $taskB) {
  $request = App\Models\CareRequest::create([
    'family_user_id' => $family->id,
    'title' => 'Lifecycle audit '.$suffix,
    'additional_info' => 'Please keep the visit calm, speak clearly, and leave a short note at the end.',
    'status' => App\Models\CareRequest::STATUS_FILLED,
    'request_type' => App\Models\CareRequest::TYPE_ONE_TIME,
    'requested_start_at' => $start,
    'requested_end_at' => $end,
    'address_line1' => '1520 Home Creek Drive',
    'city' => 'Durham',
    'state' => 'NC',
    'zip' => '27703',
  ]);
  $request->recipient()->create([
    'family_user_id' => $family->id,
    'full_name' => 'Don Johnson',
    'relationship_to_family' => 'Self',
    'recipient_is_requester' => true,
  ]);
  $request->tasks()->sync([
    $taskA->id => ['task_note' => 'Talk, check comfort, and keep the visit relaxed.'],
    $taskB->id => ['task_note' => 'Warm lunch and tidy the kitchen area after.'],
  ]);
  $application = App\Models\CareRequestApplication::create([
    'care_request_id' => $request->id,
    'caregiver_user_id' => $caregiver->id,
    'status' => App\Models\CareRequestApplication::STATUS_HIRED,
    'cover_note' => 'Happy to help Don with a calm visit.',
    'proposed_rate' => 30,
  ]);
  $booking = App\Models\CareBooking::create(array_merge([
    'care_request_id' => $request->id,
    'care_request_application_id' => $application->id,
    'family_user_id' => $family->id,
    'caregiver_user_id' => $caregiver->id,
    'status' => $status,
    'scheduled_start_at' => $start,
    'scheduled_end_at' => $end,
    'expected_minutes' => $start->diffInMinutes($end),
    'family_terms_accepted_at' => now(),
    'caregiver_terms_accepted_at' => now(),
    'agreement_snapshot' => ['rate' => 30, 'title' => 'Lifecycle audit '.$suffix],
  ], $bookingExtra));
  App\Models\CareBookingPayment::create([
    'care_booking_id' => $booking->id,
    'family_user_id' => $family->id,
    'caregiver_user_id' => $caregiver->id,
    'status' => App\Models\CareBookingPayment::STATUS_AUTHORIZED,
    'currency' => 'usd',
    'amount_authorized_cents' => 6600,
    'caregiver_amount_cents' => 6000,
    'platform_fee_cents' => 600,
    'authorized_at' => now(),
    'authorization_expires_at' => now()->addDays(5),
  ]);
  foreach ($request->tasks as $task) {
    App\Models\CareBookingTaskCheck::create([
      'care_booking_id' => $booking->id,
      'care_task_id' => $task->id,
      'label' => $task->name,
      'notes' => $task->pivot->task_note,
      'is_completed' => in_array($status, [App\Models\CareBooking::STATUS_COMPLETED, App\Models\CareBooking::STATUS_REVIEWED], true),
      'completed_at' => in_array($status, [App\Models\CareBooking::STATUS_COMPLETED, App\Models\CareBooking::STATUS_REVIEWED], true) ? now()->subMinutes(15) : null,
      'completed_by_user_id' => in_array($status, [App\Models\CareBooking::STATUS_COMPLETED, App\Models\CareBooking::STATUS_REVIEWED], true) ? $caregiver->id : null,
    ]);
  }
  App\Models\CareBookingEvent::create([
    'care_booking_id' => $booking->id,
    'actor_user_id' => $caregiver->id,
    'actor_role' => 'caregiver',
    'event_type' => $status === App\Models\CareBooking::STATUS_IN_PROGRESS ? 'shift_started_by_caregiver' : 'booking_hired',
    'payload' => ['source' => 'playwright_lifecycle_audit'],
    'happened_at' => now()->subMinutes(30),
  ]);
  return $request->id;
};

$scheduled = $make('scheduled visit', App\Models\CareBooking::STATUS_SCHEDULED, now()->addHours(2), now()->addHours(4));
$active = $make('active visit', App\Models\CareBooking::STATUS_IN_PROGRESS, now()->subHour(), now()->addHour(), [
  'started_at' => now()->subHour(),
  'check_in_lat' => 35.9940,
  'check_in_lng' => -78.8986,
  'check_in_accuracy_meters' => 18.4,
  'check_in_source' => 'browser_gps',
]);
$review = $make('timesheet review', App\Models\CareBooking::STATUS_COMPLETED, now()->subHours(3), now()->subHour(), [
  'started_at' => now()->subHours(3),
  'completed_at' => now()->subMinutes(20),
  'timesheet_submitted_at' => now()->subMinutes(20),
  'worked_minutes' => 135,
  'check_in_lat' => 35.9940,
  'check_in_lng' => -78.8986,
  'check_out_lat' => 35.9942,
  'check_out_lng' => -78.8985,
  'check_in_accuracy_meters' => 18.4,
  'check_out_accuracy_meters' => 16.2,
  'check_in_source' => 'browser_gps',
  'check_out_source' => 'browser_gps',
  'check_out_note' => 'Don ate lunch, enjoyed conversation, and was comfortable when I left.',
]);
$done = $make('completed record', App\Models\CareBooking::STATUS_REVIEWED, now()->subDays(2)->setTime(14, 0), now()->subDays(2)->setTime(16, 0), [
  'started_at' => now()->subDays(2)->setTime(14, 0),
  'completed_at' => now()->subDays(2)->setTime(16, 0),
  'timesheet_submitted_at' => now()->subDays(2)->setTime(16, 10),
  'family_confirmed_at' => now()->subDays(2)->setTime(17, 0),
  'worked_minutes' => 120,
  'check_out_note' => 'Don was comfortable, ate lunch, and asked to book Caroline again.',
]);
$doneRequest = App\Models\CareRequest::findOrFail($done);
$doneBooking = $doneRequest->booking;
$doneBooking->payment?->update([
  'status' => App\Models\CareBookingPayment::STATUS_CAPTURED,
  'amount_captured_cents' => 6600,
  'captured_at' => now()->subDays(2)->setTime(17, 0),
]);
App\Models\CareReview::create([
  'care_request_id' => $doneRequest->id,
  'care_booking_id' => $doneBooking->id,
  'reviewer_user_id' => $family->id,
  'reviewee_user_id' => $caregiver->id,
  'rating' => 5,
  'comment' => 'Caroline was calm, punctual, and kind.',
]);
App\Models\CareReview::create([
  'care_request_id' => $doneRequest->id,
  'care_booking_id' => $doneBooking->id,
  'reviewer_user_id' => $caregiver->id,
  'reviewee_user_id' => $family->id,
  'rating' => 5,
  'comment' => 'Don was ready and clear about what he needed.',
]);

echo json_encode(['scheduled' => $scheduled, 'active' => $active, 'review' => $review, 'done' => $done]);
`;

  const ids = JSON.parse(phpEval(bootstrap));

  await login(page, familyEmail);
  await page.goto(`/family/requests/${ids.scheduled}`);
  await expect(page.locator('body')).toContainText('Lifecycle audit scheduled visit');
  await expect(page.locator('body')).toContainText('Before the visit');
  await expect(page.locator('body')).toContainText('Map and visit record');
  await capture(page, '01-family-scheduled');

  await page.goto(`/family/requests/${ids.active}`);
  await expect(page.locator('body')).toContainText('Lifecycle audit active visit');
  await expect(page.locator('body')).toContainText('Live check-in');
  await expect(page.locator('body')).toContainText('Map and visit record');
  await capture(page, '02-family-active');

  await page.goto(`/family/requests/${ids.review}`);
  await expect(page.locator('body')).toContainText('Review hours');
  await expect(page.locator('body')).toContainText('Approve timesheet');
  await expect(page.locator('body')).not.toContainText('Review caregiver timesheet');
  await capture(page, '03-family-review-timesheet');

  await page.goto(`/family/requests/${ids.done}`);
  await expect(page.locator('body')).toContainText('Lifecycle audit completed record');
  await expect(page.locator('body')).toContainText('Final details');
  await expect(page.locator('body')).toContainText('Visit receipt');
  await expect(page.locator('body')).toContainText('Reviews');
  await capture(page, '04-family-completed-record');

  await login(page, caregiverEmail);
  await page.goto(`/care-requests/${ids.scheduled}/apply`);
  await expect(page.locator('body')).toContainText('Lifecycle audit scheduled visit');
  await expect(page.locator('body')).toContainText('Check in when you arrive.');
  await expect(page.locator('body')).toContainText('Next visit for Don Johnson');
  await capture(page, '05-caregiver-scheduled');

  await page.goto(`/care-requests/${ids.active}/apply`);
  await expect(page.locator('body')).toContainText('Lifecycle audit active visit');
  await expect(page.locator('body')).toContainText('You are checked in.');
  await expect(page.locator('body')).toContainText('Stay focused on Don Johnson.');
  await capture(page, '06-caregiver-active');

  await page.goto(`/care-requests/${ids.review}/apply`);
  await expect(page.locator('body')).toContainText('Lifecycle audit timesheet review');
  await expect(page.locator('body')).toContainText('Your timesheet is submitted.');
  await expect(page.locator('body')).toContainText('Family review is the next step.');
  await capture(page, '07-caregiver-completed');
});
