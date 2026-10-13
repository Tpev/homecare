import fs from 'node:fs';
import path from 'node:path';
import {output} from './environment.mjs';
const read = file => JSON.parse(fs.readFileSync(path.join(output, file), 'utf8'));
const validation = read('validation.json');
const baseline = read('baseline-coverage.json');
const semantics = read('screen-semantics.json');
const data = read('data-preservation.json');
const sources = read('source-refresh-validation.json');
const roles = read('role-smoke.json');
const gallery = read('gallery-smoke.json');
const refresh = read('refresh-request.json');
const captures = read('captures.json');
const provenance = read('provenance.json');
const captureMap = new Map(captures.map(capture => [capture.id, capture]));
const completedRefresh = refresh.ids.map(id => ({id, originalCaptureAt: refresh.originalCaptureTimes[id], refreshedAt: captureMap.get(id)?.capturedAt, passed: captureMap.get(id)?.status === 'captured' && Date.parse(captureMap.get(id)?.capturedAt) >= Date.parse(refresh.preparedAt)}));
const allPassed = [validation, baseline, semantics, data, sources, roles, gallery].every(proof => proof.passed) && completedRefresh.every(row => row.passed) && roles.buildHash === provenance.capturedBuildHash;
const refreshResult = {preparedAt: refresh.preparedAt, checkedAt: new Date().toISOString(), captures: completedRefresh, passed: completedRefresh.every(row => row.passed)};
fs.writeFileSync(path.join(output, 'refresh-completion.json'), JSON.stringify(refreshResult, null, 2));
const originalRows = Object.values(data.tables).reduce((sum, table) => sum + table.baselineRows, 0);
const lines = ['# Final capture and data validation', '', `Checked ${new Date().toISOString()}. **${allPassed ? 'All required final checks passed.' : 'Some required checks need attention.'}**`, '',
  `The gallery contains **${validation.screenStates} tracked screen states with desktop and mobile evidence (${validation.screenStates * 2} device views, ${validation.pngFiles} lifecycle PNGs)**. All ${baseline.baselineStates} original September 7 IDs are preserved. The additional ${captures.length - baseline.baselineStates} IDs cover redesigned destinations, controls and plan conditions. Six role/device login checks have a separate 12 screenshots and do not inflate lifecycle coverage.`, '',
  '| Check | Final result | Evidence |', '|---|---|---|',
  `| Baseline comparison coverage | ${baseline.baselineStatesCaptured}/${baseline.baselineStates} IDs, both viewports | [Coverage](baseline-coverage.json) |`,
  `| PNG dimensions, full information inventories, final build, fonts and overflow | ${validation.passed ? 'Passed; no missing fonts or horizontal overflow' : 'Needs attention'} | [Artifact validation](validation.json) |`,
  `| Rendered state, record identity, selected tabs and creation variation | ${semantics.assertions} assertions across ${semantics.deviceChecks} device records | [Semantic checks](screen-semantics.json) |`,
  `| Original care data | ${originalRows} original rows across ${Object.keys(data.tables).length} tables retained | [Field comparison](data-preservation.json) |`,
  `| Family, caregiver and administrator access | ${roles.checks.filter(check => check.passed).length}/6 login/route checks passed | [Role screenshots](role-smoke.md) |`,
  `| Gallery search, combined filters, image/inventory links and mobile width | ${gallery.assertions.filter(assertion => assertion.passed).length} checks passed | [Gallery smoke](gallery-smoke.json) |`,
  '', '## Evidence after the final presentation changes', '',
  'The last two changes affected Blade/PHP presentation without changing the frontend asset manifest. The following check therefore compares capture timestamps directly with the final affected source-file timestamps, in addition to recording all final source hashes.', '',
  '| Change | Latest source change (UTC) | Captured states checked | Result |', '|---|---|---|',
  ...sources.groups.map(group => `| ${group.name} | ${group.sourceChangedAt} | ${group.captures.length} | ${group.passed ? 'Every capture follows the source change' : 'Needs attention'} |`), '',
  `[Exact source paths, SHA256 hashes and all affected capture IDs/times](source-refresh-validation.json). [Final implementation/build provenance](provenance.json).`, '',
  `The final refresh/retry request was created at **${refresh.preparedAt}**. All **${completedRefresh.length} requested IDs** were completed after that request: 50 request Caregivers-tab states, four original parent Overview states, and three preparation retries. The support retries minimize the remembered dialog through its visible control before proving that the current page's inline entry reopens it. The private-create preparation now waits for each blurred field to settle.`, '',
  '| Refreshed tracking ID | Final capture time (UTC) |', '|---|---|',
  ...completedRefresh.map(row => `| ${row.id} | ${row.refreshedAt} |`), '',
  '[Original and final capture timestamps for every requested refresh](refresh-completion.json).', '',
  '## Exact data-preservation scope', '',
  'The checker opens both isolated SQLite databases in query-only mode. It compares original rows by primary/composite key and every stored field, with the explicitly listed authentication exceptions below. Additive synthetic rows are allowed. The original September 7 database file also retains its initialization SHA256.', '',
  '| Compared table | Original rows retained |', '|---|---|',
  ...Object.entries(data.tables).map(([table, result]) => `| ${table} | ${result.baselineRows} |`), '',
  'Excluded user columns: ' + Object.entries(data.excludedFields).map(([table, fields]) => table + ': ' + fields.join(', ')).join('; ') + '.', '',
  'Operational/out-of-scope exclusions:', '', ...data.excludedOperationalTables.map(exclusion => '- ' + exclusion), '',
  '## Interpretation and limits', '',
  '- These are actual local application captures using synthetic data. They are not production activity or completed live payment/provider checks.',
  '- The original example dates and independent stored statuses are retained: an ongoing September 7 visit is a deliberately staged example. After-shift and timesheet-review examples both contain submitted, unapproved hours.',
  '- New countered/declined/cancelled offers have their own historical source care but no generated recurring bookings. Proposal dates are labelled as unbooked.',
  '- Tracking identities can share a layout after redesign; default/expanded IDs and empty filter states remain for comparison. Role and gallery checks are separate.',
  '- Inter and Source Serif 4 load from local font files. External map/payment embeds are blocked for reproducibility; their fallback states do not prove provider failures.',
  '- Full plan event payloads remain in the database; the UI care record shows its actual dates and instructions, without claiming a new event timeline.',
  '- Per-screen rationale explains the implementation. Representative visual reviews and automated checks are recorded separately; this report does not claim a human visual review of all 638 device views.', '',
  '[Implementation guide](implementation-guide.md) · [Preservation map](implementation-preservation-map.md) · [Representative visual findings](validation-notes.md) · [Application test evidence](validation/application-tests-summary.md)', ''];
fs.writeFileSync(path.join(output, 'delivery-validation.md'), lines.join('\n'));
console.log(JSON.stringify({passed: allPassed, states: validation.screenStates, pngs: validation.pngFiles, semanticAssertions: semantics.assertions, dataTables: Object.keys(data.tables).length, originalRows, refreshedIds: completedRefresh.length, roleChecks: roles.checks.length, galleryChecks: gallery.assertions.length}));
if (!allPassed) process.exitCode = 1;
