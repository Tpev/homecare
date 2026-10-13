import fs from 'node:fs';
import path from 'node:path';
import {chromium} from 'playwright';
import {output,viewports} from './environment.mjs';
const gallery='http://127.0.0.1:8032/find-care-recruitment-polish-2026-09-09/';
const read=file=>JSON.parse(fs.readFileSync(path.join(output,file),'utf8'));
const records=read('captures.json'),matrix=read('matrix.json'),validation=read('validation.json');
const report={at:new Date().toISOString(),checks:[],errors:[],states:records.length,screenshots:0};
const check=(name,passed,details={})=>report.checks.push({name,passed,...details});
check('All matrix IDs captured exactly once',new Set(records.map(row=>row.id)).size===matrix.length&&matrix.every(spec=>records.some(record=>record.id===spec.id)));
check('Capture assertions pass',validation.passed,{checks:validation.checks.length});
for(const record of records){
 check(record.id+' captured',record.status==='captured');
 for(const [device,expected] of Object.entries(viewports)){
  const capture=record.devices[device];check(record.id+'/'+device+' exists',Boolean(capture));if(!capture)continue;
  for(const key of ['viewport','full','focus','inventory','text','expandedInventory','expandedText']){
   if(key==='focus'&&!capture.focus)continue;
   const full=capture[key]&&path.join(output,capture[key]);const exists=full&&fs.existsSync(full)&&fs.statSync(full).size>0;
   check(record.id+'/'+device+'/'+key+' file',Boolean(exists));
   if(exists&&full.endsWith('.png')){
    report.screenshots++;const bytes=fs.readFileSync(full);
    check(record.id+'/'+device+'/'+key+' PNG',bytes.subarray(1,4).toString()==='PNG'&&bytes.readUInt32BE(16)===expected.width,{width:bytes.readUInt32BE(16),height:bytes.readUInt32BE(20)});
   }
  }
 }
}
for(const file of ['navigation-validation.json','data-preservation.json','historical-artifacts-preservation.json','evidence/phase-focus-validation.json']){const data=read(file);check(file+' passes',data.passed===true);}
const html=fs.readFileSync(path.join(output,'index.html'),'utf8');
const urls=[...new Set([...html.matchAll(/(?:href|src)="([^"]+)"/g)].map(match=>new URL(match[1].replaceAll('&amp;','&'),gallery).href))];
let next=0;await Promise.all(Array.from({length:6},async()=>{while(next<urls.length){const url=urls[next++];try{const response=await fetch(url,{method:'HEAD',signal:AbortSignal.timeout(20000)});check('Gallery link '+url,response.ok,{status:response.status});}catch(error){report.errors.push({url,message:error.message});}}}));
const browser=await chromium.launch({channel:'msedge',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
try{
 await page.goto(gallery);await page.locator('article').first().waitFor();
 check('Gallery shows all states',await page.locator('article:visible').count()===matrix.length);
 await page.locator('#search').fill('hire-review');await page.locator('#variation').selectOption('one_time');await page.locator('#device').selectOption('mobile');
 check('Gallery filters to requested state',await page.locator('article:visible').count()===1);
 check('Gallery mobile comparison filter',await page.locator('article:visible figure:visible').count()===1&&await page.locator('article:visible figure:visible').getAttribute('data-device')==='mobile');
 await page.setViewportSize({width:390,height:844});
 check('Gallery no mobile overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
}catch(error){report.errors.push({message:error.stack});}finally{await browser.close();}
report.passed=report.checks.every(check=>check.passed)&&!report.errors.length;
fs.writeFileSync(path.join(output,'delivery-validation.json'),JSON.stringify(report,null,2));console.log(JSON.stringify({passed:report.passed,states:report.states,screenshots:report.screenshots,checks:report.checks.length,failed:report.checks.filter(check=>!check.passed),errors:report.errors}));if(!report.passed)process.exitCode=1;
