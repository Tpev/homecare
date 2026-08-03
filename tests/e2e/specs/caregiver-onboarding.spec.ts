import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

test.describe('Caregiver Onboarding', () => {
    test('caregiver can submit onboarding for review', async ({ page }) => {
        await loginAs(page, 'caregiverNew');
        await page.goto('/caregiver/onboarding');
        await expect(page).toHaveURL(/\/caregiver\/onboarding$/);

        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByText('Step 2 of 5')).toBeVisible();

        await page.getByLabel("Memory loss, dementia, or Alzheimer's support").check();
        await page.getByLabel('No current certifications').check();

        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByText('Step 3 of 5')).toBeVisible();

        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByText('Step 4 of 5')).toBeVisible();

        await page.getByRole('button', { name: 'Continue' }).click();
        await expect(page.getByText('Step 5 of 5')).toBeVisible();

        await page.getByRole('button', { name: 'Submit for review' }).click();

        await expect(page).toHaveURL(/\/caregiver\/setup$/);
        await expect(page.getByText('Profile submitted. Review usually takes up to 1 business day.')).toBeVisible();
        await expect(page.getByText('Experience & training')).toBeVisible();
    });
});
