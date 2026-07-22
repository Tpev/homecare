import { expect, Page, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
}

test.describe('Regular Care Experience', () => {
    test('family creation and management stay clear across desktop, tablet, and mobile', async ({ page }, testInfo) => {
        const errors: string[] = [];
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        page.on('pageerror', error => errors.push(error.message));

        await page.setViewportSize({ width: 1440, height: 1000 });
        await loginAs(page, 'family');
        await page.goto('/family/requests/create');
        await page.getByText('Regular visits', { exact: true }).click();
        await expect(page.getByText('Which days each week?')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('recurring-request-creation-1440.png'), fullPage: true });

        await page.goto('/family/care');
        const planLink = page.locator('a[href*="/family/care/"]').filter({ hasText: 'Regular care for E2E Recipient' }).first();
        await expect(planLink).toBeVisible();
        await planLink.click();
        await expect(page.getByRole('heading', { name: /Regular care with E2E Ready Caregiver/i })).toBeVisible();
        await expect(page.getByText('Next visit', { exact: true })).toBeVisible();
        await expect(page.getByText('Payment needs attention', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Later visits' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-regular-care-1440.png'), fullPage: true });

        await page.getByRole('button', { name: 'Add an extra visit' }).click();
        await expect(page.getByRole('heading', { name: 'Ask for one extra visit' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-add-visit-1440.png'), fullPage: true });
        await page.getByRole('button', { name: 'Cancel' }).click();
        await page.getByRole('button', { name: 'Change future schedule' }).click();
        await expect(page.getByRole('heading', { name: 'Change future schedule' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('family-change-schedule-1440.png'), fullPage: true });

        for (const viewport of [{ width: 768, height: 900 }, { width: 375, height: 812 }]) {
            await page.setViewportSize(viewport);
            await page.reload();
            await expect(page.getByRole('heading', { name: /Regular care with E2E Ready Caregiver/i })).toBeVisible();
            await expectNoHorizontalOverflow(page);
            await page.screenshot({ path: testInfo.outputPath(`family-regular-care-${viewport.width}.png`), fullPage: true });
        }

        expect(errors).toEqual([]);
    });

    test('caregiver sees direct offer and every real visit', async ({ page }, testInfo) => {
        const errors: string[] = [];
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        page.on('pageerror', error => errors.push(error.message));

        await page.setViewportSize({ width: 1440, height: 1000 });
        await loginAs(page, 'caregiverReady');
        await page.goto('/caregiver/regular-clients');
        await expect(page.getByText('Evening companionship for E2E Recipient')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Accept schedule' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('caregiver-regular-offer-1440.png'), fullPage: true });

        await page.goto('/caregiver/shifts');
        await expect(page.getByText('Regular care').first()).toBeVisible();
        await expect(page.getByText('123 E2E Main St').first()).toBeVisible();
        await expect(page.getByText(/Payment protected|Family action needed|Payment checked before visit/).first()).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('caregiver-upcoming-visits-1440.png'), fullPage: true });

        await page.setViewportSize({ width: 375, height: 812 });
        await page.reload();
        await expectNoHorizontalOverflow(page);
        await page.screenshot({ path: testInfo.outputPath('caregiver-upcoming-visits-375.png'), fullPage: true });
        expect(errors).toEqual([]);
    });
});
