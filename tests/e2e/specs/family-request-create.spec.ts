import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { createOneTimeRequest } from '../helpers/family-request';

test.describe('Family Request Creation', () => {
    test('family can create a manual one-time request', async ({ page }) => {
        await loginAs(page, 'family');

        const { requestTitle } = await createOneTimeRequest(page);

        await expect(page).toHaveURL(/\/family\/requests\/\d+$/);
        await expect(page.getByRole('heading', { name: requestTitle })).toBeVisible();
        await page.getByRole('button', { name: /Care details/i }).click();
        await expect(page.getByText('E2E Recipient Manual')).toBeVisible();
    });

    test('family can create a manual one-time request on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'family');

        const { requestTitle } = await createOneTimeRequest(page);

        await expect(page).toHaveURL(/\/family\/requests\/\d+$/);
        await expect(page.getByRole('heading', { name: requestTitle })).toBeVisible();

        const horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(horizontalOverflow).toBeLessThanOrEqual(1);
    });

    test('family can set a different time and length for each weekly day', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'family');
        await page.goto('/family/requests/create');

        await page.getByText('Regular visits', { exact: true }).click();
        await page.getByText('Monday', { exact: true }).click();
        await page.getByText('Friday', { exact: true }).click();

        await expect(page.getByText('Each day can have a different start time and visit length.')).toBeVisible();
        await expect(page.getByRole('region', { name: 'Weekly visit times' })).toContainText('Monday');
        await expect(page.getByRole('region', { name: 'Weekly visit times' })).toContainText('Friday');

        await page.locator('#recurring-start-1').fill('14:00');
        await page.locator('#recurring-duration-1').selectOption('120');
        await expect(page.getByText('Ends at 4:00 PM')).toBeVisible();

        await page.locator('#recurring-start-5').fill('09:30');
        await page.locator('#recurring-duration-5').selectOption('180');
        await expect(page.getByText('Ends at 12:30 PM')).toBeVisible();
        await expect(page.getByText('Schedule:', { exact: true }).locator('..'))
            .toContainText('Mon · 2:00 PM–4:00 PM; Fri · 9:30 AM–12:30 PM');
        await expect(page.getByText('Time needed', { exact: true })).toBeVisible();

        const futureStart = new Date();
        futureStart.setDate(futureStart.getDate() + 14);
        const futureStartValue = [
            futureStart.getFullYear(),
            String(futureStart.getMonth() + 1).padStart(2, '0'),
            String(futureStart.getDate()).padStart(2, '0'),
        ].join('-');
        await page.getByLabel('When should this schedule begin?').fill(futureStartValue);
        await page.getByLabel('When should this schedule begin?').press('Tab');
        await expect(page.getByText('Time ready', { exact: true })).toBeVisible();

        const horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(horizontalOverflow).toBeLessThanOrEqual(1);
        await page.screenshot({ path: testInfo.outputPath('weekly-schedule-mobile.png'), fullPage: true });

        await page.setViewportSize({ width: 1440, height: 1000 });
        await expect(page.getByRole('region', { name: 'Weekly visit times' })).toBeVisible();
        const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(desktopOverflow).toBeLessThanOrEqual(1);
        await page.screenshot({ path: testInfo.outputPath('weekly-schedule-desktop.png'), fullPage: true });
    });
});
