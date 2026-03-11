import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { selectStyledSingle } from '../helpers/tallstack';

const SEEDED_REQUEST_TITLE = 'E2E Open Request - Raleigh Morning Support';

test.describe('Invitations Flow', () => {
    test('family can invite a caregiver and caregiver can accept', async ({ page }) => {
        await loginAs(page, 'family');
        await page.goto('/caregivers/e2e-ready-caregiver');

        await expect(page.getByRole('heading', { name: 'E2E Ready Caregiver' })).toBeVisible();
        await page.getByRole('button', { name: 'Invite to request' }).click();
        await selectStyledSingle(page, 'Select request', SEEDED_REQUEST_TITLE);
        await page.getByLabel('Invitation message (optional)').fill('We would like to invite you for this morning support shift.');
        await page.getByRole('button', { name: 'Send invitation' }).click();
        await expect(page.getByText('Invitation sent successfully.')).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto('/caregiver/invitations');

        await expect(page.getByText(SEEDED_REQUEST_TITLE)).toBeVisible();
        await page.getByRole('button', { name: 'Accept' }).first().click();
        await expect(page).toHaveURL(/\/messages\/\d+$/);
    });
});
