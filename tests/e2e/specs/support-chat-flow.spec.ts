import { expect, test } from '@playwright/test';
import { expectMinimumTextContrast } from '../helpers/accessibility';
import { loginAs } from '../helpers/auth';

test.describe('In-app support chat', () => {
    test('family starts a chat, admin claims and replies, and family receives the unread reply', async ({ page }, testInfo) => {
        const message = `E2E widget question ${Date.now()}`;
        const reply = `E2E support reply ${Date.now()}`;
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));

        await loginAs(page, 'family');
        const launcher = page.getByTestId('support-chat-launcher');
        await expect(launcher).toBeVisible();
        await expect(launcher).toHaveAccessibleName('Chat with LoLo Support');

        await launcher.click();
        const panel = page.getByTestId('support-chat-panel');
        await expect(panel).toBeVisible();
        await expect(panel.getByText('Leave us a message')).toBeVisible();
        await expect(panel.getByText('Hi E2E. How can we help?')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('support-chat-desktop-empty.png'), fullPage: true });

        await page.getByLabel('Message LoLo Support').fill(message);
        await page.route('**/livewire/update', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500));
            await route.continue();
        }, { times: 1 });
        await page.getByTestId('support-chat-send').click();
        await expect(page.getByTestId('support-chat-pending-message').getByText('Sending…')).toBeVisible();
        await expect(panel.getByText(message, { exact: true })).toBeVisible();
        await expect(page.getByTestId('support-chat-pending-message')).toHaveCount(0);
        expect(pageErrors).toEqual([]);

        await page.getByRole('button', { name: 'Minimize support chat' }).click();
        await expect(panel).toBeHidden();

        await loginAs(page, 'admin');
        await expect(page.getByTestId('support-chat-launcher')).toHaveCount(0);
        await page.goto('/admin/support/tickets');
        const ticketCard = page.getByTestId('support-ticket-card').filter({ hasText: message });
        await expect(ticketCard).toBeVisible();
        await expect(ticketCard.getByText('Chat', { exact: true })).toBeVisible();
        await expect(ticketCard.getByText(/Started from Dashboard/)).toBeVisible();
        await ticketCard.getByRole('link', { name: 'Open conversation' }).click();

        await expect(page.getByText('Support chat context')).toBeVisible();
        const adminTicketUrl = page.url();
        await expect(page.getByText('E2E Family', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('/dashboard', { exact: false })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('support-chat-admin-context.png'), fullPage: true });
        await page.getByRole('button', { name: 'Claim conversation' }).click();
        await expect(page.getByText('Conversation claimed.')).toBeVisible();

        await page.getByPlaceholder('Write a reply to the user...').fill(reply);
        await page.getByRole('button', { name: 'Send reply' }).click();
        await expect(page.getByText(reply, { exact: true })).toBeVisible();

        await loginAs(page, 'family');
        await expect(page.getByTestId('support-chat-unread')).toHaveText('1');
        await expectMinimumTextContrast(page.getByTestId('support-chat-unread'));
        await expect(page.getByTestId('support-chat-launcher')).toHaveAccessibleName(/1 unread message/);
        await page.getByTestId('support-chat-launcher').click();
        await expect(page.getByTestId('support-chat-panel').getByText(reply, { exact: true })).toBeVisible();
        await expect(page.getByText(/E2E from LoLo Support will reply here/)).toBeVisible();
        await expect(page.getByTestId('support-chat-panel').getByText('Online now')).toHaveCount(0);

        await loginAs(page, 'admin');
        await page.goto(adminTicketUrl);
        await page.getByLabel('Status').selectOption('closed');
        await page.getByRole('button', { name: 'Update status' }).click();
        await expect(page.getByText('Ticket status updated.')).toBeVisible();

        await loginAs(page, 'family');
        const closedPanel = page.getByTestId('support-chat-panel');
        if (await page.getByTestId('support-chat-launcher').isVisible()) {
            await page.getByTestId('support-chat-launcher').click();
        }
        await expect(closedPanel).toBeVisible();
        await expect(page.getByText('This conversation is closed and read-only.')).toBeVisible();
        await page.getByRole('button', { name: 'Start a new conversation' }).click();
        await expect(page.getByText('Hi E2E. How can we help?')).toBeVisible();
    });

    test('signed-out visitors and admins never receive the customer launcher', async ({ page }) => {
        await page.goto('/login');
        await expect(page.getByTestId('support-chat-launcher')).toHaveCount(0);

        await loginAs(page, 'admin');
        await page.goto('/profile');
        await expect(page.getByTestId('support-chat-launcher')).toHaveCount(0);
    });

    test('desktop widget is operable by keyboard and preserves its draft', async ({ page }) => {
        await loginAs(page, 'caregiverMobileVisual');
        await page.goto('/profile');

        const launcher = page.getByTestId('support-chat-launcher');
        await launcher.focus();
        await expect(launcher).toBeFocused();
        await page.keyboard.press('Enter');

        const panel = page.getByTestId('support-chat-panel');
        const composer = page.getByLabel('Message LoLo Support');
        await expect(panel).toBeVisible();
        await expect(composer).toBeFocused();
        await composer.fill('Keyboard draft remains available.');
        await page.keyboard.press('Escape');
        await expect(panel).toBeHidden();
        await expect(launcher).toBeFocused();

        await page.keyboard.press('Enter');
        await expect(composer).toHaveValue('Keyboard draft remains available.');
    });
});
