import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {chromium} from 'playwright';
import {output, base} from './environment.mjs';

const manifest = JSON.parse(fs.readFileSync(path.join(output, 'fixture-manifest.json'), 'utf8'));
const roles = [
  {role: 'family', email: manifest.familyEmail, password: manifest.password, route: '/family/requests'},
  {role: 'caregiver', email: manifest.caregivers.sofia.email, password: manifest.password, route: '/caregiver/work-inbox'},
  {role: 'admin', email: manifest.admin.email, password: manifest.admin.password, route: '/admin/users'},
];
for (const directory of ['screenshots', 'evidence']) fs.mkdirSync(path.join(output, directory), {recursive: true});
const result = {checkedAt: new Date().toISOString(), base, buildHash: crypto.createHash('sha256').update(fs.readFileSync(path.resolve('public/build/manifest.json'))).digest('hex'), checks: [], errors: []};
const browser = await chromium.launch({channel: 'msedge', headless: true});
try {
  for (const actor of roles) for (const [device, viewport] of Object.entries({desktop: {width: 1440, height: 1000}, mobile: {width: 390, height: 844}})) {
    const context = await browser.newContext({viewport, deviceScaleFactor: 1, isMobile: device === 'mobile', hasTouch: device === 'mobile', locale: 'en-US', reducedMotion: 'reduce'});
    await context.route('**/*', route => {
      const url = new URL(route.request().url());
      return ['127.0.0.1', 'localhost', 'fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname) || ['data:', 'blob:'].includes(url.protocol) ? route.continue() : route.abort();
    });
    const page = await context.newPage();
    page.setDefaultTimeout(30000);
    page.on('pageerror', error => result.errors.push({role: actor.role, device, error: error.message, url: page.url()}));
    const check = {role: actor.role, email: actor.email, device, viewport, requestedRoute: actor.route, passed: false};
    try {
      await page.goto(base + '/login', {timeout: 90000});
      await page.getByLabel('Email', {exact: true}).fill(actor.email);
      await page.getByLabel('Password', {exact: true}).fill(actor.password);
      await page.getByRole('button', {name: 'Log in', exact: true}).click();
      await page.waitForURL(url => !url.pathname.endsWith('/login'), {timeout: 60000});
      check.loginRedirect = new URL(page.url()).pathname;
      const response = await page.goto(base + actor.route, {timeout: 90000});
      await page.waitForLoadState('networkidle');
      await page.evaluate(() => document.fonts.ready);
      check.status = response?.status();
      check.finalUrl = page.url();
      if (!response?.ok() || new URL(page.url()).pathname !== actor.route) throw new Error('Role route not accessible: ' + check.status + ' ' + check.finalUrl);
      const data = await page.evaluate(() => {
        const visible = el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
        return {text: document.body.innerText, headings: [...document.querySelectorAll('h1,h2,h3')].filter(visible).map(el => ({level: el.tagName, text: el.innerText})), controls: [...document.querySelectorAll('button,input:not([type=hidden]),select,textarea')].filter(visible).map(el => ({tag: el.tagName, type: el.type, label: el.getAttribute('aria-label') || [...(el.labels || [])].map(x => x.innerText).join(' / '), text: el.tagName === 'BUTTON' ? el.innerText : '', value: el.value, disabled: el.disabled})), links: [...document.querySelectorAll('a')].filter(visible).map(el => ({text: el.innerText, href: el.getAttribute('href')})), width: innerWidth, height: innerHeight, documentHeight: document.documentElement.scrollHeight, horizontalOverflow: Math.max(0, document.documentElement.scrollWidth - innerWidth)};
      });
      if (/Internal Server Error|ErrorException|SQLSTATE\[/.test(data.text)) throw new Error('Application error rendered');
      const prefix = 'ROLE-' + actor.role + '-' + device;
      check.viewportScreenshot = 'screenshots/' + prefix + '-viewport.png';
      check.fullScreenshot = 'screenshots/' + prefix + '-full.png';
      check.text = 'evidence/' + prefix + '.txt';
      check.inventory = 'evidence/' + prefix + '.json';
      await page.screenshot({path: path.join(output, check.viewportScreenshot), animations: 'disabled'});
      await page.screenshot({path: path.join(output, check.fullScreenshot), fullPage: true, animations: 'disabled'});
      fs.writeFileSync(path.join(output, check.text), data.text);
      fs.writeFileSync(path.join(output, check.inventory), JSON.stringify(data, null, 2));
      check.horizontalOverflow = data.horizontalOverflow;
      check.passed = true;
    } catch (error) { check.error = error.message; }
    result.checks.push(check);
    fs.writeFileSync(path.join(output, 'role-smoke.json'), JSON.stringify(result, null, 2));
    console.log(JSON.stringify(check));
    await context.close();
  }
} finally { await browser.close(); }
result.passed = result.checks.length === 6 && result.checks.every(check => check.passed) && result.errors.length === 0;
fs.writeFileSync(path.join(output, 'role-smoke.json'), JSON.stringify(result, null, 2));
const summary = ['# Local role login verification', '', `Checked: ${result.checkedAt}. Result: **${result.passed ? 'Passed' : 'Needs attention'}**. These six role/device checks are separate from lifecycle screen coverage.`, '', '[Account credentials and startup instructions](local-access.md)', '', '| Role | Device | Route | HTTP | Result | Screenshots | Visible information |', '|---|---|---|---|---|---|---|'];
for (const check of result.checks) summary.push(`| ${check.role} | ${check.device} | ${check.requestedRoute} | ${check.status ?? '—'} | ${check.passed ? 'Passed' : check.error} | [First screenful](${check.viewportScreenshot}) · [Full page](${check.fullScreenshot}) | [Text](${check.text}) · [Fields and controls](${check.inventory}) |`);
summary.push('', 'No messages were sent and no business operations were confirmed during these login checks.', '');
fs.writeFileSync(path.join(output, 'role-smoke.md'), summary.join('\n'));
if (!result.passed) process.exitCode = 1;
