import { expect, test, type Page } from '@playwright/test';
import { loginAs } from '../helpers/auth';

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const width = await page.evaluate(() => ({
        scroll: document.documentElement.scrollWidth,
        client: document.documentElement.clientWidth,
    }));

    expect(width.scroll).toBeLessThanOrEqual(width.client);
}

test.describe('Caregiver background surfaces', () => {
    test('profile editor and family-facing views remain responsive', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'caregiverReady');
        await page.goto('/caregiver/profile/edit');

        await expect(page.getByText('Experience & training', { exact: true })).toBeVisible();
        await expect(page.getByLabel('CPR')).toBeChecked();
        await expect(page.getByText('Credential reported by caregiver', { exact: true })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('profile-editor-mobile.png'), fullPage: true });

        await loginAs(page, 'family');
        await page.goto('/caregivers/e2e-ready-caregiver');

        await expect(page.getByRole('heading', { name: 'Care experience' })).toBeVisible();
        await expect(page.getByText('Self-reported by caregiver', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Credentials & training' })).toBeVisible();
        await expect(page.getByText('Credential reported by caregiver', { exact: true })).toBeVisible();
        await expect(page.getByText('Expired', { exact: true })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('public-profile-mobile.png'), fullPage: true });

        await page.setViewportSize({ width: 768, height: 1024 });
        await page.goto('/caregivers/search');
        const caregiverCard = page.locator('article').filter({ hasText: 'E2E Ready Caregiver' });
        await expect(caregiverCard).toBeVisible();
        await expect(caregiverCard.getByText("Memory loss, dementia, or Alzheimer's support", { exact: true })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('caregiver-search-tablet.png'), fullPage: true });
    });

    test('admin review presents the safe background summary on desktop', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 1440, height: 1000 });
        await loginAs(page, 'admin');
        await page.goto('/admin/caregivers/reviews');

        const reviewCard = page
            .getByText('E2E Background Review Caregiver (caregiver.background.review.e2e@example.com)', { exact: true })
            .locator('xpath=ancestor::div[contains(@class, "flex w-full flex-col")][1]');
        await expect(reviewCard).toBeVisible();
        await expect(reviewCard.getByRole('heading', { name: 'Care experience' })).toBeVisible();
        await expect(reviewCard.getByRole('heading', { name: 'Credentials & training' })).toBeVisible();
        await expect(reviewCard.getByText('American Red Cross')).toBeVisible();
        await expect(reviewCard.getByText('No evidence uploaded', { exact: true })).toBeVisible();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('admin-background-review-desktop.png'), fullPage: true });
    });
});
