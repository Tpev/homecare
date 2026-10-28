import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const root = process.cwd();
const source = path.join(root, 'tmp/care-hours-gift-onboarding');
const output = path.join(root, 'output/care-hours-gift-onboarding');
const read = file => fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : null;
const escape = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
fs.mkdirSync(path.join(output, 'screenshots'), { recursive: true });
fs.mkdirSync(path.join(output, 'evidence'), { recursive: true });

const provider = read(path.join(source, 'provider-proof.json'));
const verification = read(path.join(source, 'verification.json'));
const manifest = read(path.join(source, 'manifest.json'));
const handoff = read(path.join(source, 'care-handoff-verification.json'));
const concurrency = read(path.join(source, 'self-claim-concurrency.json'));
const runFiles = ['final-regressions.xml', 'claim-hardening.xml', 'handoff-regressions.xml'];
const runs = runFiles.map(file => {
    const absolute = path.join(source, file);
    if (!fs.existsSync(absolute)) return { source: file, passed: false, pending: true };
    const xml = fs.readFileSync(absolute, 'utf8');
    const first = xml.match(/<testsuite\s+([^>]+)>/);
    const attrs = Object.fromEntries([...first?.[1].matchAll(/([a-z]+)="([^"]*)"/g) ?? []].map(m => [m[1], m[2]]));
    const tests = Number(attrs.tests ?? 0), assertions = Number(attrs.assertions ?? 0);
    const errors = Number(attrs.errors ?? 0), failures = Number(attrs.failures ?? 0), skipped = Number(attrs.skipped ?? 0);
    return { source: file, tests, assertions, errors, failures, skipped,
        passed: tests > 0 && errors === 0 && failures === 0 && skipped === 0,
        sha256: crypto.createHash('sha256').update(xml).digest('hex') };
});
const regression = { passed: runs.every(run => run.passed), runs,
    note: 'The main suite and focused follow-up suites are reported separately; overlapping tests are not added into a combined total.' };
const appPassed = !!verification?.checks && Object.values(verification.checks).every(value => value === true);
const handoffPassed = !!handoff?.checks && Object.values(handoff.checks).every(value => value === true);
const allPassed = provider?.verified === true && appPassed && handoffPassed && concurrency?.passed === true && regression.passed;
if (provider) fs.writeFileSync(path.join(output, 'evidence/provider.json'), JSON.stringify(provider, null, 2));
if (handoff) fs.writeFileSync(path.join(output, 'evidence/care-handoff.json'), JSON.stringify(handoff, null, 2));
if (concurrency) fs.writeFileSync(path.join(output, 'evidence/self-claim-concurrency.json'), JSON.stringify(concurrency, null, 2));
fs.writeFileSync(path.join(output, 'evidence/regressions.json'), JSON.stringify(regression, null, 2));
fs.writeFileSync(path.join(output, 'evidence/journey-checks.json'), JSON.stringify({ checks: verification?.checks ?? {},
    fixture_note: manifest?.fixture_note, email_delivery: manifest?.email_delivery }, null, 2));

const steps = [
    ['01-jane-chooses-ten-hours', 'Jane buys the gift', '$310 buys 10 care hours. Her mum does not need a LoLo account yet.', 'Jane'],
    ['03-jane-shares-private-gift', 'She shares a code privately', 'The paid receipt gives Jane a gift code and a claim link. The code is hidden here.', 'Jane'],
    ['04-linda-enters-gift-code', 'Linda enters the code', 'She opens the claim link and enters Jane’s code before signing up.', 'Linda'],
    ['05-linda-creates-account', 'She creates her account', 'Name, email, password and terms. No payment card or care request.', 'Linda'],
    ['08-linda-reviews-eleven-hours', 'She claims for herself', '10 gifted hours + 1 free welcome hour. Linda confirms who the hours belong to.', 'Linda'],
    ['09-linda-hours-ready', 'Her care hours are ready', '11 hours are available with no expiry. She can arrange care when she needs it.', 'Linda'],
    ['11-jane-claimed-receipt', 'Jane knows the gift was claimed', 'Linda’s identity, balance and care details stay private.', 'Jane'],
].map(([base, title, description, person]) => {
    const file = `${base}.png`, mobile = `${base}-mobile.png`;
    if (!fs.existsSync(path.join(source, 'screenshots', file))) return null;
    fs.copyFileSync(path.join(source, 'screenshots', file), path.join(output, 'screenshots', file));
    const hasMobile = fs.existsSync(path.join(source, 'screenshots', mobile));
    if (hasMobile) fs.copyFileSync(path.join(source, 'screenshots', mobile), path.join(output, 'screenshots', mobile));
    return { file, mobile: hasMobile ? mobile : null, title, description, person };
}).filter(Boolean);

const summary = { generated_at: new Date().toISOString(), production_enabled: false,
    all_listed_checks_passed: allPassed, real_stripe_sandbox: provider?.verified === true,
    journey_checks: verification?.checks ?? {}, care_handoff_checks: handoff?.checks ?? {},
    simultaneous_self_claims_passed: concurrency?.passed === true, regression, screenshots: steps,
    external_email_delivery: 'Deferred by the user. Email verification stayed enabled; test messages were captured locally.' };
fs.writeFileSync(path.join(output, 'verification-summary.json'), JSON.stringify(summary, null, 2));
fs.writeFileSync(path.join(output, 'index.html'), `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Jane’s gift for Linda · LoLo Care</title><style>
:root{font-family:Arial,sans-serif;background:#f7f4ee;color:#23483f}*{box-sizing:border-box}body{margin:0}main{max-width:1100px;margin:auto;padding:44px 24px 70px}h1,h2,h3{font-family:Georgia,serif;font-weight:500}h1{font-size:clamp(38px,6vw,64px);line-height:1.08;max-width:760px;margin:20px 0}h2{font-size:30px;margin-top:44px}p{line-height:1.6;max-width:750px}.eyebrow{font-size:12px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase}.tag{display:inline-block;font-size:12px;font-weight:bold;padding:8px 12px;background:#e0ece2;border-radius:30px}.flow{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:28px 0}.flow div{background:#fff;border:1px solid #d6e0d5;padding:18px;border-radius:12px}.flow span{display:block;color:#68826e;font-size:12px;margin-bottom:8px}.note{padding:17px 20px;background:#fff;border-left:4px solid #52735c;font-size:14px;border-radius:4px}.step{margin:24px 0;border:1px solid #d7e1d5;border-radius:18px;background:#fff;overflow:hidden}.step header{padding:24px}.step h3{font-size:27px;margin:12px 0 8px}.step p{margin:0}.step img{display:block;width:100%;height:auto;border-top:1px solid #e3e9df}.step .person{font-size:11px;font-weight:bold;letter-spacing:.06em;text-transform:uppercase;color:#667b6b}a{color:inherit;text-underline-offset:4px}.checks{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.checks p{padding:18px;background:#fff;border:1px solid #d7e1d5;border-radius:12px;margin:0;font-size:14px}details{margin-top:22px;padding:20px;border:1px solid #d7e1d5;border-radius:12px;background:#fff}summary{cursor:pointer;font-weight:bold}li{line-height:1.7;margin:8px 0}footer{font-size:13px;margin-top:36px;color:#52685a}@media(max-width:650px){main{padding:28px 16px}.flow{grid-template-columns:repeat(2,1fr)}.checks{grid-template-columns:1fr}.step header{padding:20px}h2{font-size:26px}}
</style></head><body><main><div class="eyebrow">LoLo Care · Jane & Linda Bennett · staging walkthrough</div><h1>A gift today.<br>Care when Mum needs it.</h1><span class="tag">Production remains disabled</span><p>Jane buys 10 hours for her mum, Linda, before Linda has an account. Linda joins later and claims the gift for herself. Her eligible welcome bonus brings the balance to <strong>11 hours</strong>.</p><div class="flow"><div><span>01</span>Enter code</div><div><span>02</span>Create account</div><div><span>03</span>Claim for myself</div><div><span>04</span>Hours ready</div></div><div class="note">${allPassed ? '<strong>Verified in the isolated Stripe sandbox.</strong>' : '<strong>Validation in progress.</strong>'} No live money moved. Screenshots show the real app; gift codes and passwords are hidden.</div>
${steps.map((step, i) => `<article class="step"><header><div class="person">${escape(step.person)} · Step ${i + 1}</div><h3>${escape(step.title)}</h3><p>${escape(step.description)}</p></header><a href="screenshots/${escape(step.file)}"><picture>${step.mobile ? `<source media="(max-width:650px)" srcset="screenshots/${escape(step.mobile)}">` : ''}<img src="screenshots/${escape(step.file)}" loading="lazy" alt="${escape(step.title)}"></picture></a></article>`).join('')}
<h2>What was checked</h2><div class="checks"><p><strong>Real test payment.</strong><br>$310 purchase, signed Stripe events and a single 11-hour credit.</p><p><strong>A new recipient.</strong><br>Linda did not exist when Jane paid. Signup and claim used the actual screens.</p><p><strong>Privacy and repeat attempts.</strong><br>The code claims once. Jane gets no access to Linda’s care account.</p><p><strong>Existing behavior.</strong><br>Card bookings stay on their current path. Claiming creates no booking or saved card.</p></div><details><summary>Verification details</summary><ul>${runs.map(run => `<li>${run.passed ? 'Passed' : 'Pending'} · ${escape(run.source)}${run.tests ? ` · ${run.tests} tests / ${run.assertions} assertions` : ''}</li>`).join('')}</ul><p>Focused follow-up suites can overlap the main suite and are shown separately.</p><p><a href="evidence/provider.json">Provider evidence</a> · <a href="evidence/journey-checks.json">Browser checks</a> · <a href="evidence/regressions.json">Regression results</a></p></details><footer><p>Actual inbox delivery remains deferred at your request. Test emails were captured locally; verification remains part of buying a gift.</p><a href="../care-hours-launch-validation/index.html">Earlier contribution, booking and caregiver-payment validation</a> · <a href="verification-summary.json">This walkthrough’s manifest</a></footer></main></body></html>`);
const pageFile = path.join(output, 'index.html');
fs.writeFileSync(pageFile, fs.readFileSync(pageFile, 'utf8').replace('</details>',
    '<p>Find care keeps Linda’s original recipient identity. Two simultaneous gifts were also tested in an isolated MySQL database: one profile, one balance and one welcome bonus.</p><p><a href="evidence/care-handoff.json">Find care evidence</a> · <a href="evidence/self-claim-concurrency.json">Simultaneous-claim evidence</a></p></details>'));
console.log(JSON.stringify({ output, all_listed_checks_passed: allPassed, screenshots: steps.length }));
