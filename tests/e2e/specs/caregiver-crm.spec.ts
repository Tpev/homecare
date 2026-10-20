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
    const newCard = page.getByRole('button', { name: 'Open Morgan New', exact: true });
    const appliedCard = page.getByRole('button', { name: 'Open Robin Applied', exact: true });
    const hiredCard = page.getByRole('button', { name: 'Open Avery Hired', exact: true });
    const legacyCard = page.getByRole('button', { name: 'Open Legacy Hire', exact: true });
    for (const milestone of ['applied', 'hired']) {
        await expect(newCard.locator(`[data-milestone="${milestone}"]`)).toContainText('Not yet');
        await expect(newCard.locator(`[data-milestone="${milestone}"] time`)).toHaveCount(0);
    }
    await expect(appliedCard.locator('.cg-card-counts')).toContainText('2 applications');
    await expect(appliedCard.locator('[data-milestone="applied"] time')).toHaveText('Sep 14, 2026');
    await expect(appliedCard.locator('[data-milestone="applied"] time')).toHaveAttribute('datetime', /^2026-09-14T09:15:00[+-]\d{2}:\d{2}$/);
    await expect(appliedCard.locator('[data-milestone="applied"] time')).toHaveAttribute('title', /^Sep 14, 2026 · 9:15 AM \S+$/);
    await expect(appliedCard.locator('[data-milestone="hired"]')).toContainText('Not yet');
    await expect(hiredCard.locator('.cg-card-counts')).toContainText('2 hires');
    await expect(hiredCard.locator('[data-milestone="applied"] time')).toHaveText('Sep 12, 2026');
    await expect(hiredCard.locator('[data-milestone="hired"] time')).toHaveText('Sep 15, 2026');
    await expect(hiredCard.locator('[data-milestone="hired"] time')).toHaveAttribute('datetime', /^2026-09-15T14:45:00[+-]\d{2}:\d{2}$/);
    await expect(hiredCard.locator('[data-milestone="hired"] time')).toHaveAttribute('title', /^Sep 15, 2026 · 2:45 PM \S+$/);
    await expect(legacyCard.locator('[data-milestone="applied"] time')).toHaveText('Aug 2, 2026');
    await expect(legacyCard.locator('[data-milestone="hired"]')).toContainText('Date unavailable');
    await expect(legacyCard.locator('[data-milestone="hired"] time')).toHaveCount(0);
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

test('SDR uses all CRM tabs and caregiver follow-ups without global admin access', async ({ page }, testInfo) => {
    await login(page, 'sdr');
    await page.goto('/sdr/family-calling');
    const menuToggle = page.getByRole('button', { name: 'Toggle navigation menu', exact: true });
    if (testInfo.project.name === 'mobile') {
        await menuToggle.click();
        await expect(menuToggle).toHaveAttribute('aria-expanded', 'true');
    }
    for (const [label, path] of [
        ['CRM', '/admin/crm'],
        ['Family calling console', '/sdr/family-calling'],
        ['Family leads CRM', '/admin/family-leads'],
        ['Referral outreach', '/sdr/calling'],
    ]) {
        const link = page.getByRole('link', { name: label, exact: true }).first();
        await expect(link).toBeVisible();
        await expect(link).toHaveAttribute('href', `http://127.0.0.1:8020${path}`);
    }
    await expect(page.locator('nav a[href$="/admin/users"]')).toHaveCount(0);
    await expect(page.locator('nav a[href$="/admin/requests"]')).toHaveCount(0);
    await noOverflow(page);
    await page.screenshot({ path: testInfo.outputPath('caregiver-crm-sdr-navigation.png') });
    await page.getByRole('link', { name: 'CRM', exact: true }).first().click();
    await expect(page).toHaveURL(/\/admin\/crm$/);
    if (testInfo.project.name === 'mobile' && await menuToggle.getAttribute('aria-expanded') === 'true') {
        await menuToggle.click();
    }
    await expect(page.getByRole('tab')).toHaveCount(3);
    await expect(page.getByRole('button', { name: 'Add family lead', exact: true })).toBeVisible();
    await page.getByRole('tab', { name: /^Referral source recruiting/ }).click();
    await expect(page.getByRole('button', { name: 'Add referral source', exact: true })).toBeVisible();
    await caregivers(page);
    await noOverflow(page);
    await page.screenshot({ path: testInfo.outputPath('caregiver-crm-sdr-board.png'), fullPage: true });

    const name = `Sam SDR ${testInfo.project.name}`;
    const notes = 'SDR follow-up: prefers nearby weekday morning care.';
    const conversation = 'Spoke about the first application and agreed to review nearby care.';
    await page.getByRole('button', { name: `Open ${name}`, exact: true }).click();
    await expect(page.getByRole('link', { name: /Open caregiver account/ })).toHaveCount(0);
    await page.getByRole('combobox', { name: 'Owner', exact: true }).selectOption({ label: 'Caregiver CRM SDR' });
    await page.getByLabel('Pinned notes', { exact: true }).fill(notes);
    await page.getByLabel('Next action', { exact: true }).fill('Discuss the next nearby request');
    const followUp = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
    await page.getByLabel('Follow-up date', { exact: true }).fill(followUp);
    await page.getByRole('button', { name: 'Save details', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Mark action complete', exact: true })).toBeEnabled();
    await page.getByRole('combobox', { name: 'Activity type', exact: true }).selectOption('call');
    await page.getByRole('combobox', { name: 'Call outcome', exact: true }).selectOption('connected');
    await page.getByLabel('What happened?', { exact: true }).fill(conversation);
    await page.getByRole('button', { name: 'Add to timeline', exact: true }).click();
    await expect(page.getByText(conversation, { exact: true })).toBeVisible();
    await expect(page.getByRole('dialog').locator('.cg-tags').getByText('✓ Spoke on phone', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Mark action complete', exact: true }).click();
    await expect(page.getByLabel('Next action', { exact: true })).toHaveValue('');
    await expect(page.getByLabel('Follow-up date', { exact: true })).toHaveValue('');
    await page.reload();
    await expect(page.getByLabel('Pinned notes', { exact: true })).toHaveValue(notes);
    await expect(page.getByRole('combobox', { name: 'Owner', exact: true }).locator('option:checked')).toHaveText('Caregiver CRM SDR');
    await expect(page.getByLabel('Next action', { exact: true })).toHaveValue('');
    await expect(page.getByText(conversation, { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: /Open caregiver account/ })).toHaveCount(0);
    await noOverflow(page);
    await page.getByRole('dialog').evaluate(element => element.scrollTo({ top: 0 }));
    await page.screenshot({ path: testInfo.outputPath('caregiver-crm-sdr-panel.png') });
    await page.getByRole('button', { name: 'Close', exact: true }).click();
    await page.getByRole('combobox', { name: 'Assigned to', exact: true }).selectOption('me');
    await expect(page.getByRole('button', { name: `Open ${name}`, exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open Morgan New', exact: true })).toHaveCount(0);

    for (const path of ['/admin/users', '/admin/requests']) {
        const forbidden = await page.goto(path);
        expect(forbidden?.status()).toBe(403);
    }
});
