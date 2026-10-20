import { expect, test, type Page } from '@playwright/test';

const runtimeErrors = new WeakMap<Page, string[]>();
test.beforeEach(async ({ page }) => {
    runtimeErrors.set(page, []);
    page.on('pageerror', error => runtimeErrors.get(page)?.push(error.message));
    await page.context().route('**/*', route => {
        const url = new URL(route.request().url());
        return url.hostname === '127.0.0.1' || ['data:', 'blob:'].includes(url.protocol)
            ? route.continue() : route.abort('blockedbyclient');
    });
});
test.afterEach(async ({ page }) => {
    expect(runtimeErrors.get(page) ?? [], 'No uncaught browser JavaScript errors').toEqual([]);
});

async function login(page: Page, role = 'admin'): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(`caregiver.crm.${role}@example.test`);
    await page.getByLabel('Password', { exact: true }).fill('CaregiverCrmQA!2026');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    await page.goto('/admin/crm');
}

async function caregivers(page: Page): Promise<void> {
    await page.getByRole('tab', { name: /^Caregivers/ }).click();
    await expect(page.getByRole('button', { name: 'Open Morgan New', exact: true })).toBeVisible();
}

async function noOverflow(page: Page): Promise<void> {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(1);
    const panel = page.getByRole('dialog');
    if (await panel.count()) {
        expect(await panel.evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
    }
}

test('approved caregiver board reflects activity, filters, and fits the screen', async ({ page }, testInfo) => {
    await login(page);
    await caregivers(page);
    await expect(page.getByRole('heading', { name: 'New', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Applied', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Hired', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Robin Applied', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Avery Hired', exact: true })).toBeVisible();
    await expect(page.getByText('Pending Hidden', { exact: true })).toHaveCount(0);
    await expect(page.getByText('Suspended Hidden', { exact: true })).toHaveCount(0);
    await noOverflow(page);
    await page.screenshot({ path: testInfo.outputPath('caregiver-crm-board.png'), fullPage: true });

    const search = page.getByRole('searchbox');
    await search.fill('Robin Applied');
    await expect(page.getByRole('button', { name: 'Open Morgan New', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Open Robin Applied', exact: true })).toBeVisible();
    await search.fill('no-such-caregiver');
    await expect(page.getByRole('button', { name: /^Open / })).toHaveCount(0);
    await search.fill('');
    await page.getByRole('combobox', { name: 'Follow-up', exact: true }).selectOption('due');
    await expect(page.getByRole('button', { name: 'Open Robin Applied', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Morgan New', exact: true })).toHaveCount(0);
    await page.getByRole('combobox', { name: 'Follow-up', exact: true }).selectOption('all');
    await page.getByRole('tab', { name: /^Referral source recruiting/ }).click();
    await expect(page.getByRole('button', { name: 'Add referral source', exact: true })).toBeVisible();
    await page.getByRole('tab', { name: /^Family \/ care receiver leads/ }).click();
    await expect(page.getByRole('button', { name: 'Add family lead', exact: true })).toBeVisible();
});

test('staff save notes, log a conversation, and complete the next action', async ({ page }, testInfo) => {
    await login(page);
    await caregivers(page);
    const name = `Taylor Followup ${testInfo.project.name}`;
    const notes = 'Prefers weekday mornings; start with nearby requests.';
    const revisedNotes = `${notes} Call after 9 AM.`;
    await page.getByRole('button', { name: `Open ${name}`, exact: true }).click();
    await page.getByLabel('Pinned notes', { exact: true }).fill(notes);
    await page.getByLabel('Next action', { exact: true }).fill('Review the first application together');
    const followUp = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    await page.getByLabel('Follow-up date', { exact: true }).fill(followUp);
    await page.getByRole('combobox', { name: 'Priority', exact: true }).selectOption('high');
    await page.getByRole('combobox', { name: 'Owner', exact: true }).selectOption({ label: 'Caregiver CRM Admin' });
    await page.getByRole('button', { name: 'Save details', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Mark action complete', exact: true })).toBeVisible();

    await page.getByLabel('Pinned notes', { exact: true }).fill(revisedNotes);
    await page.getByRole('combobox', { name: 'Activity type', exact: true }).selectOption('call');
    await expect(page.getByRole('button', { name: 'Mark action complete', exact: true })).toBeDisabled();
    const discardDialog = page.waitForEvent('dialog').then(async dialog => {
        expect(dialog.message()).toContain('Discard your unsaved');
        await dialog.dismiss();
    });
    await page.getByRole('button', { name: 'Close', exact: true }).click();
    await discardDialog;
    await expect(page.getByLabel('Pinned notes', { exact: true })).toHaveValue(revisedNotes);
    await page.getByRole('combobox', { name: 'Call outcome', exact: true }).selectOption('no_answer');
    await page.getByLabel('What happened?', { exact: true }).fill('No answer on the first attempt.');
    await page.getByRole('button', { name: 'Add to timeline', exact: true }).click();
    await expect(page.getByText('No answer on the first attempt.', { exact: true })).toBeVisible();
    await expect(page.getByRole('dialog').locator('.cg-tags').getByText('✓ Spoke on phone', { exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Mark action complete', exact: true })).toBeDisabled();
    await page.getByRole('button', { name: 'Save details', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Mark action complete', exact: true })).toBeEnabled();
    await page.getByRole('combobox', { name: 'Activity type', exact: true }).selectOption('call');
    await page.getByRole('combobox', { name: 'Call outcome', exact: true }).selectOption('connected');
    await page.getByLabel('What happened?', { exact: true }).fill('Spoke about nearby requests and agreed to apply this week.');
    await page.getByRole('button', { name: 'Add to timeline', exact: true }).click();
    await expect(page.getByText('Spoke about nearby requests and agreed to apply this week.', { exact: true })).toBeVisible();
    await expect(page.getByRole('dialog').locator('.cg-tags').getByText('✓ Spoke on phone', { exact: true })).toBeVisible();
    await noOverflow(page);
    await page.screenshot({ path: testInfo.outputPath('caregiver-crm-timeline.png') });
    await page.getByRole('dialog').evaluate(element => element.scrollTo({ top: 0 }));
    await page.screenshot({ path: testInfo.outputPath('caregiver-crm-panel.png') });

    await page.getByRole('button', { name: 'Mark action complete', exact: true }).click();
    await expect(page.getByLabel('Next action', { exact: true })).toHaveValue('');
    await expect(page.getByLabel('Follow-up date', { exact: true })).toHaveValue('');
    await page.reload();
    await expect(page.getByLabel('Pinned notes', { exact: true })).toHaveValue(revisedNotes);
    await expect(page.getByText('Spoke about nearby requests and agreed to apply this week.', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Next action', { exact: true })).toHaveValue('');
    await noOverflow(page);
});

test('sales keeps family and referral CRM without access to caregiver records', async ({ page }) => {
    await login(page, 'sales');
    await expect(page.getByRole('tab', { name: /^Caregivers/ })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Add family lead', exact: true })).toBeVisible();
    await page.getByRole('tab', { name: /^Referral source recruiting/ }).click();
    await expect(page.getByRole('button', { name: 'Add referral source', exact: true })).toBeVisible();
    await expect(page.getByText('Morgan New', { exact: true })).toHaveCount(0);
    const forbidden = await page.goto('/admin/crm?pipeline=caregivers');
    expect(forbidden?.status()).toBe(403);
    await expect(page.getByText('Morgan New', { exact: true })).toHaveCount(0);
});
