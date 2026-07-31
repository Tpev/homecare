import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

const SEEDED_REQUEST_TITLE = 'E2E Open Request - Raleigh Morning Support';
const SEEDED_REQUEST_ID = 1;

test.describe('Invitations Flow', () => {
    test('family can search and invite inside a request, then caregiver can accept', async ({ page }, testInfo) => {
        await loginAs(page, 'family');
        await page.goto(`/family/requests/${SEEDED_REQUEST_ID}?tab=applicants`);

        await expect(page.getByRole('heading', { name: SEEDED_REQUEST_TITLE })).toBeVisible();
        await page.getByRole('button', { name: /Search and invite|Invite someone you know/ }).click();

        const dialog = page.getByRole('dialog', { name: 'Invite a caregiver to this request' });
        await expect(dialog).toBeVisible();
        await expect(dialog.getByText(SEEDED_REQUEST_TITLE)).toBeVisible();
        await dialog.getByLabel('Search by caregiver name').fill('No Such Caregiver');
        await expect(dialog.getByText('No active caregivers found')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('invitation-empty-state.png'), fullPage: false });
        await dialog.getByLabel('Search by caregiver name').fill('E2E Ready');
        await expect(dialog.getByRole('heading', { name: 'E2E Ready Caregiver' })).toBeVisible();
        await dialog.getByRole('button', { name: 'Invite E2E' }).click();
        await page.getByLabel('Invitation message (optional)').fill('We would like to invite you for this morning support shift.');
        await page.getByRole('button', { name: 'Send invitation' }).click();
        await expect(dialog.getByText('Invitation sent to E2E Ready Caregiver.')).toBeVisible();
        await expect(dialog.getByText('Invitation sent', { exact: true }).first()).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('invitation-success-pending.png'), fullPage: false });
        await expect(page).toHaveURL(new RegExp(`/family/requests/${SEEDED_REQUEST_ID}`));
        await dialog.getByRole('button', { name: 'Close' }).click();
        const invitedSection = page.getByRole('region', { name: 'People you invited' });
        await expect(invitedSection).toBeVisible();
        await expect(invitedSection.getByText('E2E Ready Caregiver')).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto('/caregiver/invitations');

        await expect(page.getByText(SEEDED_REQUEST_TITLE)).toBeVisible();
        await page.getByRole('button', { name: 'Accept' }).first().click();
        await expect(page).toHaveURL(new RegExp(`/care-requests/${SEEDED_REQUEST_ID}/apply$`));
        await expect(page.getByText('Waiting for family')).toBeVisible();
        await page.getByRole('button', { name: 'Open chat' }).click();
        await expect(page).toHaveURL(/\/messages\/\d+$/);

        await loginAs(page, 'family');
        await page.goto(`/family/requests/${SEEDED_REQUEST_ID}?tab=applicants`);
        await page.getByRole('button', { name: /Search and invite|Invite someone you know/ }).click();
        const repliedDialog = page.getByRole('dialog', { name: 'Invite a caregiver to this request' });
        await repliedDialog.getByLabel('Search by caregiver name').fill('E2E Ready');
        await expect(repliedDialog.getByText('Already replied', { exact: true }).first()).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('invitation-accepted-replied.png'), fullPage: false });
    });
});
