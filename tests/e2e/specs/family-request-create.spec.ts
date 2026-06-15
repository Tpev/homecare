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
});
