import { expect, test } from '@playwright/test';

const authPages = [
    {
        path: '/login',
        heading: 'Welcome back',
        story: 'Your care, schedule, and conversations—together.',
        primaryAction: 'Log in',
    },
    {
        path: '/register',
        heading: 'Create your family account',
        story: 'Care coordination that feels lighter.',
        primaryAction: 'Create family account',
    },
    {
        path: '/caregiver/register',
        heading: 'Create your caregiver account',
        story: 'Build trusted relationships. Work on your terms.',
        primaryAction: 'Create caregiver account',
    },
    {
        path: '/forgot-password',
        heading: 'Reset your password',
        story: 'Get back to the people and plans that matter.',
        primaryAction: 'Send reset link',
    },
] as const;

test.describe('Guest authentication pages', () => {
    test('share an accessible branded layout on desktop and mobile', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        for (const authPage of authPages) {
            await page.setViewportSize({ width: 1440, height: 1000 });
            await page.goto(authPage.path);

            const storyPanel = page.locator('.auth-story-panel');
            const card = page.locator('.auth-card');
            const heading = card.getByRole('heading', { name: authPage.heading });
            const primaryAction = card.getByRole('button', { name: authPage.primaryAction });

            await expect(storyPanel).toBeVisible();
            await expect(storyPanel.getByRole('heading', { name: authPage.story })).toBeVisible();
            await expect(heading).toBeVisible();
            await expect(primaryAction).toBeVisible();

            expect(await storyPanel.getByRole('heading', { name: authPage.story }).evaluate((element) => getComputedStyle(element).color)).toBe('rgb(255, 247, 234)');
            expect(await heading.evaluate((element) => getComputedStyle(element).color)).toBe('rgb(23, 63, 53)');
            expect(await primaryAction.evaluate((element) => getComputedStyle(element).backgroundColor)).toBe('rgb(23, 63, 53)');

            const firstTextInput = card.locator("input:not([type='checkbox']):not([type='radio'])").first();
            await firstTextInput.focus();
            const fieldWrapper = firstTextInput.locator('xpath=..');
            expect(await fieldWrapper.evaluate((element) => getComputedStyle(element).boxShadow)).toContain('rgb(60, 113, 99)');

            expect(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)).toBe(false);

            await page.setViewportSize({ width: 390, height: 844 });
            await expect(storyPanel).toBeHidden();
            await expect(page.locator('.auth-mobile-intro')).toBeVisible();
            await expect(heading).toBeVisible();
            await expect(primaryAction).toBeVisible();

            const buttonBox = await primaryAction.boundingBox();
            expect(buttonBox?.height ?? 0).toBeGreaterThanOrEqual(48);
            expect(await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)).toBe(false);
        }

        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    });
});
