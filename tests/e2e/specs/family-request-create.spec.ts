import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { createOneTimeRequest } from '../helpers/family-request';

test.describe('Family Request Creation', () => {
    test('family can create a manual one-time request', async ({ page }) => {
        await loginAs(page, 'family');

        const uniqueTitle = `E2E Manual Request ${Date.now()}`;
        await createOneTimeRequest(page, uniqueTitle);

        await expect(page).toHaveURL(/\/family\/requests\/\d+$/);
        await expect(page.getByRole('heading', { name: uniqueTitle })).toBeVisible();
    });
});
