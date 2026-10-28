import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

const root = path.resolve('tmp/care-hours-gift-onboarding');
const file = path.join(root, 'provider.json');
const provider = JSON.parse(fs.readFileSync(file, 'utf8'));
if (provider.payment_mode !== 'stripe_test' || !/^sk_test_[A-Za-z0-9]+$/.test(provider.stripe_secret ?? '')) throw new Error('An explicit isolated Stripe sandbox key is required.');
const cli = path.resolve('tmp/stripe-cli/node_modules/@stripe/cli-win32-x64/bin/stripe.exe');
const events = ['checkout.session.completed', 'checkout.session.expired', 'payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.canceled', 'payment_intent.amount_capturable_updated', 'charge.succeeded', 'charge.updated', 'charge.captured', 'charge.refunded', 'charge.dispute.created', 'charge.dispute.closed', 'transfer.created', 'transfer.updated', 'transfer.reversed', 'payout.created', 'payout.updated', 'payout.paid', 'payout.failed'];
const child = spawn(cli, ['listen', '--events-from', '@self', '--forward-to', 'http://127.0.0.1:8016/webhooks/stripe', '--events', events.join(','), '--skip-update', '--color', 'off'], { env: { ...process.env, STRIPE_API_KEY: provider.stripe_secret }, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
let ready = false;
let pending = '';
const receive = chunk => {
    pending += String(chunk);
    const lines = pending.split(/\r?\n/); pending = lines.pop() ?? '';
    for (const line of lines) {
        const secret = line.match(/whsec_[A-Za-z0-9]+/);
        if (secret) {
            const current = JSON.parse(fs.readFileSync(file, 'utf8'));
            current.stripe_webhook_secret = secret[0];
            fs.writeFileSync(file, JSON.stringify(current, null, 2));
            if (!ready) { ready = true; console.log('Launch validation signed webhook listener ready on port8016 (test sandbox only).'); }
        }
        const safe = line.replace(/(?:sk|rk|pk|whsec)_(?:test_|live_)?[A-Za-z0-9]+/g, '[redacted]');
        if (/\[(?:200|400|500)\]|checkout\.session|payment_intent|charge\.|transfer\.|payout\./.test(safe)) console.log(safe);
    }
};
child.stdout.on('data', receive); child.stderr.on('data', receive);
child.on('error', () => { console.error('The isolated Stripe listener could not start.'); process.exitCode = 1; });
child.on('exit', code => { process.exitCode = code ?? 1; });
process.on('SIGINT', () => child.kill()); process.on('SIGTERM', () => child.kill());
