import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

const REQUEST_ID = 2;

test.describe('Invitation responsive UI', () => {
    test('invitation panel fits desktop and mobile with accessible controls', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await loginAs(page, 'family');
        await page.goto(`/family/requests/${REQUEST_ID}?tab=applicants`);
        await page.getByRole('button', { name: 'Invite someone you know' }).click();

        let dialog = page.getByRole('dialog', { name: 'Invite a caregiver to this request' });
        await expect(dialog).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('invitation-desktop-initial.png'), fullPage: false });

        await dialog.getByLabel('Search by caregiver name').fill('Visual');
        await expect(dialog.getByRole('heading', { name: 'Visual Available Caregiver' })).toBeVisible();
        await expect(dialog.getByText('Not accepting new clients', { exact: true }).first()).toBeVisible();
        await expect(dialog.getByText('Already replied', { exact: true }).first()).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('invitation-desktop-results.png'), fullPage: false });
        await expectNoHorizontalOverflow(page, dialog);
        await expectTouchTargets(dialog);

        await dialog.getByRole('button', { name: 'Close' }).click();
        await page.setViewportSize({ width: 390, height: 844 });
        await page.getByRole('button', { name: 'Invite someone you know' }).click();
        dialog = page.getByRole('dialog', { name: 'Invite a caregiver to this request' });
        await expect(dialog.getByRole('heading', { name: 'Visual Available Caregiver' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('invitation-mobile-results.png'), fullPage: false });
        await expectNoHorizontalOverflow(page, dialog);
        await expectTouchTargets(dialog);

        const unavailableStatus = dialog.getByText('Not accepting new clients', { exact: true }).first();
        await unavailableStatus.scrollIntoViewIfNeeded();
        await page.screenshot({ path: testInfo.outputPath('invitation-mobile-unavailable.png'), fullPage: false });
        const repliedStatus = dialog.getByText('Already replied', { exact: true }).first();
        await repliedStatus.scrollIntoViewIfNeeded();
        await page.screenshot({ path: testInfo.outputPath('invitation-mobile-replied.png'), fullPage: false });

        await dialog.getByRole('button', { name: 'Invite Visual' }).click();
        await expect(dialog.getByLabel('Invitation message (optional)')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('invitation-mobile-confirmation.png'), fullPage: false });
        await expectNoHorizontalOverflow(page, dialog);
        await expectTouchTargets(dialog);
    });
});

async function expectNoHorizontalOverflow(page: import('@playwright/test').Page, dialog: import('@playwright/test').Locator): Promise<void> {
    const pageWidth = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
    expect(pageWidth.scroll).toBeLessThanOrEqual(pageWidth.client);

    const dialogWidth = await dialog.evaluate((element) => ({ scroll: element.scrollWidth, client: element.clientWidth }));
    expect(dialogWidth.scroll).toBeLessThanOrEqual(dialogWidth.client);
}

async function expectTouchTargets(dialog: import('@playwright/test').Locator): Promise<void> {
    const tooSmall = await dialog.locator('button, a').evaluateAll((elements) => elements
        .filter((element) => {
            const rect = element.getBoundingClientRect();
            const style = window.getComputedStyle(element);
            return style.visibility !== 'hidden' && style.display !== 'none' && rect.width > 0 && rect.height > 0 && rect.height < 44;
        })
        .map((element) => ({ text: element.textContent?.trim(), height: element.getBoundingClientRect().height })));

    expect(tooSmall).toEqual([]);
}
