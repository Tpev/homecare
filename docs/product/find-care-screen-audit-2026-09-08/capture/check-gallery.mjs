import fs from 'node:fs';
import path from 'node:path';
import {chromium} from 'playwright';
import {output, galleryBase} from './environment.mjs';

const expected = JSON.parse(fs.readFileSync(path.join(output, 'captures.json'), 'utf8')).length;
const result = {checkedAt: new Date().toISOString(), url: galleryBase, assertions: [], errors: []};
const assert = (condition, description, detail) => { result.assertions.push({description, passed: Boolean(condition), detail}); if (!condition) throw new Error(description); };
const browser = await chromium.launch({channel: 'msedge', headless: true});
try {
  const context = await browser.newContext({viewport: {width: 1440, height: 1000}, reducedMotion: 'reduce'});
  const page = await context.newPage();
  page.on('pageerror', error => result.errors.push(error.message));
  const response = await page.goto(galleryBase, {timeout: 90000});
  assert(response?.status() === 200, 'Gallery returns HTTP200');
  await page.getByRole('heading', {name: 'Finding care, screen by screen.', exact: true}).waitFor();
  assert(await page.locator('.screen-card').count() === expected, 'Every capture state is present in gallery', expected);
  await page.getByLabel('Search screen or visible content', {exact: true}).fill('OT-hire-review');
  assert(await page.locator('.screen-card:visible').count() === 1, 'Text search narrows to intended screen');
  assert(await page.locator('.screen-card:visible').getAttribute('id') === 'OT-hire-review', 'Search result has correct identity');
  await page.getByLabel('Search screen or visible content', {exact: true}).fill('');
  await page.getByLabel('Care variation', {exact: true}).selectOption('recurring');
  const recurring = await page.locator('.screen-card:visible').count();
  assert(recurring > 0 && recurring < expected, 'Variation filter narrows the gallery', recurring);
  await page.getByLabel('Lifecycle stage', {exact: true}).selectOption('Closed');
  const closed = await page.locator('.screen-card:visible').count();
  assert(closed > 0 && closed < recurring, 'Stage filter combines with variation filter', closed);
  assert(await page.locator('.screen-card:visible').evaluateAll(cards => cards.every(card => card.dataset.variation === 'recurring' && card.dataset.stage === 'Closed')), 'Filtered cards match both selected values');
  const card = page.locator('.screen-card:visible').first();
  const screenshotPath = await card.locator('.preview-link').first().getAttribute('href');
  const screenshot = await context.request.get(new URL(screenshotPath, galleryBase).href);
  assert(screenshot.ok() && screenshot.headers()['content-type']?.startsWith('image/png'), 'Screenshot link resolves to a PNG', screenshotPath);
  const inventoryPath = await card.locator('.inventory-link').getAttribute('href');
  const inventory = await context.request.get(new URL(inventoryPath, galleryBase).href);
  assert(inventory.ok() && (await inventory.text()).includes('Visible headings'), 'Information inventory link resolves to its screen report', inventoryPath);
  await page.getByLabel('Lifecycle stage', {exact: true}).selectOption('');
  await page.getByLabel('Care variation', {exact: true}).selectOption('');
  await page.evaluate(() => scrollTo(0, 0));
  fs.mkdirSync(path.join(output, 'validation'), {recursive: true});
  await page.screenshot({path: path.join(output, 'validation/gallery-desktop.png'), animations: 'disabled'});
  await page.setViewportSize({width: 390, height: 844});
  const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - innerWidth));
  assert(overflow <= 1, 'Gallery has no horizontal overflow at390px', overflow);
  await page.screenshot({path: path.join(output, 'validation/gallery-mobile.png'), animations: 'disabled'});
  assert(result.errors.length === 0, 'Gallery produces no browser script errors');
} catch (error) {
  result.failure = error.message;
} finally {
  await browser.close();
}
result.passed = !result.failure && result.assertions.every(assertion => assertion.passed) && !result.errors.length;
fs.writeFileSync(path.join(output, 'gallery-smoke.json'), JSON.stringify(result, null, 2));
console.log(JSON.stringify(result, null, 2));
if (!result.passed) process.exitCode = 1;
