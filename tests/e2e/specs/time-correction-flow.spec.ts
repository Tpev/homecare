import { expect, Page, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

const REQUEST_TITLE = 'E2E Missed Regular Visit - Time Correction';
const SEEDED_REQUEST_ID = 3;

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
}

test.describe('Visit time correction collaboration', () => {
    test('caregiver submits, family requests a revision, and family approves the new version', async ({ page }, testInfo) => {
        const browserErrors: string[] = [];
        page.on('console', message => { if (message.type() === 'error') browserErrors.push(message.text()); });
        page.on('pageerror', error => browserErrors.push(error.message));

        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'caregiverReady');
        await page.goto(`/care-requests/${SEEDED_REQUEST_ID}/apply`);
        await expect(page.getByText(REQUEST_TITLE)).toBeVisible();
        const requestPath = new URL(page.url()).pathname;

        await expect(page.getByRole('button', { name: 'Add missed hours' })).toBeVisible();
        await page.getByRole('button', { name: 'Add missed hours' }).click();
        await expect(page.getByRole('heading', { name: 'Fix visit time' })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('caregiver-form-initial-390.png'), fullPage: true });

        await page.getByLabel('Actual start').fill('');
        await page.getByRole('button', { name: 'Review request' }).click();
        await expect(page.getByText('Enter the actual start time.')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('caregiver-validation-390.png'), fullPage: true });

        await page.getByLabel('Actual start').fill((await page.getByLabel('Actual end').inputValue()).replace('09:30', '07:30'));
        await page.getByLabel('Explain what happened').fill('I provided care from 7:30 AM until 9:30 AM but forgot to start the visit timer.');
        await page.getByLabel('I confirm these are the hours I actually provided care.').check();
        await page.getByRole('button', { name: 'Review request' }).click();
        await expect(page.getByRole('heading', { name: 'Review before sending' })).toBeVisible();
        await expect(page.getByText('Estimated earnings')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('caregiver-review-390.png'), fullPage: true });
        await page.getByRole('button', { name: 'Send to family' }).click();
        await expect(page.getByText('Time correction sent to the family for review.')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Waiting for family review' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('caregiver-pending-family-390.png'), fullPage: true });

        const requestId = requestPath.match(/care-requests\/(\d+)\/apply/)?.[1];
        expect(requestId).toBeTruthy();

        await page.setViewportSize({ width: 1440, height: 900 });
        await loginAs(page, 'family');
        await page.goto(`/family/requests/${requestId}?tab=shift`);
        await expect(page.getByRole('heading', { name: /asked you to review visit time/i })).toBeVisible();
        await expect(page.getByText('Scheduled', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('App recorded', { exact: true })).toBeVisible();
        await expect(page.getByText('Requested', { exact: true })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('family-review-1440.png'), fullPage: true });

        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await expect(page.getByRole('heading', { name: /asked you to review visit time/i })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('family-review-390.png'), fullPage: true });

        await page.setViewportSize({ width: 1440, height: 900 });
        await page.reload();

        await page.goto('/family/care');
        const planLink = page.locator('a[href*="/family/care/"]').filter({ hasText: 'E2E time correction regular care' }).first();
        await expect(planLink).toBeVisible();
        await planLink.click();
        await expect(page.getByText('regular-care visit needs attention')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-plan-banner-1440.png'), fullPage: true });

        await page.goto(`/family/requests/${requestId}?tab=shift`);
        await page.getByLabel(/Ask E2E to change this/i).fill('Please clarify that the visit started exactly at 7:30 AM.');
        await page.getByRole('button', { name: 'Send change request' }).click();
        await expect(page.getByText('Your note was sent to the caregiver.')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Changes requested' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-changes-requested-1440.png'), fullPage: true });

        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'caregiverReady');
        await page.goto(requestPath);
        await expect(page.getByText('Please clarify that the visit started exactly at 7:30 AM.')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('caregiver-changes-requested-390.png'), fullPage: true });
        await page.getByRole('button', { name: 'Update request' }).click();
        await page.getByLabel('Explain what happened').fill('I confirmed in my visit notes that I arrived and began care exactly at 7:30 AM.');
        await page.getByLabel('I confirm these are the hours I actually provided care.').check();
        await page.getByRole('button', { name: 'Review request' }).click();
        await page.getByRole('button', { name: 'Send to family' }).click();
        await expect(page.getByRole('heading', { name: 'Waiting for family review' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('caregiver-resubmitted-390.png'), fullPage: true });

        await page.setViewportSize({ width: 1440, height: 900 });
        await loginAs(page, 'family');
        await page.goto(`/family/requests/${requestId}?tab=shift`);
        await expect(page.getByText('I confirmed in my visit notes that I arrived and began care exactly at 7:30 AM.')).toBeVisible();
        await page.getByRole('button', { name: /Approve 2 hrs and pay/ }).click();
        await expect(page.getByRole('heading', { name: /Approve 2 hrs and pay/ })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-approval-confirmation-1440.png'), fullPage: true });
        await page.getByRole('button', { name: 'Confirm approval and payment' }).click();
        await expect(page.getByText('Visit time approved and updated.')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Visit time updated' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-applied-1440.png'), fullPage: true });

        await page.goto('/family/care/history');
        const historyCard = page.locator('article').filter({ hasText: REQUEST_TITLE });
        await expect(historyCard).toContainText('Visit time updated');
        await page.screenshot({ path: testInfo.outputPath('family-history-correction-1440.png'), fullPage: true });

        expect(browserErrors).toEqual([]);
    });

    test('payment and LoLo-review states remain clear on mobile and desktop', async ({ page }, testInfo) => {
        const browserErrors: string[] = [];
        page.on('console', message => { if (message.type() === 'error') browserErrors.push(message.text()); });
        page.on('pageerror', error => browserErrors.push(error.message));

        await loginAs(page, 'family');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/family/requests/4?tab=shift');
        await expect(page.getByRole('heading', { name: 'Hours approved — payment confirmation needed' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Confirm payment' })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('family-payment-action-390.png'), fullPage: true });

        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto('/family/requests/5?tab=shift');
        await expect(page.getByRole('heading', { name: 'Approved — LoLo review needed' })).toBeVisible();
        await expect(page.getByText('The approved hours and original visit evidence are saved.')).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('family-admin-required-1440.png'), fullPage: true });

        await page.goto('/family/requests/6?tab=shift');
        await expect(page.getByRole('heading', { name: 'LoLo is reviewing' })).toBeVisible();
        await expect(page.getByText('The approved hours and original visit evidence are saved.')).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('family-escalated-1440.png'), fullPage: true });

        await loginAs(page, 'admin');
        await page.goto('/admin/support/tickets/1');
        await expect(page.getByText('Caregiver + family collaboration')).toBeVisible();
        await expect(page.getByText('Original location evidence')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('admin-time-correction-evidence-1440.png'), fullPage: true });

        expect(browserErrors).toEqual([]);
    });
});
