import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const output = 'docs/product/care-release-review-2026-09-11';
const git = args => execFileSync('git', ['-c', `safe.directory=${root.replaceAll('\\', '/')}`, ...args], { cwd: root, windowsHide: true, encoding: 'utf8' }).trim();
const paths = fs.readFileSync(`${output}/runtime-files.txt`, 'utf8').trim().split(/\r?\n/);
const manifest = {
    createdAt: new Date().toISOString(),
    baseCommit: git(['rev-parse', 'HEAD']),
    description: 'Reviewed care UI/runtime files and deployment safeguards. Blob hashes use Git filters so Windows/Linux line endings do not create false mismatches.',
    files: paths.map(file => ({ path: file, blob: git(['hash-object', `--path=${file}`, file]) })),
};
fs.writeFileSync(`${output}/release-manifest.json`, JSON.stringify(manifest, null, 2));
console.log(`Recorded ${manifest.files.length} reviewed files at base ${manifest.baseCommit}.`);
