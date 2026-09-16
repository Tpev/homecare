import { chromium, expect as playwrightExpect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const base = 'http://127.0.0.1:8033';
const expect = playwrightExpect.configure({ timeout: 30000 });
const output = path.resolve('docs/product/family-onboarding-2026-09-15');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const report = { checks: [], errors: [], screenshots: [] };
const email = `onboarding-qa-${Date.now()}@example.test`;
const name = `QA Family ${Date.now()}`;
const password = 'LoLoPreview!2026';
const date = new Date(Date.now() + 4 * 86400000).toISOString().slice(0, 10);
let active;

const check = async (label, assertion) => { await assertion(); report.checks.push(label); console.log(`PASS ${label}`); };
const screenshot = async (page, label) => {
  const file = `integrated-${label}.png`;
  await page.screenshot({ path: path.join(output, 'screenshots', file), fullPage: true, animations: 'disabled' });
  report.screenshots.push(file);
};
async function context(viewport = { width: 1440, height: 1100 }) {
  const ctx = await browser.newContext({ viewport, timezoneId: 'Europe/Paris', reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  page.setDefaultTimeout(30000);
  page.setDefaultNavigationTimeout(60000);
  page.on('pageerror', error => report.errors.push(error.message));
  await page.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
  active = page;
  return { ctx, page };
}
async function login(page, username) {
  await page.goto(`${base}/login`);
  await page.locator('input[wire\\:model="form.email"]').fill(username);
  await page.locator('input[wire\\:model="form.password"]').fill(password);
  await page.locator('form[wire\\:submit="login"] button[type=submit]').click();
  await expect(page).not.toHaveURL(/\/login$/);
}

try {
  const { page } = await context();
  await page.goto(`${base}/register`);
  for (const [field, value] of Object.entries({ name, email, phone: '(984) 555-0100', password, password_confirmation: password })) {
    await page.locator(`input[wire\\:model="${field}"]`).fill(value);
  }
  await page.locator('input[type=checkbox]').check();
  await page.locator('form[wire\\:submit="register"] button[type=submit]').click();
  await check('Normal registration opens integrated onboarding', () => expect(page).toHaveURL(`${base}/family/onboarding`));
  await expect(page.getByRole('heading', { name: 'Who is receiving care?' })).toBeVisible();
  await screenshot(page, '01-person-desktop');
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await check('Validation is visible and focused', async () => {
    await expect(page.getByRole('alert')).toContainText('Choose who is receiving care.');
    await expect(page.getByRole('alert')).toBeFocused();
  });
  await page.getByText('A family member', { exact: true }).click();
  await page.getByLabel('Their full name').fill('Susan Browser Test');
  await page.getByLabel('Their relationship to you').selectOption('Parent');
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Where will care happen?' })).toBeVisible();
  for (const [label, value] of Object.entries({ 'Street address': '123 Browser Street', 'City': 'Raleigh', 'State': 'NC', 'ZIP code': '27601' })) {
    await page.getByLabel(label, { exact: true }).fill(value);
  }
  await screenshot(page, '02-address-desktop');
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await expect(page.getByLabel('What should a caregiver know?')).toBeVisible();
  await page.reload();
  await check('Saved step resumes after refresh', () => expect(page.getByLabel('What should a caregiver know?')).toBeVisible());
  await page.getByLabel('What should a caregiver know?').fill('Enjoys gardening and conversation.');
  await page.getByLabel('What helps care go well?').fill('Explain things calmly and allow extra time.');
  await screenshot(page, '03-notes-desktop');
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await page.getByText("Yes, I'd like a visit", { exact: true }).click();
  await page.getByLabel('Preferred date', { exact: true }).fill(date);
  await page.getByText('Noon', { exact: true }).click();
  await check('Range options and text confirmation appear', async () => {
    for (const range of ['Morning', 'Noon', 'Afternoon']) await expect(page.getByText(range, { exact: true })).toBeVisible();
    await expect(page.getByText('Our team will confirm the final time by text.')).toBeVisible();
    await expect(page.getByLabel('Phone number for confirmation')).toHaveValue('+19845550100');
  });
  await screenshot(page, '04-visit-desktop');
  await page.setViewportSize({ width: 390, height: 844 });
  await screenshot(page, '04-visit-mobile');
  await check('390px layout has no horizontal overflow', async () => expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true));
  await page.setViewportSize({ width: 320, height: 800 });
  await screenshot(page, '04-visit-320');
  await check('320px layout has no horizontal overflow', async () => expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true));
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.getByRole('button', { name: 'Continue', exact: true }).click();
  await expect(page.getByRole('heading', { name: "Let's post your first request" })).toBeVisible();
  await screenshot(page, '05-ready-desktop');
  await page.getByRole('button', { name: 'Create my first request' }).click();
  await check('Completion opens request creation', () => expect(page).toHaveURL(`${base}/family/requests/create`));
  await check('Request receives the saved address and care notes', async () => {
    await expect(page.locator('input[wire\\:model\\.blur="address_line1"], input[wire\\:model="address_line1"], input[wire\\:model\\.live="address_line1"]')).toHaveValue('123 Browser Street');
  });
  await screenshot(page, '06-request-handoff');
  await page.goto(`${base}/family/onboarding`);
  await check('Completed family cannot reopen onboarding', () => expect(page).toHaveURL(`${base}/family/requests`));

  const { page: existing } = await context();
  await login(existing, 'existing.preview@example.test');
  await check('Existing family keeps its current landing page', () => expect(existing).toHaveURL(`${base}/family/requests`));

  const { page: admin } = await context();
  await login(admin, 'onboarding.admin@example.test');
  await admin.goto(`${base}/admin/family-onboarding`);
  await admin.getByRole('link', { name: new RegExp(name) }).click();
  await check('Admin receives all saved form information', async () => {
    for (const text of ['Susan Browser Test', '123 Browser Street', 'Enjoys gardening and conversation.', 'Explain things calmly and allow extra time.', 'Noon (12 pm – 2 pm)']) {
      await expect(admin.getByText(text, { exact: true })).toBeVisible();
    }
  });
  await screenshot(admin, '07-admin-submission');
  await expect.poll(() => admin.getByLabel('Follow-up status').evaluate(el => Boolean(el.closest('[wire\\:id]')?.__livewire))).toBe(true);
  await admin.getByLabel('Follow-up status').selectOption('confirmed');
  await expect(admin.getByLabel('The family has confirmed this time by text.')).toBeVisible();
  await admin.getByLabel('Agreed start time (America/New_York)').fill(`${date}T12:30`);
  await admin.getByLabel('The family has confirmed this time by text.').check();
  await admin.getByLabel('Follow-up note').fill('Synthetic QA confirmation; no real text sent.');
  await admin.getByRole('button', { name: 'Save follow-up' }).click();
  await check('Admin can record follow-up', () => expect(admin.getByText('Welcome visit updated.', { exact: true })).toBeVisible());
  if (report.errors.length) throw new Error(`Browser errors: ${report.errors.join('; ')}`);
  report.passed = true;
} catch (error) {
  report.passed = false;
  report.failure = error.stack;
  if (active) await screenshot(active, 'failure').catch(() => {});
  throw error;
} finally {
  fs.writeFileSync(path.join(output, 'integrated-validation.json'), JSON.stringify(report, null, 2));
  await browser.close();
}
