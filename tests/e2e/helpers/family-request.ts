import { expect, type Page } from '@playwright/test';
import { selectStyledMultiple } from './tallstack';

function toLocalDateTime(value: Date): string {
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}T${pad(value.getHours())}:${pad(value.getMinutes())}`;
}

export async function createOneTimeRequest(page: Page, title: string): Promise<number> {
    const start = new Date(Date.now() + 1000 * 60 * 60 * 48);
    const end = new Date(start.getTime() + 1000 * 60 * 60 * 3);

    await page.goto('/family/requests/create');
    await expect(page.getByRole('heading', { name: 'New care request' })).toBeVisible();

    await page.getByLabel('Request title').fill(title);
    await page.getByLabel('Additional info').fill('Need support while family attends a medical appointment. Clear communication is important.');
    await page.getByLabel('Scope of work').fill('Companionship, light meal setup, and hydration reminders throughout the shift.');
    await page.getByLabel('Time expectations').fill('Arrive 10 minutes early and keep routine calm.');
    await page.getByLabel('Home access notes').fill('Lockbox at front door. Code will be shared in chat.');
    await page.getByLabel('Preferred caregiver response SLA (hours)').fill('12');
    await page.getByLabel('Start date and time').fill(toLocalDateTime(start));
    await page.getByLabel('End date and time').fill(toLocalDateTime(end));
    await page.getByLabel('Address line 1').fill('501 E2E Maple Ave');
    await page.getByLabel('City').fill('Raleigh');
    await page.getByLabel('ZIP').fill('27601');
    await selectStyledMultiple(page, 'Tasks needed', ['Companionship', 'Meal preparation']);

    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByText('Step 2 of 4')).toBeVisible();
    await page.getByLabel('Recipient full name').fill('E2E Recipient Manual');
    await page.getByLabel('Relationship to your account').fill('Mother');
    await page.getByLabel('Recipient care notes (optional)').fill('Needs gentle reminders to hydrate and standby support when walking.');

    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByText('Step 3 of 4')).toBeVisible();
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page.getByText('Step 4 of 4')).toBeVisible();
    await page.getByRole('button', { name: 'Publish request' }).click();

    await expect(page).toHaveURL(/\/family\/requests\/\d+$/);

    const requestId = Number(page.url().split('/').pop());
    if (Number.isNaN(requestId)) {
        throw new Error(`Could not parse request id from URL: ${page.url()}`);
    }

    return requestId;
}
