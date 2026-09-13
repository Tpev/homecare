import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

// Exercise the actual Livewire page handler with Stripe and the server boundary stubbed.
const view = readFileSync(new URL('../../resources/views/livewire/family/manage-care-request.blade.php', import.meta.url), 'utf8');
const script = view.match(/@script\s*<script>([\s\S]*?)<\/script>\s*@endscript/)[1];

function harness(confirmPayment) {
    let handler;
    const finalized = [];
    const failed = [];
    const events = [];
    const window = {
        location: { href: 'https://example.test/family/requests/1' },
        homecareLoadStripeJs: async () => () => ({ confirmPayment }),
        dispatchEvent: event => events.push(event.type),
    };
    runInNewContext(script, {
        window,
        CustomEvent: class { constructor(type) { this.type = type; } },
        $wire: {
            on: (event, callback) => { assert.equal(event, 'confirm-stripe-booking-payment'); handler = callback; },
            finalizeStripeAuthorization: async id => finalized.push(id),
            failStripeAuthorization: async (id, message) => failed.push({ id, message }),
        },
    });
    return { handler, finalized, failed, events, window };
}

const detail = { publishableKey: 'pk_test_local', clientSecret: 'pi_local_secret', paymentIntentId: 'pi_local' };

for (const type of ['card', 'link']) {
    test(`confirms the saved ${type} method and finalizes on the server`, async () => {
        const h = harness(async options => {
            assert.equal(options.clientSecret, detail.clientSecret);
            assert.equal(options.confirmParams.payment_method, `pm_${type}`);
            assert.equal(options.confirmParams.return_url, 'https://example.test/family/requests/1');
            assert.equal(options.redirect, 'if_required');
            return { paymentIntent: { id: 'pi_local', status: 'requires_capture' } };
        });
        await h.handler([{ ...detail, paymentMethodId: `pm_${type}` }]);
        assert.deepEqual(h.finalized, ['pi_local']);
        assert.deepEqual(h.failed, []);
        assert.equal(h.window.homecareConfirmingBookingPayment, false);
    });
}

test('a declined or cancelled authentication is reported without finalizing', async () => {
    const h = harness(async () => ({ error: { message: 'Authentication cancelled', payment_intent: { id: 'pi_failed' } } }));
    await h.handler({ ...detail, paymentMethodId: 'pm_link' });
    assert.deepEqual(h.finalized, []);
    assert.deepEqual(h.failed, [{ id: 'pi_failed', message: 'Authentication cancelled' }]);
    assert.deepEqual(h.events, ['payment-confirmation-started', 'payment-confirmation-finished']);
    assert.equal(h.window.homecareConfirmingBookingPayment, false);
});

test('a second click cannot start a duplicate confirmation while the first is pending', async () => {
    let release;
    let calls = 0;
    const h = harness(async () => {
        calls++;
        await new Promise(resolve => { release = resolve; });
        return { paymentIntent: { id: 'pi_local' } };
    });
    const first = h.handler({ ...detail, paymentMethodId: 'pm_link' });
    await h.handler({ ...detail, paymentMethodId: 'pm_link' });
    assert.equal(calls, 1);
    release();
    await first;
    assert.deepEqual(h.finalized, ['pi_local']);
});
