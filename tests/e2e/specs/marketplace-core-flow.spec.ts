import { expect, test, type Page } from '@playwright/test';
import { loginAs } from '../helpers/auth';

const SEEDED_REQUEST_TITLE = 'E2E Open Request - Raleigh Morning Support';
const SEEDED_REQUEST_ID = 1;
const FAMILY_MESSAGE = 'Hi, please confirm you can arrive 10 minutes early.';
const CAREGIVER_MESSAGE = 'Confirmed. I will arrive 10 minutes early.';

async function openSeededRequestAsCaregiver(page: Page): Promise<void> {
    await page.goto(`/care-requests/${SEEDED_REQUEST_ID}/apply`);
    await expect(page.getByText(SEEDED_REQUEST_TITLE)).toBeVisible();
}

async function openSeededRequestAsFamily(page: Page): Promise<void> {
    await page.goto(`/family/requests/${SEEDED_REQUEST_ID}`);
    await expect(page.getByText(SEEDED_REQUEST_TITLE)).toBeVisible();
}

test.describe('Marketplace Core Flow', () => {
    test('apply, hire, chat, run shift, and review', async ({ page }) => {
        await loginAs(page, 'caregiverReady');
        await openSeededRequestAsCaregiver(page);

        await page.getByRole('button', { name: 'I can do this visit' }).click();
        await expect(page.getByText('Application sent to family.')).toBeVisible();

        await loginAs(page, 'family');
        await openSeededRequestAsFamily(page);

        await page.getByRole('button', { name: /Caregivers|Review caregivers/i }).first().click();
        await page.getByRole('button', { name: /^Hire /i }).first().click();
        await expect(page.getByText(/caregiver hired/i)).toBeVisible();

        await page.getByRole('button', { name: 'Message caregiver' }).click();
        await expect(page).toHaveURL(/\/messages\/\d+$/);
        await page.getByPlaceholder('Type your message...').fill(FAMILY_MESSAGE);
        await page.getByRole('button', { name: 'Send' }).click();
        await expect(page.getByText(FAMILY_MESSAGE)).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto('/messages');
        await expect(page.getByText(FAMILY_MESSAGE)).toBeVisible();
        await page.getByPlaceholder('Type your message...').fill(CAREGIVER_MESSAGE);
        await page.getByRole('button', { name: 'Send' }).click();
        await expect(page.getByText(CAREGIVER_MESSAGE)).toBeVisible();

        await page.goto(`/care-requests/${SEEDED_REQUEST_ID}/apply`);
        await expect(page.getByText(SEEDED_REQUEST_TITLE)).toBeVisible();
        await page.context().grantPermissions(['geolocation']);
        await page.context().setGeolocation({ latitude: 35.7796, longitude: -78.6382 });
        await page.getByRole('button', { name: 'Visit' }).click();
        await page.getByRole('button', { name: 'Accept agreement' }).click();
        await page.getByRole('button', { name: 'Start visit' }).click();
        await expect(page.getByText(/Visit started/i)).toBeVisible();
        await page.getByRole('button', { name: 'End visit' }).click();
        await expect(page.getByText('Visit completed. Review your recap below.')).toBeVisible();

        await loginAs(page, 'family');
        await openSeededRequestAsFamily(page);
        await page.getByRole('button', { name: 'Visit' }).click();
        await page.getByRole('button', { name: /Approve hours and pay/ }).click();
        await expect(page.getByText('Timesheet confirmed.')).toBeVisible();

        await page.getByRole('button', { name: 'Rate 5 out of 5' }).click();
        await page.getByLabel('Review comment').fill('Great communication and very reliable support.');
        await page.getByRole('button', { name: 'Submit review' }).click();
        await expect(page.getByText('Review submitted.', { exact: true }).first()).toBeVisible();
    });
});
