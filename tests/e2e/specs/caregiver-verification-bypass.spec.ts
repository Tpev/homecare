import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

test.describe('Caregiver Identity Verification (Bypass Mode)', () => {
    test('caregiver can complete identity verification without external provider in E2E', async ({ page }) => {
        await loginAs(page, 'caregiverNew');
        await page.goto('/caregiver/verification');

        await expect(page.getByRole('heading', { name: 'Identity Verification' })).toBeVisible();
        await page.getByRole('button', { name: /Start verification|Start new verification/i }).click();

        await expect(page.getByText('Verification submitted. We will update your status shortly.')).toBeVisible();
        const identityStep = page.getByRole('link', { name: /Identity verification/ });
        await expect(identityStep).toContainText('DONE');
    });
});
