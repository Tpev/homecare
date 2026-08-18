import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';
import { loginAs } from '../helpers/auth';

async function expectNoHorizontalOverflow(page: Page) {
    const layout = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        offenders: [...document.querySelectorAll<HTMLElement>('body *')]
            .filter((element) => {
                const style = getComputedStyle(element);
                const box = element.getBoundingClientRect();

                return style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && (box.left < -1 || box.right > document.documentElement.clientWidth + 1);
            })
            .slice(0, 8)
            .map((element) => ({
                className: element.className,
                left: Math.round(element.getBoundingClientRect().left),
                right: Math.round(element.getBoundingClientRect().right),
                tag: element.tagName,
                text: element.innerText?.slice(0, 80),
            })),
    }));

    expect(layout.scrollWidth, JSON.stringify(layout)).toBeLessThanOrEqual(layout.clientWidth);
}

function expireActiveRecap(): void {
    const phpCode = String.raw`
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = App\Models\AiSupportMessageAction::query()
    ->where('action_type', App\Models\AiSupportMessageAction::TYPE_RECAP)
    ->whereNull('consumed_at')
    ->whereNull('invalidated_at')
    ->latest('created_at')
    ->firstOrFail();
$action->forceFill(['expires_at' => now()->subMinute()])->save();
`;

    execFileSync('php', ['-r', phpCode], {
        cwd: process.cwd(),
        env: {
            ...process.env,
            APP_ENV: 'playwright',
            APP_URL: 'http://127.0.0.1:8010',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: path.join(process.cwd(), 'database', 'playwright.sqlite'),
            CACHE_STORE: 'file',
            SESSION_DRIVER: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'array',
        },
    });
}

test.describe.serial('Interactive AI Support pilot', () => {
    test('exact Family pilot reviews and confirms a private draft into one live request', async ({ page }, testInfo) => {
        await loginAs(page, 'familyAi');
        await page.getByTestId('support-chat-launcher').click();

        const panel = page.getByTestId('support-chat-panel');
        await expect(panel).toBeVisible();
        await expect(panel.getByText('AI assistant - You can ask for a person anytime')).toBeVisible();
        await expect(panel.getByRole('button', { name: 'Human help' })).toBeVisible();

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

        expireActiveRecap();
        await confirm.click();
        await expect(panel.getByRole('alert')).toHaveText('This confirmation expired or changed. Review the current draft and confirm again.');
        await expect(recap).toBeFocused();
        await recap.getByRole('button', { name: 'Review and confirm again' }).click();

        const renewedRecap = panel.getByRole('region', { name: 'Care request recap' }).last();
        await expect(renewedRecap).toBeVisible();
        await renewedRecap.getByRole('button', { name: 'Confirm and create request' }).click();
        await expect(panel.getByText('Your care request is live. Eligible caregivers can now see it.')).toBeVisible();
        const viewRequest = panel.getByRole('link', { name: 'View request' });
        await expect(viewRequest).toBeVisible();
        await viewRequest.click();

        await expect(page).toHaveURL(/\/family\/requests\/\d+$/);
        await expect(page.getByRole('heading', { name: 'Companionship for Arthur E2E' })).toBeVisible();
        await page.getByRole('button', { name: /Care details/ }).click();
        await expect(page.getByText('Arthur E2E', { exact: true }).first()).toBeVisible();
    });

    test('guides the Family owner to the exact card control and verifies the Stripe return', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await loginAs(page, 'familyAi');
        await page.getByTestId('support-chat-launcher').click();

        const panel = page.getByTestId('support-chat-panel');
        const start = panel.getByRole('button', { name: 'Add payment method' });
        const draft = 'Please keep this note while I update the card.';
        await page.getByLabel('Message LoLo Support').fill(draft);
        await expect(start).toBeVisible();
        await start.click();

        await expect(page).toHaveURL(/\/family\/billing$/);
        const cardButton = page.getByTestId('family-billing-manage-payment-method');
        const guide = page.getByTestId('ai-guided-task-strip');
        await expect(page.getByLabel('Message LoLo Support')).toHaveValue(draft);
        await expect(cardButton).toBeVisible();
        await expect(cardButton).toHaveAttribute('data-ai-guided', 'true');
        await expect(cardButton).toBeFocused();
        await expect(guide).toContainText('Use the highlighted Add card securely button.');
        await expect(guide.getByRole('button', { name: 'Show me' })).toBeVisible();
        await expect(guide.getByRole('button', { name: 'Stop' })).toBeVisible();
        await expect(guide.getByRole('button', { name: 'Talk to a person' })).toBeVisible();
        expect(pageErrors).toEqual([]);

        await cardButton.evaluate((element) => element.replaceWith(element.cloneNode(true)));
        await expect(page.getByTestId('family-billing-manage-payment-method')).toHaveAttribute('data-ai-guided', 'true');
        await expect(page.getByTestId('family-billing-manage-payment-method')).toBeFocused();
        await page.getByTestId('family-billing-manage-payment-method').evaluate((element) => {
            element.classList.remove('ai-guide-target-highlight');
            delete element.dataset.aiGuided;
        });
        await expect(page.getByTestId('family-billing-manage-payment-method')).toHaveClass(/ai-guide-target-highlight/);

        await page.reload();
        await expect(cardButton).toHaveAttribute('data-ai-guided', 'true');
        await expect(cardButton).toBeFocused();
        await expect(guide).toBeVisible();
        await expect(page.getByLabel('Message LoLo Support')).toHaveValue(draft);

        await page.setViewportSize({ width: 390, height: 844 });
        await expectNoHorizontalOverflow(page);
        const guideBox = await guide.boundingBox();
        expect(guideBox?.x ?? -1).toBeGreaterThanOrEqual(0);
        expect((guideBox?.x ?? 0) + (guideBox?.width ?? 0)).toBeLessThanOrEqual(391);
        for (const button of await guide.getByRole('button').all()) {
            expect((await button.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(43.9);
        }

        await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
        await expectNoHorizontalOverflow(page);
        await expect(cardButton).toBeVisible();
        await page.addStyleTag({ content: 'html { font-size: 16px !important; }' });

        await cardButton.click();
        await expect(page).toHaveURL(/\/family\/billing$/);
        await expect(panel).toBeVisible();
        await expect(page.getByLabel('Message LoLo Support')).toHaveValue(draft);
        await expect(panel.getByText(/payment method ending in 4242 is now on file/i)).toBeVisible();
        await expect(page.getByText('VISA ending in 4242')).toBeVisible();
        await expect(page.getByTestId('ai-guided-task-strip')).toHaveCount(0);
        await expect(cardButton).toHaveText('Update card');
        expect(pageErrors.filter((error) => error !== 'Could not find Livewire component in DOM tree')).toEqual([]);
    });

    test('creates and verifies a care profile from a mobile-friendly chat recap', async ({ page }) => {
        const pageErrors: string[] = [];
        page.on('pageerror', (error) => pageErrors.push(error.message));
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'familyAi');
        await page.getByTestId('support-chat-launcher').click();

        const panel = page.getByTestId('support-chat-panel');
        const composer = page.getByLabel('Message LoLo Support');
        await composer.fill('Create a care receiver profile for Maria Browser.');
        await composer.press('Enter');

        const recap = panel.getByRole('region', { name: 'Action recap' }).last();
        await expect(recap).toBeVisible();
        await expect(recap.getByRole('heading', { name: 'Review new care receiver profile' })).toBeVisible();
        await expect(recap.getByText('Maria Browser', { exact: true })).toBeVisible();
        await expect(recap.getByText(/does not silently change any live request/i)).toBeVisible();
        await expectNoHorizontalOverflow(page);

        const confirm = recap.getByRole('button', { name: 'Confirm and save' });
        const modify = recap.getByRole('button', { name: 'Modify something' });
        expect((await confirm.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(43.9);
        expect((await modify.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(43.9);
        await modify.click();
        await expect(composer).toHaveValue('I want to change ');
        await expect(composer).toBeFocused();
        await confirm.click();

        await expect(panel.getByText(/profile was saved and checked/i)).toBeVisible();
        const receipt = panel.getByRole('link', { name: 'View care profiles' }).last();
        await expect(receipt).toBeVisible();
        await receipt.click();
        await expect(page).toHaveURL(/\/family\/care-profiles$/);
        await expect(page.getByText('Maria Browser', { exact: true }).first()).toBeVisible();
        expect(pageErrors).toEqual([]);
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
        await expect(page.getByText('Pilot only', { exact: true })).toBeVisible();
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
        const requestReceipt = page.getByTestId('ai-confirmed-action-receipt')
            .filter({ hasText: /care-request-\d+.*care_request_live/ });
        await expect(requestReceipt.getByText('Confirmed action receipt')).toBeVisible();
        await expect(requestReceipt.getByText(/care-request-\d+.*care_request_live/)).toBeVisible();
        await expect(page.getByText(/State published/)).toBeVisible();
    });
});
