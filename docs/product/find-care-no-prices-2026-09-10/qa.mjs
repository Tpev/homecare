import fs from 'node:fs';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const output = 'docs/product/find-care-no-prices-2026-09-10';
fs.mkdirSync(`${output}/screenshots`, { recursive: true });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const report = { checks: [], errors: [], blockedActions: [] };
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    page.setDefaultTimeout(45000);
    page.on('pageerror', error => report.errors.push(error.message));
    await page.route('**/*', route => {
        const request = route.request(), url = new URL(request.url());
        if (url.hostname !== '127.0.0.1') return route.abort();
        if (request.method() !== 'GET') {
            if (url.pathname !== '/livewire/update') return route.abort();
            const calls = request.postDataJSON()?.components?.flatMap(component => component.calls ?? []) ?? [];
            if (calls.some(call => !['login', 'setActiveTab', 'setCaregiverView', 'clearCaregiverSearch', 'beginCaregiverInvitation', 'cancelCaregiverInvitation', 'closeCaregiverInvitePanel'].includes(call.method))) {
                report.blockedActions.push(calls.map(call => call.method));
                return route.abort();
            }
        }
        return route.continue();
    });
    const settle = async () => { await page.waitForTimeout(500); await page.waitForLoadState('networkidle'); };
    const check = async (surface, name, width) => {
        const text = await surface.innerText();
        assert.doesNotMatch(text, /\$\s*\d|\/\s*hr|processing fee|hourly rate|price range/i);
        assert.equal(await surface.locator('.hc-candidate-rate').count(), 0);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        report.checks.push({ name, width, noPrices: true, noOverflow: true });
    };
    await page.goto('http://127.0.0.1:8028/login');
    await page.getByLabel('Email', { exact: true }).fill('maria.find-care-audit@example.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto('http://127.0.0.1:8028/family/requests/44?tab=invite');
        const surface = page.locator('.hc-recruit-browser');
        await surface.waitFor();
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await surface.getByTestId('discovery-row').count() > 0);
        await check(surface, 'recommended', width);
        await page.screenshot({ path: `${output}/screenshots/find-caregivers-${width}.png`, fullPage: true });
        const invite = surface.locator('[data-invite-trigger]').first();
        if (await invite.count()) {
            await invite.click();
            const dialog = page.getByRole('dialog');
            await dialog.waitFor();
            await settle();
            await check(dialog, 'invitation-preview', width);
            await page.screenshot({ path: `${output}/screenshots/invitation-preview-${width}.png` });
            await dialog.locator('[wire\\:click="closeCaregiverInvitePanel"]').click();
            await dialog.waitFor({ state: 'hidden' });
        }
        await surface.getByRole('searchbox').fill('Sofia');
        await surface.getByRole('heading', { name: 'Search results', exact: true }).waitFor();
        await settle();
        assert.ok(await surface.getByTestId('discovery-row').count() > 0);
        await check(surface, 'search-results', width);
        await surface.getByRole('button', { name: 'Saved profiles', exact: true }).click();
        await settle();
        await check(surface, 'saved-profiles', width);
        await surface.getByRole('button', { name: /^Invited / }).click();
        await surface.getByRole('heading', { name: 'People you invited', exact: true }).waitFor();
        await settle();
        await check(surface, 'invited-history', width);
    }
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    await browser.close();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
}
