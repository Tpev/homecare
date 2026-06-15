import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const familyEmail = 'don.hub@example.com';
const caregiverEmail = 'caroline.hub@example.com';
const password = 'password';
const screenshotDir = path.join(process.cwd(), '.playwright-mcp', 'hub-navigation');

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

async function capture(page, name: string, openMobileMenu = false): Promise<void> {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(screenshotDir, `${name}-desktop.png`), fullPage: false });

  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.evaluate(() => window.scrollTo(0, 0));
  if (openMobileMenu) {
    await page.locator('nav button').first().click();
    await page.waitForTimeout(350);
  }
  await page.screenshot({ path: path.join(screenshotDir, `${name}-mobile.png`), fullPage: false });
}

test('dashboard, care hub, visits, and mobile navigation stay clear', async ({ page }) => {
  fs.mkdirSync(screenshotDir, { recursive: true });

  const bootstrap = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$password = Illuminate\Support\Facades\Hash::make('password');
$family = App\Models\User::updateOrCreate(
  ['email' => 'don.hub@example.com'],
  ['name' => 'Don Johnson', 'role' => 'family', 'password' => $password, 'email_verified_at' => now(), 'city' => 'Durham', 'state' => 'NC', 'stripe_customer_id' => 'cus_hub_audit']
);
$caregiver = App\Models\User::updateOrCreate(
  ['email' => 'caroline.hub@example.com'],
  ['name' => 'Caroline Petrini-Poli', 'role' => 'caregiver', 'password' => $password, 'email_verified_at' => now(), 'city' => 'Durham', 'state' => 'NC', 'date_of_birth' => '1978-04-15']
);
$profile = App\Models\CaregiverProfile::firstOrNew(['user_id' => $caregiver->id]);
$profile->fill([
  'status' => 'active',
  'bio' => str_repeat('Calm reliable caregiver. ', 5),
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

App\Models\CareRequest::query()->where('title', 'like', 'Hub audit %')->delete();
$task = App\Models\CareTask::firstOrCreate(['name' => 'Companionship']);

$makeRequest = function (string $suffix, string $status, ?string $bookingStatus = null, array $bookingExtra = []) use ($family, $caregiver, $task) {
  $start = $bookingStatus === App\Models\CareBooking::STATUS_COMPLETED ? now()->subHours(3) : now()->addHours(3);
  $end = $bookingStatus === App\Models\CareBooking::STATUS_COMPLETED ? now()->subHour() : now()->addHours(5);
  if ($bookingStatus === App\Models\CareBooking::STATUS_IN_PROGRESS) {
    $start = now()->subHour();
    $end = now()->addHour();
  }

  $request = App\Models\CareRequest::create([
    'family_user_id' => $family->id,
    'title' => 'Hub audit '.$suffix,
    'additional_info' => 'Please keep the visit calm and simple.',
    'status' => $status,
    'request_type' => App\Models\CareRequest::TYPE_ONE_TIME,
    'requested_start_at' => $start,
    'requested_end_at' => $end,
    'address_line1' => '1520 Home Creek Drive',
    'city' => 'Durham',
    'state' => 'NC',
    'zip' => '27703',
    'first_applicant_at' => now()->subMinutes(35),
  ]);
  $request->recipient()->create([
    'family_user_id' => $family->id,
    'full_name' => 'Don Johnson',
    'relationship_to_family' => 'Self',
    'recipient_is_requester' => true,
  ]);
  $request->tasks()->sync([$task->id => ['task_note' => 'Companionship and calm conversation.']]);
  $application = App\Models\CareRequestApplication::create([
    'care_request_id' => $request->id,
    'caregiver_user_id' => $caregiver->id,
    'status' => $status === App\Models\CareRequest::STATUS_OPEN ? App\Models\CareRequestApplication::STATUS_APPLIED : App\Models\CareRequestApplication::STATUS_HIRED,
    'cover_note' => 'Happy to support this visit.',
    'proposed_rate' => 30,
  ]);
  if ($status === App\Models\CareRequest::STATUS_FILLED) {
    $request->forceFill(['first_hire_at' => now()->subMinutes(15)])->save();
  }
  if ($bookingStatus) {
    $booking = App\Models\CareBooking::create(array_merge([
      'care_request_id' => $request->id,
      'care_request_application_id' => $application->id,
      'family_user_id' => $family->id,
      'caregiver_user_id' => $caregiver->id,
      'status' => $bookingStatus,
      'scheduled_start_at' => $start,
      'scheduled_end_at' => $end,
      'expected_minutes' => $start->diffInMinutes($end),
      'family_terms_accepted_at' => now(),
      'caregiver_terms_accepted_at' => now(),
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
  }
  return $request->id;
};

$review = $makeRequest('hours need approval', App\Models\CareRequest::STATUS_FILLED, App\Models\CareBooking::STATUS_COMPLETED, [
  'started_at' => now()->subHours(3),
  'completed_at' => now()->subMinutes(20),
  'timesheet_submitted_at' => now()->subMinutes(20),
  'worked_minutes' => 130,
]);
$scheduled = $makeRequest('next scheduled visit', App\Models\CareRequest::STATUS_FILLED, App\Models\CareBooking::STATUS_SCHEDULED);
$active = $makeRequest('care happening now', App\Models\CareRequest::STATUS_FILLED, App\Models\CareBooking::STATUS_IN_PROGRESS, [
  'started_at' => now()->subHour(),
]);
$open = $makeRequest('caregiver to review', App\Models\CareRequest::STATUS_OPEN);
App\Models\CareRequestInvitation::updateOrCreate(
  ['care_request_id' => $open, 'caregiver_user_id' => $caregiver->id],
  ['family_user_id' => $family->id, 'status' => App\Models\CareRequestInvitation::STATUS_PENDING, 'message' => 'We think this is a good fit.', 'expires_at' => now()->addDays(3)]
);

echo json_encode(['review' => $review, 'scheduled' => $scheduled, 'active' => $active, 'open' => $open]);
`;

  const ids = JSON.parse(phpEval(bootstrap));

  await login(page, familyEmail);
  await page.goto('/dashboard');
  await expect(page.locator('body')).toContainText('Approve caregiver hours');
  await capture(page, '01-family-dashboard');

  await page.goto('/family/requests');
  await expect(page.locator('body')).toContainText('Needs attention');
  await capture(page, '02-family-care-hub');

  await page.goto('/dashboard');
  await capture(page, '03-family-mobile-menu-open', true);

  await login(page, caregiverEmail);
  await page.goto('/dashboard');
  await expect(page.locator('body')).toContainText('Caregiver Dashboard');
  await capture(page, '04-caregiver-dashboard');

  await page.goto('/caregiver/shifts');
  await expect(page.locator('body')).toContainText('My visits');
  await capture(page, '05-caregiver-visits');

  await page.goto('/dashboard');
  await capture(page, '06-caregiver-mobile-menu-open', true);

  expect(ids.review).toBeTruthy();
});
