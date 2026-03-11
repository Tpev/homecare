import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { createOneTimeRequest } from '../helpers/family-request';

test.describe('Support + Admin Ticket Flow', () => {
    test('caregiver opens support ticket and admin resolves it', async ({ page }) => {
        const requestTitle = `E2E Support Flow ${Date.now()}`;
        const ticketSubject = `E2E Support Ticket ${Date.now()}`;

        await loginAs(page, 'family');
        const requestId = await createOneTimeRequest(page, requestTitle);
        await expect(page.getByRole('heading', { name: requestTitle })).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto(`/care-requests/${requestId}/apply`);
        await expect(page.getByText(requestTitle)).toBeVisible();
        await page.getByRole('button', { name: /Application/i }).first().click();
        await page.getByLabel('Your proposed hourly rate ($)').fill('30');
        await page.getByLabel('Cover note').fill('I can support this one-time shift and follow all household instructions with clear communication.');
        await page.getByRole('button', { name: /Send application|Update application/i }).click();
        await expect(page.getByText('Application sent to family.')).toBeVisible();

        await loginAs(page, 'family');
        await page.goto(`/family/requests/${requestId}`);
        await page.getByRole('button', { name: /Applicants/i }).first().click();
        await page.getByRole('button', { name: 'Hire caregiver' }).click();
        await expect(page.getByText(/caregiver hired/i)).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto(`/care-requests/${requestId}/apply`);
        await page.getByRole('button', { name: 'Shift' }).click();
        await page.getByRole('button', { name: 'Accept agreement' }).click();
        await page.getByRole('button', { name: 'Check in / Start' }).click();
        await expect(page.getByText(/Shift marked in progress/i)).toBeVisible();
        await page.getByRole('button', { name: 'Check out / Submit timesheet' }).click();
        await expect(page.getByText('Shift marked completed and timesheet submitted.')).toBeVisible();

        await loginAs(page, 'family');
        await page.goto(`/family/requests/${requestId}`);
        await page.getByRole('button', { name: 'Shift' }).click();
        await page.getByRole('button', { name: 'Confirm timesheet' }).click();
        await expect(page.getByText('Timesheet confirmed.')).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto(`/care-requests/${requestId}/apply`);
        await page.getByRole('button', { name: 'Support' }).click();
        await page.locator('summary', { hasText: 'Support ticket' }).click();
        await page.getByLabel('Subject').fill(ticketSubject);
        await page.getByLabel('Describe issue').fill('Need clarification about expected arrival window for upcoming repeat visits.');
        await page.getByRole('button', { name: 'Create support ticket' }).click();
        await expect(page.getByText('Support ticket created.')).toBeVisible();

        await loginAs(page, 'admin');
        await page.goto('/admin/support/tickets');
        const ticketCard = page.locator('div').filter({ hasText: ticketSubject }).first();
        await expect(ticketCard).toBeVisible();
        await ticketCard.getByPlaceholder('Admin note').fill('E2E reviewed and acknowledged.');
        await ticketCard.getByRole('button', { name: 'Resolve' }).click();
        await expect(page.getByText('Ticket updated.')).toBeVisible();
    });
});
