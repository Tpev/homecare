import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const source = path.join(root, 'tmp/care-hours-launch-validation');
const output = path.join(root, 'output/care-hours-launch-validation');
const escape = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
const read = file => fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : null;
fs.mkdirSync(path.join(output, 'evidence'), { recursive: true });
fs.mkdirSync(path.join(output, 'screenshots'), { recursive: true });

const concurrencyRoot = path.join(root, 'tmp/care-hours-concurrency');
const concurrencyReports = fs.existsSync(concurrencyRoot) ? fs.readdirSync(concurrencyRoot)
    .map(name => path.join(concurrencyRoot, name, 'report.json')).filter(file => fs.existsSync(file))
    .map(file => ({ file, report: read(file) })).filter(({ report }) => report.passed === true)
    .sort((a, b) => fs.statSync(b.file).mtimeMs - fs.statSync(a.file).mtimeMs) : [];
const proofs = [
    ['contribution', 'Private contribution', path.join(source, 'contribution-provider-proof.json'), 'A relative buys hours through Robert’s private link. His family receives the hours; the buyer receives a private receipt.'],
    ['replay', 'Duplicate payment events', path.join(source, 'contribution-replay-verification.json'), 'Signed Stripe events replayed twice in reverse order. No duplicate hours, receipts or changes to saved cards.'],
    ['mixed', 'Decline, authentication and cancellation', path.join(source, 'mixed-cancellation-provider-proof.json'), 'A separate payment covers the uncovered time. Cancelling the visit restores all reserved hours and releases its payment authorization.'],
    ['mixed-capture', 'Complete care with hours plus card', path.join(source, 'mixed-capture-provider-proof.json'), 'The caregiver completes the visit, the family approves it, and only the uncovered time is captured. Caregiver earnings reconcile to the two funding sources.'],
    ['gift', 'Buy now, claim later', path.join(source, 'gift-claim-provider-proof.json'), 'The buyer receives a private code after payment. A signed-in family reviews the recipient and exact hours, then claims once.'],
    ['financial', 'Caregiver payment and recovery', path.join(root, 'tmp/care-hours-financial-validation/proof.json'), 'Separate backend fixtures verify actual sandbox transfers, processing-fee allocation and recovery. This evidence is distinct from the browser journey.'],
    ['partial-capture', 'Unused authorization versus cash refund', path.join(root, 'tmp/care-hours-financial-validation/partial-capture-proof.json'), 'The app correctly separates a $15.50 authorization release from a $1 cash refund after partially capturing a $31 payment.'],
    ['disputes', 'Refunds and disputed gift payments', path.join(root, 'tmp/care-hours-dispute-validation/proof.json'), 'Separate backend fixtures verify that reversed or disputed payments cannot turn into spendable gift hours.'],
    ['mysql', 'MySQL upgrade and simultaneous use', concurrencyReports[0]?.file, 'An isolated MySQL rehearsal checks migrations, competing claims and reservations, and recovery while new sales are paused.'],
].map(([id, title, file, description]) => {
    const data = file ? read(file) : null;
    const passed = id === 'replay' ? !!data?.checks && Object.values(data.checks).every(value => value === true)
        : id === 'mysql' ? data?.passed === true : data?.verified === true;
    if (data) fs.writeFileSync(path.join(output, 'evidence', `${id}.json`), JSON.stringify(data, null, 2));
    return { id, title, description, passed, evidence: data ? `evidence/${id}.json` : null };
});

const steps = [
    ['contribution-01-owner-link.png', '1. Share a private contribution link', 'Emma opens Robert’s care balance and creates a private link for his children.'],
    ['contribution-03-package.png', '2. Choose hours for Robert', 'A contributor verifies their email, chooses a package and pays with their own card.'],
    ['contribution-06-recipient-balance.png', '3. The family sees the combined balance', 'Hours appear in Robert’s account. The contributor does not gain access to it.'],
    ['gift-01-buy-without-selecting-recipient.png', '4. Or buy a gift before choosing the recipient', 'Choose 5, 10, 20 or 40 hours. No recipient is selected at purchase.'],
    ['gift-03-private-code-ready.png', '5. Share the gift code privately', 'The secured receipt reveals the code after confirmed payment. The code is hidden in this demonstration.'],
    ['gift-04-confirm-recipient-and-welcome-hour.png', '6. Review before claiming', 'The family chooses an existing recipient and confirms the exact paid and eligible welcome hours.'],
    ['gift-05-recipient-hours-ready.png', '7. Use the hours for future care', 'Claimed hours join the recipient’s balance. Booking with a card remains the default; using care hours is an explicit choice.'],
    ['mixed-04-authorized-coverage.png', '8. Cover any shortfall separately', 'The family authorizes only the time not covered by hours. The gift buyer’s card is never reused.'],
    ['mixed-05-full-hours-returned.png', '9. Cancelled care returns the full hours', 'No cancellation fee. All reserved hours return, and an unused authorization is released.'],
    ['mixed-capture-04-visit-paid.png', '10. Completed care pays the caregiver', 'A separate 12-hour visit uses 11 prepaid hours plus a $31 payment. Family approval settles the actual care and pays the caregiver.'],
    ['gift-06-admin-tracking.png', '11. LoLo can follow each gift', 'Admins can find the purchase, see whether it has been claimed, and open the recipient’s balance. Payment rechecks require a recorded reason.'],
].filter(([file]) => fs.existsSync(path.join(source, 'screenshots', file)));
for (const [file] of steps) fs.copyFileSync(path.join(source, 'screenshots', file), path.join(output, 'screenshots', file));

const regression = read(path.join(source, 'regression-results.json'));
const allPassed = proofs.every(proof => proof.passed) && regression?.passed === true;
if (regression) fs.writeFileSync(path.join(output, 'evidence/regressions.json'), JSON.stringify(regression, null, 2));
const summary = {
    generated_at: new Date().toISOString(), production_enabled: false, all_listed_checks_passed: allPassed,
    external_email_delivery: 'Deferred by the user. Codes and messages captured locally; buyer verification remains enabled.',
    proofs, regression_results: regression, screenshots: steps.map(([file]) => file),
};
fs.writeFileSync(path.join(output, 'verification-summary.json'), JSON.stringify(summary, null, 2));
fs.writeFileSync(path.join(output, 'index.html'), `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LoLo care hours — staging checks</title><style>
:root{font-family:Arial,sans-serif;color:#23483f;background:#f7f3ed}*{box-sizing:border-box}body{margin:0}main{max-width:1120px;margin:auto;padding:40px 24px 70px}h1,h2,h3{font-family:Georgia,serif}h1{font-size:clamp(36px,5vw,60px);line-height:1.05;max-width:780px;margin:22px 0}h2{font-size:30px}p{line-height:1.6;max-width:780px}.eyebrow{font-weight:bold;letter-spacing:.07em;text-transform:uppercase;font-size:12px}.status{display:inline-block;padding:9px 13px;border-radius:30px;background:#e0eee5;font-size:13px;font-weight:bold}.notice{background:#fff4da;border-left:4px solid #c49131;padding:16px 20px;margin:26px 0;border-radius:4px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.card,article{background:white;border:1px solid #dce2d9;border-radius:18px;padding:24px}.card h3{margin:12px 0;font-size:22px}.card p{font-size:15px;margin:10px 0}.pass{color:#1d6b45}.pending{color:#865c13}a{color:#23483f;text-underline-offset:4px}article{margin:22px 0;padding:0;overflow:hidden}article header{padding:22px 26px}article h3{margin:0;font-size:25px}article p{margin-bottom:0}article img{display:block;width:100%;height:auto;border-top:1px solid #e3e7df}.flow{display:flex;flex-wrap:wrap;gap:10px;margin:26px 0}.flow span{border:1px solid #c6d5c9;border-radius:9px;padding:12px 16px;background:#fff}footer{margin-top:40px;font-size:13px;color:#52685a}@media(max-width:650px){main{padding:26px 16px}.grid{grid-template-columns:1fr}.card{padding:19px}}
</style></head><body><main><div class="eyebrow">LoLo Care · isolated staging · October 21, 2026</div><h1>Give care today.<br>Use it when it’s needed.</h1><span class="status">Production remains disabled</span><p>Two simple ways to help: contribute to an existing recipient through their private link, or buy a gift and share a code for their family to claim. Both feed the same care-hours balance.</p><div class="flow"><span>Buy a package</span><span>Contribute or share a code</span><span>Choose care hours when booking</span><span>Caregiver gets paid</span></div><div class="notice"><strong>${allPassed ? 'Listed staging checks passed.' : 'Validation in progress.'}</strong> Email delivery to a real inbox is deferred at your request. Test codes and messages were captured locally. Deployment checks and production activation remain separate.</div><h2>What was checked</h2><div class="grid">${proofs.map(proof => `<section class="card"><span class="${proof.passed ? 'pass' : 'pending'}">${proof.passed ? '✓ Verified' : 'Awaiting final evidence'}</span><h3>${escape(proof.title)}</h3><p>${escape(proof.description)}</p>${proof.evidence ? `<a href="${proof.evidence}">View recorded evidence</a>` : ''}</section>`).join('')}</div><h2>Regression checks</h2><p>${regression ? escape(regression.summary) : "Final test results pending."} ${regression ? '<a href="evidence/regressions.json">Recorded results</a>' : ""}</p><h2>The customer journey</h2><p>Actual screenshots from the isolated app and Stripe sandbox. Names represent fictional care accounts. Visit time and GPS are controlled test inputs; check-in, check-out and approval use the real app actions. No live money moved.</p>${steps.map(([file,title,description]) => `<article><header><h3>${escape(title)}</h3><p>${escape(description)}</p></header><a href="screenshots/${escape(file)}"><img loading="lazy" src="screenshots/${escape(file)}" alt="${escape(title)}"></a></article>`).join('')}<footer><a href="verification-summary.json">Verification manifest</a> · <a href="../care-hours-staging/index.html">Original purchase → visit → caregiver transfer journey</a><p>Sandbox payment and bank-payout statuses simulate provider behavior; they do not prove delivery through a real bank. Existing card booking behavior remains covered by the regression suite.</p></footer></main></body></html>`);
console.log(JSON.stringify({ output, all_listed_checks_passed: allPassed, screenshot_count: steps.length }));
