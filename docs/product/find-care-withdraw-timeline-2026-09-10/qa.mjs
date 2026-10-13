import fs from 'node:fs';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const output = 'docs/product/find-care-withdraw-timeline-2026-09-10';
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
            if (calls.some(call => !['login', 'setActiveTab'].includes(call.method))) {
                report.blockedActions.push(calls.map(call => call.method));
                return route.abort();
            }
        }
        return route.continue();
    });
    await page.goto('http://127.0.0.1:8028/login');
    await page.getByLabel('Email', { exact: true }).fill('maria.find-care-audit@example.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    await page.goto('http://127.0.0.1:8028/family/requests/44');
    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(() => window.scrollTo(0, 0));
        const trigger = page.locator('.hc-recruit-withdraw-trigger');
        assert.equal(await trigger.isVisible(), true);
        assert.equal(await page.getByRole('link', { name: 'Care timeline', exact: true }).count(), 1);
        assert.equal(await page.getByRole('link', { name: 'Care story', exact: true }).count(), 0);
        await page.screenshot({ path: `${output}/screenshots/request-${width}.png` });
        await trigger.click();
        const dialog = page.getByRole('dialog', { name: 'Withdraw this request?', exact: true });
        await dialog.waitFor();
        assert.equal(await dialog.getByRole('button', { name: 'Keep request', exact: true }).evaluate(el => el === document.activeElement), true);
        assert.ok((await dialog.innerText()).includes('Affected caregivers will be notified.'));
        assert.ok((await dialog.innerText()).includes('Your request and caregiver history stay available.'));
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const box = await dialog.boundingBox();
        assert.ok(box.x >= 0 && box.x + box.width <= width);
        await page.screenshot({ path: `${output}/screenshots/confirmation-${width}.png` });
        await dialog.getByRole('button', { name: 'Keep request', exact: true }).click();
        await dialog.waitFor({ state: 'hidden' });
        assert.equal(await trigger.evaluate(el => el === document.activeElement), true);
        await trigger.click();
        await dialog.waitFor();
        await page.keyboard.press('Escape');
        await dialog.waitFor({ state: 'hidden' });
        assert.equal(await trigger.evaluate(el => el === document.activeElement), true);
        report.checks.push({ width, visibleHeaderAction: true, confirmationCopy: true, cancelPreservesRequest: true, escapeAndFocusReturn: true, overflow: false });
    }
    await page.getByRole('link', { name: 'Care timeline', exact: true }).click();
    await page.getByText('Progress at a glance', { exact: true }).waitFor();
    const back = page.getByRole('link', { name: 'Back to request', exact: false });
    assert.equal(await back.getAttribute('href'), 'http://127.0.0.1:8028/family/requests/44');
    await back.click();
    await page.locator('.hc-recruit-stages').waitFor();
    assert.ok(page.url().endsWith('/family/requests/44'));
    report.checks.push({ timelineNameAndReturnToExactRequest: true });
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    await browser.close();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
}
