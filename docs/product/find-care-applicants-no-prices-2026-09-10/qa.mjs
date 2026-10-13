import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { chromium } from 'playwright';
import { env, root } from '../find-care-screen-audit-2026-09-08/capture/environment.mjs';

const output = path.resolve('docs/product/find-care-applicants-no-prices-2026-09-10');
const runtime = path.resolve('.tmp/find-care-applicants-no-prices-2026-09-10');
fs.mkdirSync(`${output}/screenshots`, { recursive: true });
fs.mkdirSync(`${runtime}/views`, { recursive: true });
// QA uses a disposable copy of the pre-cleanup backup; the running app DB is untouched.
fs.copyFileSync('.tmp/maria-request-cleanup-20260910-195402-applied/before.sqlite', `${runtime}/qa.sqlite`);
const listener = net.createServer();
await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
const port = listener.address().port;
await new Promise(resolve => listener.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('C:/php/php.exe', ['-d', 'zend_extension=opcache', '-d', 'opcache.enable_cli=1', '-S', `127.0.0.1:${port}`, `${root}/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`], {
    cwd: `${root}/public`, windowsHide: true,
    env: { ...env, APP_URL: base, DB_DATABASE: `${runtime}/qa.sqlite`, VIEW_COMPILED_PATH: `${runtime}/views`, SESSION_COOKIE: 'applicant_no_prices_qa', QUEUE_CONNECTION: 'sync', APP_CONFIG_CACHE: `${runtime}/config.php`, APP_ROUTES_CACHE: `${runtime}/routes.php` },
    stdio: ['ignore', 'ignore', 'pipe'],
});
let serverErrors = '';
server.stderr.on('data', chunk => { serverErrors += chunk.toString(); });
let browser;
const report = { checks: [], errors: [], blockedActions: [] };
try {
    let ready = false;
    for (let attempt = 0; attempt < 30; attempt++) {
        try { const response = await fetch(`${base}/login`); if (response.ok) { ready = true; break; } } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    assert.ok(ready, 'Isolated QA server must be ready');
    browser = await chromium.launch({ channel: 'msedge', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    page.setDefaultTimeout(45000);
    page.on('pageerror', error => report.errors.push(error.message));
    await page.route('**/*', route => {
        const request = route.request(), url = new URL(request.url());
        if (url.origin !== base) return route.abort();
        if (request.method() !== 'GET') {
            if (url.pathname !== '/livewire/update') return route.abort();
            const calls = request.postDataJSON()?.components?.flatMap(component => component.calls ?? []) ?? [];
            if (calls.some(call => !['login', 'setActiveTab', 'setApplicantView'].includes(call.method))) {
                report.blockedActions.push(calls.map(call => call.method));
                return route.abort();
            }
        }
        return route.continue();
    });
    const settle = async () => { await page.waitForTimeout(450); await page.waitForLoadState('networkidle'); };
    await page.goto(`${base}/login`);
    await page.getByLabel('Email', { exact: true }).fill('maria.find-care-audit@example.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    for (const [variation, requestId] of [['one-time', 28], ['recurring', 34]]) {
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 1000 });
            await page.goto(`${base}/family/requests/${requestId}?tab=applicants`);
            const surface = page.locator('[data-ai-target="family.request.applicants"]');
            await surface.waitFor();
            assert.ok(await surface.getByTestId('applicant-row').count() > 0);
            for (const list of ['all', 'shortlisted', 'past']) {
                await surface.locator(`[wire\\:click="setApplicantView('${list}')"]`).click();
                await surface.locator(`[wire\\:click="setApplicantView('${list}')"][aria-current="page"]`).waitFor();
                await settle();
                // Inspect collapsed content too, including legacy submitted rates and options.
                assert.doesNotMatch(await surface.textContent(), /\$\s*\d|\/\s*(hr|hour)|processing fee|platform fee|rate recorded|Care rate high-low|Care rate low-high/i);
                assert.equal(await surface.locator('.hc-candidate-rate, option[value="rate_low"], option[value="rate_high"]').count(), 0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
                report.checks.push({ variation, width, list, rows: await surface.getByTestId('applicant-row').count(), noPrices: true, noRateSorting: true, noOverflow: true });
                if (list === 'all') {
                    await page.evaluate(() => document.fonts.ready);
                    await page.screenshot({ path: `${output}/screenshots/${variation}-${width}.png`, fullPage: true });
                    await surface.locator('.hc-recruit-filters > summary').click();
                    const details = surface.locator('.hc-profile-disclosure > summary').first();
                    if (await details.count()) await details.click();
                    await page.screenshot({ path: `${output}/screenshots/${variation}-details-${width}.png`, fullPage: true });
                }
            }
        }
    }
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    if (browser) await browser.close();
    server.kill();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    if (!report.passed) fs.writeFileSync(`${runtime}/server.log`, serverErrors);
    console.log(JSON.stringify(report));
}
