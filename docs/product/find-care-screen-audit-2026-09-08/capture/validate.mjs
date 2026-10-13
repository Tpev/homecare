import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {output,root} from './environment.mjs';
const rows=JSON.parse(fs.readFileSync(path.join(output,'captures.json'),'utf8'));
const currentBuildHash=crypto.createHash('sha256').update(fs.readFileSync(path.join(root,'public/build/manifest.json'))).digest('hex');
const failures=[];const overflows=[];let pngs=0;let informationFiles=0;
const browserErrorsPath=path.join(output,'browser-errors.json');
if(fs.existsSync(browserErrorsPath))for(const error of JSON.parse(fs.readFileSync(browserErrorsPath,'utf8')))failures.push('Browser error: '+error.error+' '+error.url);
if(new Set(rows.map(x=>x.id)).size!==rows.length)failures.push('Duplicate screen ID');
for(const row of rows){
 if(row.status!=='captured'||row.captureVersion!==5)failures.push(row.id+': capture incomplete or superseded');
 if(row.buildHash!==currentBuildHash)failures.push(row.id+': frontend build changed after capture');
 for(const device of ['desktop','mobile']){
  const item=row[device];if(!item){failures.push(row.id+': missing '+device);continue;}
  for(const key of ['viewport','full','focus']){
   try{const bytes=fs.readFileSync(path.join(output,item[key]));
    if(!bytes.subarray(0,8).equals(Buffer.from([137,80,78,71,13,10,26,10])))throw new Error('Invalid PNG signature');
    if(bytes.readUInt32BE(16)!==item.metadata.width)throw new Error('Incorrect width');
    if(key!=='full'&&bytes.readUInt32BE(20)!==item.metadata.height)throw new Error('Incorrect height');
    if(bytes.length<1000)throw new Error('PNG too small');pngs++;
   }catch(e){failures.push(row.id+'/'+device+'/'+key+': '+e.message);}
  }
  try{const data=JSON.parse(fs.readFileSync(path.join(output,item.inventory),'utf8'));const text=fs.readFileSync(path.join(output,item.text),'utf8');
   if(data.text!==text)throw new Error('Rendered text and structured inventory differ');
   if(!text.trim()||!Array.isArray(data.controls)||!Array.isArray(data.links))throw new Error('Missing visible content');
   informationFiles+=2;
   if(item.metadata.horizontalOverflow>1){overflows.push({id:row.id,device,pixels:item.metadata.horizontalOverflow});failures.push(row.id+'/'+device+': horizontal overflow exceeds 1px');}
   if((row.url||'').startsWith('/family/'))for(const family of ['Inter','Source Serif 4'])if(!(data.fontFaces||[]).some(font=>font.family.replaceAll('"','')===family&&font.status==='loaded'))failures.push(row.id+'/'+device+': brand font not loaded: '+family);
  }catch(e){failures.push(row.id+'/'+device+': '+e.message);}
 }
 if(!fs.existsSync(path.join(output,'screens',row.id+'.md')))failures.push(row.id+': missing screen information page');
}
const stages=['Create','Waiting / inviting','Hired / before shift','During shift','After shift','Timesheet / review','Closed'];
const coverage=Object.fromEntries(['one_time','recurring'].map(v=>[v,Object.fromEntries(stages.map(s=>[s,rows.filter(r=>r.variation===v&&r.stage===s&&r.status==='captured').length]))]));
for(const [variation,counts] of Object.entries(coverage))for(const [stage,count] of Object.entries(counts))if(!count)failures.push('Missing stage '+variation+'/'+stage);
const html=fs.readFileSync(path.join(output,'index.html'),'utf8');
for(const match of html.matchAll(/(?:href|src)="([^"]+)"/g)){
 const target=match[1];if(/^(?:https?:|#|data:)/.test(target))continue;
 if(!fs.existsSync(path.join(output,target.split('#')[0])))failures.push('Broken report link '+target);
}
const sourceFiles=['resources/css/app.css','resources/views/livewire/family/create-care-request-wizard.blade.php','resources/views/livewire/family/manage-care-request.blade.php','resources/views/livewire/family/regular-care-show.blade.php','resources/views/livewire/support/chat-widget.blade.php','app/Livewire/Family/CreateCareRequestWizard.php','app/Livewire/Family/ManageCareRequest.php','app/Livewire/Family/RegularCareShow.php','app/Support/CareRequestProgress.php',...fs.readdirSync(path.join(root,'resources/views/livewire/family/partials')).filter(name=>name.endsWith('.blade.php')).map(name=>'resources/views/livewire/family/partials/'+name)];
sourceFiles.push('resources/css/care-workspace.css','app/Services/Family/FamilyCarePresentationService.php','app/Livewire/Family/CareJourney.php','resources/views/layouts/app.blade.php',...fs.readdirSync(path.join(root,'public/fonts')).map(name=>'public/fonts/'+name));
const provenance={capturedBuildHash:currentBuildHash,gitRevision:execFileSync('git',['-c','safe.directory='+root.replaceAll('\\','/'),'rev-parse','HEAD'],{encoding:'utf8'}).trim(),sources:Object.fromEntries(sourceFiles.filter(f=>fs.existsSync(path.join(root,f))).map(f=>[f,crypto.createHash('sha256').update(fs.readFileSync(path.join(root,f))).digest('hex')]))};
fs.writeFileSync(path.join(output,'provenance.json'),JSON.stringify(provenance,null,2));
const result={checkedAt:new Date().toISOString(),screenStates:rows.length,pngFiles:pngs,informationFiles,coreCoverage:coverage,horizontalOverflows:overflows,failures:[...new Set(failures)],passed:failures.length===0};
fs.writeFileSync(path.join(output,'validation.json'),JSON.stringify(result,null,2));
console.log(JSON.stringify(result,null,2));
if(failures.length)process.exitCode=1;
