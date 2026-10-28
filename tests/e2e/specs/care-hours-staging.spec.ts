import { expect, test, type Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { stagingEnvironment, stagingRoot } from '../care-hours-staging-environment';

type Fixture = { run_id: string; payment_mode: 'stripe_test' | 'bypass'; request_id: number; account_id: number; purchase_at: string; visit_start: string; visit_end: string };
type ScreenshotStep = { order: number; id: string; title: string; role: string; url: string; application_time: string; files: string[] };

test('customer buys care hours, books and completes care, approves hours, and caregiver receives a transfer', async ({ page }) => {
    page.setDefaultTimeout(20_000);
    const resumeAfterPurchase = process.env.CARE_HOURS_STAGING_RESUME === 'after-purchase';
    const fixture: Fixture = JSON.parse(fs.readFileSync(path.join(stagingRoot, 'fixture.json'), 'utf8'));
    const screenshots = path.join(stagingRoot, 'screenshots');
    const steps: ScreenshotStep[] = [];
    if (resumeAfterPurchase) {
        const previous = JSON.parse(fs.readFileSync(path.join(screenshots, 'manifest.json'), 'utf8'));
        expect(previous.run_id).toBe(fixture.run_id);
        expect(previous.payment_mode).toBe(fixture.payment_mode);
        expect(previous.steps.at(-1)?.id).toBe('06-hours-available');
        steps.push(...previous.steps);
    }
    let applicationTime = fixture.purchase_at;
    await page.clock.setFixedTime(new Date(applicationTime));
    const advanceClock = async (now: string, stage: string) => {
        applicationTime = now;
        fs.writeFileSync(path.join(stagingRoot, 'clock.json'), JSON.stringify({ now, stage }, null, 2));
        await page.clock.setFixedTime(new Date(now));
    };
    const capture = async (id: string, title: string, role: string, focus?: Locator, mobile = false) => {
        const files: string[] = [];
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: path.join(screenshots, `${id}-full.png`), fullPage: true, animations: 'disabled' });
        files.push(`${id}-full.png`);
        if (focus) {
            await focus.screenshot({ path: path.join(screenshots, `${id}-focus.png`), animations: 'disabled' });
            files.push(`${id}-focus.png`);
        }
        if (mobile) {
            await page.setViewportSize({ width: 375, height: 812 });
            await page.evaluate(() => window.scrollTo(0, 0));
            expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
            await page.screenshot({ path: path.join(screenshots, `${id}-mobile.png`), fullPage: true, animations: 'disabled' });
            files.push(`${id}-mobile.png`);
            await page.setViewportSize({ width: 1440, height: 1000 });
        }
        // Checkout URLs contain a client token; publish only the provider origin in the screenshot index.
        const url = page.url().startsWith('https://checkout.stripe.com') ? 'https://checkout.stripe.com/[test-checkout]' : page.url();
        steps.push({ order: steps.length + 1, id, title, role, url, application_time: applicationTime, files });
        fs.writeFileSync(path.join(stagingRoot, 'screenshots', 'manifest.json'), JSON.stringify({
            run_id: fixture.run_id, payment_mode: fixture.payment_mode, scale: 2,
            time_control: 'Time and GPS are controlled test inputs. The isolated local router and browser clock advance between actual check-in/check-out UI actions. No worked time, balance, purchase, booking or transfer is inserted by this test.',
            steps,
        }, null, 2));
    };
    const login = async (email: string) => {
        await page.context().clearCookies();
        await page.goto('/login');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Password').fill('Staging-only-2026!');
        await page.getByRole('button', { name: 'Log in', exact: true }).click();
        await expect(page).not.toHaveURL(/\/login$/);
    };
    const snapshot = () => JSON.parse(execFileSync('php', ['tests/e2e/care-hours-staging-fixtures.php', 'snapshot'], {
        cwd: process.cwd(), env: { ...process.env, ...stagingEnvironment() }, encoding: 'utf8', windowsHide: true,
    }));

    if (resumeAfterPurchase) {
        const existing = snapshot();
        expect(existing.run_id).toBe(fixture.run_id);
        expect(existing.purchase?.status).toBe('issued');
        expect(existing.balance.available_minutes).toBe(660);
        expect(existing.booking).toBeNull();
    }
    const balance = page.getByRole('region', { name: 'Care hours balance' });
    await login('emma.bennett@example.test');
    if (!resumeAfterPurchase) {
    await page.goto('/family/requests');
    await expect(page.getByRole('link', { name: 'Care hours', exact: true })).toBeVisible();
    await capture('01-care-overview', 'Emma opens Care hours from My care', 'Family');
    await page.getByRole('link', { name: 'Care hours', exact: true }).click();
    await expect(balance).toContainText('0 h');
    await capture('02-empty-balance', 'Robert has no prepaid hours yet', 'Family', balance);

    await page.getByRole('link', { name: 'Buy hours', exact: true }).click();
    const packageChoice = page.locator('label').filter({ has: page.locator('input[name="hours"][value="10"]') });
    await expect(packageChoice).toContainText('+ 1 h free');
    await expect(packageChoice).toContainText('$310');
    await page.locator('input[name="hours"][value="10"]').check();
    await page.locator('input[name="terms"]').check();
    await capture('03-package-choice', 'Emma buys 10 hours for $310 and receives 1 welcome hour', 'Family', page.locator('main'), true);
    await page.getByRole('button', { name: 'Continue to payment', exact: true }).click();

    if (fixture.payment_mode === 'stripe_test') {
        await expect(page).toHaveURL(/^https:\/\/checkout\.stripe\.com\//, { timeout: 60_000 });
        await expect(page.getByRole('heading', { name: 'Payment method', exact: true })).toBeVisible({ timeout: 30_000 });
        await page.screenshot({ path: path.join(stagingRoot, 'checkout-before-input.png'), fullPage: true });
        // Hosted Checkout can offer adaptive currency and collapsed payment-method choices.
        if (await page.getByText('Choose currency', { exact: true }).isVisible()) {
            await page.getByRole('button', { name: 'US $310.00', exact: true }).click();
        }
        // Stripe's transparent CSS-expanded overlay has no own box; click the visible method label.
        const cardLabel = page.getByText('Card', { exact: true });
        await expect(cardLabel).toBeVisible();
        const cardBox = await cardLabel.boundingBox();
        if (!cardBox) throw new Error('The visible Stripe card choice has no click target.');
        await page.mouse.click(cardBox.x + cardBox.width / 2, cardBox.y + cardBox.height / 2);
        // These are official Stripe test-card values. No real card or live-mode payment is used.
        await page.locator('input[autocomplete="cc-number"], #cardNumber').first().fill('4242424242424242');
        await page.locator('input[autocomplete="cc-exp"], #cardExpiry').first().fill('1230');
        await page.locator('input[autocomplete="cc-csc"], #cardCvc').first().fill('123');
        const name = page.locator('input[autocomplete="cc-name"], #billingName').first();
        if (await name.isVisible()) await name.fill('Emma Bennett');
        const country = page.locator('#billingCountry').first();
        if (await country.isVisible()) await country.selectOption('US');
        const postalCode = page.locator('input[autocomplete="postal-code"], #billingPostalCode').first();
        if (await postalCode.isVisible()) await postalCode.fill('27601');
        await capture('04-stripe-test-checkout', 'Secure Stripe Checkout in the isolated test sandbox', 'Family');
        await page.locator('button[type="submit"]').filter({ hasText: /Pay|Subscribe/ }).last().click();
    }
    await expect(page).toHaveURL(/\/family\/care-hours\/purchases\//, { timeout: 90_000 });
    await expect(page.getByText(/11 (care )?hours added|11 h added|Hours added/i).first()).toBeVisible({ timeout: 30_000 });
    // A standalone PHP server has no scheduler. Run the actual scheduled outbox/reconciliation command.
    execFileSync('php', ['tests/e2e/care-hours-staging-fixtures.php', 'deliver-notices'], {
        cwd: process.cwd(), env: { ...process.env, ...stagingEnvironment() }, encoding: 'utf8', windowsHide: true,
    });
    await capture('05-purchase-receipt', 'Payment succeeds and 11 hours are added', 'Family', page.locator('main'));
    await page.goto('/family/care-hours');
    await expect(balance).toContainText('11 h');
    await capture('06-hours-available', 'Robert now has 11 hours available', 'Family', balance, true);
    }

    await page.goto(`/family/requests/${fixture.request_id}?tab=applicants`);
    await page.getByTestId('hire-caregiver').click();
    await expect(page.getByRole('radio', { name: /Use care hours/ })).toBeChecked();
    await page.getByRole('radio', { name: /Use care hours/ }).check();
    await expect(page.getByRole('complementary', { name: 'Care hours coverage' })).toContainText(/Remaining payment\s*\$0\.00/);
    await capture('07-book-with-hours', 'Emma chooses care hours for Claire’s two-hour visit; $0 remains to pay', 'Family', page.locator('#care-decision-review'), true);
    await page.getByRole('button', { name: 'Confirm hire', exact: true }).click();
    const coverage = page.getByRole('region', { name: 'Care-hour payment', exact: true });
    await expect(coverage).toContainText('2 h reserved for this visit.');
    await expect(coverage).toContainText('Scheduled care is covered.');
    await capture('08-visit-booked', 'Claire is booked and two hours are reserved', 'Family', coverage);
    await page.goto('/family/care-hours');
    await expect(balance).toContainText('9 h');
    await expect(balance).toContainText('2 h reserved');
    await capture('09-hours-reserved', 'Nine hours remain available; two hours are set aside for the visit', 'Family', balance);

    await advanceClock(fixture.visit_start, 'caregiver-check-in');
    await login('claire.martin@example.test');
    await page.goto(`/care-requests/${fixture.request_id}/apply`);
    const shiftTab = page.locator('button[wire\\:click="setActiveTab(\'shift\')"]').first();
    if (await shiftTab.isVisible()) await shiftTab.click();
    await page.getByRole('button', { name: 'Accept agreement', exact: true }).click();
    await page.getByRole('button', { name: 'Start visit', exact: true }).click();
    await expect(page.getByText(/Visit started with GPS check-in|Visit started\./).first()).toBeVisible();
    await capture('10-caregiver-check-in', 'Claire checks in at 9:00 AM with test location captured', 'Caregiver', page.locator('[x-data^="homecareShiftFocus"]'));

    await advanceClock(fixture.visit_end, 'caregiver-check-out');
    await page.reload();
    await page.getByRole('button', { name: 'End visit', exact: true }).click();
    await expect(page.getByText('Visit completed. Review your recap below.', { exact: true })).toBeVisible();
    const recap = page.locator('[x-data^="homecareShiftFocus"]');
    await expect(recap).toContainText('Worked time');
    await capture('11-caregiver-check-out', 'Claire ends the visit at 11:00 AM; two worked hours are submitted', 'Caregiver', recap);

    await login('emma.bennett@example.test');
    await page.goto(`/family/requests/${fixture.request_id}`);
    await expect(page.locator('#care-request-primary')).toContainText('$0.00 remaining');
    await capture('12-family-review-ready', 'Emma is asked to review the two hours; no separate payment is due', 'Family', page.locator('#care-request-primary'));
    await page.getByRole('button', { name: 'Review hours & payment', exact: true }).click();
    await expect(page.locator('#care-decision-review')).toContainText('Care hours to use');
    await expect(page.locator('#care-decision-review')).toContainText('$0.00');
    await capture('13-family-approval', 'Emma reviews and approves two hours from Robert’s balance', 'Family', page.locator('#care-decision-review'), true);
    await page.getByRole('button', { name: 'Approve hours', exact: true }).click();
    await expect(coverage).toContainText('Paid with care hours');
    await expect(coverage).toContainText('2 h used · 9 h still available.');
    await capture('14-visit-paid', 'The completed visit is paid with two care hours', 'Family', coverage);
    await page.goto('/family/care-hours');
    await expect(balance).toContainText('9 h');
    await expect(balance).not.toContainText('2 h reserved');
    await capture('15-final-balance', 'Nine hours remain for future care and the visit appears in history', 'Family', page.locator('main'), true);

    await login('care-hours.admin@example.test');
    await page.goto(`/admin/care-hours/${fixture.account_id}`);
    await page.locator('summary').filter({ hasText: 'Payment details and recovery' }).click();
    // If fee information is still propagating, use the product's audited reconciliation action.
    for (let attempt = 0; attempt < 3 && snapshot().settlement?.status !== 'paid'; attempt++) {
        await page.getByLabel('Reason for visit recheck').fill('Isolated staging verification after actual test payment');
        await page.getByRole('button', { name: 'Recheck visit payments', exact: true }).click();
        await page.locator('summary').filter({ hasText: 'Payment details and recovery' }).click();
    }
    const operationDetails = page.locator('details').filter({ has: page.locator('summary').filter({ hasText: 'Payment details and recovery' }) });
    await expect(operationDetails).toContainText('Caregiver gross $54.00');
    await expect(operationDetails).toContainText('Succeeded');
    await capture('16-caregiver-transfer', 'Admin verifies the caregiver transfer after the proportional processing fee', 'Admin', operationDetails);

    await login('claire.martin@example.test');
    await page.goto('/caregiver/earnings?tab=shifts');
    const paidSnapshot = snapshot();
    await expect(page.locator('main')).toContainText('Transferred');
    await expect(page.locator('main')).toContainText((paidSnapshot.settlement.caregiver_net_cents / 100).toFixed(2));
    await capture('17-caregiver-earnings', 'Claire sees the completed visit and transfer in her earnings', 'Caregiver', page.locator('main'), true);

    if (fixture.payment_mode === 'stripe_test') {
        await expect.poll(() => snapshot().webhook_coverage.ready, { timeout: 30_000, intervals: [1000, 2000, 3000] }).toBe(true);
    }

    execFileSync('php', ['tests/e2e/care-hours-staging-fixtures.php', 'verify'], {
        cwd: process.cwd(), env: { ...process.env, ...stagingEnvironment() }, encoding: 'utf8', windowsHide: true,
    });
});
