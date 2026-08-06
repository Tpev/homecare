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

    test('caregiver account menu opens below its toggle and exposes the profile link', async ({ page }) => {
        await page.setViewportSize({ width: 1562, height: 910 });
        await loginAs(page, 'caregiverReady');

        const accountToggle = page.locator('nav button').filter({ hasText: accounts.caregiverReady.name }).first();
        await accountToggle.click();

        const accountMenu = page.locator('#account-navigation-menu');
        const profileLink = accountMenu.getByRole('link', { name: 'My Caregiver Profile' });

        await expect(accountMenu).toBeVisible();
        await expect(profileLink).toBeVisible();
        await expect(profileLink).toHaveAttribute('href', /\/caregiver\/profile\/edit$/);

        const toggleBox = await accountToggle.boundingBox();
        const menuBox = await accountMenu.boundingBox();

        expect(toggleBox).not.toBeNull();
        expect(menuBox).not.toBeNull();
        expect(menuBox!.y).toBeGreaterThanOrEqual(toggleBox!.y + toggleBox!.height);
    });
});
