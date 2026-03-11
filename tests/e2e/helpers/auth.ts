import { expect, Page } from '@playwright/test';

type AccountKey = 'family' | 'caregiverReady' | 'caregiverNew' | 'admin';

const accounts: Record<AccountKey, { email: string; password: string; name: string }> = {
    family: {
        email: 'family.e2e@example.com',
        password: 'password',
        name: 'E2E Family',
    },
    caregiverReady: {
        email: 'caregiver.ready.e2e@example.com',
        password: 'password',
        name: 'E2E Ready Caregiver',
    },
    caregiverNew: {
        email: 'caregiver.new.e2e@example.com',
        password: 'password',
        name: 'E2E New Caregiver',
    },
    admin: {
        email: 'test@test.com',
        password: 'password',
        name: 'E2E Admin',
    },
};

export async function loginAs(page: Page, key: AccountKey): Promise<void> {
    const account = accounts[key];

    await page.context().clearCookies();
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();

    await page.getByLabel('Email').fill(account.email);
    await page.getByLabel('Password').fill(account.password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 15_000 });
    await page.waitForLoadState('networkidle');
}

export async function logoutFromProfileMenu(page: Page, accountName: string): Promise<void> {
    const accountToggle = page.locator('nav button').filter({ hasText: accountName }).first();
    await expect(accountToggle).toBeVisible();
    await accountToggle.click();

    await page.getByRole('button', { name: 'Log Out' }).click();
    await page.waitForLoadState('networkidle');
}

export { accounts };
