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

        await page.getByRole('button', { name: /Application/i }).first().click();
        await page.getByLabel('Your proposed hourly rate ($)').fill('31');
        await page.getByLabel('Cover note').fill(
            'I am available for this schedule, have strong non-medical experience, and can follow household routines consistently.'
        );
        await page.getByRole('button', { name: /Send application|Update application/i }).click();
        await expect(page.getByText('Application sent to family.')).toBeVisible();

        await loginAs(page, 'family');
        await openSeededRequestAsFamily(page);

        await page.getByRole('button', { name: /Review applicants|Applicants/i }).first().click();
        await page.getByRole('button', { name: 'Hire caregiver' }).click();
        await expect(page.getByText(/caregiver hired/i)).toBeVisible();

        await page.getByRole('link', { name: 'Open chat' }).first().click();
        await expect(page).toHaveURL(/\/messages\/\d+$/);
        await page.getByPlaceholder('Type your message...').fill(FAMILY_MESSAGE);
        await page.getByRole('button', { name: 'Send message' }).click();
        await expect(page.getByText(FAMILY_MESSAGE)).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto('/messages');
        await expect(page.getByText(FAMILY_MESSAGE)).toBeVisible();
        await page.getByPlaceholder('Type your message...').fill(CAREGIVER_MESSAGE);
        await page.getByRole('button', { name: 'Send message' }).click();
        await expect(page.getByText(CAREGIVER_MESSAGE)).toBeVisible();

        await page.goto(`/care-requests/${SEEDED_REQUEST_ID}/apply`);
        await expect(page.getByText(SEEDED_REQUEST_TITLE)).toBeVisible();
        await page.getByRole('button', { name: 'Shift' }).click();
        await page.getByRole('button', { name: 'Accept agreement' }).click();
        await page.getByRole('button', { name: 'Check in / Start' }).click();
        await expect(page.getByText(/Shift marked in progress/i)).toBeVisible();
        await page.getByRole('button', { name: 'Check out / Submit timesheet' }).click();
        await expect(page.getByText('Shift marked completed and timesheet submitted.')).toBeVisible();

        await loginAs(page, 'family');
        await openSeededRequestAsFamily(page);
        await page.getByRole('button', { name: 'Shift' }).click();
        await page.getByRole('button', { name: 'Confirm timesheet' }).click();
        await expect(page.getByText('Timesheet confirmed.')).toBeVisible();

        await page.getByLabel('Rating (1-5)').fill('5');
        await page.getByLabel('Review comment').fill('Great communication and very reliable support.');
        await page.getByRole('button', { name: 'Submit review' }).click();
        await expect(page.getByText('Review submitted.', { exact: true }).first()).toBeVisible();
    });
});
