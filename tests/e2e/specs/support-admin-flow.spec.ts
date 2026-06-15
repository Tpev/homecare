import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { createOneTimeRequest } from '../helpers/family-request';

test.describe('Support + Admin Ticket Flow', () => {
    test('caregiver opens support ticket and admin resolves it', async ({ page }) => {
        const ticketSubject = `E2E Support Ticket ${Date.now()}`;

        await loginAs(page, 'family');
        const { requestId, requestTitle } = await createOneTimeRequest(page);
        await expect(page.getByRole('heading', { name: requestTitle })).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto(`/care-requests/${requestId}/apply`);
        await expect(page.getByText(requestTitle)).toBeVisible();
        await page.getByRole('button', { name: 'I can do this visit' }).click();
        await expect(page.getByText('Application sent to family.')).toBeVisible();

        await loginAs(page, 'family');
        await page.goto(`/family/requests/${requestId}`);
        await page.getByRole('button', { name: /Caregivers|Review caregivers/i }).first().click();
        await page.getByRole('button', { name: /^Hire /i }).first().click();
        await expect(page.getByText(/caregiver hired/i)).toBeVisible();

        await loginAs(page, 'caregiverReady');
        await page.goto(`/care-requests/${requestId}/apply`);
        await page.context().grantPermissions(['geolocation']);
        await page.context().setGeolocation({ latitude: 35.7796, longitude: -78.6382 });
        await page.getByRole('button', { name: 'Visit' }).click();
        await page.getByRole('button', { name: 'Accept agreement' }).click();
        await page.getByRole('button', { name: 'Start visit' }).click();
        await expect(page.getByText(/Visit started/i)).toBeVisible();
        await page.getByRole('button', { name: 'End visit' }).click();
        await expect(page.getByText('Visit completed. Review your recap below.')).toBeVisible();

        await loginAs(page, 'family');
        await page.goto(`/family/requests/${requestId}`);
        await page.getByRole('button', { name: 'Visit' }).click();
        await page.getByRole('button', { name: /Approve hours and pay/ }).click();
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
