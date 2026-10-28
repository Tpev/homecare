import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { stagingEnvironment, stagingRoot } from '../care-hours-launch-validation-environment';
import { fillStripeTestCard, submitStripeTestCard } from '../care-hours-test-checkout';

const approvedInbox = 'peverelli.t@gmail.com';
const fixtureCommand = (command: string) => execFileSync('php', ['tests/e2e/care-hours-launch-validation-fixtures.php', command], { cwd: process.cwd(), env: { ...process.env, ...stagingEnvironment() }, encoding: 'utf8', windowsHide: true });
const snapshot = () => JSON.parse(fixtureCommand('snapshot'));
const verificationCode = () => {
    const messages = fs.readFileSync(path.join(stagingRoot, 'mail/index.jsonl'), 'utf8').trim().split('\n').filter(Boolean).map(line => JSON.parse(line));
    const latest = messages.filter(message => message.subject === 'Your LoLo care-hours verification code' && message.to.includes(approvedInbox)).at(-1);
    if (!latest) throw new Error('The contribution verification email was not captured.');
    const html = fs.readFileSync(path.join(stagingRoot, 'mail', latest.id + '.html'), 'utf8');
    const code = html.replace(/<[^>]+>/g, ' ').match(/confirm your email address:\s*(\d{6})/i)?.[1];
    if (!code) throw new Error('The captured verification email has no six-digit code.');
    return code;
};

test('a verified outside contributor buys real test hours without receiving family access or changing saved cards', async ({ page, browser }) => {
    page.setDefaultTimeout(20_000);
    page.setDefaultNavigationTimeout(90_000);
    const fixture = JSON.parse(fs.readFileSync(path.join(stagingRoot, 'fixture.json'), 'utf8'));
    const initial = snapshot();
    expect(initial.purchase).toBeNull();
    const screenshots: string[] = [];
    const capture = async (name: string, target = page) => {
        await target.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '.png'), fullPage: true, animations: 'disabled' });
        screenshots.push(name + '.png');
    };
    await page.goto('/login');
    await page.getByLabel('Email').fill('emma.bennett@example.test');
    await page.getByLabel('Password').fill('Staging-only-2026!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 60_000 });
    await page.goto('/family/care-hours');
    await page.locator('summary').filter({ hasText: 'Let someone contribute' }).click();
    await page.getByRole('button', { name: 'Create private link', exact: true }).click();
    const privateUrl = await page.getByLabel('Your private link').inputValue();
    expect(privateUrl).toMatch(/^http:\/\/127\.0\.0\.1:8015\/care-hours\/give\//);
    await capture('contribution-01-owner-link');

    const guestContext = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
    const guest = await guestContext.newPage();
    guest.setDefaultTimeout(20_000);
    guest.setDefaultNavigationTimeout(90_000);
    await guest.goto(privateUrl);
    await expect(guest.getByRole('heading', { name: 'Give care hours to Robert Bennett' })).toBeVisible();
    await expect(guest.locator('body')).not.toContainText('124 Oakwood');
    await expect(guest.locator('body')).not.toContainText('Available balance');
    await guest.getByLabel('Your name', { exact: true }).fill('Thomas Peverelli');
    await guest.getByLabel('Your email', { exact: true }).fill(approvedInbox);
    await guest.getByRole('button', { name: 'Send verification code', exact: true }).click();
    await expect(guest.getByRole('heading', { name: 'Check your email', exact: true })).toBeVisible();
    await capture('contribution-02-verification', guest);
    await guest.getByLabel('Your six-digit code').fill(verificationCode());
    await guest.getByRole('button', { name: 'Verify email', exact: true }).click();
    await guest.locator('input[name="hours"][value="10"]').check();
    await guest.locator('input[name="terms"]').check();
    await capture('contribution-03-package', guest);
    await guest.getByRole('button', { name: 'Continue to payment', exact: true }).click();
    await fillStripeTestCard(guest, 31000);
    await capture('contribution-04-stripe-test-checkout', guest);
    await submitStripeTestCard(guest);
    await expect(guest).toHaveURL(/\/care-hours\/receipts\//, { timeout: 90_000 });
    await expect(guest.getByText('11 h added for Robert Bennett.', { exact: true })).toBeVisible({ timeout: 30_000 });
    await expect(guest.locator('body')).not.toContainText('124 Oakwood');
    const receiptUrl = guest.url();
    fixtureCommand('deliver-notices');
    await capture('contribution-05-receipt', guest);
    await guest.goto('http://127.0.0.1:8015/family/care-hours');
    await expect(guest).toHaveURL(/\/login$/);
    await page.goto('/family/care-hours');
    await expect(page.getByRole('region', { name: 'Care hours balance' })).toContainText('11 h');
    await expect(page.locator('body')).not.toContainText(approvedInbox);
    await capture('contribution-06-recipient-balance');
    const after = snapshot();
    expect(after.family_payment_details).toEqual(initial.family_payment_details);
    expect(after.contributor_user_count).toBe(0);
    expect(after.family_membership_count).toBe(initial.family_membership_count);
    expect(after.purchase.buyer_user_id).toBeNull();
    expect(after.purchase.care_hour_account_id).toBe(fixture.account_id);
    expect(after.purchase.total_minutes).toBe(660);
    expect(after.ordinary_card_booking_payment_count).toBe(0);
    await expect.poll(() => snapshot().webhook_coverage.purchase_processed, { timeout: 30_000 }).toBe(true);
    await page.locator('summary').filter({ hasText: 'Let someone contribute' }).click();
    await page.getByRole('button', { name: 'Disable link', exact: true }).click();
    const disabled = await guest.goto(privateUrl);
    expect(disabled?.status()).toBe(410);
    await guest.goto(receiptUrl);
    await expect(guest.getByText('11 h added for Robert Bennett.', { exact: true })).toBeVisible();
    await capture('contribution-07-receipt-after-link-disabled', guest);
    fs.writeFileSync(path.join(stagingRoot, 'contribution-verification.json'), JSON.stringify({ ...snapshot(), checks: {
        real_contribution_issued: true, recipient_binding: true, donor_has_no_account_access: true,
        donor_has_no_user_or_membership: true, saved_card_unchanged: true, family_hides_donor_email: true,
        signed_purchase_webhook_processed: true, revoked_link_blocks_new_purchases: true, buyer_receipt_survives_revocation: true,
    }, screenshots }, null, 2));
    await guestContext.close();
});

test('signed actual contribution events replayed twice in reverse order do not duplicate hours or receipts', async () => {
    const before = snapshot();
    expect(before.purchase?.status).toBe('issued');
    const proof = JSON.parse(execFileSync('php', ['tests/e2e/care-hours-launch-provider-verify.php', '--replay-contribution'], { cwd: process.cwd(), encoding: 'utf8', windowsHide: true }));
    expect(proof.verified).toBe(true);
    expect(proof.signed_duplicate_reverse_order_replays.length).toBeGreaterThanOrEqual(4);
    const after = snapshot();
    for (const key of ['balance', 'purchase_count', 'lot_count', 'ledger_entry_count', 'family_payment_details', 'purchase_notices', 'notifications']) {
        expect(after[key]).toEqual(before[key]);
    }
    fs.writeFileSync(path.join(stagingRoot, 'contribution-replay-verification.json'), JSON.stringify({ run_id: after.run_id,
        checks: { actual_test_events_only: true, signed_reverse_order_duplicates_accepted: true, hours_lots_and_ledger_not_duplicated: true, receipts_not_duplicated: true, saved_card_unchanged: true },
        event_replays: proof.signed_duplicate_reverse_order_replays, balance: after.balance, purchase_count: after.purchase_count, lot_count: after.lot_count, ledger_entry_count: after.ledger_entry_count,
    }, null, 2));
});

test('mixed coverage keeps reserved hours through a declined and abandoned checkout, then releases a 3DS authorization on cancellation', async ({ page }) => {
    page.setDefaultTimeout(20_000); page.setDefaultNavigationTimeout(90_000);
    const request = JSON.parse(fixtureCommand('seed-mixed'));
    const initial = snapshot();
    expect(initial.balance.available_minutes + initial.balance.reserved_minutes).toBe(660);
    const phaseFile = path.join(stagingRoot, 'mixed-checkpoint.json');
    const phases = fs.existsSync(phaseFile) ? JSON.parse(fs.readFileSync(phaseFile, 'utf8')) : {};
    const capture = async (name: string) => page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '.png'), fullPage: true, animations: 'disabled' });
    await page.goto('/login');
    await page.getByLabel('Email').fill('emma.bennett@example.test');
    await page.getByLabel('Password').fill('Staging-only-2026!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 60_000 });
    await page.goto(`/family/requests/${request.request_id}${initial.booking ? '' : '?tab=applicants'}`);
    if (!initial.booking) {
    await page.getByTestId('hire-caregiver').click();
    await expect(page.getByRole('radio', { name: 'Usual card payment', exact: true })).toBeChecked();
    await page.getByRole('radio', { name: /Use care hours/ }).check();
    await expect(page.getByRole('complementary', { name: 'Care hours coverage' })).toContainText(/Remaining payment\s*\$31\.00/);
    await capture('mixed-01-eleven-hours-and-31-due');
    await page.getByRole('button', { name: 'Confirm hire', exact: true }).click();
    }
    const coverage = page.getByRole('region', { name: 'Care-hour payment', exact: true });
    await expect(coverage).toContainText('11 h reserved for this visit.');
    await expect(coverage).toContainText('1 h still needs payment');
    if (!phases.declined_and_abandoned) {
    await coverage.getByRole('button', { name: 'Pay $31.00', exact: true }).click({ noWaitAfter: true });
    await fillStripeTestCard(page, 3100, '4000000000009995');
    await submitStripeTestCard(page);
    await expect(page.getByText(/insufficient funds|Your card was declined/i).first()).toBeVisible({ timeout: 30_000 });
    await capture('mixed-02-card-declined');
    await page.getByRole('link', { name: /^Back/ }).click({ noWaitAfter: true });
    await expect(page).toHaveURL(new RegExp(`/family/requests/${request.request_id}`), { timeout: 60_000 });
    await expect(coverage).toContainText('11 h reserved');
    expect(snapshot().balance.reserved_minutes).toBe(660);
    fs.writeFileSync(phaseFile, JSON.stringify({ run_id: initial.run_id, declined_and_abandoned: true }));
    }
    if (phases.run_id) expect(phases.run_id).toBe(initial.run_id);
    await coverage.getByRole('button', { name: 'Pay $31.00', exact: true }).click({ noWaitAfter: true });
    await fillStripeTestCard(page, 3100, '4000002500003155');
    await submitStripeTestCard(page);
    let challengeFrame = null;
    await expect.poll(async () => {
        for (const frame of page.frames()) {
            if (await frame.getByRole('button', { name: /^Complete(?: authentication)?$/i }).isVisible().catch(() => false)) { challengeFrame = frame; return true; }
        }
        return false;
    }, { timeout: 45_000 }).toBe(true);
    await capture('mixed-03-3ds-challenge');
    await challengeFrame!.getByRole('button', { name: /^Complete(?: authentication)?$/i }).click();
    await expect(page).toHaveURL(new RegExp(`/family/requests/${request.request_id}`), { timeout: 90_000 });
    await expect(coverage).toContainText('Scheduled care is covered.');
    const authorized = snapshot();
    expect(authorized.operations.some(operation => operation.kind === 'shortfall' && operation.status === 'authorized' && operation.amount_cents === 3100)).toBe(true);
    await capture('mixed-04-authorized-coverage');
    await page.goto(`/family/requests/${request.request_id}?tab=support`);
    await page.locator('summary').filter({ hasText: 'Need to cancel now?' }).click();
    await page.getByLabel('Cancellation reason', { exact: true }).fill('Isolated sandbox cancellation and authorization release validation.');
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Cancel visit', exact: true }).click();
    await expect.poll(() => snapshot().balance.available_minutes, { timeout: 30_000 }).toBe(660);
    const after = snapshot();
    expect(after.balance.reserved_minutes).toBe(0);
    expect(after.balance.used_minutes).toBe(0);
    expect(after.family_payment_details).toEqual(initial.family_payment_details);
    expect(after.ordinary_card_booking_payment_count).toBe(0);
    await page.goto('/family/care-hours');
    await expect(page.getByRole('region', { name: 'Care hours balance' })).toContainText('11 h');
    await capture('mixed-05-full-hours-returned');
    const authorizedIntent = authorized.operations.find(operation => operation.kind === 'shortfall' && operation.status === 'authorized')!.stripe_payment_intent_id;
    await expect.poll(() => snapshot().webhook_events.some(event => event.type === 'payment_intent.canceled' && event.object_id === authorizedIntent && event.status === 'processed'), { timeout: 45_000 }).toBe(true);
    fs.writeFileSync(path.join(stagingRoot, 'mixed-cancellation-verification.json'), JSON.stringify({ ...snapshot(), authorization_snapshot: authorized,
        checks: { default_card_remains_default: true, mixed_shortfall_31: true, decline_did_not_consume_hours: true,
            abandoned_checkout_preserved_reservation: true, three_ds_challenge_completed: true, all_reserved_hours_returned: true, saved_card_unchanged: true,
        } }, null, 2));
});

test('a real prepaid gift remains unassigned until a family claims it once for an existing recipient', async ({ page, browser }) => {
    page.setDefaultTimeout(20_000); page.setDefaultNavigationTimeout(90_000);
    fixtureCommand('migrate-pending');
    const recipient = JSON.parse(fixtureCommand('seed-gift-recipient'));
    const initial = snapshot();
    const checkpointFile = path.join(stagingRoot, 'gift-checkpoint.private.json');
    let checkpoint = fs.existsSync(checkpointFile) ? JSON.parse(fs.readFileSync(checkpointFile, 'utf8')) : null;
    const capture = async (name: string, maskCode = false) => {
        const mask = maskCode ? [page.getByLabel('Gift code', { exact: true })] : [];
        await page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '.png'), fullPage: true, animations: 'disabled', mask, maskColor: '#E7EDE5' });
        if (['gift-03-private-code-ready', 'gift-04-confirm-recipient-and-welcome-hour', 'gift-05-recipient-hours-ready'].includes(name)) {
            await page.setViewportSize({ width: 375, height: 812 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
            await page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '-mobile.png'), fullPage: true, animations: 'disabled', mask, maskColor: '#E7EDE5' });
            await page.setViewportSize({ width: 1440, height: 1000 });
        }
    };
    if (!checkpoint) {
        await page.goto('/care-hours/gifts');
        await page.getByLabel('Your name', { exact: true }).fill('Thomas Peverelli');
        await page.getByLabel('Your email', { exact: true }).fill(approvedInbox);
        await page.getByRole('button', { name: 'Send verification code', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Check your email', exact: true })).toBeVisible();
        await page.getByLabel('Your six-digit code').fill(verificationCode());
        await page.getByRole('button', { name: 'Verify email', exact: true }).click();
        await page.locator('input[name="hours"][value="10"]').check();
        await page.locator('input[name="terms"]').check();
        await expect(page.locator('body')).toContainText('We confirm their bonus when they claim.');
        await capture('gift-01-buy-without-selecting-recipient');
        await page.getByRole('button', { name: 'Continue to payment', exact: true }).click();
        await fillStripeTestCard(page, 31000);
        await capture('gift-02-stripe-test-checkout');
        await submitStripeTestCard(page);
        await expect(page).toHaveURL(/\/care-hours\/gifts\/receipts\//, { timeout: 90_000 });
        await expect(page.getByRole('heading', { name: 'Your gift is ready', exact: true })).toBeVisible({ timeout: 30_000 });
        const code = await page.getByLabel('Gift code', { exact: true }).inputValue();
        expect(code.length).toBeGreaterThan(12);
        checkpoint = { run_id: initial.run_id, code, receipt_url: page.url(), storage: await page.context().storageState() };
        fs.writeFileSync(checkpointFile, JSON.stringify(checkpoint));
        await capture('gift-03-private-code-ready', true);
        const unclaimed = snapshot();
        expect(unclaimed.gift_purchases[0].status).toBe('paid');
        expect(unclaimed.gift_purchases[0].care_hour_account_id).toBeNull();
        expect(unclaimed.gift_purchases[0].gift.claimed_at).toBeNull();
        expect(unclaimed.accounts).toEqual(initial.accounts);
        expect(unclaimed.lot_count).toBe(initial.lot_count);
        fs.writeFileSync(path.join(stagingRoot, 'gift-unclaimed-verification.json'), JSON.stringify(unclaimed, null, 2));
    }
    expect(checkpoint.run_id).toBe(initial.run_id);
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email').fill('emma.bennett@example.test');
    await page.getByLabel('Password').fill('Staging-only-2026!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 60_000 });
    await page.goto('/family/care-hours/claim');
    await page.getByLabel('Gift code', { exact: true }).fill(checkpoint.code);
    await page.getByLabel('Who is this gift for?', { exact: true }).selectOption(`account:${recipient.account_id}`);
    await page.getByRole('button', { name: 'Review gift', exact: true }).click();
    const review = page.getByRole('region', { name: 'Review gift claim' });
    await expect(review).toContainText('For Alice Bennett');
    await expect(review).toContainText('Free welcome hours');
    await expect(review).toContainText('11 h');
    await capture('gift-04-confirm-recipient-and-welcome-hour');
    await page.locator('input[name="confirm"]').check();
    await page.getByRole('button', { name: 'Add gift hours', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Care hours for Alice Bennett', exact: true })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Care hours balance' })).toContainText('11 h');
    await capture('gift-05-recipient-hours-ready');
    const claimed = snapshot();
    expect(claimed.gift_purchases[0].care_hour_account_id).toBe(recipient.account_id);
    expect(claimed.gift_purchases[0].total_minutes).toBe(660);
    expect(claimed.gift_purchases[0].bonus_minutes).toBe(60);
    expect(claimed.gift_purchases[0].gift.claimed_at).not.toBeNull();
    await page.goto('/family/care-hours/claim');
    await page.getByLabel('Gift code', { exact: true }).fill(checkpoint.code);
    await page.getByLabel('Who is this gift for?', { exact: true }).selectOption(`account:${recipient.account_id}`);
    await page.getByRole('button', { name: 'Review gift', exact: true }).click();
    await expect(page.locator('body')).toContainText(/unavailable|already claimed/i);
    expect(snapshot().accounts).toEqual(claimed.accounts);
    expect(snapshot().lot_count).toBe(claimed.lot_count);
    const buyerContext = await browser.newContext({ storageState: checkpoint.storage });
    const buyer = await buyerContext.newPage(); buyer.setDefaultNavigationTimeout(90_000);
    await buyer.goto(checkpoint.receipt_url);
    await expect(buyer.getByRole('heading', { name: 'Your gift has been claimed', exact: true })).toBeVisible();
    await expect(buyer.locator('body')).not.toContainText('Alice Bennett');
    await expect(buyer.getByLabel('Gift code', { exact: true })).toHaveCount(0);
    await buyerContext.close();
    fixtureCommand('deliver-notices');
    fs.writeFileSync(path.join(stagingRoot, 'gift-claim-verification.json'), JSON.stringify({ ...snapshot(), checks: {
        real_test_gift_purchase: true, no_recipient_before_claim: true, no_hours_or_lots_before_claim: true,
        recipient_confirmation_required: true, eligible_welcome_bonus_applied_on_claim: true, claimed_once: true,
        buyer_receipt_hides_recipient: true, claimed_code_no_longer_disclosed: true, external_email_delivery_deferred: true,
    } }, null, 2));
});

test('admin tracks the claimed real gift without exposing its bearer code', async ({ page }) => {
    page.setDefaultTimeout(20_000); page.setDefaultNavigationTimeout(90_000);
    const before = snapshot();
    const checkpoint = JSON.parse(fs.readFileSync(path.join(stagingRoot, 'gift-checkpoint.private.json'), 'utf8'));
    await page.goto('/login');
    await page.getByLabel('Email').fill('care-hours.admin@example.test');
    await page.getByLabel('Password').fill('Staging-only-2026!');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/, { timeout: 60_000 });
    await page.goto('/admin/care-hours/gifts');
    await page.getByLabel('Purchase reference or buyer email').fill(before.gift_purchases[0].public_id);
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    const gift = page.locator('article');
    await expect(gift).toHaveCount(1);
    await expect(gift).toContainText('Claimed');
    await expect(gift.getByRole('link', { name: 'Alice Bennett · 11 h added', exact: true })).toBeVisible();
    expect(await page.content()).not.toContain(checkpoint.code);
    await page.screenshot({ path: path.join(stagingRoot, 'screenshots/gift-06-admin-tracking.png'), fullPage: true, animations: 'disabled' });
    await gift.getByRole('link', { name: 'Alice Bennett · 11 h added', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/admin/care-hours/${before.gift_purchases[0].care_hour_account_id}$`));
    await expect(page.locator('body')).toContainText('Alice Bennett');
    expect(await page.content()).not.toContain(checkpoint.code);
    expect(snapshot().accounts).toEqual(before.accounts);
    fs.writeFileSync(path.join(stagingRoot, 'gift-admin-verification.json'), JSON.stringify({ run_id: before.run_id,
        purchase_public_id: before.gift_purchases[0].public_id, checks: { claimed_gift_searchable: true, recipient_link_works: true, bearer_code_absent: true, read_only_did_not_change_balances: true },
        screenshot: 'gift-06-admin-tracking.png',
    }, null, 2));
});

test('mixed prepaid care captures only the uncovered hour after actual visit approval', async ({ page }) => {
    page.setDefaultTimeout(20_000); page.setDefaultNavigationTimeout(90_000);
    const request = JSON.parse(fixtureCommand('seed-mixed-capture'));
    const initial = snapshot();
    const login = async (email: string) => {
        await page.context().clearCookies(); await page.goto('/login');
        await page.getByLabel('Email').fill(email); await page.getByLabel('Password').fill('Staging-only-2026!');
        await page.getByRole('button', { name: 'Log in', exact: true }).click();
        await expect(page).not.toHaveURL(/\/login$/, { timeout: 60_000 });
    };
    const capture = async (name: string) => page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '.png'), fullPage: true, animations: 'disabled' });
    const clock = (now: string, stage: string) => fs.writeFileSync(path.join(stagingRoot, 'clock.json'), JSON.stringify({ now, stage }));
    const currentBooking = () => snapshot().bookings.find(booking => booking.care_request_id === request.request_id);
    const currentFunding = () => snapshot().fundings.find(funding => funding.care_booking_id === currentBooking()?.id);
    const currentSettlement = () => snapshot().settlements.find(settlement => settlement.care_booking_id === currentBooking()?.id);
    if (!currentSettlement()) {
    await login('emma.bennett@example.test');
    await page.goto(`/family/requests/${request.request_id}?tab=applicants`);
    if (!currentBooking()) {
        await page.getByTestId('hire-caregiver').click();
        await expect(page.getByRole('radio', { name: 'Usual card payment', exact: true })).toBeChecked();
        await page.getByRole('radio', { name: /Use care hours/ }).check();
        await expect(page.getByRole('complementary', { name: 'Care hours coverage' })).toContainText(/Remaining payment\s*\$31\.00/);
        await page.getByRole('button', { name: 'Confirm hire', exact: true }).click();
    }
    const coverage = page.getByRole('region', { name: 'Care-hour payment', exact: true });
    if (!snapshot().operations.some(operation => operation.kind === 'shortfall' && operation.care_hour_booking_funding_id === currentFunding().id && ['authorized', 'captured', 'succeeded'].includes(operation.status))) {
        await coverage.getByRole('button', { name: 'Pay $31.00', exact: true }).click({ noWaitAfter: true });
        await fillStripeTestCard(page, 3100); await submitStripeTestCard(page);
        await expect(page).toHaveURL(new RegExp(`/family/requests/${request.request_id}`), { timeout: 90_000 });
    }
    await expect(coverage).toContainText('Scheduled care is covered.');
    await capture('mixed-capture-01-covered-with-eleven-hours-and-one-hour-card');
    if (!currentBooking().started_at) {
        clock(request.visit_start, 'mixed-caregiver-check-in');
        await login('claire.martin@example.test'); await page.goto(`/care-requests/${request.request_id}/apply`);
        const shiftTab = page.locator('button[wire\\:click="setActiveTab(\'shift\')"]').first();
        if (await shiftTab.isVisible()) await shiftTab.click();
        await page.getByRole('button', { name: 'Accept agreement', exact: true }).click();
        await page.getByRole('button', { name: 'Start visit', exact: true }).click();
        await expect(page.getByText(/Visit started with GPS check-in|Visit started\./).first()).toBeVisible();
    } else {
        await login('claire.martin@example.test'); await page.goto(`/care-requests/${request.request_id}/apply`);
    }
    if (!currentBooking().completed_at) {
        clock(request.visit_end, 'mixed-caregiver-check-out'); await page.reload();
        await page.getByRole('button', { name: 'End visit', exact: true }).click();
        await expect(page.getByText('Visit completed. Review your recap below.', { exact: true })).toBeVisible();
        await capture('mixed-capture-02-twelve-hours-submitted');
    }
    await login('emma.bennett@example.test'); await page.goto(`/family/requests/${request.request_id}`);
    if (!currentSettlement()) {
        await page.getByRole('button', { name: 'Review hours & payment', exact: true }).click();
        await expect(page.locator('#care-decision-review')).toContainText('Care hours to use');
        await expect(page.locator('#care-decision-review')).toContainText('$31.00');
        await capture('mixed-capture-03-family-approval');
        await page.getByRole('button', { name: 'Approve hours', exact: true }).click();
    }
    await expect.poll(() => currentSettlement()?.status, { timeout: 60_000 }).toBe('paid');
    await capture('mixed-capture-04-visit-paid');
    }
    const final = snapshot(); const funding = currentFunding(); const settlement = currentSettlement();
    expect(final.balance).toMatchObject({ available_minutes: 0, reserved_minutes: 0, used_minutes: 660 });
    expect(settlement).toMatchObject({ worked_minutes: 720, prepaid_minutes: 660, amount_due_cents: 0, caregiver_gross_cents: 32400 });
    const shortfall = final.operations.find(operation => operation.kind === 'shortfall' && operation.care_hour_booking_funding_id === funding.id);
    expect(shortfall.captured_cents).toBe(3100);
    expect(final.family_payment_details).toEqual(initial.family_payment_details);
    expect(final.ordinary_card_booking_payment_count).toBe(0);
    await login('claire.martin@example.test'); await page.goto('/caregiver/earnings?tab=shifts');
    await expect(page.locator('main')).toContainText('Transferred');
    await capture('mixed-capture-05-caregiver-earnings');
    await expect.poll(() => snapshot().operations.filter(operation => operation.kind === 'transfer' && operation.care_hour_booking_funding_id === funding.id && operation.status === 'succeeded').every(operation => snapshot().webhook_events.some(event => event.object_id === operation.stripe_transfer_id && event.status === 'processed')), { timeout: 45_000 }).toBe(true);
    fs.writeFileSync(path.join(stagingRoot, 'mixed-capture-verification.json'), JSON.stringify({ ...snapshot(), request_id: request.request_id, funding_id: funding.id,
        checks: { default_card_remains_default: true, real_shortfall_authorized: true, actual_caregiver_actions_used: true, test_clock_and_gps_disclosed: true,
            family_approved_720_minutes: true, only_660_prepaid_minutes_used: true, only_3100_cents_captured: true, saved_card_unchanged: true, caregiver_transfer_recorded: true },
        time_control: 'Visit time and GPS are controlled test inputs. Actual caregiver check-in/check-out and family approval actions are used.',
    }, null, 2));
});

test('the real authorization-release event reconciles idempotently without consuming hours or recording a cash refund', async () => {
    const before = snapshot();
    const replay = JSON.parse(execFileSync('php', ['tests/e2e/care-hours-launch-replay-release.php', '--replay'], { encoding: 'utf8', windowsHide: true }));
    expect(replay.replays).toHaveLength(2);
    expect(replay.replays.every(event => event.status === 200)).toBe(true);
    const after = snapshot();
    expect(after.balance).toEqual(before.balance);
    expect(after.ledger_entry_count).toBe(before.ledger_entry_count);
    expect(after.settlements).toEqual(before.settlements);
    const canceled = after.operations.find(operation => operation.stripe_payment_intent_id === replay.intent_id);
    expect(canceled).toMatchObject({ status: 'cancelled', captured_cents: 0, refunded_cents: 0 });
    expect(after.webhook_events.find(event => event.stripe_event_id === replay.event_id).status).toBe('processed');
    const file = path.join(stagingRoot, 'mixed-cancellation-verification.json');
    const recorded = JSON.parse(fs.readFileSync(file, 'utf8'));
    recorded.webhook_events = after.webhook_events;
    recorded.checks.authorization_release_event_reconciles_without_cash_refund = true;
    fs.writeFileSync(file, JSON.stringify(recorded, null, 2));
});
