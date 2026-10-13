import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Local documentation generator. No app imports, network access, database writes,
// screenshot fabrication, or publishing. Re-run after capture.mjs finishes.
const auditRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const inputPath = process.argv[2] ? path.resolve(process.argv[2]) : path.join(auditRoot, 'captures.json');
const stages = ['Create', 'Waiting / inviting', 'Hired / before shift', 'During shift', 'After shift', 'Timesheet / review', 'Closed'];
const variationLabels = { one_time: 'One-time care', recurring: 'Recurring care', shared: 'Shared navigation' };
const deviceLabels = { desktop: 'Desktop', mobile: 'Mobile' };
const sourceNames = ['one-time-source-inventory.md', 'recurring-source-inventory.md', 'shared-source-inventory.md', 'method-and-coverage.md', 'validation-notes.md'];
const warnings = [];
const exists = relative => typeof relative === 'string' && fs.existsSync(path.resolve(auditRoot, relative));
const toPosix = value => String(value ?? '').replaceAll('\\', '/');
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
const inline = value => String(value ?? '').replace(/\\/g, '\\\\').replace(/([`*_\[\]<>|])/g, '\\$1').replace(/\r?\n/g, '<br>');
const textValue = value => value === undefined ? '(not captured)' : value === null ? 'null' : typeof value === 'string' ? value : JSON.stringify(value);
const mdLink = (label, target, prefix = '') => target ? `[${inline(label)}](<${prefix}${toPosix(target)}>)` : '—';
const formatDate = new Date().toISOString();
const readJson = filename => JSON.parse(fs.readFileSync(filename, 'utf8'));

if (!fs.existsSync(inputPath)) throw new Error(`Capture manifest not found: ${inputPath}. Run the capture before generating the report.`);
const input = readJson(inputPath);
const captures = Array.isArray(input) ? input : input.screens;
if (!Array.isArray(captures)) throw new Error('captures.json must contain an array, or an object with a screens array.');
const seenIds = new Set();
for (const screen of captures) {
  if (!screen.id || !/^[a-zA-Z0-9_-]+$/.test(screen.id)) throw new Error(`Unsafe or missing screen id: ${screen.id}`);
  if (seenIds.has(screen.id)) throw new Error(`Duplicate screen id: ${screen.id}`);
  seenIds.add(screen.id);
  if (!stages.includes(screen.stage)) warnings.push(`${screen.id}: unknown stage ${screen.stage}; included under Additional surfaces.`);
}
const notesPath = path.join(auditRoot, 'review-notes.json');
const reviewNotes = fs.existsSync(notesPath) ? readJson(notesPath) : {};
const sourceFiles = sourceNames.filter(exists);
const sourceEvidence = sourceFiles.map(name => ({ title: name === 'method-and-coverage.md' ? 'Method and coverage' : name === 'validation-notes.md' ? 'Verified UI observations' : name.replace('-source-inventory.md', '').replaceAll('-', ' ') + ' source inventory', path: name }));
fs.mkdirSync(path.join(auditRoot, 'screens'), { recursive: true });

function codeBlock(content, language = '') {
  const body = String(content ?? '');
  const longest = Math.max(2, ...[...body.matchAll(/`+/g)].map(match => match[0].length));
  const fence = '`'.repeat(longest + 1);
  return `${fence}${language}\n${body}\n${fence}`;
}

function evidenceFor(screen, device) {
  const item = screen[device];
  if (!item) return null;
  const evidence = { ...item, metadata: item.metadata ?? {}, inventoryData: null, visibleText: '', missing: [] };
  for (const key of ['viewport', 'full', 'text', 'inventory']) {
    if (!exists(item[key])) evidence.missing.push(key);
  }
  if (item.focus && !exists(item.focus)) evidence.missing.push('focus');
  if (typeof item.metadata === 'string' && exists(item.metadata)) evidence.metadata = readJson(path.resolve(auditRoot, item.metadata));
  if (exists(item.inventory)) evidence.inventoryData = readJson(path.resolve(auditRoot, item.inventory));
  if (exists(item.text)) evidence.visibleText = fs.readFileSync(path.resolve(auditRoot, item.text), 'utf8');
  else evidence.visibleText = evidence.inventoryData?.text ?? '';
  if (evidence.missing.length) warnings.push(`${screen.id} ${device}: missing ${evidence.missing.join(', ')} evidence.`);
  return evidence;
}

const screens = captures.map(screen => {
  const evidence = { desktop: evidenceFor(screen, 'desktop'), mobile: evidenceFor(screen, 'mobile') };
  const complete = screen.status === 'captured' && Object.values(evidence).every(device => device && !device.missing.length);
  return { ...screen, evidence, verifiedStatus: complete ? 'Captured' : screen.status === 'failed' ? 'Failed' : 'Incomplete evidence', review: reviewNotes[screen.id] ?? {} };
});
const byStage = [...stages, ...(screens.some(screen => !stages.includes(screen.stage)) ? ['Additional surfaces'] : [])];
const sorted = [...screens].sort((a, b) => {
  const stageOrder = value => stages.includes(value) ? stages.indexOf(value) : stages.length;
  const variations = ['one_time', 'recurring', 'shared'];
  return stageOrder(a.stage) - stageOrder(b.stage)
    || variations.indexOf(a.variation) - variations.indexOf(b.variation)
    || a.id.localeCompare(b.id);
});

function deviceReport(screen, device) {
  const item = screen.evidence[device];
  if (!item) return `## ${deviceLabels[device]}\n\nNo ${device} capture was recorded.\n`;
  const data = item.inventoryData ?? {};
  const meta = item.metadata ?? {};
  const output = [`## ${deviceLabels[device]}`, ''];
  output.push(`Viewport: **${meta.width ?? data.width ?? '?'} × ${meta.height ?? data.height ?? '?'} CSS px**. Document height: ${meta.documentHeight ?? data.documentHeight ?? '?'} px. Horizontal overflow: ${meta.horizontalOverflow ?? 'not recorded'} px.`, '');
  if (meta.finalUrl) output.push(`Captured URL: ${inline(meta.finalUrl)}`, '');
  output.push([item.focus ? mdLink('Focused view', item.focus, '../') : null, mdLink('First screenful', item.viewport, '../'), mdLink('Full-page screenshot', item.full, '../'), mdLink('Raw visible text', item.text, '../'), mdLink('Raw information inventory (JSON)', item.inventory, '../')].filter(Boolean).join(' · '), '');
  if (item.missing.length) output.push(`**Evidence missing:** ${item.missing.join(', ')}.`, '');
  const preview = exists(item.focus) ? item.focus : item.viewport;
  if (exists(preview)) output.push(`![${deviceLabels[device]} screenshot of ${inline(screen.title)}](<../${toPosix(preview)}>)`, '');
  output.push('### Everything visible as text', '', 'This preserves the full rendered text, including global navigation, tabs, helper text, notices and data values. The controls inventory below also records input values and dropdown options that are not represented in body text.', '', codeBlock(item.visibleText || '(No visible-text evidence available.)'), '');
  output.push('### Visible headings', '');
  const headings = data.headings ?? [];
  if (headings.length) output.push('| Level | Heading |', '|---|---|', ...headings.map(h => `| ${inline(h.level)} | ${inline(h.text)} |`), '');
  else output.push('No headings recorded.', '');
  output.push('### Every visible control and its current value', '');
  const controls = data.controls ?? [];
  if (!controls.length) output.push('No controls recorded.', '');
  for (const [index, control] of controls.entries()) {
    const label = control.label || control.text || control.placeholder || `${control.tag ?? 'Control'} ${control.type ?? ''}`;
    output.push(`#### ${index + 1}. ${inline(label)}`, '');
    for (const [key, value] of Object.entries(control)) {
      if (key === 'options' && Array.isArray(value)) {
        output.push('- **Dropdown options:**');
        for (const option of value) output.push(`  - ${inline(option.label ?? option.text ?? option.value)} — value: ${inline(textValue(option.value))}; selected: ${inline(textValue(option.selected))}${Object.keys(option).some(k => !['label', 'text', 'value', 'selected'].includes(k)) ? `; ${inline(JSON.stringify(option))}` : ''}`);
      } else output.push(`- **${inline(key)}:** ${value === '' ? '(empty)' : inline(textValue(value))}`);
    }
    output.push('');
  }
  output.push('### Every visible link', '');
  const links = data.links ?? [];
  if (links.length) output.push('| Label | Destination |', '|---|---|', ...links.map(link => `| ${inline(link.text || link.label || '(no text label)')} | ${inline(link.href ?? link.url ?? '(none)')} |`), '');
  else output.push('No links recorded.', '');
  output.push('### Disclosure states', '');
  const details = data.details ?? [];
  if (details.length) output.push('| Disclosure | State |', '|---|---|', ...details.map(detail => `| ${inline(detail.summary ?? detail.text)} | ${detail.open ? 'Open' : 'Closed'} |`), '');
  else output.push('No native disclosures recorded.', '');
  output.push('### Visible images and loading state', '');
  const images = data.images ?? [];
  if (images.length) output.push('| Alternative text | Source | Loaded |', '|---|---|---|', ...images.map(image => `| ${inline(image.alt || '(empty)')} | ${inline(image.src)} | ${inline(textValue(image.loaded))} |`), '');
  else output.push('No image elements recorded.', '');
  const additional = Object.fromEntries(Object.entries(data).filter(([key]) => !['text', 'headings', 'controls', 'links', 'details', 'images'].includes(key)));
  output.push('### Capture metadata', '', codeBlock(JSON.stringify({ ...additional, ...meta }, null, 2), 'json'), '');
  return output.join('\n');
}

for (const screen of sorted) {
  const body = [
    `# ${screen.id} — ${screen.title}`, '',
    `${mdLink('Master tracker', 'master-tracker.md', '../')} · ${mdLink('Visual screenshot browser', 'index.html', '../')}`, '',
    '| Audit field | Value |', '|---|---|',
    `| Care variation | ${inline(variationLabels[screen.variation] ?? screen.variation)} |`,
    `| Lifecycle stage | ${inline(screen.stage)} |`,
    `| Route | ${inline(screen.url)} |`,
    `| Selected tab | ${inline(screen.tab || 'No local tab')} |`,
    `| Panel / disclosure | ${inline(screen.disclosure || 'Default')} |`,
    `| Capture status | ${screen.verifiedStatus} |`,
    `| UX review | ${inline(screen.review.review ?? '')} |`,
    `| UX findings | ${inline(screen.review.findings ?? '')} |`,
    `| Redesign decision | ${inline(screen.review.redesign ?? '')} |`,
    `| Owner | ${inline(screen.review.owner ?? '')} |`,
    `| Priority | ${inline(screen.review.priority ?? '')} |`, '',
    'These screenshots show the current application with synthetic audit data. This is capture and source-audit evidence; no UI redesign is implemented. Remote map/payment widgets were blocked in this local capture session, so their current fallback states are preserved. Google Fonts were allowed.', '',
  ];
  if (screen.error) body.push(`**Capture error:** ${inline(screen.error)}`, '');
  const additional = Object.fromEntries(Object.entries(screen).filter(([key]) => !['id', 'title', 'variation', 'stage', 'url', 'tab', 'disclosure', 'status', 'desktop', 'mobile', 'evidence', 'verifiedStatus', 'review', 'error'].includes(key)));
  if (Object.keys(additional).length) body.push('## Additional capture context', '', codeBlock(JSON.stringify(additional, null, 2), 'json'), '');
  body.push(deviceReport(screen, 'desktop'), deviceReport(screen, 'mobile'));
  fs.writeFileSync(path.join(auditRoot, 'screens', `${screen.id}.md`), body.join('\n'));
}

const capturedCount = screens.filter(screen => screen.verifiedStatus === 'Captured').length;
const screenshotCount = screens.reduce((sum, screen) => sum + ['desktop', 'mobile'].reduce((n, device) => n + ['focus', 'viewport', 'full'].filter(key => exists(screen.evidence[device]?.[key])).length, 0), 0);
const representative = (label, ...ids) => ({ label, screen: ids.map(id => screens.find(screen => screen.id === id && screen.verifiedStatus === 'Captured')).find(Boolean) });
const primaryJourney = stages.map((stage, index) => {
  const forVariation = prefix => {
    const steps = [
      [representative('Create request', `${prefix}-create-ready`, `${prefix}-create-empty`)],
      [representative('Waiting and inviting', `${prefix}-waiting-applicants`)],
      [representative('Before the visit', `${prefix}-scheduled-shift`)],
      [representative('Visit in progress', `${prefix}-in_progress-shift`)],
      [representative('After the visit', `${prefix}-after_shift-shift`)],
      [representative('Review hours', `${prefix}-timesheet_review-shift`), representative('Leave a review', `${prefix}-review-form-empty`, `${prefix}-leave_review-shift`)],
      [representative('Closed visit record', `${prefix}-closed-shift`)],
    ];
    if (prefix === 'RC' && index === 2) steps[index].push(representative('Active recurring plan', 'RC-plan-active'));
    if (prefix === 'RC' && index === 6) steps[index].push(representative('Ended recurring plan', 'RC-plan-ended'));
    return steps[index];
  };
  return { stage, one_time: forVariation('OT'), recurring: forVariation('RC') };
});
function journeyMarkdownCell(items) {
  return items.map(({ label, screen }) => {
    if (!screen) return `${inline(label)} — Not captured`;
    const links = ['desktop', 'mobile'].map(device => {
      const evidence = screen.evidence[device];
      return mdLink(deviceLabels[device], exists(evidence.focus) ? evidence.focus : evidence.viewport);
    });
    return `${mdLink(label, `screens/${screen.id}.md`)}<br>${links.join(' · ')}`;
  }).join('<br><br>');
}
const master = [
  '# Find-care screen audit — master tracker', '',
  'Audit date: **2026-09-07**. Scope: family finding care, one-time and recurring, from creation through closed records. **Current UI inventory and screenshots; redesign has not started.**', '',
  `${mdLink('Open visual screenshot browser', 'index.html')} · ${mdLink('Machine-readable capture manifest', 'captures.json')}`, '',
  `**${capturedCount} of ${screens.length} screen states have verified desktop + mobile evidence. ${screenshotCount} screenshot files are linked.** Each captured state has a first-screenful and full-page PNG at each viewport, plus rendered text and a structured information inventory. Focused PNGs begin at the page heading where available; gallery previews use these to make the workflow easier to inspect.`, '',
  'Desktop: 1440 × 1000. Mobile: 390 × 844, touch/mobile emulation. Browser: headless Edge Chromium, DPR 1. The application was captured after the current Vite build using isolated synthetic audit data. Remote map and payment widgets were blocked for the local capture session; Google Fonts were allowed. Visible widget fallback states are part of the evidence. A captured screen does not validate a third-party payment or map interaction.', '',
  'A stage is a customer step; a screen state is a specific route, selected tab, disclosure/panel state and data condition. Recurring care has both an arrangement lifecycle and a dated-visit lifecycle. A closed visit can belong to an active recurring arrangement.', '',
  '## Follow the primary journey', '',
  'Start with these representative screens, then use the full matrix below for tabs and conditional variations. Screen titles open the complete information inventory; Desktop and Mobile open the focused screenshot where available.', '',
  '| Step | One-time care | Recurring care |', '|---|---|---|',
  ...primaryJourney.map((row, index) => `| **${index + 1}. ${inline(row.stage)}** | ${journeyMarkdownCell(row.one_time)} | ${journeyMarkdownCell(row.recurring)} |`), '',
  '**Recurring closure has two meanings:** a completed/closed visit preserves its final record while the agreement may continue. Ending the recurring plan stops the agreement; its next confirmed visit may still be retained. Both screens are linked separately above.', '',
  '## Coverage by stage', '',
  '| Stage | One-time care | Recurring care | Shared navigation | Captured / listed |', '|---|---:|---:|---:|---:|',
];
for (const stage of byStage) {
  const rows = screens.filter(screen => stage === 'Additional surfaces' ? !stages.includes(screen.stage) : screen.stage === stage);
  master.push(`| ${inline(stage)} | ${rows.filter(row => row.variation === 'one_time').length} | ${rows.filter(row => row.variation === 'recurring').length} | ${rows.filter(row => row.variation === 'shared').length} | ${rows.filter(row => row.verifiedStatus === 'Captured').length} / ${rows.length} |`);
}
master.push('', '## Source audit and conditional coverage', '', 'The source inventories list the full field/action set, branch triggers and additional conditional states. A source-listed branch without a matching capture row remains not visually captured. Do not infer screenshot completeness from source coverage.', '');
for (const source of sourceEvidence) master.push(`- ${mdLink(source.title, source.path)}`);
master.push('', '## Screen matrix', '', 'Blank UX review, redesign and owner cells are intentionally reserved for the next phase. To preserve these fields when regenerating the report, add entries keyed by screen ID in `review-notes.json` using `review`, `findings`, `redesign`, `owner` and `priority`.', '');
for (const stage of byStage) {
  master.push(`### ${stage}`, '', '| Screen / visible state | Variation | Tab / disclosure | Desktop | Mobile | Every visible field | Evidence | UX review | Redesign | Owner |', '|---|---|---|---|---|---|---|---|---|---|');
  const rows = sorted.filter(screen => stage === 'Additional surfaces' ? !stages.includes(screen.stage) : screen.stage === stage);
  if (!rows.length) master.push('| No screen state captured for this stage yet | | | | | | Not captured | | | |');
  for (const screen of rows) {
    const evidenceCell = device => {
      const item = screen.evidence[device];
      return item ? [exists(item.focus) ? mdLink('Focus', item.focus) : null, exists(item.viewport) ? mdLink('First', item.viewport) : 'First missing', exists(item.full) ? mdLink('Full', item.full) : 'Full missing'].filter(Boolean).join(' · ') : 'Not captured';
    };
    master.push(`| **${inline(screen.id)}**<br>${inline(screen.title)} | ${inline(variationLabels[screen.variation] ?? screen.variation)} | ${inline(screen.tab || '—')}<br>${inline(screen.disclosure || 'Default')} | ${evidenceCell('desktop')} | ${evidenceCell('mobile')} | ${mdLink('Information inventory', `screens/${screen.id}.md`)} | ${screen.verifiedStatus} | ${inline(screen.review.review ?? '')} | ${inline(screen.review.redesign ?? '')} | ${inline(screen.review.owner ?? '')} |`);
  }
  master.push('');
}
master.push('## Capture limitations and follow-up', '', '- All visible UI content is documented per viewport. Closed tabs/disclosures hide content; expanded and alternate-tab captures are separate rows.', '- Synthetic data demonstrates the recorded state. This audit does not claim live production data or completed live hiring/payment actions.', '- Remote map/payment integration behavior needs a later connected-session check if it affects the redesign.', '- Source-only conditional branches remain a follow-up capture checklist; implementation discrepancies are preserved in the source inventories.', '');
if (warnings.length) master.push('### Evidence warnings', '', ...warnings.map(warning => `- ${inline(warning)}`), '');
master.push('Generated from captures.json at ' + formatDate + '. Rebuild with the bundled Node runtime: `node capture/build-report.mjs`.', '');
fs.writeFileSync(path.join(auditRoot, 'master-tracker.md'), master.join('\n'));

function htmlLink(label, href, className = '') {
  return href ? `<a${className ? ` class="${escapeHtml(className)}"` : ''} href="${escapeHtml(toPosix(href))}">${escapeHtml(label)}</a>` : '';
}

function journeyHtmlCell(items) {
  return items.map(({ label, screen }) => {
    if (!screen) return `<div class="journey-item"><span>${escapeHtml(label)}</span><small>Not captured</small></div>`;
    const shots = ['desktop', 'mobile'].map(device => {
      const evidence = screen.evidence[device];
      return htmlLink(deviceLabels[device], exists(evidence.focus) ? evidence.focus : evidence.viewport);
    }).join('');
    return `<div class="journey-item"><a class="journey-screen" href="#${escapeHtml(screen.id)}">${escapeHtml(label)} →</a><div class="journey-links">${htmlLink('All fields', `screens/${screen.id}.md`)}${shots}</div></div>`;
  }).join('');
}
const primaryJourneyHtml = `<section class="primary-journey" aria-labelledby="primary-journey-heading"><div class="journey-intro"><div class="eyebrow">Start here · 7 stages</div><h2 id="primary-journey-heading">Follow the primary journey.</h2><p>Open a representative screen at each step, then explore every tab and variation below.</p></div><div class="journey-table-wrap"><table class="journey-table"><thead><tr><th scope="col">Stage</th><th scope="col">One-time care</th><th scope="col">Recurring care</th></tr></thead><tbody>${primaryJourney.map((row, index) => `<tr><th scope="row"><span>${index + 1}</span>${escapeHtml(row.stage)}</th><td>${journeyHtmlCell(row.one_time)}</td><td>${journeyHtmlCell(row.recurring)}</td></tr>`).join('')}</tbody></table></div><p class="journey-note"><strong>A closed recurring visit and an ended recurring plan are different.</strong> The visit keeps its final record while recurring care may continue. Ending the agreement can still retain the next confirmed visit; both closure views are linked separately.</p></section>`;

function screenshotHtml(screen, device) {
  const evidence = screen.evidence[device];
  const title = deviceLabels[device];
  if (!evidence || !exists(evidence.viewport)) return `<div class="shot ${device}"><div class="shot-label">${title}</div><div class="missing">No ${title.toLowerCase()} screenshot available</div></div>`;
  const meta = evidence.metadata;
  const preview = exists(evidence.focus) ? evidence.focus : evidence.viewport;
  const previewLabel = exists(evidence.focus) ? 'focused view' : 'first screenful';
  const imageWidth = Number(meta.width) || (device === 'desktop' ? 1440 : 390);
  const imageHeight = Number(meta.height) || (device === 'desktop' ? 1000 : 844);
  return `<div class="shot ${device}"><div class="shot-label">${title} <span>${escapeHtml(meta.width)} × ${escapeHtml(meta.height)}</span></div><a class="preview-link" href="${escapeHtml(toPosix(preview))}" title="Open ${title.toLowerCase()} ${previewLabel}"><img src="${escapeHtml(toPosix(preview))}" width="${imageWidth}" height="${imageHeight}" alt="${escapeHtml(screen.title)} — ${title.toLowerCase()}, ${previewLabel}" loading="lazy" decoding="async"></a><div class="shot-actions">${exists(evidence.focus) ? htmlLink('Focused view', evidence.focus) : ''}${htmlLink('First screenful', evidence.viewport)}${htmlLink('Full page', evidence.full)}${htmlLink('Data JSON', evidence.inventory)}</div></div>`;
}

const cards = sorted.map(screen => {
  const searchText = [screen.id, screen.title, screen.stage, screen.variation, screen.url, screen.tab, screen.disclosure, ...['desktop', 'mobile'].map(device => screen.evidence[device]?.visibleText ?? '')].join(' ').toLowerCase();
  return `<article class="screen-card" id="${escapeHtml(screen.id)}" data-variation="${escapeHtml(screen.variation)}" data-stage="${escapeHtml(screen.stage)}" data-status="${escapeHtml(screen.verifiedStatus)}" data-search="${escapeHtml(searchText)}"><header class="card-header"><div class="kickers"><span>${escapeHtml(variationLabels[screen.variation] ?? screen.variation)}</span><span>${escapeHtml(screen.stage)}</span><span class="status ${screen.verifiedStatus === 'Captured' ? 'captured' : 'incomplete'}">${escapeHtml(screen.verifiedStatus)}</span></div><h2>${escapeHtml(screen.title)}</h2><div class="route"><strong>${escapeHtml(screen.id)}</strong> · ${escapeHtml(screen.url)}</div><div class="state">${screen.tab ? `Tab: ${escapeHtml(screen.tab)} · ` : ''}${escapeHtml(screen.disclosure || 'Default screen')}</div></header><div class="shots">${screenshotHtml(screen, 'desktop')}${screenshotHtml(screen, 'mobile')}</div><footer class="card-footer">${htmlLink('Every visible field & control ↗', `screens/${screen.id}.md`, 'inventory-link')}<span>${screen.review.review ? escapeHtml(screen.review.review) : 'UX review: pending'} · ${screen.review.redesign ? escapeHtml(screen.review.redesign) : 'Redesign: not started'}</span></footer>${screen.error ? `<p class="error">${escapeHtml(screen.error)}</p>` : ''}</article>`;
}).join('\n');

const html = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Find care · current screen audit</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#23372f;background:#f5f4ef;font-synthesis:none}*{box-sizing:border-box}body{margin:0}a{color:#225746;text-underline-offset:3px}button,input,select{font:inherit}a:focus-visible,input:focus-visible,select:focus-visible,button:focus-visible{outline:3px solid #d27f41;outline-offset:3px}.page{max-width:1440px;margin:auto;padding:40px 28px 64px}.hero{display:flex;justify-content:space-between;gap:28px;align-items:flex-end;margin-bottom:26px}.eyebrow{font-size:12px;font-weight:750;letter-spacing:.14em;text-transform:uppercase;color:#586e5e}h1{font-size:clamp(30px,4vw,54px);letter-spacing:-.045em;line-height:1.05;margin:10px 0 17px;max-width:830px}p{line-height:1.6}.intro{max-width:850px;color:#536359;margin:0;font-size:16px}.hero-count{flex:0 0 auto;text-align:right;white-space:nowrap}.hero-count b{font-size:38px;letter-spacing:-.04em}.hero-count span{display:block;color:#637368;font-size:13px}.nav{display:flex;gap:20px;flex-wrap:wrap;margin:24px 0;font-size:14px;font-weight:650}.note{border:1px solid #d8ddd2;border-radius:14px;padding:15px 18px;background:#fffefa;font-size:13px;line-height:1.65;color:#647065}.note strong{color:#334b3c}.filters{position:sticky;top:0;z-index:5;margin:24px 0 16px;display:grid;grid-template-columns:minmax(220px,1fr) 200px 230px 160px;gap:12px;padding:16px;background:rgba(245,244,239,.96);backdrop-filter:blur(14px);border:1px solid #d9dfd3;border-radius:15px;box-shadow:0 6px 16px #23372f08}.filter label{display:block;font-size:11px;font-weight:750;text-transform:uppercase;letter-spacing:.09em;color:#627363;margin-bottom:7px}.filter input,.filter select{border:1px solid #c8d2c2;border-radius:9px;background:white;padding:10px 12px;width:100%;min-height:42px;color:#23372f}.results-line{font-size:13px;color:#6e776d;margin:0 2px 22px}.screen-card{border:1px solid #d2dcd0;border-radius:20px;background:#fff;overflow:hidden;margin-bottom:26px;box-shadow:0 10px 28px #23372f07;scroll-margin-top:120px}.card-header{padding:22px 24px 18px;border-bottom:1px solid #e8ebe3}.kickers{display:flex;flex-wrap:wrap;gap:9px;font-size:11px;font-weight:650;color:#637361}.kickers>span{padding:5px 9px;background:#f0f3ed;border-radius:6px}.kickers>.captured{background:#e5f3e8;color:#2e6344}.kickers>.incomplete{background:#fff0d9;color:#815914}h2{font-size:22px;line-height:1.25;letter-spacing:-.025em;margin:12px 0 8px}.route{font-size:12px;color:#637463;overflow-wrap:anywhere}.route strong{font-weight:650;color:#3a523f}.state{font-size:12px;color:#7c8477;margin-top:7px}.shots{display:grid;grid-template-columns:minmax(0,1fr) 250px;gap:24px;background:#f8f9f5;padding:22px 24px}.shot-label{font-size:12px;font-weight:750;margin-bottom:9px;display:flex;justify-content:space-between;gap:8px}.shot-label span{color:#889080;font-weight:400}.preview-link{display:block;overflow:hidden;border:1px solid #d6ded0;border-radius:10px;background:white;line-height:0}.shot img{display:block;width:100%;height:auto}.shot-actions{display:flex;flex-wrap:wrap;gap:15px;font-size:12px;margin-top:11px}.card-footer{padding:17px 24px;display:flex;flex-wrap:wrap;gap:16px;justify-content:space-between;align-items:center;border-top:1px solid #e8ebe3;font-size:12px}.inventory-link{font-size:13px;font-weight:650}.card-footer>span{color:#7e887b}.missing{border:1px dashed #bec8b7;border-radius:10px;display:grid;place-items:center;min-height:200px;font-size:13px;color:#7d8278}.error{padding:12px 24px;color:#954f30;font-size:13px}.empty{text-align:center;padding:70px 20px;font-size:17px;color:#6a786b}[hidden]{display:none!important}.footer{margin:28px 0 0;font-size:12px;color:#7b8476}.footer a{margin-left:12px}.hint{margin-top:16px;font-size:12px;color:#7b8378}@media(min-width:1250px){.shots{grid-template-columns:minmax(0,1fr) 290px}}@media(max-width:900px){.page{padding:25px 16px}.hero{align-items:flex-start}.hero-count{display:none}.filters{grid-template-columns:1fr 1fr;position:relative;margin-top:20px}.shots{grid-template-columns:minmax(0,1fr) 180px;gap:16px;padding:18px}.card-header{padding:20px}.card-footer{padding:16px 20px}}@media(max-width:560px){.filters{grid-template-columns:1fr 1fr;gap:10px;padding:12px}.filter:first-child{grid-column:1/-1}.filter:last-child{grid-column:1/-1}.shots{grid-template-columns:1fr}.shot.mobile{max-width:250px;margin:auto;width:100%}.card-header{padding:17px}.card-footer{display:block}.card-footer>span{display:block;margin-top:10px}.nav{gap:12px 18px}.intro{font-size:14px}h2{font-size:20px}}
.primary-journey{margin-top:26px;overflow:hidden;border:1px solid #cfd9c9;border-radius:17px;background:#fff}.journey-intro{padding:21px 23px 16px}.journey-intro h2{margin:8px 0 6px;font-size:25px}.journey-intro p{margin:0;font-size:13px;color:#6b7766}.journey-table-wrap{overflow-x:auto}.journey-table{border-collapse:collapse;width:100%;min-width:580px;text-align:left}.journey-table th,.journey-table td{padding:14px 23px;border-top:1px solid #e5eae0;vertical-align:top;font-size:13px}.journey-table thead th{background:#f4f7ef;color:#64745d;font-size:11px;letter-spacing:.06em;text-transform:uppercase}.journey-table tbody th{width:25%;font-weight:600;line-height:1.65}.journey-table tbody th>span{display:inline-flex;align-items:center;justify-content:center;width:23px;height:23px;margin-right:9px;border-radius:50%;background:#e8efdf;font-size:11px;color:#47663d}.journey-table td{width:37.5%}.journey-item+.journey-item{margin-top:12px}.journey-screen{font-weight:650;text-decoration:none}.journey-screen:hover{text-decoration:underline}.journey-links{display:flex;flex-wrap:wrap;gap:13px;margin-top:6px;font-size:11px;color:#63775e}.journey-item small{display:block;margin-top:4px;color:#8b8170;font-size:11px}.journey-note{font-size:12px;line-height:1.65;background:#f8f7f1;color:#6b7564;padding:15px 23px;margin:0;border-top:1px solid #e5eae0}.journey-note strong{color:#495e3e}@media(max-width:560px){.journey-intro{padding:18px}.journey-table th,.journey-table td{padding:12px 16px}.journey-note{padding:14px 18px}}</style></head><body><main class="page"><header class="hero"><div><div class="eyebrow">LoLo care · current UI evidence · 07 Sep 2026</div><h1>Finding care, screen by screen.</h1><p class="intro">One-time and recurring care from creation to closed. Explore every captured tab and expanded panel, compare desktop with mobile, and open the complete inventory before planning the redesign.</p></div><div class="hero-count"><b>${capturedCount}</b><span>screen states captured</span><span>${screenshotCount} PNGs · 2 viewports</span></div></header><nav class="nav" aria-label="Audit files">${htmlLink('Master tracking file', 'master-tracker.md')}${htmlLink('Capture manifest', 'captures.json')}${sourceEvidence.map(source => htmlLink(source.title, source.path)).join('')}</nav><div class="note"><strong>Capture and source audit. Redesign has not started.</strong> These are actual current-application screenshots with synthetic data, captured in Edge Chromium after the Vite build. Desktop 1440 × 1000; mobile 390 × 844 with touch emulation; DPR 1. Remote map/payment widgets were blocked in this local session; Google Fonts were allowed. Widget fallback states are preserved. Source inventories list conditional branches beyond the pictured states.</div>${primaryJourneyHtml}<form class="filters" id="filters" role="search"><div class="filter"><label for="search">Search screen or visible content</label><input id="search" type="search" placeholder="Try: payment, review, address, paused…" autocomplete="off"></div><div class="filter"><label for="variation">Care variation</label><select id="variation"><option value="">All variations</option><option value="one_time">One-time care</option><option value="recurring">Recurring care</option><option value="shared">Shared navigation</option></select></div><div class="filter"><label for="stage">Lifecycle stage</label><select id="stage"><option value="">All 7 stages</option>${stages.map(stage => `<option value="${escapeHtml(stage)}">${escapeHtml(stage)}</option>`).join('')}</select></div><div class="filter"><label for="status">Evidence</label><select id="status"><option value="">All statuses</option><option value="Captured">Captured</option><option value="Failed">Failed</option><option value="Incomplete evidence">Incomplete evidence</option></select></div></form><p id="result-count" class="results-line" role="status" aria-live="polite">Showing ${screens.length} screen states</p><section id="screens" aria-label="Screen captures">${cards}</section><p id="empty" class="empty" hidden>No screen matches these filters.</p><p class="hint">Focused views begin at the page heading when available. “First screenful” preserves the page top. “Full page” opens all content below the fold. Each information file preserves desktop and mobile text, field values, dropdown options, links and disclosure states.</p><footer class="footer">Generated ${escapeHtml(formatDate)}. Local report; no deployment.${htmlLink('Master tracker', 'master-tracker.md')}</footer></main><script>
const cards = [...document.querySelectorAll('.screen-card')];
const search = document.querySelector('#search');
const variation = document.querySelector('#variation');
const stage = document.querySelector('#stage');
const status = document.querySelector('#status');
function filterScreens() {
  const terms = search.value.toLowerCase().trim().split(/\\s+/).filter(Boolean);
  let visible = 0;
  for (const card of cards) {
    const match = (!variation.value || card.dataset.variation === variation.value)
      && (!stage.value || card.dataset.stage === stage.value)
      && (!status.value || card.dataset.status === status.value)
      && terms.every(term => card.dataset.search.includes(term));
    card.hidden = !match;
    if (match) visible++;
  }
  document.querySelector('#result-count').textContent = 'Showing ' + visible + ' of ' + cards.length + ' screen states';
  document.querySelector('#empty').hidden = visible > 0;
}
document.querySelector('#filters').addEventListener('submit', event => event.preventDefault());
for (const field of [search, variation, stage, status]) field.addEventListener('input', filterScreens);
for (const link of document.querySelectorAll('.journey-screen')) link.addEventListener('click', () => {
  search.value = ''; variation.value = ''; stage.value = ''; status.value = '';
  filterScreens();
});</script></body></html>`;
fs.writeFileSync(path.join(auditRoot, 'index.html'), html);
fs.writeFileSync(path.join(auditRoot, 'report-summary.json'), JSON.stringify({ generatedAt: formatDate, screens: screens.length, captured: capturedCount, screenshots: screenshotCount, warnings, stages: stages.map(stage => ({ stage, screens: screens.filter(screen => screen.stage === stage).length, captured: screens.filter(screen => screen.stage === stage && screen.verifiedStatus === 'Captured').length })) }, null, 2));
console.log(`Built master-tracker.md, index.html, ${screens.length} screen inventories, and report-summary.json. ${capturedCount}/${screens.length} screen states have both viewports and all evidence files; ${screenshotCount} PNGs linked.`);
for (const warning of warnings) console.warn(warning);



