import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

test.describe('Caregiver Onboarding', () => {
    test('caregiver can submit onboarding for review', async ({ page }) => {
        await loginAs(page, 'caregiverNew');
        await expect(page).toHaveURL(/\/caregiver\/onboarding$/);

        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByText('Step 2 of 4')).toBeVisible();

        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByText('Step 3 of 4')).toBeVisible();

        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByText('Step 4 of 4')).toBeVisible();

        await page.getByRole('button', { name: 'Submit for review' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByText('Caregiver Dashboard')).toBeVisible();
    });
});
