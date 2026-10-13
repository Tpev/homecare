import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {env, root, output} from './environment.mjs';
const manifestPath = path.join(output, 'fixture-manifest.json');
const raw = execFileSync('php', [path.join(output, 'capture/seed-extra.php')], {cwd: root, env, encoding: 'utf8', windowsHide: true});
let extras;
try { extras = JSON.parse(raw); } catch (error) { console.error(raw); throw error; }
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
for (const variation of ['one_time', 'recurring']) {
  Object.assign(manifest.requests[variation], extras.requests[variation]);
  Object.assign(manifest.bookings[variation], extras.bookings[variation]);
}
Object.assign(manifest.caregivers, extras.caregivers);
manifest.extraPlans = extras.extraPlans;
manifest.timeCorrections = extras.timeCorrections;
fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
console.log(JSON.stringify(extras, null, 2));
