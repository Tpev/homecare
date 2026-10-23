import { expect, test } from '@playwright/test';

test('SDR manages templates, sends from both CRM views, and sees replies', async ({ page }, testInfo) => {
    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.context().route('**/*', route => {
        const url = new URL(route.request().url());
        return url.hostname === '127.0.0.1' || ['data:', 'blob:'].includes(url.protocol)
            ? route.continue() : route.abort('blockedbyclient');
    });
    const leadId = testInfo.project.name === 'desktop' ? 1 : 2;
    const templateName = `Callback ${testInfo.project.name}`;
    const body = `Hi ${testInfo.project.name}, what time works for a call?`;
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill('crm.sms.sdr@example.test');
    await page.getByLabel('Password', { exact: true }).fill('CrmSmsQA!2026');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    await page.goto(`/admin/crm?lead=${leadId}`);
    await page.getByRole('button', { name: 'Manage SMS templates', exact: true }).click();
    await page.getByLabel('Template name', { exact: true }).fill(templateName);
    await page.getByLabel('Template text', { exact: true }).fill('When can we call?');
    await page.getByRole('button', { name: 'Save template', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('SMS template saved.');
    await page.getByRole('button', { name: `Edit template ${templateName}`, exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Edit template', exact: true })).toBeVisible();
    await expect(page.getByLabel('Template text', { exact: true })).toHaveValue('When can we call?');
    await page.getByLabel('Template text', { exact: true }).fill('What time works for a call?');
    await page.getByRole('button', { name: 'Save template', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Create template', exact: true })).toBeVisible();
    await expect(page.getByText('What time works for a call?', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Close templates', exact: true }).click();
    await expect(page.getByLabel('Template text', { exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Send SMS', exact: true }).click();
    await page.getByRole('combobox', { name: 'Message template', exact: true }).selectOption({ label: templateName });
    await expect(page.getByLabel('Message', { exact: true })).toHaveValue('What time works for a call?');
    await page.getByLabel('Message', { exact: true }).fill(body);
    await page.locator('section').filter({ has: page.getByRole('heading', { name: 'Text messages', exact: true }) }).scrollIntoViewIfNeeded();
    await page.screenshot({ path: testInfo.outputPath('crm-sms-composer.png') });
    await page.getByRole('button', { name: 'Send text', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Text submitted');
    await expect(page.getByText(body, { exact: true })).toBeVisible();
    await expect(page.getByText('Delivery: Queued', { exact: true })).toBeVisible();

    await page.goto(`/admin/family-leads?lead=${leadId}`);
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), 'Family CRM fits the viewport').toBeLessThanOrEqual(1);
    await expect(page.getByText(body, { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Send SMS', exact: true }).click();
    await page.getByLabel('Message', { exact: true }).fill('A free text from the family CRM.');
    await page.getByRole('button', { name: 'Send text', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Text submitted');
    await expect(page.getByText('A free text from the family CRM.', { exact: true })).toBeVisible();
    const reply = `Tomorrow at 10 works for ${testInfo.project.name}.`;
    const response = await page.request.post('/webhooks/twilio/sms', { form: {
        MessageSid: `SM_browser_${testInfo.project.name}`, From: `+1919555100${leadId}`,
        To: '+19195550000', Body: reply, NumMedia: '0', SmsStatus: 'received',
    } });
    expect(response.ok()).toBeTruthy();
    await page.getByText('Timeline', { exact: true }).scrollIntoViewIfNeeded();
    await expect(page.getByText(reply, { exact: true })).toBeVisible({ timeout: 25_000 });
    await expect(page.getByText('Status: Received', { exact: true })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('crm-sms-timeline.png') });
    await page.getByRole('button', { name: 'Manage SMS templates', exact: true }).click();
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: `Delete template ${templateName}`, exact: true }).click();
    await expect(page.getByRole('status')).toContainText('SMS template deleted');
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
    expect(errors).toEqual([]);
});
