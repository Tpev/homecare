import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

const UNDER_REVIEW_CAREGIVER_LABEL = 'E2E Under Review Caregiver (caregiver.review.e2e@example.com)';

test.describe('Admin Caregiver Moderation', () => {
    test('admin can approve under-review caregiver and view moderation logs', async ({ page }) => {
        await loginAs(page, 'admin');
        await page.goto('/admin/caregivers/reviews');

        const reviewCard = page
            .getByText(UNDER_REVIEW_CAREGIVER_LABEL, { exact: true })
            .locator('xpath=ancestor::div[contains(@class, "flex w-full flex-col")][1]');
        await expect(reviewCard).toBeVisible();
        await reviewCard.getByRole('button', { name: 'Approve' }).click();

        await expect(page.getByText('Trust Badge Management (Active Caregivers)')).toBeVisible();
        await expect(page.getByText('E2E Under Review Caregiver')).toBeVisible();

        await page.goto('/admin/caregivers/moderation-logs');
        await expect(page.getByText('Caregiver Moderation Logs')).toBeVisible();
        await expect(page.getByText(/APPROVED/)).toBeVisible();
    });
});
