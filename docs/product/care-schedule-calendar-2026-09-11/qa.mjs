import { chromium } from 'playwright';
import fs from 'node:fs';
import assert from 'node:assert/strict';

const output = 'docs/product/care-schedule-calendar-2026-09-11';
const base = 'http://127.0.0.1:8028';
fs.mkdirSync(`${output}/screenshots`, { recursive: true });
const report = { checks: [], errors: [], blockedActions: [] };
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1100 }, reducedMotion: 'reduce' });
    page.setDefaultTimeout(45000);
    page.on('pageerror', error => report.errors.push(error.message));
    await page.route('**/*', route => {
        const request = route.request(), url = new URL(request.url());
        if (url.origin !== base) return route.abort();
        if (request.method() !== 'GET') {
            if (url.pathname !== '/livewire/update') return route.abort();
            const calls = request.postDataJSON()?.components?.flatMap(component => component.calls ?? []) ?? [];
            const allowed = ['login', 'setView', 'previousMonth', 'nextMonth', 'goToToday', 'selectDate', 'loadMoreVisits'];
            if (calls.some(call => !allowed.includes(call.method))) {
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
    await page.goto(`${base}/family/care/schedule`);
    const main = page.locator('main');
    assert.equal(await main.getByRole('button', { name: 'List', exact: true }).getAttribute('aria-pressed'), 'true');
    const listCount = await main.locator('section[aria-label="Upcoming care timeline"] article').count();
    await page.screenshot({ path: `${output}/screenshots/list-1440.png`, fullPage: true });
    await main.getByRole('button', { name: 'Calendar', exact: true }).click();
    await main.locator('.hc-schedule-toggle button[aria-pressed="true"]').filter({ hasText: 'Calendar' }).waitFor();
    const initialMonth = await main.locator('#care-calendar-month').innerText();
    const dates = await main.locator('.hc-calendar-day').evaluateAll(days => days.filter(day => !day.getAttribute('aria-label').includes('— 0 upcoming')).map(day => day.dataset.date));
    assert.ok(dates.length > 0, 'Live schedule should show booked dates immediately.');
    // Dense-month completeness is covered by isolated feature fixtures; live care can span months.
    await main.locator(`.hc-calendar-day[data-date="${dates.at(-1)}"]`).click();
    await main.locator(`.hc-calendar-day[data-date="${dates.at(-1)}"][aria-pressed="true"]`).waitFor();
    assert.ok(await main.locator('.hc-calendar-visit').count() > 0);
    await page.waitForFunction(() => document.querySelector('#care-calendar-day-visits').getBoundingClientRect().bottom <= innerHeight + 2);
    const selectedDate = dates.at(-1);
    for (const width of [1440, 768, 390, 320]) {
        await page.setViewportSize({ width, height: 1100 });
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Overflow at ${width}`);
        assert.ok(await main.getByRole('button', { name: 'Today', exact: true }).isVisible());
        assert.ok(await main.locator('.hc-calendar-visit').first().isVisible());
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: `${output}/screenshots/calendar-${width}.png`, fullPage: true });
        report.checks.push({ width, noOverflow: true, selectedDate });
    }
    await main.getByRole('button', { name: 'Next month', exact: true }).click();
    await page.waitForFunction(previous => document.querySelector('#care-calendar-month')?.textContent !== previous, initialMonth);
    const nextMonth = await main.locator('#care-calendar-month').innerText();
    await page.screenshot({ path: `${output}/screenshots/calendar-empty-320.png`, fullPage: true });
    await main.getByRole('button', { name: 'Previous month', exact: true }).click();
    await page.waitForFunction(previous => document.querySelector('#care-calendar-month')?.textContent !== previous, nextMonth);
    await main.getByLabel('Care type', { exact: true }).selectOption('one_time');
    await page.waitForURL(url => url.searchParams.get('type') === 'one_time');
    await main.getByRole('button', { name: 'List', exact: true }).click();
    await main.locator('.hc-schedule-toggle button[aria-pressed="true"]').filter({ hasText: 'List' }).waitFor();
    assert.equal(await main.getByLabel('Care type', { exact: true }).inputValue(), 'one_time');
    await main.getByRole('button', { name: 'Calendar', exact: true }).click();
    await main.locator('.hc-schedule-toggle button[aria-pressed="true"]').filter({ hasText: 'Calendar' }).waitFor();
    await page.reload();
    assert.equal(await main.getByRole('button', { name: 'Calendar', exact: true }).getAttribute('aria-pressed'), 'true');
    assert.equal(await main.getByLabel('Care type', { exact: true }).inputValue(), 'one_time');
    await main.getByRole('button', { name: 'Today', exact: true }).click();
    await main.locator('.hc-calendar-day[aria-current="date"][aria-pressed="true"]').waitFor();
    await main.getByLabel('Care type', { exact: true }).selectOption('all');
    await page.waitForURL(url => !url.searchParams.has('type') || url.searchParams.get('type') === 'all');
    await page.goto(`${base}/family/care/schedule?view=calendar&day=${selectedDate}`);
    assert.equal(await main.locator('.hc-calendar-day[aria-pressed="true"]').getAttribute('data-date'), selectedDate);
    const visitLink = main.getByRole('link', { name: 'View visit', exact: true }).first();
    const visitUrl = await visitLink.getAttribute('href');
    assert.match(visitUrl, /\/family\/requests\/\d+\?tab=shift$/);
    await visitLink.click();
    await page.waitForURL(visitUrl);
    report.checks.push({ defaultList: true, listCount, initialMonth, calendarBookedDates: dates.length, monthNavigation: true, filtersPreserved: true, urlRestoresView: true, todaySelectsToday: true, dayOnlyUrl: true, dateClickRevealsDetails: true, exactVisitUrl: visitUrl });
    assert.deepEqual(report.errors, []);
    assert.deepEqual(report.blockedActions, []);
    report.passed = true;
} finally {
    await browser.close();
    fs.writeFileSync(`${output}/validation.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
}
