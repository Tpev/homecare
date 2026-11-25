import { expect, test } from '@playwright/test';

test('Customer Success activates assistance, posts care and ends enrollment without repricing the request', async ({ page }, testInfo) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.context().route('**/*', route => {
        const url = new URL(route.request().url());
        return ['127.0.0.1'].includes(url.hostname) || ['data:', 'blob:'].includes(url.protocol) ? route.continue() : route.abort();
    });
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('assistance.cs@example.test');
    await page.getByLabel('Password', { exact: true }).fill('CareAssistanceQA!2026');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    const name = `Assistance Family ${testInfo.project.name}`;
    await page.goto(`/admin/customer-care?search=${encodeURIComponent(name)}`);
    await page.getByRole('link', { name: new RegExp(name) }).click();
    const accountURL = page.url();
    const program = page.locator('#care-assistance');
    await program.locator('summary').filter({ hasText: 'Activate Care Assistance' }).click();
    const option = await program.locator('select[name="application_id"] option').filter({ hasText: name }).getAttribute('value');
    await program.getByRole('combobox', { name: 'Approved application', exact: true }).selectOption(option!);
    await program.getByLabel(/I verified the approval/).check();
    await program.getByLabel('Approval reference / reason').fill('Approved application verified during browser QA.');
    await program.getByRole('button', { name: 'Activate Care Assistance', exact: true }).click();
    await expect(program.getByText('Active for new requests', { exact: true })).toBeVisible();
    await expect(program).toContainText('Customer: $23.00/hour total.');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
    await page.screenshot({ path: testInfo.outputPath('account-active.png'), fullPage: true });

    await page.getByRole('link', { name: 'Create a request', exact: true }).click();
    await expect(page.getByText('Customer $23.00/hour total · Caregiver $20.00/hour before Stripe fees')).toBeVisible();
    await page.getByLabel('Request title').fill(`Assistance visit ${testInfo.project.name}`);
    await page.getByLabel('What should the caregiver do?').fill('Companionship and meal preparation.');
    await page.getByRole('checkbox', { name: 'Companionship', exact: true }).check();
    const day = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);
    await page.getByLabel('Visit start').fill(`${day}T10:00`);
    await page.getByLabel('Visit end').fill(`${day}T12:00`);
    await page.getByLabel('Street address').fill('100 Synthetic Lane');
    await page.getByLabel('ZIP code').fill('27601');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
    await page.screenshot({ path: testInfo.outputPath('request-create.png'), fullPage: true });
    await page.getByRole('button', { name: 'Post request', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/customer-care\/requests\/\d+/);
    const requestURL = page.url();
    await expect(page.getByText('$23.00/hour total · Saved-card payment')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
    await page.screenshot({ path: testInfo.outputPath('request-posted.png'), fullPage: true });

    await page.goto(accountURL);
    await program.getByText('End participation for new requests', { exact: true }).click();
    await program.getByLabel('Reason for ending participation').fill('End future enrollment for synthetic QA.');
    await program.getByRole('button', { name: 'End Care Assistance for new requests', exact: true }).click();
    await expect(program.getByText('Not active for new requests', { exact: true })).toBeVisible();
    await page.goto(requestURL);
    await expect(page.getByText('$23.00/hour total · Saved-card payment')).toBeVisible();
    expect(errors).toEqual([]);
});
