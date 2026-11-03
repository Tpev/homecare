import { test, expect, type Page } from '@playwright/test';

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('recorded.admin@example.test');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    await page.goto('/admin/requests');
    await page.getByRole('link', { name: 'Record past visit', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Record a past visit' })).toBeVisible();
}

async function fillVisit(page: Page, date: string) {
    await page.getByRole('button', { name: /John Example/ }).click();
    await expect(page.getByText(/recorded.family@example.test · Account/)).toBeVisible();
    await page.getByRole('button', { name: /Charles Example/ }).click();
    await expect(page.getByRole('button', { name: /Charles Example/ })).toHaveCount(0);
    await page.getByLabel('Visit date', { exact: true }).fill(date);
    await page.getByLabel('Start time', { exact: true }).fill('11:30');
    await page.getByLabel('Time zone', { exact: true }).selectOption('America/New_York');
    await page.getByLabel('Total duration (minutes)', { exact: false }).fill('90');
    await page.getByLabel('Unpaid breaks (minutes)', { exact: true }).fill('15');
    await page.getByLabel('Why is this visit being recorded now?').fill('Family and caregiver confirmed the visit; neither entered it in the app.');
    await expect(page.getByLabel('Billable worked minutes')).toHaveText('75');
    await page.evaluate(() => window.scrollTo(0, 0));
}

test('admin records, reviews, pays, reopens, and detects a duplicate visit', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page);
    await fillVisit(page, '2026-10-20');
    await page.screenshot({ path: testInfo.outputPath('desktop-form.png'), fullPage: true });
    await page.getByRole('button', { name: 'Review visit & charge' }).click();
    await expect(page.getByRole('heading', { name: 'Check the completed visit' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Create visit & charge $38.75' })).toBeVisible();
    await expect(page.getByText(/11:30 AM EDT/)).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('desktop-review.png'), fullPage: true });
    await page.getByRole('checkbox', { name: /I verified that this care happened/ }).check();
    await page.getByRole('button', { name: 'Create visit & charge $38.75' }).click();
    await expect(page).toHaveURL(/\/admin\/requests\/recorded-visits\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Visit completed and payment settled' })).toBeVisible();
    await expect(page.getByText('Simulated payment — no real money moved.')).toBeVisible();
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Visit completed and payment settled' })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('desktop-receipt.png'), fullPage: true });
    await page.getByRole('link', { name: 'Record another visit' }).click();
    await fillVisit(page, '2026-10-20');
    await page.getByRole('button', { name: 'Review visit & charge' }).click();
    await expect(page.getByRole('alert')).toContainText('overlapping booking');
});

test('mobile layout supports entry and review without horizontal overflow', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await fillVisit(page, '2026-10-21');
    await page.screenshot({ path: testInfo.outputPath('mobile-form.png'), fullPage: true });
    await expect(page.locator('body')).toHaveJSProperty('scrollWidth', 390);
    await page.getByRole('button', { name: 'Review visit & charge' }).click();
    await expect(page.getByRole('button', { name: 'Create visit & charge $38.75' })).toBeVisible();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: testInfo.outputPath('mobile-review.png'), fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});
