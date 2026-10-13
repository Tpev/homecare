import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {root,output} from './environment.mjs';
const label=process.argv[2]||'after';
const target=path.join(output,'historical-artifacts-before.json');
const files={};
const walk=directory=>{for(const entry of fs.readdirSync(directory,{withFileTypes:true})){const full=path.join(directory,entry.name);if(entry.isDirectory())walk(full);else if(entry.isFile())files[path.relative(root,full).replaceAll('\\','/')]=crypto.createHash('sha256').update(fs.readFileSync(full)).digest('hex');}};
for(const directory of ['find-care-screen-audit-2026-09-07','find-care-screen-audit-2026-09-08'])walk(path.join(root,'docs/product',directory));
if(label==='before'){
 if(fs.existsSync(target))throw new Error('Historical artifact baseline already exists; refusing to overwrite it');
 fs.writeFileSync(target,JSON.stringify({at:new Date().toISOString(),files},null,2));console.log('Saved hashes for '+Object.keys(files).length+' historical files.');
}else{
 const before=JSON.parse(fs.readFileSync(target,'utf8')).files;
 const changed=Object.keys(before).filter(file=>files[file]!==before[file]);
 const added=Object.keys(files).filter(file=>!Object.hasOwn(before,file));
 const report={at:new Date().toISOString(),checkedFiles:Object.keys(before).length,changed,added,passed:!changed.length&&!added.length};
 fs.writeFileSync(path.join(output,'historical-artifacts-preservation.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));if(!report.passed)process.exitCode=1;
}
