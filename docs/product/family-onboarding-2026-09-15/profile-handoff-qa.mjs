import { chromium, expect as baseExpect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const expect = baseExpect.configure({ timeout: 30000 });
const base = 'http://127.0.0.1:8033';
const output = path.resolve('docs/product/family-onboarding-2026-09-15');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1100 }, reducedMotion: 'reduce' });
page.setDefaultTimeout(30000);
page.setDefaultNavigationTimeout(60000);
const report = { checks: [], errors: [] };
page.on('pageerror', error => report.errors.push(error.message));
await page.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
const hydrated = async locator => expect.poll(() => locator.evaluate(el => Boolean(el.closest('[wire\\:id]')?.__livewire))).toBe(true);
const check = async (label, action) => { await action(); report.checks.push(label); console.log(`PASS ${label}`); };

try {
  await page.goto(`${base}/register`);
  await hydrated(page.locator('input[wire\\:model="name"]'));
  for (const [field, value] of Object.entries({ name: 'Profile QA Family', email: `profile-qa-${Date.now()}@example.test`, phone: '(984) 555-0100', password: 'LoLoPreview!2026', password_confirmation: 'LoLoPreview!2026' })) {
    await page.locator(`input[wire\\:model="${field}"]`).fill(value);
  }
  await page.locator('input[type=checkbox]').check();
  await page.locator('form[wire\\:submit="register"] button[type=submit]').click();
  await expect(page).toHaveURL(`${base}/family/onboarding`);
  await hydrated(page.getByRole('button', { name: 'Continue', exact: true }));
  await page.getByText('A family member', { exact: true }).click();
  await page.getByLabel('Their full name').fill('Susan Profile Test');
  await page.getByLabel('Their relationship to you').selectOption('Parent');
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Where will care happen?' })).toBeVisible();
  for (const [label, value] of Object.entries({ 'Street address': '123 Profile Street', City: 'Raleigh', State: 'NC', 'ZIP code': '27601' })) {
    await page.getByLabel(label, { exact: true }).fill(value);
  }
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await page.getByLabel('What should a caregiver know?').fill('Enjoys gardening and conversation.');
  await page.getByLabel('What helps care go well?').fill('Explain things calmly and allow extra time.');
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await page.locator('input[value="no"]').check();
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await page.getByRole('button', { name: 'Create my first request' }).click();
  await expect(page).toHaveURL(`${base}/family/requests/create`);
  await hydrated(page.getByLabel('What should a caregiver know?'));
  await check('All seven standard help options are available', async () => {
    for (const name of ['Companionship', 'Meal preparation', 'Light housekeeping', 'Transportation', 'Medication reminders', 'Errands', 'Daily living assistance']) {
      await expect(page.getByText(name, { exact: true })).toBeVisible();
    }
  });
  await check('Both onboarding answers are visible in the simple care profile', async () => {
    await expect(page.getByText('Simple care profile', { exact: true })).toBeVisible();
    await expect(page.getByLabel('What should a caregiver know?')).toHaveValue('Enjoys gardening and conversation.');
    await expect(page.getByLabel('What helps care go well?')).toHaveValue('Explain things calmly and allow extra time.');
    await expect(page.locator('textarea[wire\\:model="recipient_care_notes"]')).toHaveValue('');
  });
  await page.getByLabel('What helps care go well?').fill('Explain things calmly and allow time to respond.');
  await page.getByLabel('What helps care go well?').blur();
  await expect.poll(() => page.getByLabel('What helps care go well?').evaluate(el => el.closest('[wire\\:id]')?.__livewire?.canonical.quick_profile_good_visit)).toBe('Explain things calmly and allow time to respond.');
  await page.reload();
  await check('Profile edits survive refreshing the request form', () => expect(page.getByLabel('What helps care go well?')).toHaveValue('Explain things calmly and allow time to respond.'));
  await page.screenshot({ path: path.join(output, 'screenshots/profile-handoff-desktop.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await check('Mobile form has no horizontal overflow', async () => expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true));
  await page.screenshot({ path: path.join(output, 'screenshots/profile-handoff-mobile.png'), fullPage: true });
  expect(report.errors).toEqual([]);
  report.passed = true;
} catch (error) {
  report.passed = false;
  report.failure = error.stack;
  await page.screenshot({ path: path.join(output, 'screenshots/profile-handoff-failure.png'), fullPage: true }).catch(() => {});
  throw error;
} finally {
  fs.writeFileSync(path.join(output, 'profile-handoff-validation.json'), JSON.stringify(report, null, 2));
  await browser.close();
}
