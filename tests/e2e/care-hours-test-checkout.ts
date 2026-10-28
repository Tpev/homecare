import { expect, type Page } from '@playwright/test';

export async function fillStripeTestCard(page: Page, amountCents: number, card = '4242424242424242') {
    await expect(page).toHaveURL(/^https:\/\/checkout\.stripe\.com\//, { timeout: 60_000 });
    await expect(page.getByRole('heading', { name: 'Payment method', exact: true })).toBeVisible({ timeout: 30_000 });
    if (await page.getByText('Choose currency', { exact: true }).isVisible()) {
        const usd = page.getByRole('button', { name: `US $${(amountCents / 100).toFixed(2)}`, exact: true });
        if (await usd.isEnabled()) await usd.click();
    }
    const number = page.locator('input[autocomplete="cc-number"], #cardNumber').first();
    if (!await number.isVisible()) {
        const label = page.getByText('Card', { exact: true });
        await expect(label).toBeVisible();
        const box = await label.boundingBox();
        if (!box) throw new Error('Stripe card method has no visible click target.');
        await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
    }
    await number.fill(card);
    await page.locator('input[autocomplete="cc-exp"], #cardExpiry').first().fill('1230');
    await page.locator('input[autocomplete="cc-csc"], #cardCvc').first().fill('123');
    const name = page.locator('input[autocomplete="cc-name"], #billingName').first();
    if (await name.isVisible()) await name.fill('Thomas Peverelli');
    const country = page.locator('#billingCountry').first();
    if (await country.isVisible()) await country.selectOption('US');
    const postal = page.locator('input[autocomplete="postal-code"], #billingPostalCode').first();
    if (await postal.isVisible()) await postal.fill('27601');
}

export async function submitStripeTestCard(page: Page) {
    await page.locator('button[type="submit"]').filter({ hasText: /Pay|Subscribe/ }).last().click({ noWaitAfter: true });
}
