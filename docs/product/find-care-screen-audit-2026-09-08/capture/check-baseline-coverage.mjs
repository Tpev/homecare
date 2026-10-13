import fs from 'node:fs';
import path from 'node:path';
import {root, output} from './environment.mjs';

const baseline = JSON.parse(fs.readFileSync(path.join(root, 'docs/product/find-care-screen-audit-2026-09-07/captures.json'), 'utf8'));
const current = JSON.parse(fs.readFileSync(path.join(output, 'captures.json'), 'utf8'));
const currentMap = new Map(current.map(row => [row.id, row]));
const missing = baseline.filter(row => !currentMap.has(row.id)).map(row => row.id);
const failed = baseline.filter(row => currentMap.has(row.id) && (currentMap.get(row.id).status !== 'captured' || !currentMap.get(row.id).desktop || !currentMap.get(row.id).mobile)).map(row => row.id);
const extras = current.filter(row => !baseline.some(old => old.id === row.id)).map(row => row.id);
const result = {checkedAt: new Date().toISOString(), baselineStates: baseline.length, baselineStatesCaptured: baseline.length - missing.length - failed.length, currentStates: current.length, addedStates: extras, missingBaselineIds: missing, failedBaselineIds: failed, passed: missing.length === 0 && failed.length === 0};
fs.writeFileSync(path.join(output, 'baseline-coverage.json'), JSON.stringify(result, null, 2));
console.log(JSON.stringify(result, null, 2));
if (!result.passed) process.exitCode = 1;
