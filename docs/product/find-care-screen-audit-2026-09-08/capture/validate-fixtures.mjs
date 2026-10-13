import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {env, root, output} from './environment.mjs';
const json = execFileSync('php', [path.join(output, 'capture/validate-fixtures.php')], {cwd: root, env, encoding: 'utf8', windowsHide: true});
const data = JSON.parse(json);
fs.writeFileSync(path.join(output, 'fixture-semantics.json'), JSON.stringify(data, null, 2));
console.log(JSON.stringify({requests: Object.keys(data.requests).length, plans: Object.keys(data.plans).length, file: 'fixture-semantics.json'}));
