import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {env, root, output, runtime, base, galleryBase} from './environment.mjs';

const prior = path.join(root, 'docs/product/find-care-screen-audit-2026-09-07');
const sourceDb = path.join(root, '.tmp/find-care-screen-audit/find-care-audit.sqlite');
const targetDb = env.DB_DATABASE;
const hash = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const sourceHash = hash(sourceDb);
fs.mkdirSync(output, {recursive: true});
if (!fs.existsSync(targetDb)) {
  if (fs.existsSync(sourceDb + '-wal')) throw new Error('Source database has a WAL file; take a SQLite backup instead of copying an active WAL database.');
  fs.copyFileSync(sourceDb, targetDb, fs.constants.COPYFILE_EXCL);
  fs.copyFileSync(path.join(prior, 'fixture-manifest.json'), path.join(output, 'fixture-manifest.json'), fs.constants.COPYFILE_EXCL);
  fs.writeFileSync(path.join(runtime, 'initialization.json'), JSON.stringify({createdAt: new Date().toISOString(), sourceDb, sourceSha256: sourceHash, targetDb, originalBaselineUnchanged: true}, null, 2));
}
console.log(execFileSync('php', ['artisan', 'migrate', '--force'], {cwd: root, env, encoding: 'utf8', windowsHide: true}));
const actor = JSON.parse(execFileSync('php', [path.join(output, 'capture/seed-admin.php')], {cwd: root, env, encoding: 'utf8', windowsHide: true}));
const manifestPath = path.join(output, 'fixture-manifest.json');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
manifest.admin = actor;
manifest.runtime = {base, galleryBase, database: targetDb, auditDate: '2026-09-08', baseline: '../find-care-screen-audit-2026-09-07/master-tracker.md'};
fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
if (hash(sourceDb) !== sourceHash) throw new Error('The baseline database changed during initialization; inspect source activity before claiming an immutable snapshot.');
console.log(JSON.stringify({base, galleryBase, database: targetDb, family: manifest.familyEmail, caregiver: manifest.caregivers.sofia.email, admin: actor.email, password: manifest.password, baselineDatabaseUnchanged: true}, null, 2));
