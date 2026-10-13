import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {root, output} from './environment.mjs';
const rows = JSON.parse(fs.readFileSync(path.join(output, 'captures.json'), 'utf8'));
const hash = crypto.createHash('sha256').update(fs.readFileSync(path.join(root, 'public/build/manifest.json'))).digest('hex');
const fresh = rows.filter(row => row.buildHash === hash);
const issues = [];
for (const row of fresh) for (const device of ['desktop', 'mobile']) {
  const item = row[device];
  if (!item) continue;
  const data = JSON.parse(fs.readFileSync(path.join(output, item.inventory), 'utf8'));
  if (item.metadata.horizontalOverflow > 1) issues.push({id: row.id, device, overflow: item.metadata.horizontalOverflow});
  if (row.url.startsWith('/family/')) for (const family of ['Inter', 'Source Serif 4']) if (!(data.fontFaces || []).some(font => font.family.replaceAll('"', '') === family && font.status === 'loaded')) issues.push({id: row.id, device, missingFont: family});
}
console.log(JSON.stringify({freshStates: fresh.length, successful: fresh.filter(row => row.status === 'captured').length, failures: fresh.filter(row => row.status === 'failed').map(row => ({id: row.id, error: row.error.split('\n')[0]})), lastFreshId: fresh.at(-1)?.id, visualIntegrityIssues: issues}, null, 2));
