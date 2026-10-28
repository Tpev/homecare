import { expect, test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { stagingEnvironment, stagingRoot, stagingUrl } from '../care-hours-gift-onboarding-environment';

const snapshot = () => JSON.parse(execFileSync('php', ['tests/e2e/care-hours-gift-onboarding-fixtures.php', 'snapshot'], {
    cwd: process.cwd(), env: { ...process.env, ...stagingEnvironment() }, encoding: 'utf8', windowsHide: true,
}));

test('future care keeps Linda’s gift recipient identity and does not create a visit', async ({ browser }) => {
    const checkpoint = JSON.parse(fs.readFileSync(path.join(stagingRoot, 'checkpoint.private.json'), 'utf8'));
    const before = snapshot();
    expect(before.purchases[0].status).toBe('issued');
    const context = await browser.newContext({ baseURL: stagingUrl, storageState: checkpoint.mother_storage, viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 });
    const page = await context.newPage(); page.setDefaultTimeout(20_000); page.setDefaultNavigationTimeout(90_000);
    await page.goto(checkpoint.ready_url);
    const findCare = page.getByRole('link', { name: 'Find care', exact: true });
    await expect(findCare).toHaveAttribute('href', `${stagingUrl}/family/requests/create?care_hours_account=${before.accounts[0].id}`);
    await findCare.click({ noWaitAfter: true });
    await expect(page).toHaveURL(new RegExp(`/family/requests/create\\?care_hours_account=${before.accounts[0].id}$`), { timeout: 60_000 });
    await expect(page.getByRole('radio', { name: 'Me', exact: true })).toBeChecked();
    const component = page.locator('[wire\\:snapshot]').filter({ has: page.getByRole('radio', { name: 'Me', exact: true }) }).first();
    const state = JSON.parse((await component.getAttribute('wire:snapshot'))!).data;
    expect(state.careHoursHandoffAccountId).toBe(before.accounts[0].id);
    expect(state.careHoursHandoffProfileId).toBe(before.profiles[0].id);
    expect(state.careHoursHandoffProfileRevision).toBeGreaterThanOrEqual(1);
    expect(state.care_for).toBe('self'); expect(state.createQuickCareProfile).toBe(false);
    expect(state.selected_care_recipient_profile_id).toBeNull(); expect(state.startedFromCareHours).toBe(true);
    expect(state.careHourAccountId).toBe(before.accounts[0].id);
    await expect(page.getByRole('radio', { name: /Use care hours first/ })).toBeChecked();
    const manifestFile = path.join(stagingRoot, 'manifest.json'); const manifest = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
    const screenshots = [];
    for (const mobile of [false, true]) {
        await page.setViewportSize(mobile ? { width: 375, height: 812 } : { width: 1440, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
        const name = '12-find-care-keeps-linda-profile' + (mobile ? '-mobile' : '');
        await page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '.png'), fullPage: true, animations: 'disabled' });
        await page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '-focus.png'), fullPage: false, animations: 'disabled' });
        await page.getByRole('group', { name: 'Payment when you book', exact: true }).screenshot({ path: path.join(stagingRoot, 'screenshots', name + '-payment-choice.png'), animations: 'disabled' });
        const entry = { filename: name + '.png', focused: name + '-focus.png', paymentChoice: name + '-payment-choice.png', title: 'Find care keeps Linda’s existing gift balance connected to her own recipient profile.', mobile };
        manifest.screenshots = manifest.screenshots.filter(item => item.filename !== entry.filename); manifest.screenshots.push(entry); screenshots.push(entry.filename);
    }
    fs.writeFileSync(manifestFile, JSON.stringify(manifest, null, 2));
    const after = snapshot();
    expect(after.accounts).toEqual(before.accounts); expect(after.profiles).toEqual(before.profiles);
    expect(after.lots).toEqual(before.lots); expect(after.ledger).toEqual(before.ledger);
    expect(after.care_request_count).toBe(0); expect(after.booking_count).toBe(0); expect(after.ordinary_card_payment_count).toBe(0);
    fs.writeFileSync(path.join(stagingRoot, 'care-handoff-verification.json'), JSON.stringify({ run_id: before.run_id,
        care_hour_account_id: state.careHoursHandoffAccountId, care_recipient_profile_id: state.careHoursHandoffProfileId,
        profile_revision: state.careHoursHandoffProfileRevision, checks: { ready_link_carries_authorized_account: true,
            same_self_profile_loaded: true, self_selected: true, optional_profile_remains_optional: true,
            eligible_care_hours_selected_by_default: true,
            no_onboarding_detour: true, no_request_or_booking_created: true, balance_and_ledger_unchanged: true }, screenshots,
    }, null, 2));
    await context.close();
});

test('gift purchase wording is current without creating another purchase', async ({ browser }) => {
    const checkpoint = JSON.parse(fs.readFileSync(path.join(stagingRoot, 'checkpoint.private.json'), 'utf8'));
    const before = snapshot();
    const context = await browser.newContext({ baseURL: stagingUrl, storageState: checkpoint.buyer_storage, viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 });
    const page = await context.newPage(); page.setDefaultTimeout(20_000); page.setDefaultNavigationTimeout(90_000);
    await page.goto('/care-hours/gifts');
    if (await page.getByLabel('Your name', { exact: true }).isVisible()) {
        await page.getByLabel('Your name', { exact: true }).fill('Jane Bennett');
        await page.getByLabel('Your email', { exact: true }).fill('jane.bennett@example.test');
        await page.getByRole('button', { name: 'Send verification code', exact: true }).click();
        const messages = fs.readFileSync(path.join(stagingRoot, 'mail/index.jsonl'), 'utf8').trim().split('\n').filter(Boolean).map(line => JSON.parse(line));
        const message = messages.filter(mail => mail.subject === 'Your LoLo care-hours verification code' && mail.to.includes('jane.bennett@example.test')).at(-1);
        const code = fs.readFileSync(path.join(stagingRoot, 'mail', message.id + '.html'), 'utf8').replace(/<[^>]+>/g, ' ').match(/confirm your email address:\s*(\d{6})/i)?.[1];
        expect(code).toMatch(/^\d{6}$/);
        await page.getByLabel('Your six-digit code').fill(code!);
        await page.getByRole('button', { name: 'Verify email', exact: true }).click();
    }
    await expect(page.locator('main')).toContainText('They can create their free LoLo account when they’re ready to claim.');
    await page.locator('input[name="hours"][value="10"]').check();
    await page.locator('input[name="terms"]').check();
    const manifestFile = path.join(stagingRoot, 'manifest.json'); const manifest = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
    for (const mobile of [false, true]) {
        await page.setViewportSize(mobile ? { width: 375, height: 812 } : { width: 1440, height: 1000 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
        const name = '01-jane-chooses-ten-hours' + (mobile ? '-mobile' : '');
        await page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '.png'), fullPage: true, animations: 'disabled', timeout: 15_000 });
        await page.screenshot({ path: path.join(stagingRoot, 'screenshots', name + '-focus.png'), fullPage: false, animations: 'disabled', timeout: 15_000 });
        const entry = { filename: name + '.png', focused: name + '-focus.png', title: 'Jane chooses 10 hours for $310. Linda does not need an account yet.', mobile };
        manifest.screenshots = manifest.screenshots.filter(item => item.filename !== entry.filename); manifest.screenshots.push(entry);
    }
    fs.writeFileSync(manifestFile, JSON.stringify(manifest, null, 2));
    const after = snapshot();
    expect(after.purchases).toEqual(before.purchases); expect(after.accounts).toEqual(before.accounts);
    expect(after.lots).toEqual(before.lots); expect(after.ledger).toEqual(before.ledger);
    await context.close();
});
