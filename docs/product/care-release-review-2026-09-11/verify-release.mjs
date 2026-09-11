import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

// Read only. Compare the final reviewed runtime blobs with a release commit.
// --working-tree checks the current files; otherwise provide the intended git ref.
const root = process.cwd();
const manifest = JSON.parse(fs.readFileSync('docs/product/care-release-review-2026-09-11/release-manifest.json', 'utf8'));
const target = process.argv[2];
if (!target) throw new Error('Usage: node docs/product/care-release-review-2026-09-11/verify-release.mjs <git-ref|--working-tree>');
const git = args => execFileSync('git', ['-c', `safe.directory=${root.replaceAll('\\', '/')}`, ...args], { cwd: root, windowsHide: true, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
const issues = [];
for (const entry of manifest.files) {
    try {
        const actual = target === '--working-tree'
            ? git(['hash-object', `--path=${entry.path}`, entry.path])
            : git(['rev-parse', '--verify', '--end-of-options', `${target}:${entry.path}`]);
        if (actual !== entry.blob) issues.push({ path: entry.path, issue: 'Content differs from reviewed version' });
    } catch {
        issues.push({ path: entry.path, issue: 'Required file missing' });
    }
}
console.log(JSON.stringify({ target, reviewedBase: manifest.baseCommit, checked: manifest.files.length, passed: issues.length === 0, issues }, null, 2));
process.exitCode = issues.length ? 1 : 0;
