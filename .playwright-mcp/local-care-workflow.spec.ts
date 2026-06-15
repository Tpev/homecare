import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const familyEmail = 'don.browser@example.com';
const caregiverEmail = 'caroline.browser@example.com';
const password = 'password';
const screenshotDir = path.join(process.cwd(), '.playwright-mcp', 'current-care-workflow');

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

async function captureFirstScreen(page, name: string): Promise<void> {
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.waitForTimeout(100);
  await page.screenshot({ path: path.join(screenshotDir, `${name}-desktop.png`), fullPage: false });

  await page.evaluate(() => window.scrollTo(0, 0));
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(100);
  await page.screenshot({ path: path.join(screenshotDir, `${name}-mobile.png`), fullPage: false });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.waitForTimeout(100);
}

test('Don posts a simple request, Caroline accepts invite, Don can hire', async ({ page }) => {
  fs.mkdirSync(screenshotDir, { recursive: true });

  const bootstrap = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$password = Illuminate\Support\Facades\Hash::make('password');
App\Models\User::query()->whereIn('email', ['don.browser@example.com', 'caroline.browser@example.com'])->update(['password' => $password, 'email_verified_at' => now()]);
$don = App\Models\User::where('email', 'don.browser@example.com')->firstOrFail();
$don->forceFill(['name' => 'Don Johnson', 'role' => 'family', 'city' => 'Durham', 'state' => 'NC'])->save();
$caroline = App\Models\User::where('email', 'caroline.browser@example.com')->firstOrFail();
$caroline->forceFill(['name' => 'Caroline Petrini-Poli', 'role' => 'caregiver', 'city' => 'Durham', 'state' => 'NC'])->save();
$profile = App\Models\CaregiverProfile::firstOrNew(['user_id' => $caroline->id]);
$profile->fill(['status' => 'active', 'bio' => str_repeat('Warm reliable caregiver. ', 4), 'platform_hourly_rate' => 30, 'years_experience' => 5, 'service_area_zip' => '27703', 'service_radius_miles' => 25, 'insurance_status' => App\Models\CaregiverProfile::INSURANCE_NO, 'identity_verification_status' => 'approved', 'identity_verified_at' => now(), 'is_accepting_new_clients' => true]);
$profile->save();
App\Models\CareRequest::query()->where('title', 'Browser smoke simple companionship')->delete();
echo 'ready';
`;
  expect(phpEval(bootstrap).trim()).toBe('ready');

  await login(page, familyEmail);
  await page.goto('/family/requests/create');
  await expect(page.getByRole('heading', { name: /tell us what care you need/i })).toBeVisible();
  await expect(page.locator('body')).toContainText('Before publishing');
  await expect(page.locator('body')).toContainText('0 of 4 essentials ready');
  await expect(page.locator('body')).toContainText('Review and publish');
  await expect(page.locator('body')).not.toContainText('Request summary');
  await captureFirstScreen(page, '00-family-create-empty');

  await page.locator('input[value="self"]').check({ force: true });
  const task = page.locator('label').filter({ hasText: /Companionship|Errands|Meal|Personal care/i }).first();
  await expect(task).toBeVisible();
  await task.click();

  const startDate = new Date(Date.now() + 4 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
  await page.getByLabel('Starting day').first().fill(startDate);
  await page.getByLabel('Starting time').first().fill('09:30');
  await page.getByLabel('Duration (HH:MM)').selectOption('120');
  await page.getByLabel('Street address').fill('1520 Home Creek Drive');
  await page.getByLabel('City').fill('Durham');
  await page.getByLabel('State').selectOption('NC');
  await page.getByLabel('ZIP').fill('27703');
  await page.getByRole('button', { name: /publish request/i }).click();
  await page.waitForURL(/\/family\/requests\/\d+/, { timeout: 30_000 });
  await expect(page.getByText(/Finding care/i)).toBeVisible();
  await expect(page.getByText(/Suggested caregivers/i)).toBeVisible();
  await expect(page.getByText(/Invite one or two caregivers/i)).toBeVisible();
  await expect(page.locator('body')).toContainText('After a caregiver replies, this screen changes to compare, chat, and hire.');
  await expect(page.locator('body')).toContainText('Invite matching people');
  await expect(page.locator('body')).not.toContainText('At a glance');
  await expect(page.locator('body')).not.toContainText('Invite, chat, hire');
  await captureFirstScreen(page, '01-family-request-published');
  await page.screenshot({ path: path.join(screenshotDir, '01-family-request-published.png'), fullPage: true });

  const requestIdMatch = page.url().match(/\/family\/requests\/(\d+)/);
  expect(requestIdMatch).not.toBeNull();
  const requestId = requestIdMatch![1];

  await login(page, caregiverEmail);
  await page.goto(`/care-requests/${requestId}/apply`);
  await expect(page.locator('body')).toContainText('Interested in this visit?');
  await expect(page.locator('body')).toContainText('I can do this visit');
  await expect(page.locator('body')).toContainText('This can be left blank.');
  await expect(page.getByRole('button', { name: 'Overview' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Application' })).toHaveCount(0);
  await captureFirstScreen(page, '02-caregiver-new-request-decision');
  await page.screenshot({ path: path.join(screenshotDir, '02-caregiver-new-request-decision.png'), fullPage: true });

  const invite = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = App\Models\CareRequest::findOrFail(${requestId});
$request->forceFill(['title' => 'Browser smoke simple companionship', 'status' => App\Models\CareRequest::STATUS_OPEN])->save();
$caregiver = App\Models\User::where('email', 'caroline.browser@example.com')->firstOrFail();
$invitation = App\Models\CareRequestInvitation::updateOrCreate(
  ['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id],
  ['family_user_id' => $request->family_user_id, 'status' => App\Models\CareRequestInvitation::STATUS_PENDING, 'message' => 'We think your profile could be a strong fit for this request.', 'expires_at' => now()->addHours(72), 'responded_at' => null, 'care_request_application_id' => null]
);
echo $invitation->id;
`;
  const invitationId = phpEval(invite).trim();
  expect(invitationId).toMatch(/^\d+$/);

  await login(page, caregiverEmail);
  await page.goto('/caregiver/work-inbox');
  await expect(page.getByRole('heading', { name: /you have 1 request to answer/i })).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Visible visit value');
  await expect(page.locator('body')).not.toContainText('Ready-to-respond value');
  await expect(page.getByText('Browser smoke simple companionship')).toBeVisible();
  await captureFirstScreen(page, '02-caregiver-work-inbox-invite');
  await page.screenshot({ path: path.join(screenshotDir, '02-caregiver-work-inbox-invite.png'), fullPage: true });
  await page.getByRole('button', { name: /accept invite/i }).first().click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText('Browser smoke simple companionship');
  await expect(page.locator('body')).toContainText('SHORTLISTED');
  await expect(page.locator('body')).toContainText('Waiting for family');
  await expect(page.locator('body')).toContainText('You accepted the invitation.');
  await expect(page.locator('body')).toContainText('No action needed.');
  await expect(page.locator('body')).toContainText('What happens next');
  await expect(page.locator('body')).toContainText('If hired');
  await expect(page.locator('body')).toContainText('Tasks, address, and notes');
  await expect(page.locator('body')).toContainText('Your reply');
  await expect(page.getByRole('button', { name: 'Overview' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Application' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Visit' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Support' })).toHaveCount(0);
  await expect(page.getByText('No active visit yet')).toHaveCount(0);
  await captureFirstScreen(page, '03-caregiver-invite-accepted');
  await page.screenshot({ path: path.join(screenshotDir, '03-caregiver-invite-accepted.png'), fullPage: true });

  await login(page, familyEmail);
  await page.goto(`/family/requests/${requestId}`);
  await expect(page.getByText(/Choose caregiver/i)).toBeVisible();
  await expect(page.getByText('Caroline Petrini-Poli').first()).toBeVisible();
  await expect(page.locator('body')).toContainText('Review, chat, hire');
  await expect(page.locator('body')).not.toContainText('At a glance');
  await expect(page.locator('body')).not.toContainText('Invite, chat, hire');
  await captureFirstScreen(page, '04-family-caregiver-ready-to-hire');
  await page.screenshot({ path: path.join(screenshotDir, '04-family-caregiver-ready-to-hire.png'), fullPage: true });

  await page.getByRole('button', { name: /open chat/i }).first().click();
  await page.waitForURL(/\/messages\/\d+/, { timeout: 30_000 });
  await expect(page.locator('body')).toContainText('Hire decision');
  await expect(page.locator('body')).toContainText('Chat here, then hire from the request page.');
  await expect(page.locator('body')).toContainText('Open request to hire');
  await captureFirstScreen(page, '04a-family-chat-hire-decision');
  await page.screenshot({ path: path.join(screenshotDir, '04a-family-chat-hire-decision.png'), fullPage: true });
  await page.getByRole('link', { name: /open request to hire/i }).click();
  await page.waitForURL(new RegExp(`/family/requests/${requestId}$`), { timeout: 30_000 });
  await expect(page.getByText(/Choose caregiver/i)).toBeVisible();

  const addSecondCandidate = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = App\Models\CareRequest::findOrFail(${requestId});
$michael = App\Models\User::updateOrCreate(
  ['email' => 'michael.browser@example.com'],
  ['name' => 'Michael Rivera', 'role' => 'caregiver', 'city' => 'Durham', 'state' => 'NC', 'password' => Illuminate\Support\Facades\Hash::make('password'), 'email_verified_at' => now()]
);
$profile = App\Models\CaregiverProfile::firstOrNew(['user_id' => $michael->id]);
$profile->fill(['status' => 'active', 'bio' => str_repeat('Calm experienced support. ', 4), 'platform_hourly_rate' => 30, 'years_experience' => 4, 'service_area_zip' => '27703', 'service_radius_miles' => 25, 'insurance_status' => App\Models\CaregiverProfile::INSURANCE_NO, 'identity_verification_status' => 'approved', 'identity_verified_at' => now(), 'is_accepting_new_clients' => true]);
$profile->save();
App\Models\CareRequestApplication::updateOrCreate(
  ['care_request_id' => $request->id, 'caregiver_user_id' => $michael->id],
  ['family_user_id' => $request->family_user_id, 'status' => App\Models\CareRequestApplication::STATUS_APPLIED, 'proposed_rate' => 30, 'cover_note' => 'I can help with this visit.']
);
echo 'added';
`;
  expect(phpEval(addSecondCandidate).trim()).toBe('added');
  await page.reload();
  await expect(page.locator('body')).toContainText('2 caregivers replied');
  await expect(page.locator('body')).toContainText('Review each card below');
  await expect(page.locator('body')).not.toContainText('Compare caregivers');
  await expect(page.locator('body')).not.toContainText('Filter or sort caregivers');
  await expect(page.locator('body')).not.toContainText('Ready to choose');
  await captureFirstScreen(page, '04b-family-compare-caregivers');
  await page.screenshot({ path: path.join(screenshotDir, '04b-family-compare-caregivers.png'), fullPage: true });

  await page.getByRole('button', { name: /^hire caroline/i }).first().click();
  await expect(page.locator('body')).not.toContainText('Ask this caregiver to accept your invitation first');
  await expect(page.locator('body')).not.toContainText('caregiver pre-launch mode');
  await expect(page.locator('body')).toContainText('Visit scheduled', { timeout: 30_000 });
  await expect(page.locator('body')).toContainText('Your visit is scheduled.');
  await expect(page.locator('body')).toContainText('Caroline is coming');
  await expect(page.locator('body')).toContainText('Before the visit');
  await expect(page.locator('body')).toContainText('Visit plan');
  await expect(page.locator('body')).not.toContainText('Caregivers are ready for review.');
  await captureFirstScreen(page, '05-family-after-hire-click');
  await page.screenshot({ path: path.join(screenshotDir, '05-family-after-hire-click.png'), fullPage: true });
});
