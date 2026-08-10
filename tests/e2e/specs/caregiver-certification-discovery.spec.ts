import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';

test.describe('Family caregiver certification discovery', () => {
    test('filters are accessible, URL-backed, and responsive', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        await page.setViewportSize({ width: 1440, height: 1000 });
        await loginAs(page, 'family');
        await page.goto('/caregivers/search');

        const filter = page.locator('details').filter({ hasText: 'Certifications & training' }).first();
        const trigger = filter.locator('summary');
        await trigger.focus();
        await page.keyboard.press('Enter');

        await expect(filter.getByRole('group', { name: 'Required certifications & training' })).toBeVisible();
        await expect(filter.getByRole('checkbox', { name: 'CPR', exact: true })).toBeVisible();
        await filter.getByRole('checkbox', { name: 'CPR', exact: true }).check();

        await expect(page).toHaveURL(/certifications.*cpr/);
        await expect(filter.getByRole('group', { name: 'Verification' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Remove CPR filter' })).toBeVisible();
        await expect(page.getByRole('status')).toContainText('caregiver(s) found');
        await expect(page.getByText('Reported by caregiver').first()).toBeVisible();
        await expect(page.getByText('LoLo verified').first()).toBeVisible();

        await filter.getByRole('radio', { name: /LoLo verified only/ }).check();
        await expect(page).toHaveURL(/certification_verification=verified_only/);
        await expect(page.getByText('E2E Marketplace Caregiver')).toBeVisible();
        await expect(page.getByText('E2E Ready Caregiver')).toHaveCount(0);

        await page.reload();
        await expect(page.getByRole('button', { name: 'Remove CPR filter' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Remove LoLo verified only filter' })).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        const mobileOverflow = await page.evaluate(
            () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
        );
        expect(mobileOverflow).toBe(false);
        await expect(page.getByRole('button', { name: 'Remove CPR filter' })).toBeVisible();

        await page.getByRole('button', { name: 'Remove LoLo verified only filter' }).click();
        await expect(page).not.toHaveURL(/certification_verification=verified_only/);
        await page.getByRole('button', { name: 'Remove CPR filter' }).click();
        await expect(page).not.toHaveURL(/certifications.*cpr/);
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    });
});
