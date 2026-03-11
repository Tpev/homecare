import { expect, Page } from '@playwright/test';

async function openButtonForLabel(page: Page, label: string) {
    const sibling = page.locator(
        `xpath=(//*[normalize-space()="${label}" and not(*)]/following-sibling::button[@dusk='tallstackui_select_open_close'][1])[1]`
    );
    if (await sibling.count()) {
        return sibling;
    }

    const following = page.locator(
        `xpath=(//*[contains(normalize-space(), "${label}") and not(*)]/following::button[@dusk='tallstackui_select_open_close'][1])[1]`
    );

    return following;
}

export async function selectStyledSingle(page: Page, label: string, optionText: string): Promise<void> {
    const openButton = await openButtonForLabel(page, label);
    await expect(openButton).toBeVisible();
    await openButton.click();

    const option = page
        .locator('[dusk="tallstackui_select_options"]:visible li[role="option"]', { hasText: optionText })
        .first();
    await expect(option).toBeVisible();
    await option.click();
}

export async function selectStyledMultiple(page: Page, label: string, optionTexts: string[]): Promise<void> {
    const openButton = await openButtonForLabel(page, label);
    await expect(openButton).toBeVisible();
    await openButton.click();

    for (const text of optionTexts) {
        const option = page
            .locator('[dusk="tallstackui_select_options"]:visible li[role="option"]', { hasText: text })
            .first();
        await expect(option).toBeVisible();
        await option.click();
    }

    await openButton.click();
}
