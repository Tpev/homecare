import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {env,root,output} from './environment.mjs';
try {
 const raw=execFileSync('php',[path.join(output,'capture/seed-audit.php')],{cwd:root,env,encoding:'utf8',windowsHide:true});
 const fixture=JSON.parse(raw);
 fs.writeFileSync(path.join(output,'fixture-manifest.json'),JSON.stringify(fixture,null,2));
 console.log(raw);
} catch(e){console.error(e.stdout||e.message);process.exit(1);}
