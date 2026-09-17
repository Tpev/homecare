import { chromium, expect as baseExpect } from '@playwright/test';
import fs from 'node:fs';

const expect = baseExpect.configure({ timeout: 30000 });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
page.setDefaultTimeout(30000);
page.setDefaultNavigationTimeout(60000);
const base = 'http://127.0.0.1:8033';
const report = { checks: [], errors: [] };
page.on('pageerror', error => report.errors.push(error.message));
await page.route('**/*', route => route.request().url().startsWith(base) ? route.continue() : route.abort());
const check = async (label, action) => { await action(); report.checks.push(label); console.log(`PASS ${label}`); };
try {
  await page.goto(`${base}/login`);
  const email = page.locator('input[wire\\:model="form.email"]');
  await expect.poll(() => email.evaluate(el => Boolean(el.closest('[wire\\:id]')?.__livewire))).toBe(true);
  await email.fill('onboarding.admin@example.test');
  await page.locator('input[wire\\:model="form.password"]').fill('LoLoPreview!2026');
  await page.locator('form[wire\\:submit="login"] button[type=submit]').click();
  await expect(page).not.toHaveURL(/\/login$/);
  await page.goto(`${base}/admin/family-onboarding`);
  const search = page.getByLabel('Search family');
  const filter = page.locator('select[wire\\:model\\.live="filter"]');
  await expect.poll(() => search.evaluate(el => Boolean(el.closest('[wire\\:id]')?.__livewire))).toBe(true);
  await check('Automatic onboarding policy is visible', () => expect(page.getByText(/New family signups automatically start onboarding/)).toBeVisible());
  await search.fill('existing.preview@example.test');
  await check('Existing family without enrollment is searchable', async () => {
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await expect(page.locator('tbody')).toContainText('existing.preview@example.test');
    await expect(page.locator('tbody')).toContainText('Not enrolled');
  });
  await filter.selectOption('completed');
  await check('Completed filter does not mislabel an unenrolled family', () => expect(page.getByText('Clear search and filters')).toBeVisible());
  await filter.selectOption('not_enrolled');
  await expect(page.locator('tbody')).toContainText('existing.preview@example.test');
  await page.reload();
  await check('Search and filter survive refreshing', async () => {
    await expect(search).toHaveValue('existing.preview@example.test');
    await expect(filter).toHaveValue('not_enrolled');
    await expect(page.locator('tbody')).toContainText('Not enrolled');
  });
  await page.screenshot({ path: 'docs/product/family-onboarding-2026-09-15/screenshots/admin-enrollment-visibility.png', fullPage: true });
  await page.locator('tbody tr a').first().click();
  await check('User profile explains the missing enrollment', () => expect(page.getByText('Family onboarding: Not enrolled', { exact: true })).toBeVisible());
  expect(report.errors).toEqual([]);
  report.passed = true;
} catch (error) {
  report.passed = false;
  report.failure = error.stack;
  throw error;
} finally {
  fs.writeFileSync('docs/product/family-onboarding-2026-09-15/admin-visibility-validation.json', JSON.stringify(report, null, 2));
  await browser.close();
}
