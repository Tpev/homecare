import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

test.describe('Caregiver experience and training', () => {
    test('onboarding step is clear and usable on mobile and desktop', async ({ page }, testInfo) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'caregiverNew');
        await page.goto('/caregiver/onboarding?step=2');

        await expect(page.getByText('Step 2 of 5')).toBeVisible();
        await expect(page.getByRole('group', { name: /Which care needs have you supported/ })).toBeVisible();
        await expect(page.getByRole('group', { name: /Which current certifications/ })).toBeVisible();
        await expect(page.getByText('Certifications do not expand the non-medical services offered through LoLo Care.')).toBeVisible();

        const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(mobileOverflow).toBe(false);
        await page.screenshot({ path: testInfo.outputPath('care-background-mobile.png'), fullPage: true });

        await page.setViewportSize({ width: 768, height: 1024 });
        const tabletOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(tabletOverflow).toBe(false);
        await page.screenshot({ path: testInfo.outputPath('care-background-tablet.png'), fullPage: true });

        await page.setViewportSize({ width: 1440, height: 1000 });
        await expect(page.getByLabel('CPR')).toBeVisible();
        expect(await page.evaluate(() => typeof (window as typeof window & { Livewire?: unknown }).Livewire)).toBe('object');
        expect(await page.getByLabel('CPR').getAttribute('wire:model.live')).toBe('selectedCertificationTypes');
        await page.getByLabel('CPR').check();
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
        await expect(page.getByLabel('Issuing organization (optional)')).toBeVisible();

        const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(desktopOverflow).toBe(false);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: testInfo.outputPath('care-background-desktop.png'), fullPage: true });
    });
});
