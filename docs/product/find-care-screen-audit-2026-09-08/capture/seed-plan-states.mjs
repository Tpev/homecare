import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {env, root, output} from './environment.mjs';
console.log(execFileSync('php', [path.join(output, 'capture/seed-plan-states.php')], {cwd: root, env, encoding: 'utf8', windowsHide: true}));
