import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const base = process.env.ONBOARDING_PREVIEW_URL || 'http://127.0.0.1:8032';
const output = path.resolve('docs/product/family-onboarding-2026-09-15');
fs.mkdirSync(path.join(output, 'screenshots'), { recursive: true });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const report = { checks: [], errors: [], screenshots: [] };
const check = (name, passed) => { assert.ok(passed, name); report.checks.push(name); };
const screenshot = async (page, name) => {
    await page.screenshot({ path: path.join(output, 'screenshots', `${name}.png`), fullPage: true, animations: 'disabled' });
    report.screenshots.push(name);
};

async function open(viewport) {
    // Test from Paris: visit scheduling must still use the care address's Eastern Time.
    const context = await browser.newContext({ viewport, timezoneId: 'Europe/Paris', reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.on('pageerror', error => report.errors.push(error.message));
    await page.clock.setFixedTime(new Date('2026-09-15T14:00:00Z'));
    await page.goto(`${base}/previews/family-onboarding/`);
    await page.evaluate(() => document.fonts.ready);
    return { page, context };
}

async function address(page) {
    await page.getByLabel('Street address', { exact: true }).fill('123 Oak Street');
    await page.getByLabel('City', { exact: true }).fill('Raleigh');
    await page.getByLabel('State', { exact: true }).fill('NC');
    await page.getByLabel('ZIP code', { exact: true }).fill('27601');
}

try {
    const { page, context } = await open({ width: 1440, height: 1000 });
    await screenshot(page, '01-person-desktop');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('A recipient selection is required', await page.locator('#care_for-error').isVisible());
    await page.getByText('A family member', { exact: true }).click();
    check('Family fields are revealed', await page.locator('#family-fields').isVisible());
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('Family name and relationship are required', await page.locator('#recipient_name-error').isVisible() && await page.locator('#relationship-error').isVisible());
    await page.getByLabel('Their full name').fill('Susan Taylor');
    await page.getByLabel('Their relationship to you').selectOption('parent');
    await screenshot(page, '01-family-desktop');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await address(page);
    await page.getByLabel('ZIP code', { exact: true }).fill('abc');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('Invalid ZIP blocks the next step', await page.locator('#zip-error').isVisible());
    await page.getByLabel('ZIP code', { exact: true }).fill('27601');
    await screenshot(page, '02-address-desktop');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.getByLabel('What should a caregiver know?').fill('Susan enjoys gardening and conversation.');
    await screenshot(page, '03-notes-desktop');
    await page.getByRole('button', { name: 'Back', exact: true }).click();
    check('Address survives back navigation', await page.getByLabel('Street address', { exact: true }).inputValue() === '123 Oak Street');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('Notes survive back navigation', await page.getByLabel('What should a caregiver know?').inputValue() === 'Susan enjoys gardening and conversation.');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('Visit preference is required', await page.locator('#welcome_visit-error').isVisible());
    await page.getByText("Yes, I'd like a visit", { exact: true }).click();
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('Visit date and time range are required', await page.locator('#visit_date-error').isVisible() && await page.locator('#visit_time-error').isVisible());
    await page.getByLabel('Preferred date', { exact: true }).fill('2026-09-16');
    await page.getByText('Morning', { exact: true }).click();
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('A range starting less than 24 hours ahead is rejected in care timezone', await page.locator('#visit_datetime-error').isVisible());
    await page.getByText('Noon', { exact: true }).click();
    await page.clock.setFixedTime(new Date('2026-09-15T16:00:00Z'));
    await screenshot(page, '04-visit-desktop');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('Exactly 24 hours ahead is accepted', await page.locator('[data-step="4"]').isVisible());
    check('Summary shows the preferred range and text confirmation', /Noon \(12 pm – 2 pm, ET\)/.test(await page.locator('#visit-summary-date').innerText()) && (await page.locator('#visit-summary').innerText()).includes('Our team will confirm the final time by text.'));
    check('All four request stages appear', await page.locator('.care-journey li').count() === 4);
    await screenshot(page, '05-request-desktop');
    await page.clock.setFixedTime(new Date('2026-09-15T16:02:00Z'));
    await page.getByRole('button', { name: 'Create my first request' }).click();
    check('Stale preferred range is revalidated before handoff', await page.locator('#visit_datetime-error').isVisible());
    await page.getByText('No thanks', { exact: true }).click();
    check('No hides and disables visit schedule', !(await page.locator('#visit-scheduling').isVisible()) && await page.locator('#visit-date').isDisabled());
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('No visit preference reaches final step without date validation', await page.locator('[data-step="4"]').isVisible() && !(await page.locator('#visit-summary').isVisible()));
    for (let step = 4; step > 0; step--) await page.getByRole('button', { name: 'Back', exact: true }).click();
    await page.getByLabel('Their full name').fill('');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    check('Continue cannot bypass required fields', await page.locator('#recipient_name-error').isVisible());
    await page.getByText('Me', { exact: true }).click();
    check('Me disables family-only fields', await page.locator('#recipient-name').isDisabled());
    for (let step = 0; step < 4; step++) await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await page.route('**/family/requests/create', route => route.fulfill({ status: 200, contentType: 'text/plain', body: 'Verified request creation handoff.' }));
    await page.getByRole('button', { name: 'Create my first request' }).click();
    await page.waitForURL('**/family/requests/create');
    check('Final CTA navigates to the existing request creation path', new URL(page.url()).pathname === '/family/requests/create');
    await context.close();

    for (const width of [390, 320]) {
        const { page, context } = await open({ width, height: 844 });
        await screenshot(page, `01-person-${width}`);
        await page.getByText('Me', { exact: true }).click();
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await address(page);
        await screenshot(page, `02-address-${width}`);
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        check(`${width}: self copy is personalized`, await page.getByRole('heading', { name: 'Want to tell caregivers a little about yourself?' }).isVisible());
        await screenshot(page, `03-notes-${width}`);
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        check(`${width}: both care notes are optional`, await page.locator('[data-step="3"]').isVisible());
        await page.getByText("Yes, I'd like a visit", { exact: true }).click();
        await page.getByLabel('Preferred date', { exact: true }).fill('2026-09-17');
        await page.getByText('Afternoon', { exact: true }).click();
        await screenshot(page, `04-visit-${width}`);
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await screenshot(page, `05-request-${width}`);
        for (let step = 4; step >= 0; step--) {
            if (step < 4) await page.getByRole('button', { name: 'Back', exact: true }).click();
            check(`${width}: step ${step + 1} has no horizontal overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        }
        await context.close();
    }
    check('No browser runtime errors', report.errors.length === 0);
    report.passed = true;
} catch (error) {
    report.passed = false;
    report.errors.push(error.stack);
    process.exitCode = 1;
} finally {
    await browser.close();
    fs.writeFileSync(path.join(output, 'validation.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report, null, 2));
}
