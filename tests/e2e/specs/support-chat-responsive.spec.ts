import { expect, test, type Page } from '@playwright/test';
import { loginAs } from '../helpers/auth';

const phoneSizes = [
    { width: 320, height: 700 },
    { width: 360, height: 800 },
    { width: 375, height: 667 },
    { width: 390, height: 844 },
    { width: 430, height: 932 },
];

async function expectNoHorizontalOverflow(page: Page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
}

test.describe('Mobile support chat', () => {
    test('keeps the launcher and sheet usable across required phone sizes', async ({ page }, testInfo) => {
        await page.setViewportSize(phoneSizes[0]);
        await loginAs(page, 'caregiverMobileVisual');
        await page.goto('/profile');

        for (const size of phoneSizes) {
            await page.setViewportSize(size);
            const launcher = page.getByTestId('support-chat-launcher');
            await expect(launcher).toBeVisible();
            const launcherBox = await launcher.boundingBox();
            expect(launcherBox?.width ?? 0).toBeGreaterThanOrEqual(52);
            expect(launcherBox?.height ?? 0).toBeGreaterThanOrEqual(52);
            expect((launcherBox?.x ?? 0) + (launcherBox?.width ?? 0)).toBeLessThanOrEqual(size.width);
            await expectNoHorizontalOverflow(page);

            await launcher.click();
            const panel = page.getByTestId('support-chat-panel');
            await expect(panel).toBeVisible();
            await expect(panel).toHaveCSS('opacity', '1');
            await expect(page.getByRole('button', { name: 'Minimize support chat' })).toHaveAccessibleName('Minimize support chat');
            const panelBox = await panel.boundingBox();
            expect(panelBox?.x ?? -1).toBeGreaterThanOrEqual(0);
            expect((panelBox?.x ?? 0) + (panelBox?.width ?? 0)).toBeLessThanOrEqual(size.width + 1);
            expect(panelBox?.height ?? size.height + 1).toBeLessThanOrEqual(size.height);

            const composer = page.getByLabel('Message LoLo Support');
            const send = page.getByTestId('support-chat-send');
            await expect(composer).toHaveAccessibleName('Message LoLo Support');
            await expect(send).toHaveAccessibleName('Send message to LoLo Support');
            await expect(composer).toBeVisible();
            await expect(send).toBeVisible();
            expect(await composer.evaluate((element) => Number.parseFloat(getComputedStyle(element).fontSize))).toBeGreaterThanOrEqual(16);
            expect((await send.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(44);
            await expectNoHorizontalOverflow(page);
            await page.screenshot({
                path: testInfo.outputPath(`support-chat-${size.width}x${size.height}.png`),
            });

            await page.getByRole('button', { name: 'Minimize support chat' }).click();
            await expect(panel).toBeHidden();
        }
    });

    test('preserves a draft through minimize, navigation, rotation, and browser-back dismissal', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'caregiverReady');
        await page.goto('/profile');

        const launcher = page.getByTestId('support-chat-launcher');
        await launcher.focus();
        await expect(launcher).toBeFocused();
        await page.keyboard.press('Enter');
        const panel = page.getByTestId('support-chat-panel');
        const minimize = page.getByRole('button', { name: 'Minimize support chat' });
        await minimize.focus();
        await page.keyboard.press('Shift+Tab');
        await expect(panel.getByRole('link', { name: 'Open Support Center' })).toBeFocused();
        const composer = page.getByLabel('Message LoLo Support');
        const draft = 'Draft retained across mobile navigation and rotation.';
        await composer.fill(draft);
        await page.getByRole('button', { name: 'Minimize support chat' }).click();

        await page.goto('/caregiver/work-inbox');
        await page.getByTestId('support-chat-launcher').click();
        await expect(page.getByLabel('Message LoLo Support')).toHaveValue(draft);

        await page.setViewportSize({ width: 844, height: 390 });
        await expect(page.getByLabel('Message LoLo Support')).toHaveValue(draft);
        await expect(page.getByTestId('support-chat-send')).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await page.goBack();
        await expect(page.getByTestId('support-chat-panel')).toBeHidden();
        await expect(page.getByTestId('support-chat-launcher')).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        await page.getByTestId('support-chat-launcher').click();
        await expect(page.getByLabel('Message LoLo Support')).toHaveValue(draft);
    });

    test('keeps failed offline messages retryable and sends on reconnect', async ({ page, context }) => {
        await page.setViewportSize({ width: 360, height: 800 });
        await loginAs(page, 'caregiverReady');
        await page.goto('/profile');
        await page.getByTestId('support-chat-launcher').click();

        const message = `Offline retry ${Date.now()} ${'LongUnbrokenSupportMessage'.repeat(8)}`;
        await context.setOffline(true);
        await page.getByLabel('Message LoLo Support').fill(message);
        await page.getByTestId('support-chat-send').click();
        await expect(page.getByText("You're offline. We'll send when you reconnect.").first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Try sending this support message again' })).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await context.setOffline(false);
        await expect(page.getByTestId('support-chat-panel').getByText(message, { exact: true })).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('support-chat-pending-message')).toHaveCount(0);
        await expectNoHorizontalOverflow(page);
    });

    test('supports long history, resolved state, large text, and a new conversation', async ({ page }) => {
        await page.setViewportSize({ width: 430, height: 932 });
        await loginAs(page, 'caregiverMarketplace');
        await page.goto('/profile');
        await page.getByTestId('support-chat-launcher').click();

        const panel = page.getByTestId('support-chat-panel');
        await expect(panel.getByText('This conversation is resolved. Replying will reopen it.')).toBeVisible();
        await expect(panel.getByRole('button', { name: 'Load earlier messages' })).toBeVisible();
        await panel.getByRole('button', { name: 'Load earlier messages' }).click();
        await expect(panel.getByText('E2E oldest mobile support message')).toBeVisible();

        await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
        await expectNoHorizontalOverflow(page);
        await expect(panel.getByRole('button', { name: 'Start a new conversation' })).toBeVisible();

        await page.addStyleTag({ content: 'html { font-size: 16px !important; }' });
        await panel.getByRole('button', { name: 'Start a new conversation' }).click();
        await expect(panel.getByText(/How can we help/)).toBeVisible();
        await expect(page.getByLabel('Message LoLo Support')).toBeVisible();
    });
});
