import fs from 'node:fs';
import path from 'node:path';
export const root=process.cwd();
export const output=path.join(root,'docs/product/find-care-recruitment-polish-2026-09-09');
export const base='http://127.0.0.1:8028';
export const manifest=JSON.parse(fs.readFileSync(path.join(root,'docs/product/find-care-screen-audit-2026-09-08/fixture-manifest.json'),'utf8'));
export const viewports={desktop:{width:1440,height:1000},mobile:{width:390,height:844},narrow:{width:320,height:844}};
for(const directory of ['screenshots','evidence'])fs.mkdirSync(path.join(output,directory),{recursive:true});
