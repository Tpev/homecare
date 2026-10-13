import {execFileSync} from 'node:child_process';
import {env,root} from '../../find-care-screen-audit-2026-09-08/capture/environment.mjs';
const output=execFileSync('php',['artisan','migrate:status','--no-ansi'],{cwd:root,env,encoding:'utf8',windowsHide:true});
console.log(output.split('\n').filter(line=>line.includes('Pending')).join('\n')||'No pending migrations');
