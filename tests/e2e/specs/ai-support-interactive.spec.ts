import { expect, test, type Page } from '@playwright/test';
import { loginAs } from '../helpers/auth';

async function expectNoHorizontalOverflow(page: Page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
}

test.describe.serial('Interactive AI Support pilot', () => {
    test('exact Family pilot reviews and confirms a private draft into one live request', async ({ page }, testInfo) => {
        await loginAs(page, 'familyAi');
        await page.getByTestId('support-chat-launcher').click();

        const panel = page.getByTestId('support-chat-panel');
        await expect(panel).toBeVisible();
        await expect(panel.getByText('AI assistant - You can ask for a person anytime')).toBeVisible();
        await expect(panel.getByRole('button', { name: 'Talk to a person' })).toBeVisible();

        const recap = panel.getByRole('region', { name: 'Care request recap' });
        await expect(recap).toBeVisible();
        await expect(recap.getByRole('heading', { name: 'Review your request' })).toBeVisible();
        await expect(recap.getByText('One-time care', { exact: true })).toBeVisible();
        await expect(recap.getByText('Arthur E2E', { exact: true })).toBeVisible();
        await expect(recap.getByText('Companionship', { exact: true })).toBeVisible();
        await expect(recap.getByText(/Eastern Time/)).toBeVisible();
        await expect(recap.getByText('110 Pilot Lane, Raleigh, NC 27601', { exact: true })).toBeVisible();
        await expect(recap.getByText(/No caregiver will be hired, and no payment will be authorized/)).toBeVisible();
        await expect(panel.getByText('$30', { exact: false })).toHaveCount(0);

        const confirm = recap.getByRole('button', { name: 'Confirm and create request' });
        const modify = recap.getByRole('button', { name: 'Modify something' });
        expect((await confirm.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(43.9);
        expect((await modify.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(43.9);

        await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
        await expectNoHorizontalOverflow(page);
        await expect(confirm).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('ai-support-recap-200-percent.png') });
        await page.addStyleTag({ content: 'html { font-size: 16px !important; }' });

        await confirm.click();
        await expect(panel.getByText('Your care request is live. Eligible caregivers can now see it.')).toBeVisible();
        const viewRequest = panel.getByRole('link', { name: 'View request' });
        await expect(viewRequest).toBeVisible();
        await viewRequest.click();

        await expect(page).toHaveURL(/\/family\/requests\/\d+$/);
        await expect(page.getByRole('heading', { name: 'Companionship for Arthur E2E' })).toBeVisible();
        await page.getByRole('button', { name: /Care details/ }).click();
        await expect(page.getByText('Arthur E2E', { exact: true }).first()).toBeVisible();
    });

    test('same-account Family member cannot see or inherit the exact-user AI conversation', async ({ page }) => {
        await loginAs(page, 'familyAiMember');
        await page.getByTestId('support-chat-launcher').click();

        const panel = page.getByTestId('support-chat-panel');
        await expect(panel).toBeVisible();
        await expect(panel.getByText('Leave us a message')).toBeVisible();
        await expect(panel.getByText('AI assistant - You can ask for a person anytime')).toHaveCount(0);
        await expect(panel.getByText('Arthur E2E', { exact: true })).toHaveCount(0);
        await expect(panel.getByRole('region', { name: 'Care request recap' })).toHaveCount(0);
    });

    test('admin can audit pilot state, governed KB drafts, and confirmed publication evidence', async ({ page }) => {
        await loginAs(page, 'admin');
        await page.goto('/admin/ai-support');

        await expect(page.getByRole('heading', { name: 'AI Support' })).toBeVisible();
        await expect(page.getByText('Pilot controls open')).toBeVisible();
        await expect(page.getByText('12 working')).toBeVisible();

        await page.getByRole('link', { name: 'Knowledge base' }).click();
        await expect(page.getByRole('heading', { name: 'Knowledge base' })).toBeVisible();
        await expect(page.getByText('KB-CARE-001', { exact: true })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Create draft' })).toBeVisible();

        await page.goto('/admin/support/tickets');
        const ticket = page.getByTestId('support-ticket-card').filter({ hasText: 'AI support pilot request fixture' });
        await expect(ticket).toBeVisible();
        await ticket.getByRole('link', { name: 'Open conversation' }).click();

        await expect(page.getByRole('heading', { name: 'AI support pilot request fixture' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'AI evidence' })).toBeVisible();
        await expect(page.getByText('Confirmed action receipt')).toBeVisible();
        await expect(page.getByText(/care-request-\d+.*care_request_live/)).toBeVisible();
        await expect(page.getByText(/State published/)).toBeVisible();
    });
});
