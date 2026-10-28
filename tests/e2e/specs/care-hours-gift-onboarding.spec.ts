import { expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { stagingEnvironment, stagingRoot, stagingUrl } from '../care-hours-gift-onboarding-environment';
import { fillStripeTestCard, submitStripeTestCard } from '../care-hours-test-checkout';

const buyerEmail = 'jane.bennett@example.test';
const recipientEmail = 'linda.bennett@example.test';
const password = 'Gift-onboarding-only-2026!';
const fixtureCommand = (command: string) => execFileSync('php', ['tests/e2e/care-hours-gift-onboarding-fixtures.php', command], {
    cwd: process.cwd(), env: { ...process.env, ...stagingEnvironment() }, encoding: 'utf8', windowsHide: true,
});
const snapshot = () => JSON.parse(fixtureCommand('snapshot'));
const codeFromCapturedEmail = () => {
    const messages = fs.readFileSync(path.join(stagingRoot, 'mail/index.jsonl'), 'utf8').trim().split('\n').filter(Boolean).map(line => JSON.parse(line));
    const message = messages.filter(mail => mail.subject === 'Your LoLo care-hours verification code' && mail.to.includes(buyerEmail)).at(-1);
    if (!message) throw new Error('Jane’s verification email was not captured locally.');
    const html = fs.readFileSync(path.join(stagingRoot, 'mail', message.id + '.html'), 'utf8');
    const code = html.replace(/<[^>]+>/g, ' ').match(/confirm your email address:\s*(\d{6})/i)?.[1];
    if (!code) throw new Error('The captured email did not contain a verification code.');
    return code;
};

test('Jane buys a gift before Linda exists, then Linda signs up and claims eleven hours for herself', async ({ page, browser }) => {
    page.setDefaultTimeout(20_000); page.setDefaultNavigationTimeout(90_000);
    fixtureCommand('migrate-pending');
    const initial = snapshot();
    const usesStripe = initial.payment_mode === 'stripe_test';
    expect(['stripe_test', 'bypass']).toContain(initial.payment_mode);
    const checkpointFile = path.join(stagingRoot, 'checkpoint.private.json');
    let checkpoint = fs.existsSync(checkpointFile) ? JSON.parse(fs.readFileSync(checkpointFile, 'utf8')) : { run_id: initial.run_id };
    expect(checkpoint.run_id).toBe(initial.run_id);
    const manifestFile = path.join(stagingRoot, 'manifest.json');
    const manifest = fs.existsSync(manifestFile) ? JSON.parse(fs.readFileSync(manifestFile, 'utf8')) : {
        run_id: initial.run_id, payment_mode: initial.payment_mode, title: 'Jane’s gift for Linda',
        payment_verification: usesStripe ? 'Real Stripe sandbox checkout and webhook.' : 'Local bypass simulation only; no Stripe checkout, webhook or provider proof.',
        email_delivery: 'Captured locally; external delivery verification was deferred by the user.',
        fixture_note: 'Linda does not exist at purchase. Account and recipient records are created through the real signup and gift-claim UI. No visit or payment method is created. Time is not simulated.',
        screenshots: [],
    };
    const saveCheckpoint = () => fs.writeFileSync(checkpointFile, JSON.stringify(checkpoint));
    const capture = async (target: Page, filename: string, title: string, mobile = false) => {
        const mask = [target.locator('input[type="password"]'), target.getByLabel('Gift code', { exact: true }),
            target.locator('input[autocomplete="one-time-code"]'), target.locator('input[autocomplete="cc-number"], input[autocomplete="cc-csc"]')];
        const shot = async (suffix: string) => {
            await target.screenshot({ path: path.join(stagingRoot, 'screenshots', filename + suffix + '.png'), fullPage: true, animations: 'disabled', mask, maskColor: '#E7EDE5', timeout: 15_000 });
            await target.screenshot({ path: path.join(stagingRoot, 'screenshots', filename + suffix + '-focus.png'), fullPage: false, animations: 'disabled', mask, maskColor: '#E7EDE5', timeout: 15_000 });
            const entry = { filename: filename + suffix + '.png', focused: filename + suffix + '-focus.png', title, mobile: suffix === '-mobile' };
            manifest.screenshots = manifest.screenshots.filter(item => item.filename !== entry.filename); manifest.screenshots.push(entry);
            fs.writeFileSync(manifestFile, JSON.stringify(manifest, null, 2));
        };
        await target.setViewportSize({ width: 1440, height: 1000 }); await shot('');
        if (mobile) {
            await target.setViewportSize({ width: 375, height: 812 });
            expect(await target.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
            await shot('-mobile');
        }
        await target.setViewportSize({ width: 1440, height: 1000 });
    };
    if (!checkpoint.code) {
        expect(initial.users).toEqual([]); expect(initial.profiles).toEqual([]); expect(initial.purchases.length).toBeLessThanOrEqual(1);
        await page.goto('/care-hours/gifts');
        await page.getByLabel('Your name', { exact: true }).fill('Jane Bennett');
        await page.getByLabel('Your email', { exact: true }).fill(buyerEmail);
        await page.getByRole('button', { name: 'Send verification code', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Check your email', exact: true })).toBeVisible();
        await page.getByLabel('Your six-digit code').fill(codeFromCapturedEmail());
        await page.getByRole('button', { name: 'Verify email', exact: true }).click();
        await page.locator('input[name="hours"][value="10"]').check(); await page.locator('input[name="terms"]').check();
        await capture(page, '01-jane-chooses-ten-hours', 'Jane chooses 10 hours for $310. Linda does not need an account yet.', true);
        checkpoint.buyer_storage = await page.context().storageState(); saveCheckpoint();
        if (initial.purchases.length) {
            await page.goto(`/care-hours/gifts/receipts/${initial.purchases[0].public_id}`);
            if (initial.purchases[0].status === 'pending') await page.getByRole('link', { name: 'Continue payment', exact: true }).click({ noWaitAfter: true });
        } else {
            await page.getByRole('button', { name: 'Continue to payment', exact: true }).click({ noWaitAfter: true });
        }
        if (usesStripe && (!initial.purchases.length || initial.purchases[0].status === 'pending')) {
            await expect(page).toHaveURL(/^https:\/\/checkout\.stripe\.com\//, { timeout: 120_000 });
            await fillStripeTestCard(page, 31000);
            const billingName = page.locator('input[autocomplete="cc-name"], #billingName').first();
            if (await billingName.isVisible()) await billingName.fill('Jane Bennett');
            await capture(page, '02-real-stripe-test-checkout', 'Jane pays $310 through the real Stripe sandbox checkout.');
            await submitStripeTestCard(page);
        }
        await expect(page).toHaveURL(/\/care-hours\/gifts\/receipts\//, { timeout: 90_000 });
        await expect(page.getByRole('heading', { name: 'Your gift is ready', exact: true })).toBeVisible({ timeout: 30_000 });
        checkpoint.code = await page.getByLabel('Gift code', { exact: true }).inputValue();
        expect(checkpoint.code.length).toBeGreaterThan(12);
        checkpoint.receipt_url = page.url(); checkpoint.buyer_storage = await page.context().storageState(); saveCheckpoint();
        await capture(page, '03-jane-shares-private-gift', 'Jane receives a private gift code and a simple claim link.', true);
        if (usesStripe) {
            await expect.poll(() => snapshot().webhook_events.some(event => event.type === 'checkout.session.completed' && event.status === 'processed'), { timeout: 40_000 }).toBe(true);
        } else {
            expect(snapshot().webhook_events).toEqual([]);
        }
        const unclaimed = snapshot();
        expect(unclaimed.users).toEqual([]); expect(unclaimed.accounts).toEqual([]); expect(unclaimed.profiles).toEqual([]); expect(unclaimed.lots).toEqual([]);
        expect(unclaimed.purchases).toHaveLength(1);
        expect(unclaimed.purchases[0]).toMatchObject({ status: 'paid', care_hour_account_id: null, buyer_user_id: null, paid_minutes: 600, amount_cents: 31000 });
        expect(unclaimed.purchases[0].gift.claimed_at).toBeNull();
        fs.writeFileSync(path.join(stagingRoot, 'unclaimed-verification.json'), JSON.stringify(unclaimed, null, 2));
    }

    let motherContext = await browser.newContext({ baseURL: stagingUrl, viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 });
    let mother = await motherContext.newPage(); mother.setDefaultTimeout(20_000); mother.setDefaultNavigationTimeout(90_000);
    if (!snapshot().users.some(user => user.email === recipientEmail)) {
        await mother.goto('/care-hours/claim');
        await expect(mother.getByRole('heading', { name: 'Claim your gift of care', exact: true })).toBeVisible();
        await mother.getByLabel('Gift code', { exact: true }).fill(checkpoint.code);
        await capture(mother, '04-linda-enters-gift-code', 'Linda enters Jane’s gift code before creating an account.', true);
        await mother.getByRole('button', { name: 'Continue', exact: true }).click();
        await expect(mother).toHaveURL(/\/register$/);
        await expect.poll(() => mother.evaluate(() => Boolean((window as any).Livewire?.all?.().length))).toBe(true);
        await mother.getByLabel('Full name', { exact: true }).fill('Linda Bennett');
        await mother.getByLabel('Email', { exact: true }).fill(recipientEmail);
        await mother.getByLabel('Password', { exact: true }).fill(password);
        await mother.getByLabel('Confirm password', { exact: true }).fill(password);
        await mother.locator('#accept_terms').check();
        await expect(mother.getByLabel('Full name', { exact: true })).toHaveValue('Linda Bennett');
        await expect(mother.getByLabel('Email', { exact: true })).toHaveValue(recipientEmail);
        await capture(mother, '05-linda-creates-account', 'Linda creates her own account. Password fields are hidden.', true);
        await mother.getByRole('button', { name: 'Create account and continue', exact: true }).click();
        await expect(mother).toHaveURL(/\/family\/care-hours\/claim$/, { timeout: 60_000 });
        await expect(mother.getByRole('button', { name: 'For myself', exact: true })).toBeVisible();
        checkpoint.signup_completed = true; saveCheckpoint();
        const registered = snapshot();
        expect(registered.users).toHaveLength(1); expect(registered.users[0]).toMatchObject({ name: 'Linda Bennett', email: recipientEmail, role: 'family' });
        expect(registered.care_request_count).toBe(0); expect(registered.booking_count).toBe(0);
        fs.writeFileSync(path.join(stagingRoot, 'signup-verification.json'), JSON.stringify(registered, null, 2));
        await capture(mother, '06-gift-resumes-after-signup', 'Linda goes straight back to her gift after signup.', true);
    }

    if (!snapshot().purchases[0].gift.claimed_at) {
        await motherContext.close();
        motherContext = await browser.newContext({ baseURL: stagingUrl, viewport: { width: 375, height: 812 }, deviceScaleFactor: 2 });
        mother = await motherContext.newPage(); mother.setDefaultTimeout(20_000); mother.setDefaultNavigationTimeout(90_000);
        await mother.goto('/care-hours/claim'); await mother.getByLabel('Gift code', { exact: true }).fill(checkpoint.code);
        await mother.getByRole('button', { name: 'Continue', exact: true }).click();
        await expect(mother).toHaveURL(/\/register$/);
        await mother.getByRole('link', { name: 'Already registered?', exact: true }).click();
        await expect(mother).toHaveURL(/\/login$/);
        await expect.poll(() => mother.evaluate(() => {
            const form = document.querySelector('form[wire\\:submit="login"]');
            const id = form?.closest('[wire\\:id]')?.getAttribute('wire:id');
            return Boolean(id && (window as any).Livewire?.find?.(id));
        })).toBe(true);
        await mother.getByLabel('Email', { exact: true }).fill(recipientEmail); await mother.getByLabel('Password', { exact: true }).fill(password);
        await capture(mother, '07-resume-by-signing-in', 'The gift also resumes when Linda signs in on another device.', true);
        await expect(mother.getByLabel('Email', { exact: true })).toHaveValue(recipientEmail);
        await expect(mother.getByLabel('Password', { exact: true })).toHaveValue(password);
        await mother.getByRole('button', { name: 'Log in', exact: true }).click();
        await expect(mother).toHaveURL(/\/family\/care-hours\/claim$/, { timeout: 60_000 });
        checkpoint.login_resume_confirmed = true; saveCheckpoint();
        await mother.getByRole('button', { name: 'For myself', exact: true }).click();
        const review = mother.getByRole('region', { name: 'Review gift claim' });
        await expect(review).toContainText('Linda Bennett'); await expect(review).toContainText('10 h');
        await expect(review).toContainText('1 h'); await expect(review).toContainText('11 h');
        await capture(mother, '08-linda-reviews-eleven-hours', 'Linda confirms 10 gift hours plus 1 free welcome hour.', true);
        const confirmation = mother.locator('input[name="confirm"]'); if (await confirmation.isVisible()) await confirmation.check();
        await mother.getByRole('button', { name: 'Claim my gift', exact: true }).click();
        await expect(mother.getByRole('heading', { name: 'Your care hours are ready', exact: true })).toBeVisible();
        await expect(mother.locator('main')).toContainText('11 h');
        checkpoint.ready_url = mother.url(); checkpoint.mother_storage = await motherContext.storageState(); saveCheckpoint();
        await capture(mother, '09-linda-hours-ready', 'Linda has 11 hours ready to use whenever she chooses.', true);
    } else {
        await motherContext.addCookies(checkpoint.mother_storage.cookies);
        await mother.goto(checkpoint.ready_url);
        await expect(mother.getByRole('heading', { name: 'Your care hours are ready', exact: true })).toBeVisible();
        await expect(mother.locator('main')).not.toContainText('@if');
        await expect(mother.locator('main')).not.toContainText('@endif');
        await capture(mother, '09-linda-hours-ready', 'Linda has 11 hours ready to use whenever she chooses.', true);
    }
    const claimed = snapshot();
    expect(claimed.users).toHaveLength(1); expect(claimed.accounts).toHaveLength(1); expect(claimed.profiles).toHaveLength(1);
    expect(claimed.profiles[0]).toMatchObject({ full_name: 'Linda Bennett', status: 'draft' });
    expect(claimed.accounts[0]).toMatchObject({ display_name: 'Linda Bennett', care_recipient_profile_id: claimed.profiles[0].id,
        balance: { available_minutes: 660, reserved_minutes: 0, used_minutes: 0, held_minutes: 0 } });
    expect(claimed.purchases[0]).toMatchObject({ status: 'issued', care_hour_account_id: claimed.accounts[0].id, bonus_minutes: 60, total_minutes: 660 });
    expect(claimed.purchases[0].gift.claimed_by_user_id).toBe(claimed.users[0].id);
    expect(claimed.care_request_count).toBe(0); expect(claimed.request_recipient_count).toBe(0); expect(claimed.booking_count).toBe(0);
    expect(claimed.ordinary_card_payment_count).toBe(0); expect(claimed.users[0].stripe_default_payment_method_id).toBeNull();
    expect(claimed.families.every(family => family.stripe_customer_id === null && family.payment_method_summary === null)).toBe(true);
    await mother.goto('/care-hours/claim'); await mother.getByLabel('Gift code', { exact: true }).fill(checkpoint.code);
    await mother.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(mother.locator('body')).toContainText(/unavailable|already claimed/i);
    await capture(mother, '10-used-code-cannot-claim-again', 'The same gift cannot be claimed twice.', true);
    const repeated = snapshot(); expect(repeated.accounts).toEqual(claimed.accounts); expect(repeated.lots).toEqual(claimed.lots); expect(repeated.ledger).toEqual(claimed.ledger);
    const buyerContext = await browser.newContext({ baseURL: stagingUrl, storageState: checkpoint.buyer_storage, deviceScaleFactor: 2 });
    const buyer = await buyerContext.newPage(); await buyer.goto(checkpoint.receipt_url);
    if (await buyer.getByRole('heading', { name: 'Open your gift receipt', exact: true }).isVisible()) {
        await buyer.getByLabel('Your email', { exact: true }).fill(buyerEmail);
        await buyer.getByRole('button', { name: 'Send verification code', exact: true }).click();
        await buyer.getByLabel('Your six-digit code').fill(codeFromCapturedEmail());
        await buyer.getByRole('button', { name: 'View gift receipt', exact: true }).click();
        checkpoint.buyer_storage = await buyerContext.storageState(); saveCheckpoint();
    }
    await expect(buyer.getByRole('heading', { name: 'Your gift has been claimed', exact: true })).toBeVisible();
    expect(await buyer.content()).not.toContain(checkpoint.code); await expect(buyer.locator('body')).not.toContainText('Linda Bennett');
    await capture(buyer, '11-jane-claimed-receipt', 'Jane sees that the gift was claimed; Linda’s care account remains private.');
    await buyer.goto(`/family/care-hours/${claimed.accounts[0].id}`); await expect(buyer).toHaveURL(/\/login$/);
    fixtureCommand('deliver-notices');
    const final = snapshot();
    fs.writeFileSync(path.join(stagingRoot, 'verification.json'), JSON.stringify({ ...final, checks: {
        jane_verified_buyer_email_locally: true, gift_payment_confirmed_before_any_user_existed: true,
        real_paid_gift_before_any_user_existed: usesStripe, no_recipient_or_hours_before_claim: true,
        linda_registered_through_actual_ui: checkpoint.signup_completed === true, gift_resumes_after_login: checkpoint.login_resume_confirmed === true,
        self_claim_confirms_exact_paid_and_welcome_hours: true, fresh_recipient_receives_660_minutes_once: true,
        no_care_request_or_booking_created: true, no_saved_card_or_customer_created: true, repeated_claim_rejected_without_mutation: true,
        buyer_has_no_family_access_or_recipient_details: true, screenshots_hide_bearer_and_password: true, external_email_delivery_deferred: true,
    } }, null, 2));
    await motherContext.close(); await buyerContext.close();
});
