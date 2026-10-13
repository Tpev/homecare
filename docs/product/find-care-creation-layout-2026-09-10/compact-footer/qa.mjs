import fs from 'node:fs';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const output = 'docs/product/find-care-creation-layout-2026-09-10/compact-footer';
fs.mkdirSync(`${output}/screenshots`, { recursive: true });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const report = { captures: [], errors: [] };
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
            if (calls.some(call => !['login', 'updateRecurringScheduleSlot'].includes(call.method))) {
                report.errors.push('Unexpected mutation blocked: ' + JSON.stringify(calls));
                return route.abort();
            }
        }
        return route.continue();
    });
    const settle = async () => { await page.waitForTimeout(400); await page.waitForLoadState('networkidle'); };
    const change = async (field, value) => { await field.fill(value); await field.press('Tab'); await settle(); };
    await page.goto('http://127.0.0.1:8028/login');
    await page.getByLabel('Email', { exact: true }).fill('maria.find-care-audit@example.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));

    for (const type of ['one_time', 'recurring']) {
        await page.goto('http://127.0.0.1:8028/family/requests/create?type=' + type);
        await settle();
        if (type === 'one_time') {
            await change(page.getByLabel('Starting day', { exact: true }), '2026-09-16');
            await change(page.getByLabel('Starting time', { exact: true }), '10:00');
            await page.getByLabel('Duration (HH:MM)', { exact: true }).selectOption('120');
            await settle();
        } else {
            await page.locator('#care-schedule input[type="checkbox"][value="3"]').locator('..').click();
            await settle();
            await change(page.locator('#recurring-start-3'), '10:00');
            await page.locator('#recurring-duration-3').selectOption('120');
            await settle();
            await change(page.getByLabel('When should this schedule begin?', { exact: true }), '2026-09-16');
            await page.getByText('First planned visit:', { exact: true }).waitFor();
        }
        await page.locator('.hc-create-estimate-total').waitFor();
        assert.equal(await page.locator('.hc-create-review-list,.hc-create-readiness,.hc-create-review-panel').count(), 0);
        await page.getByLabel('Make this request private', { exact: false }).check();
        await settle();
        await page.getByRole('button', { name: 'Create private request', exact: true }).waitFor();
        assert.equal(await page.getByRole('button', { name: 'Create private request', exact: true }).count(), 1);
        await page.locator('#private-care-request').uncheck();
        await settle();
        await page.getByRole('button', { name: 'Publish request', exact: true }).waitFor();

        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: 1000 });
            await page.evaluate(() => document.fonts.ready);
            await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
            const metrics = await page.locator('.hc-create-checkout').evaluate(el => ({ height: el.getBoundingClientRect().height, overflow: document.documentElement.scrollWidth > innerWidth, width: innerWidth }));
            assert.equal(metrics.overflow, false);
            assert.ok(metrics.height < 450, `Footer too tall: ${metrics.height}px at ${width}px`);
            await page.locator('.hc-create-checkout').screenshot({ path: `${output}/screenshots/${type}-${width}-footer.png` });
            await page.getByText('Cost breakdown', { exact: true }).click();
            assert.equal(await page.locator('.hc-create-cost-details').evaluate(el => el.open), true);
            await page.getByText('Processing fee ·', { exact: false }).waitFor();
            await page.locator('.hc-create-checkout').screenshot({ path: `${output}/screenshots/${type}-${width}-expanded.png` });
            await page.getByText('Cost breakdown', { exact: true }).click();
            report.captures.push({ type, ...metrics });
        }
    }
    assert.deepEqual(report.errors, []);
    report.passed = true;
} finally {
    await browser.close();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
}
