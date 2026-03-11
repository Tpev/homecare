import { expect, test } from '@playwright/test';
import { accounts, loginAs, logoutFromProfileMenu } from '../helpers/auth';

test.describe('Auth and Role Routing', () => {
    test('family can log in and log out from profile menu', async ({ page }) => {
        await loginAs(page, 'family');

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByText('Family Dashboard')).toBeVisible();

        await logoutFromProfileMenu(page, accounts.family.name);
        await expect(page).toHaveURL(/\/$/);
    });

    test('new caregiver is redirected to onboarding after login', async ({ page }) => {
        await loginAs(page, 'caregiverNew');

        await expect(page).toHaveURL(/\/caregiver\/onboarding$/);
        await expect(page.getByRole('heading', { name: 'Caregiver onboarding' })).toBeVisible();
    });
});
