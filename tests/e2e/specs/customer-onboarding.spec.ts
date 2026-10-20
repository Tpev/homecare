import { expect, test, type Page } from '@playwright/test';

const runtimeErrors = new WeakMap<Page, string[]>();

test.beforeEach(async ({ page }) => {
    runtimeErrors.set(page, []);
    page.on('pageerror', error => runtimeErrors.get(page)?.push(error.message));
});

test.afterEach(async ({ page }) => {
    expect(runtimeErrors.get(page) ?? [], 'No uncaught browser JavaScript errors').toEqual([]);
});

async function localOnly(page: Page): Promise<void> {
    await page.context().route('**/*', route => {
        const url = new URL(route.request().url());
        return url.hostname === '127.0.0.1' || url.protocol === 'data:' || url.protocol === 'blob:'
            ? route.continue()
            : route.abort('blockedbyclient');
    });
}

async function login(page: Page): Promise<void> {
    await localOnly(page);
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('onboarding.admin@example.test');
    await page.getByLabel('Password', { exact: true }).fill('CustomerOnboardingQA!2026');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    await page.goto('/admin/customer-onboarding');
    await expect(page.getByRole('heading', { name: 'Customer onboarding', exact: true })).toBeVisible();
}

async function noHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
}

test('families are searchable, progress is visible, and the workspace fits the screen', async ({ page }, testInfo) => {
    await login(page);
    await expect(page.getByRole('button', { name: 'Open Morgan Account', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Robin Request', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Avery Hired', exact: true })).toBeVisible();
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath('customer-onboarding-directory.png'), fullPage: true });

    const filter = page.getByRole('combobox', { name: 'Filter families', exact: true });
    await filter.selectOption('hired');
    await expect(page.getByRole('button', { name: 'Open Avery Hired', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Morgan Account', exact: true })).toHaveCount(0);
    await filter.selectOption('no_account');
    await expect(page.getByRole('button', { name: `Open Jamie Prospect ${testInfo.project.name}`, exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Avery Hired', exact: true })).toHaveCount(0);
    await filter.selectOption('all');

    const search = page.getByRole('searchbox');
    await search.fill('Morgan Account');
    await expect(page.getByRole('button', { name: 'Open Morgan Account', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Robin Request', exact: true })).toHaveCount(0);
    await search.fill('no-such-family');
    await expect(page.getByText(/No families match/)).toBeVisible();
    await search.fill('');

    await page.getByRole('button', { name: 'Open Morgan Account', exact: true }).click();
    await expect(page.getByRole('button', { name: /Back to families/ })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Generate payment link', exact: true })).toBeVisible();
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath('customer-onboarding-workspace.png'), fullPage: true });
});

test('staff prepare an account and a guest can open, start, and lose access to a revoked payment link', async ({ page, browser }, testInfo) => {
    await login(page);
    const name = `Taylor Prepared ${testInfo.project.name}`;
    await page.getByRole('button', { name: /Add family/ }).click();
    await page.getByLabel('Contact name', { exact: true }).fill(name);
    await page.getByLabel('Email', { exact: true }).fill(`taylor.prepared.${testInfo.project.name}@example.test`);
    await page.getByLabel(/^Phone/).fill('9195550110');
    await page.getByRole('button', { name: 'Create family account', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Generate payment link', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Generate payment link', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Copy link', exact: true })).toBeVisible();

    const link = page.locator('input[readonly]').filter({ visible: true }).first();
    await expect(link).toHaveValue(/\/payment-setup\/[A-Za-z0-9_-]+/);
    const paymentUrl = await link.inputValue();
    await page.context().grantPermissions(['clipboard-read', 'clipboard-write'], { origin: new URL(paymentUrl).origin });
    await page.getByRole('button', { name: 'Copy link', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Copied!', exact: true })).toBeVisible();
    await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toBe(paymentUrl);
    await page.getByRole('button', { name: 'Show QR code', exact: true }).click();
    const qrCode = page.getByRole('img', { name: "Scan to open this family's payment setup link", exact: true });
    await expect(qrCode).toBeVisible();
    await expect.poll(() => qrCode.evaluate(canvas =>
        (canvas as HTMLCanvasElement).getContext('2d')?.getImageData(0, 0, 1, 1).data[3],
    )).toBe(255);
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath('customer-onboarding-payment-link.png'), fullPage: true });

    const guestContext = await browser.newContext({ viewport: testInfo.project.use.viewport });
    const guest = await guestContext.newPage();
    const guestErrors: string[] = [];
    guest.on('pageerror', error => guestErrors.push(error.message));
    try {
        await localOnly(guest);
        await guest.goto(paymentUrl);
        await expect(guest.getByRole('heading', { name: 'Add your payment method', exact: true })).toBeVisible();
        await expect(guest.getByLabel('Password', { exact: true })).toHaveCount(0);
        await noHorizontalOverflow(guest);
        await guest.screenshot({ path: testInfo.outputPath('guest-payment-setup.png'), fullPage: true });
        await guest.locator('input[name="consent"]').check();
        await guest.getByRole('button', { name: 'Continue to secure payment setup', exact: true }).click();
        await expect(guest.getByText('We’re confirming your payment method', { exact: true })).toBeVisible();
        await expect(guest.getByText('Payment method saved', { exact: true })).toHaveCount(0);

        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: /Revoke link/ }).click();
        await expect(page.getByText('Payment link revoked.', { exact: true })).toBeVisible();
        await guest.goto(paymentUrl);
        await expect(guest.getByText('This link is no longer available', { exact: true })).toBeVisible();
        await expect(guest.locator('input[name="consent"]')).toHaveCount(0);
        expect(guestErrors, 'No guest payment page JavaScript errors').toEqual([]);
    } finally {
        await guestContext.close();
    }
});

test('an existing lead becomes one prepared household and keeps its contact details', async ({ page }, testInfo) => {
    await login(page);
    const name = `Jamie Prospect ${testInfo.project.name}`;
    await page.getByRole('button', { name: `Open ${name}`, exact: true }).click();
    await page.getByRole('button', { name: 'Create family account', exact: true }).first().click();
    await expect(page.getByLabel('Contact name', { exact: true })).toHaveValue(name);
    await expect(page.getByLabel('Email', { exact: true })).toHaveValue(`jamie.${testInfo.project.name}@example.test`);
    await page.locator('form').getByRole('button', { name: 'Create family account', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Generate payment link', exact: true })).toBeVisible();
    await page.getByRole('button', { name: /Back to families/ }).click();
    await page.getByRole('searchbox').fill(name);
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await expect(page.getByRole('button', { name: `Open ${name}`, exact: true })).toHaveCount(1);
    await expect(page.locator('tbody td[data-label="Account"]')).toHaveText(/Created/);
    await page.getByRole('combobox', { name: 'Filter families', exact: true }).selectOption('no_account');
    await expect(page.getByRole('button', { name: `Open ${name}`, exact: true })).toHaveCount(0);
});
