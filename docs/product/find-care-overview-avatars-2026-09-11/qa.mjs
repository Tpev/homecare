import { chromium } from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';

const output = 'docs/product/find-care-overview-avatars-2026-09-11';
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
    const cta = main.getByRole('link', { name: 'Request new care', exact: true });
    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.evaluate(() => document.fonts.ready);
        assert.ok(await cta.isVisible());
        assert.equal(await cta.getAttribute('href'), 'http://127.0.0.1:8028/family/requests/create');
        const avatars = main.locator('#care-actions .h-9.w-9, section[aria-labelledby="next-care-heading"] .h-9.w-9');
        assert.equal(await avatars.count(), 2);
        const avatarStates = [];
        for (const avatar of await avatars.all()) {
            const box = await avatar.boundingBox();
            assert.equal(box.width, 36);
            assert.equal(box.height, 36);
            const photo = avatar.locator('img');
            if (await photo.count()) {
                await photo.scrollIntoViewIfNeeded();
                await photo.evaluate(image => image.decode());
                assert.ok(await photo.evaluate(image => image.naturalWidth > 0));
                avatarStates.push('photo');
            } else {
                assert.match((await avatar.innerText()).trim(), /^[A-Z]{1,2}$/);
                avatarStates.push('initials');
            }
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const ctaBox = await cta.boundingBox();
        const actionBox = await main.locator('#care-actions').boundingBox();
        assert.ok(Math.abs(ctaBox.x + ctaBox.width - actionBox.x - actionBox.width) < 2);
        assert.ok(ctaBox.y < actionBox.y);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: `${output}/screenshots/overview-${width}.png`, fullPage: true });
        report.checks.push({ width, avatarStates, ctaTopRight: true, noOverflow: true });
    }
    await cta.click();
    await page.waitForURL('http://127.0.0.1:8028/family/requests/create');
    assert.ok(await page.locator('main input').count() > 0);
    report.checks.push({ newCareButtonOpensCreationForm: true });
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    await browser.close();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
}
