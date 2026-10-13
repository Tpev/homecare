import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { env, root } from '../find-care-screen-audit-2026-09-08/capture/environment.mjs';

const output = path.resolve('docs/product/care-release-review-2026-09-11');
const runtime = path.resolve('.tmp/preprod-snapshot');
fs.mkdirSync(`${runtime}/views`, { recursive: true });
fs.mkdirSync(`${output}/screenshots`, { recursive: true });
const source = '.tmp/maria-request-cleanup-20260910-195402-applied/before.sqlite';
const database = `${runtime}/qa.sqlite`;
fs.copyFileSync(source, database);
const inventory = () => JSON.parse(execFileSync('C:/php/php.exe', [`${output}/snapshot-inventory.php`, database], { cwd: root, windowsHide: true, encoding: 'utf8' }));
const before = inventory();
const listener = net.createServer();
await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
const port = listener.address().port;
await new Promise(resolve => listener.close(resolve));
const base = `http://127.0.0.1:${port}`;
const isolatedEnv = { ...env, APP_URL: base, DB_DATABASE: database, VIEW_COMPILED_PATH: `${runtime}/views`,
    APP_CONFIG_CACHE: '.tmp/preprod-snapshot/config.php', APP_ROUTES_CACHE: '.tmp/preprod-snapshot/routes.php',
    SESSION_COOKIE: 'preprod_snapshot_qa', QUEUE_CONNECTION: 'database' };
const run = (command, args, commandEnv = isolatedEnv) => new Promise((resolve, reject) => {
    const child = spawn(command, args, { cwd: root, windowsHide: true, env: commandEnv, stdio: ['ignore', 'pipe', 'pipe'] });
    let outputText = '';
    child.stdout.on('data', chunk => outputText += chunk);
    child.stderr.on('data', chunk => outputText += chunk);
    child.on('error', reject);
    child.on('close', code => code === 0 ? resolve(outputText) : reject(new Error(`${args.join(' ')} failed: ${outputText.slice(-3000)}`)));
});
const report = { source: 'Synthetic pre-cleanup September 10 backup; disposable copy', requests: before.requests, plans: before.plans, cacheChecks: [], routes: [], browserChecks: [], errors: [], blockedActions: [] };
let server, browser;
try {
    // Exercise the cached deployment path with providers disabled and synthetic data only.
    for (const command of ['config:cache', 'route:cache', 'view:cache']) {
        const result = await run('C:/php/php.exe', ['artisan', command], { ...isolatedEnv, APP_ENV: 'production', APP_DEBUG: 'false' });
        report.cacheChecks.push({ command, passed: true });
        console.log(`${command} passed`);
    }
    server = spawn('C:/php/php.exe', ['-d', 'zend_extension=opcache', '-d', 'opcache.enable_cli=1', '-S', `127.0.0.1:${port}`, `${root}/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`], {
        cwd: `${root}/public`, windowsHide: true, env: isolatedEnv, stdio: ['ignore', 'ignore', 'pipe'],
    });
    let serverErrors = '';
    server.stderr.on('data', chunk => { serverErrors += chunk.toString(); if (serverErrors.length > 40000) serverErrors = serverErrors.slice(-40000); });
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        try { if ((await fetch(`${base}/login`)).ok) { ready = true; break; } } catch {}
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    assert.ok(ready, 'Isolated cached server must start');
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
            if (calls.some(call => !['login', 'setActiveTab', 'setView', 'selectDate', 'refreshWidget'].includes(call.method))) {
                report.blockedActions.push(calls.map(call => call.method));
                return route.abort();
            }
        }
        return route.continue();
    });
    await page.goto(`${base}/login`);
    await page.getByLabel('Email', { exact: true }).fill('maria.find-care-audit@example.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    const urls = ['/family/requests', '/family/requests/create', '/family/care', '/family/care/schedule', '/family/care/schedule?view=calendar', '/family/care/history'];
    for (const request of before.requests) {
        for (const tab of ['', 'home', 'applicants', 'invite', 'shift', 'details', 'visits', 'support']) {
            urls.push(`/family/requests/${request.id}${tab ? `?tab=${tab}` : ''}`);
        }
    }
    for (const plan of before.plans) {
        for (const tab of ['', 'home', 'visits', 'caregivers', 'details', 'manage']) urls.push(`/family/care/${plan.id}${tab ? `?tab=${tab}` : ''}`);
        urls.push(`/family/care/journey/regular/${plan.id}`);
    }
    for (const url of urls) {
        const response = await page.request.get(`${base}${url}`);
        const status = response.status();
        report.routes.push({ url, status });
        if (status !== 200) report.errors.push(`${status}: ${url}`);
        if (report.routes.length % 40 === 0) console.log(`Checked ${report.routes.length}/${urls.length} legacy routes`);
    }
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const [label, url] of [
            ['one-time-applicants', '/family/requests/28?tab=applicants'],
            ['recurring-applicants', '/family/requests/34?tab=applicants'],
            ['legacy-visit', '/family/requests/5?tab=shift'],
            ['legacy-recurring', '/family/care/1?tab=visits'],
            ['history', '/family/care/history'],
            ['calendar', '/family/care/schedule?view=calendar'],
        ]) {
            await page.goto(`${base}${url}`);
            await page.evaluate(() => document.fonts.ready);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
            if (overflow) report.errors.push(`Horizontal overflow: ${label} at ${width}`);
            if (label.includes('applicants')) {
                const surface = page.locator('[data-ai-target="family.request.applicants"]');
                assert.doesNotMatch(await surface.textContent(), /\$\s*\d|\/\s*(hr|hour)|Care rate high-low|Care rate low-high/i);
            }
            await page.screenshot({ path: `${output}/screenshots/${label}-${width}.png`, fullPage: true });
            report.browserChecks.push({ label, width, overflow });
        }
    }
    for (const asset of ['/fonts/Inter-Variable.woff', '/fonts/SourceSerif4-Variable.woff']) {
        assert.equal((await page.request.get(`${base}${asset}`)).status(), 200);
    }
    const after = inventory();
    assert.deepEqual(after.businessTables, before.businessTables, 'Opening existing care must preserve every business row');
    report.businessTablesUnchanged = Object.entries(after.businessTables).map(([table, value]) => ({ table, rows: value.rows }));
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    if (browser) await browser.close();
    if (server) server.kill();
    fs.writeFileSync(`${output}/snapshot-validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ passed: report.passed ?? false, routes: report.routes.length, errors: report.errors, browserChecks: report.browserChecks.length }));
}
