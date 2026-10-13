import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {root, output} from './environment.mjs';
const rows = JSON.parse(fs.readFileSync(path.join(output, 'captures.json'), 'utf8'));
const groups = [
  {name: 'Applicant replies before invitation area', files: ['resources/views/livewire/family/manage-care-request.blade.php', 'resources/views/livewire/family/partials/caregiver-invitations.blade.php'], matches: row => /^\/family\/requests\/\d+(?:\?|$)/.test(row.url) && row.tab === 'applicants'},
  {name: 'Current visit before follow-up; operational decisions before optional reviews', files: ['app/Livewire/Family/RegularCareShow.php', 'resources/views/livewire/family/partials/regular-care-overview.blade.php', 'resources/views/livewire/family/partials/regular-care-next-visit.blade.php'], matches: row => /^\/family\/care\/\d+(?:\?|$)/.test(row.url) && (row.tab === 'overview' || !new URL(row.url, 'http://localhost').searchParams.has('tab'))},
];
const results = groups.map(group => {
  const sources = group.files.map(file => ({file, modifiedAt: fs.statSync(path.join(root, file)).mtime.toISOString(), sha256: crypto.createHash('sha256').update(fs.readFileSync(path.join(root, file))).digest('hex')}));
  const newestSource = Math.max(...sources.map(source => Date.parse(source.modifiedAt)));
  const captures = rows.filter(group.matches).map(row => ({id: row.id, capturedAt: row.capturedAt, status: row.status, afterFinalSourceChange: Date.parse(row.capturedAt) >= newestSource, desktop: row.desktop?.viewport, mobile: row.mobile?.viewport}));
  return {name: group.name, sourceChangedAt: new Date(newestSource).toISOString(), sources, captures, passed: captures.length > 0 && captures.every(capture => capture.status === 'captured' && capture.afterFinalSourceChange)};
});
const result = {checkedAt: new Date().toISOString(), groups: results, passed: results.every(group => group.passed)};
fs.writeFileSync(path.join(output, 'source-refresh-validation.json'), JSON.stringify(result, null, 2));
console.log(JSON.stringify(result, null, 2));
if (!result.passed) process.exitCode = 1;
