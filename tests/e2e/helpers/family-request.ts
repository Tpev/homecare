import { expect, type Page } from '@playwright/test';

export type CreatedCareRequest = {
    requestId: number;
    requestTitle: string;
};

function toLocalDate(value: Date): string {
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;
}

function toLocalTime(value: Date): string {
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${pad(value.getHours())}:${pad(value.getMinutes())}`;
}

export async function createOneTimeRequest(page: Page): Promise<CreatedCareRequest> {
    const start = new Date(Date.now() + 1000 * 60 * 60 * 48);
    start.setMinutes(0, 0, 0);

    await page.goto('/family/requests/create');
    await expect(page.getByRole('heading', { name: 'Tell us what care you need.' })).toBeVisible();

    await page.getByLabel('Family member receiving care').fill('E2E Recipient Manual');
    await page.getByLabel('Relationship to you').fill('Mother');
    await page.locator('label').filter({ hasText: 'Companionship' }).click();
    await page.locator('label').filter({ hasText: 'Meal preparation' }).click();

    await page.getByLabel('Starting day').fill(toLocalDate(start));
    await page.getByLabel('Starting time').fill(toLocalTime(start));
    await page.getByLabel('Duration (HH:MM)').selectOption('180');

    await page.getByLabel('Street address').fill('501 E2E Maple Ave');
    await page.getByLabel('City').fill('Raleigh');
    await page.getByLabel('ZIP').fill('27601');

    await page.getByRole('button', { name: 'Publish request' }).click();
    await expect(page).toHaveURL(/\/family\/requests\/\d+$/);

    const requestId = Number(page.url().split('/').pop());
    if (Number.isNaN(requestId)) {
        throw new Error(`Could not parse request id from URL: ${page.url()}`);
    }

    const requestTitle = (await page.getByRole('heading').first().innerText()).trim();

    return { requestId, requestTitle };
}
