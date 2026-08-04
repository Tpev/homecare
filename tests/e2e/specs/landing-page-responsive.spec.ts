import { expect, test } from '@playwright/test';

test.describe('Public LoLo landing page', () => {
    test('matches the new responsive experience and keeps the care entry functional', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/');

        await expect(page.getByRole('heading', { name: /Trusted help at home/ })).toBeVisible();
        await expect(page.getByRole('img', { name: /warm and reassuring guide/ })).toHaveJSProperty('complete', true);
        await expect(page.locator('.nav-actions').getByRole('link', { name: 'Find care' })).toHaveAttribute('href', /\/register$/);

        const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(mobileOverflow).toBe(false);

        await page.getByText('Menu', { exact: true }).click();
        await expect(page.getByRole('navigation', { name: 'Mobile navigation' })).toBeVisible();
        await expect(page.getByRole('navigation', { name: 'Mobile navigation' }).getByRole('link', { name: 'Become a caregiver' })).toHaveAttribute('href', /\/caregiver\/register$/);

        await page.getByPlaceholder('ZIP code or city').fill('27601');
        await page.getByLabel('Recurring').check();
        await expect(page.getByLabel('One-time')).not.toBeChecked();
        await expect(page.getByLabel('Recurring')).toBeChecked();
        await page.getByRole('button', { name: 'See available caregivers' }).click();

        await expect(page).toHaveURL(/\/get-care\?.*zip=27601/);
        await expect(page.getByText('Find trusted home care support')).toBeVisible();

        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto('/');

        await expect(page.getByRole('link', { name: 'Caregiver? Join LoLo' })).toHaveAttribute('href', /\/caregiver\/register$/);
        const featuredCaregivers = page.locator('.profile-grid .caregiver-card');
        expect(await featuredCaregivers.count()).toBeGreaterThan(0);
        await expect(featuredCaregivers.first().getByRole('link', { name: 'View profile' })).toHaveAttribute('href', /\/caregivers\//);
        expect(await page.getByRole('heading', { name: /Trusted help at home/ }).evaluate((element) => getComputedStyle(element).color)).toBe('rgb(23, 63, 53)');
        expect(await page.getByRole('heading', { name: 'Find someone who fits your family.' }).evaluate((element) => getComputedStyle(element).color)).toBe('rgb(23, 63, 53)');
        expect(await page.getByRole('heading', { name: 'Care at home, made simple.' }).evaluate((element) => getComputedStyle(element).color)).toBe('rgb(255, 247, 234)');
        const portraitBox = await featuredCaregivers.first().locator('.portrait').boundingBox();
        expect(portraitBox).not.toBeNull();
        expect((portraitBox?.width ?? 0) / (portraitBox?.height ?? 1)).toBeGreaterThan(1.5);
        expect(portraitBox?.height ?? 0).toBeGreaterThanOrEqual(220);
        await expect(page.getByTitle('How LoLo Care works for families')).toHaveAttribute('src', /youtube-nocookie\.com\/embed\/_nve3ZnFsGM/);
        await expect(page.locator('.nav-shell .brand img')).toHaveAttribute('src', /lolo-wordmark-evergreen\.svg/);
        await expect(page.getByRole('heading', { name: 'Care at home, made simple.' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'What families ask us.' })).toBeVisible();

        const desktopOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(desktopOverflow).toBe(false);
        expect(pageErrors, pageErrors.join('\n')).toEqual([]);
    });
});
