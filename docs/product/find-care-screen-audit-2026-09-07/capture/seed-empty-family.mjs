import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {env, root, output} from './environment.mjs';
const manifestPath=path.join(output,'fixture-manifest.json');
const manifest=JSON.parse(fs.readFileSync(manifestPath,'utf8'));
if(manifest.emptyProfileFamily)throw new Error('Empty-profile family already recorded; refusing duplicate fixtures.');
const raw=execFileSync('php',[path.join(output,'capture/seed-empty-family.php')],{cwd:root,env,encoding:'utf8',windowsHide:true});
let emptyProfileFamily;
try{emptyProfileFamily=JSON.parse(raw);}catch(error){console.error(raw);throw error;}
const fresh=JSON.parse(fs.readFileSync(manifestPath,'utf8'));
fresh.emptyProfileFamily=emptyProfileFamily;
fs.writeFileSync(manifestPath,JSON.stringify(fresh,null,2));
console.log(JSON.stringify({emptyProfileFamily},null,2));
