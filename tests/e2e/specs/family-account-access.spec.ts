import { expect, test } from '@playwright/test';
import { accounts, loginAs } from '../helpers/auth';

test.describe('Family account access', () => {
    test('owner and member get a simple shared household experience', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 1440, height: 1000 });
        await loginAs(page, 'family');
        await page.goto('/family/access');

        await expect(page.getByRole('heading', { name: 'People helping manage care' })).toBeVisible();
        await expect(page.getByText('E2E Family Member')).toBeVisible();
        await expect(page.getByText('family.invited.e2e@example.com')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Invite someone' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-access-owner-desktop.png'), fullPage: true });

        await page.getByRole('button', { name: 'Invite someone' }).click();
        await expect(page.getByRole('heading', { name: 'Invite someone' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-invite-panel-desktop.png'), fullPage: true });
        await page.getByRole('button', { name: 'Cancel' }).click();
        await page.getByRole('button', { name: 'Remove access' }).first().click();
        await expect(page.getByRole('heading', { name: /Remove E2E Family Member's access/ })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-remove-confirmation-desktop.png'), fullPage: true });
        await page.getByRole('button', { name: 'Keep access' }).click();

        await loginAs(page, 'familyMember');
        await page.goto('/dashboard');
        await expect(page.getByRole('heading', { name: /You have 1 upcoming visit/ })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-member-dashboard-desktop.png'), fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/family/requests');
        await expect(page.getByRole('heading', { name: 'E2E Invitation Visual States' })).toBeVisible();

        await page.goto('/family/access');
        await expect(page.getByText('You are helping manage care with E2E Family.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Leave this family account' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Invite someone' })).toHaveCount(0);
        await page.getByRole('button', { name: 'Leave this family account' }).click();
        await expect(page.getByRole('heading', { name: 'Leave this family account?' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-leave-confirmation-mobile.png'), fullPage: true });
        await page.getByRole('button', { name: 'Keep access' }).click();

        await page.goto('/family/billing');
        await expect(page.getByRole('button', { name: /Add card|Update card/ })).toBeVisible();
    });

    test('new-user invitation is private and pre-binds the invited email', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`/family/invitations/${'a'.repeat(64)}`);

        await expect(page.getByRole('heading', { name: 'Create your login' })).toBeVisible();
        await expect(page.getByLabel('Email')).toHaveValue('family.invited.e2e@example.com');
        await expect(page.getByLabel('Email')).toHaveAttribute('readonly', '');
        await page.screenshot({ path: testInfo.outputPath('family-invitation-registration-mobile.png'), fullPage: true });
    });

    test('eligible existing relative can accept while removed access stays blocked', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto(`/family/invitations/${'b'.repeat(64)}`);
        await expect(page).toHaveURL(/\/login$/);
        await page.getByLabel('Email').fill(accounts.familyEligible.email);
        await page.getByLabel('Password').fill(accounts.familyEligible.password);
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page.getByRole('heading', { name: 'Help E2E manage care' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-invitation-acceptance-desktop.png'), fullPage: true });
        await page.getByRole('button', { name: 'Join family account' }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await page.goto('/family/requests');
        await expect(page.getByRole('heading', { name: 'E2E Invitation Visual States' })).toBeVisible();

        await loginAs(page, 'familyRemoved');
        await page.goto('/family/access');
        await expect(page.getByRole('heading', { name: 'Your access to this family account has ended.' })).toBeVisible();
        await expect(page.getByText('E2E Open Request - Raleigh Morning Support')).toHaveCount(0);
        await page.screenshot({ path: testInfo.outputPath('family-access-ended-desktop.png'), fullPage: true });
    });
});
