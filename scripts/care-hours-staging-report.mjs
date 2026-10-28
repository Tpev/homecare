import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const source = path.resolve('tmp/care-hours-staging');
const destination = path.resolve('output/care-hours-staging');
const manifest = JSON.parse(fs.readFileSync(path.join(source, 'screenshots/manifest.json'), 'utf8'));
const verificationPath = path.join(source, 'verification.json');
const verification = fs.existsSync(verificationPath) ? JSON.parse(fs.readFileSync(verificationPath, 'utf8')) : null;
const proofPath = path.join(source, 'provider-verification.json');
const proof = fs.existsSync(proofPath) ? JSON.parse(fs.readFileSync(proofPath, 'utf8')) : null;
const escape = value => String(value ?? '').replace(/[&<>"']/g, value => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[value]);
const money = cents => `$${(Number(cents || 0) / 100).toFixed(2)}`;
const complete = verification?.run_id === (manifest.run_id ?? manifest.steps[0]?.application_time) && verification?.checks && Object.values(verification.checks).every(Boolean);
const simulated = manifest.payment_mode === 'bypass';
const transfers = verification?.operations?.filter(operation => operation.kind === 'transfer' && operation.status === 'succeeded').map(operation => operation.stripe_transfer_id) ?? [];
const providerVerified = !simulated && complete && proof?.verified === true && proof?.livemode === false
    && proof.run_id === verification.run_id
    && proof.verification_sha256 === crypto.createHash('sha256').update(fs.readFileSync(verificationPath)).digest('hex')
    && proof.signed_webhooks_processed === true && Array.isArray(proof.webhook_event_ids) && proof.webhook_event_ids.length >= 2
    && proof.webhook_event_ids.every(id => verification.webhook_events?.some(event => event.stripe_event_id === id && event.status === 'processed' && event.livemode === false && !event.connected_account_id && event.processed_at))
    && proof.purchase_public_id === verification.purchase.public_id && proof.stripe_charge_id === verification.purchase.stripe_charge_id
    && transfers.length > 0 && transfers.every(id => proof.transfer_ids?.includes(id));
const pick = (record, fields) => Object.fromEntries(fields.filter(field => record[field] !== undefined).map(field => [field, record[field]]));
const outputProof = providerVerified ? pick(proof, [
    'verified', 'livemode', 'verified_at', 'run_id', 'verification_sha256', 'purchase_public_id', 'stripe_charge_id',
    'transfer_ids', 'signed_webhooks_processed', 'webhook_event_ids', 'stripe_checkout_session_id', 'stripe_payment_intent_id',
    'connected_account_id', 'balance_transaction_id', 'purchase_amount_cents', 'currency', 'purchase_processing_fee_cents',
    'allocated_processing_fee_cents', 'caregiver_gross_cents', 'caregiver_net_cents', 'transferred_cents',
]) : { verified: false, run_id: manifest.run_id ?? manifest.steps[0]?.application_time ?? null,
    reason: simulated ? 'simulation_no_provider_verification' : 'matching_signed_provider_verification_not_available' };
if (providerVerified) {
    outputProof.webhook_events = (proof.webhook_events ?? []).map(event => pick(event, ['id', 'type', 'object_id', 'livemode', 'processed_at']));
    outputProof.transfers = (proof.transfers ?? []).map(transfer => pick(transfer, ['id', 'operation_public_id', 'amount_cents', 'currency', 'destination', 'source_charge_id', 'amount_reversed_cents', 'livemode']));
}
const proofLink = providerVerified ? '<div class="image-links"><a href="provider-verification.json">View Stripe verification evidence ↗</a></div>' : '';
fs.mkdirSync(path.join(destination, 'screenshots'), { recursive: true });
const steps = manifest.steps.map(step => {
    const title = simulated ? step.title.replace('Payment succeeds', 'Simulated payment succeeds').replace('the caregiver transfer', 'the simulated caregiver transfer').replace('and transfer in her earnings', 'and simulated transfer in her earnings') : step.title;
    for (const filename of step.files) {
        if (!/^[a-z0-9-]+\.png$/.test(filename)) throw new Error('Unexpected screenshot filename.');
        fs.copyFileSync(path.join(source, 'screenshots', filename), path.join(destination, 'screenshots', filename));
    }
    const focused = step.files.find(filename => filename.endsWith('-focus.png')) ?? step.files[0];
    const otherImages = step.files.filter(filename => filename !== focused).map(filename => `<a href="screenshots/${filename}">${filename.endsWith('-mobile.png') ? 'Mobile screen' : 'Full screen'} ↗</a>`).join('');
    return `<article id="${escape(step.id)}"><div class="step-heading"><span class="number">${step.order}</span><div><p class="role">${escape(step.role)}</p><h2>${escape(title)}</h2></div></div><a class="screenshot" href="screenshots/${focused}"><img src="screenshots/${focused}" alt="${escape(title)}" loading="lazy"></a><div class="image-links">${otherImages}</div></article>`;
}).join('\n');
const transferLabel = simulated ? 'Simulated transfer' : providerVerified ? 'Verified Stripe transfer' : 'App-reported transfer';
const feeLabel = simulated ? 'modeled' : providerVerified ? 'actual' : 'recorded';
const results = complete ? `<section class="results" aria-label="Verified application result"><div><strong>11 h</strong><span>Added for ${simulated ? 'simulated ' : ''}$310 payment</span></div><div><strong>2 h</strong><span>Care completed</span></div><div><strong>9 h</strong><span>Still available</span></div><div><strong>${money(verification.settlement.caregiver_net_cents)}</strong><span>${transferLabel}</span></div></section><p class="evidence">Caregiver gross ${money(verification.settlement.caregiver_gross_cents)} − allocated ${feeLabel} processing fee ${money(verification.settlement.package_fee_cents)} = ${money(verification.settlement.caregiver_net_cents)} ${transferLabel.toLowerCase()}. The package’s ${feeLabel} processing fee was ${money(verification.purchase.processing_fee_cents)}, spread across all 11 hours. ${providerVerified ? 'The charge and transfer were independently retrieved from Stripe.' : 'Real Stripe payment and transfer verification is pending.'}</p>` : `<p class="pending">This walkthrough is in progress. Final payment and balance verification has not completed.</p>`;
const provider = simulated ? 'Simulation — real Stripe verification pending' : providerVerified ? 'Verified real Stripe sandbox payments' : 'Stripe sandbox — provider verification pending';
const journeySummary = !complete ? 'Only the captured steps are shown; the remaining journey is still being verified.'
    : simulated ? 'The simulated purchase, welcome bonus, reservation, actual check-in and check-out actions, family approval, hour consumption, modeled fee allocation and simulated caregiver transfer passed the application checks. Real Stripe payment and transfer verification is pending.'
    : providerVerified ? 'Purchase, welcome bonus, reservation, actual check-in and check-out actions, family approval, hour consumption, proportional fee allocation and the independently verified Stripe test transfer passed.'
    : 'Application checks passed. Independent verification of the Stripe payment and transfer is still pending.';
const navigation = manifest.steps.map(step => `<a href="#${escape(step.id)}">${step.order}. ${escape(step.title)}</a>`).join('');
const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LoLo — Prepaid care hours customer journey</title><style>
:root{color-scheme:light;font-family:Arial,Helvetica,sans-serif;color:#183e36;background:#f6f4ef}*{box-sizing:border-box}body{margin:0}header{background:#173e36;color:white;padding:52px max(24px,calc((100vw - 1080px)/2))}header p{max-width:780px;line-height:1.65;color:#d4e6df}h1{font-size:clamp(30px,4vw,48px);line-height:1.15;letter-spacing:-1.3px;max-width:850px;margin:14px 0}.eyebrow{font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase}.badge{display:inline-block;border:1px solid #8bb5a7;border-radius:20px;padding:7px 12px;font-size:12px}main{max-width:1130px;margin:auto;padding:32px 24px 70px}.results{display:grid;grid-template-columns:repeat(4,1fr);background:#fff;border:1px solid #d5dfd8;border-radius:18px;overflow:hidden}.results div{padding:26px;text-align:center;border-right:1px solid #e0e7e2}.results div:last-child{border:0}.results strong{display:block;font-size:32px;line-height:1.4}.results span{font-size:14px;color:#567167}.evidence,.note{color:#567167;font-size:14px;line-height:1.7}.pending{padding:20px;background:#fff3d4;border-radius:12px}details{margin:28px 0}summary{cursor:pointer;font-weight:bold}.index{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:20px 0}.index a{color:#285c50;text-decoration:none;font-size:14px;line-height:1.5}article{background:#fff;border:1px solid #d5dfd8;border-radius:18px;padding:26px;margin-top:26px;break-inside:avoid}.step-heading{display:flex;gap:16px;align-items:center;margin-bottom:20px}.number{width:38px;height:38px;border-radius:50%;background:#e9f2ed;display:grid;place-content:center;flex:none;font-weight:bold}.role{font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#648073;margin:0 0 5px}h2{font-size:20px;line-height:1.4;margin:0}.screenshot{display:block;background:#f6f4ef;border:1px solid #e0e6e1;border-radius:10px;overflow:hidden}.screenshot img{display:block;width:100%;height:auto}.image-links{display:flex;gap:22px;flex-wrap:wrap;margin-top:16px}.image-links a{font-size:13px;color:#376c5d}footer{border-top:1px solid #d5dfd8;margin-top:34px;padding-top:20px;color:#567167;font-size:13px;line-height:1.7}@media(max-width:650px){header{padding:32px 22px}.results{grid-template-columns:1fr 1fr}.results div{padding:18px;border-bottom:1px solid #e0e7e2}.results strong{font-size:27px}.index{grid-template-columns:1fr}article{padding:16px}main{padding:24px 12px}h2{font-size:17px}}@media print{header{padding:24px;background:white;color:#183e36}header p{color:#567167}main{padding:16px}.index{display:none}article{page-break-inside:avoid}.image-links{display:none}h1{font-size:30px}.screenshot img{max-height:750px;object-fit:contain}details{display:none}}
</style></head><body><header><div class="eyebrow">LoLo · Customer walkthrough</div><h1>Buy care hours.<br>Use them when care happens.</h1><p>Emma buys hours for her father Robert, books Claire for a two-hour visit, and approves the completed care. Each image below comes from the working isolated staging application.</p><span class="badge">${provider} · ${complete ? 'Journey verified' : 'In progress'}</span></header><main>${results}<details><summary>Jump to a step</summary><nav class="index">${navigation}</nav></details>${steps}<footer><strong>What this demonstration proves</strong><br>${escape(journeySummary)}<br>${escape(manifest.time_control)}<br>Payment mode: ${escape(manifest.payment_mode)}. All people and care details are staging fixtures. Emails are captured locally. A successful connected-account transfer is distinct from a bank payout.</footer></main></body></html>`;
// Always replace this exact evidence file, so an unverified rebuild cannot retain a prior successful proof.
fs.writeFileSync(path.join(destination, 'provider-verification.json'), JSON.stringify(outputProof, null, 2));
fs.writeFileSync(path.join(destination, 'index.html'), html.replace('</footer>', `${proofLink}</footer>`));
fs.writeFileSync(path.join(destination, 'manifest.json'), JSON.stringify(manifest, null, 2));
if (verification) fs.copyFileSync(verificationPath, path.join(destination, 'verification.json'));
console.log(`Staging walkthrough written to ${path.join(destination, 'index.html')}`);
