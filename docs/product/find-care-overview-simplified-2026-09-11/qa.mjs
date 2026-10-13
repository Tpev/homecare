import { chromium } from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';

const output = 'docs/product/find-care-overview-simplified-2026-09-11';
fs.mkdirSync(`${output}/screenshots`, { recursive: true });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const report = { checks: [], errors: [], blockedActions: [] };
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    page.setDefaultTimeout(45000);
    page.on('pageerror', error => report.errors.push(error.message));
    await page.route('**/*', route => {
        const request = route.request(), url = new URL(request.url());
        if (url.origin !== 'http://127.0.0.1:8028') return route.abort();
        if (request.method() !== 'GET') {
            if (url.pathname !== '/livewire/update') return route.abort();
            const calls = request.postDataJSON()?.components?.flatMap(component => component.calls ?? []) ?? [];
            if (calls.some(call => call.method !== 'login')) {
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
    await page.goto('http://127.0.0.1:8028/family/requests');
    const main = page.locator('main');
    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.evaluate(() => document.fonts.ready);
        const text = await main.innerText();
        assert.doesNotMatch(text, /Care overview|Your care|Hi, Maria|Here’s an overview|Arrangements/i);
        assert.equal(await main.locator('#care-arrangements').count(), 0);
        assert.equal(await main.getByRole('heading', { name: 'Needs your attention' }).count(), 1);
        assert.equal(await main.locator('#next-care-heading').count(), 1);
        const nav = main.getByRole('navigation', { name: 'Care sections' });
        const tab = nav.getByRole('link', { name: 'Care request', exact: true });
        assert.ok(await tab.isVisible());
        assert.equal(await nav.getByRole('link', { name: 'Overview', exact: true }).getAttribute('aria-current'), 'page');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const actionsBox = await main.locator('#care-actions').boundingBox();
        const visitBox = await main.locator('section[aria-labelledby="next-care-heading"]').boundingBox();
        assert.ok(Math.abs(actionsBox.width - visitBox.width) < 1);
        await page.screenshot({ path: `${output}/screenshots/overview-${width}.png`, fullPage: true });
        report.checks.push({ width, removedSections: true, tabRenamed: true, nextVisitFullWidth: true, noOverflow: true });
    }
    const destination = main.getByRole('navigation', { name: 'Care sections' }).getByRole('link', { name: 'Care request', exact: true });
    const destinationUrl = await destination.getAttribute('href');
    await destination.click();
    await page.waitForURL(destinationUrl);
    assert.equal(await page.getByRole('navigation', { name: 'Care sections' }).getByRole('link', { name: 'Care request', exact: true }).getAttribute('aria-current'), 'page');
    report.checks.push({ careRequestNavigationWorks: true, destinationUrl });
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    await browser.close();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
}
