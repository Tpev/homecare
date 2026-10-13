import { chromium } from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';

const output = 'docs/product/find-care-reply-message-2026-09-11';
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
    const card = page.locator('#care-actions a[href="http://127.0.0.1:8028/family/requests/47?tab=applicants"]');
    await card.waitFor();
    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.evaluate(() => document.fonts.ready);
        const text = await card.innerText();
        assert.match(text, /accepted your invitation/);
        assert.ok(text.includes('Review the response, then choose Hire and confirm your caregiver.'));
        assert.ok(text.includes('Review & hire'));
        assert.ok(text.includes('Request #47'));
        assert.doesNotMatch(text, /A caregiver is waiting for your review/);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        const box = await card.boundingBox();
        assert.ok(box.x >= 0 && box.x + box.width <= width);
        await page.screenshot({ path: `${output}/screenshots/reply-${width}.png`, fullPage: true });
        report.checks.push({ width, text, noOverflow: true });
    }
    await card.click();
    await page.waitForURL('http://127.0.0.1:8028/family/requests/47?tab=applicants');
    await page.locator('[data-ai-target="family.request.applicants"]').waitFor();
    assert.equal(await page.locator('[wire\\:click="confirmReviewedHire"]').count(), 0);
    report.checks.push({ reviewAndHireOpensApplicantsWithoutConfirmingHire: true });
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    await browser.close();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
}
