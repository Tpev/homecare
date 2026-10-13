import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {env,root,output,runtime} from './environment.mjs';
if (!fs.existsSync(env.DB_DATABASE)) fs.writeFileSync(env.DB_DATABASE,'');
for (const args of [['artisan','migrate','--force']]) {
 console.log(execFileSync('php',args,{cwd:root,env,encoding:'utf8',windowsHide:true}));
}
console.log('Isolated audit database ready: '+env.DB_DATABASE);
