import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {chromium} from 'playwright';
import {output,base} from './environment.mjs';
const manifest=JSON.parse(fs.readFileSync(path.join(output,'fixture-manifest.json'),'utf8'));
const buildHash=crypto.createHash('sha256').update(fs.readFileSync(path.resolve('public/build/manifest.json'))).digest('hex');

for (const dir of ['screenshots','evidence','screens']) fs.mkdirSync(path.join(output,dir),{recursive:true});
const capturesPath=path.join(output,'captures.json');
const results=fs.existsSync(capturesPath)?JSON.parse(fs.readFileSync(capturesPath,'utf8')):[];
const reconcile=process.env.AUDIT_CAPTURE_MODE==='reconcile';
const recapturePath=path.join(output,'recapture-ids.json');
const recaptureIds=new Set(fs.existsSync(recapturePath)?JSON.parse(fs.readFileSync(recapturePath,'utf8')):[]);
const requestedIds=new Set((process.env.AUDIT_CAPTURE_IDS||'').split(',').map(id=>id.trim()).filter(Boolean));
const forceCapture=process.env.AUDIT_FORCE_CAPTURE==='true';
const browser=await chromium.launch({channel:'msedge',headless:true});
const errors=[];
const contexts={};
for(const [device,viewport] of Object.entries({desktop:{width:1440,height:1000},mobile:{width:390,height:844}})){
 const context=await browser.newContext({viewport,deviceScaleFactor:1,isMobile:device==='mobile',hasTouch:device==='mobile',locale:'en-US',reducedMotion:'reduce'});
 // Keep the capture session local: remote map/payment widgets have their native fallback.
 await context.route('**/*',route=>{
  const u=new URL(route.request().url());
  return ['127.0.0.1','localhost','fonts.googleapis.com','fonts.gstatic.com'].includes(u.hostname)||['data:','blob:'].includes(u.protocol)?route.continue():route.abort();
 });
 const page=await context.newPage();
 page.setDefaultTimeout(20000);
 page.setDefaultNavigationTimeout(90000);
 page.on('pageerror',e=>errors.push({device,error:e.message,url:page.url()}));
 await page.goto(base+'/login');
 await page.getByLabel('Email',{exact:true}).fill(manifest.familyEmail);
 await page.getByLabel('Password',{exact:true}).fill(manifest.password);
 await page.getByRole('button',{name:'Log in',exact:true}).click();
 await page.waitForURL(u=>!u.pathname.endsWith('/login'));
 contexts[device]={context,page,viewport};
}

async function settle(page){
 await page.waitForTimeout(400);
 await page.waitForLoadState('networkidle');
 await page.evaluate(()=>document.fonts.ready);
}
async function openDetails(page){
 // Native disclosures are opened through their controls; no app state is fabricated.
 for(let i=0;i<3;i++){
  const summaries=page.locator('main details:not([open]) > summary');
  const n=await summaries.count();
  let opened=0;
  for(let j=n-1;j>=0;j--){const s=summaries.nth(j);if(await s.isVisible()){await s.focus();await s.press('Enter');opened++;}}
  if(!opened)break;
 }
}
async function inventory(page){return page.evaluate(()=>{
 const visible=el=>{
  if(!el.getClientRects().length||getComputedStyle(el).visibility==='hidden'||getComputedStyle(el).display==='none')return false;
  for(let ancestor=el.parentElement;ancestor;ancestor=ancestor.parentElement){
   if(ancestor.tagName==='DETAILS'&&!ancestor.open&&!ancestor.querySelector(':scope > summary')?.contains(el))return false;
  }
  return typeof el.checkVisibility!=='function'||el.checkVisibility({checkVisibilityCSS:true});
 };
 const txt=el=>(el.innerText||el.textContent||'').trim();
 const label=el=>el.getAttribute('aria-label')||(el.labels?[...el.labels].map(txt).join(' / '):'')||el.getAttribute('placeholder')||el.getAttribute('title')||'';
 return {text:document.body.innerText,headings:[...document.querySelectorAll('h1,h2,h3,h4')].filter(visible).map(el=>({level:el.tagName,text:txt(el)})),
 controls:[...document.querySelectorAll('input:not([type=hidden]),textarea,select,button')].filter(visible).map(el=>({tag:el.tagName,type:el.type,label:label(el),text:el.tagName==='BUTTON'?txt(el):'',value:el.value,checked:el.checked,disabled:el.disabled,required:el.required,current:el.getAttribute('aria-current'),expanded:el.getAttribute('aria-expanded'),pressed:el.getAttribute('aria-pressed'),placeholder:el.getAttribute('placeholder'),options:el.tagName==='SELECT'?[...el.options].map(o=>({label:o.text,value:o.value,selected:o.selected})):undefined})),
 links:[...document.querySelectorAll('a')].filter(visible).map(el=>({text:label(el)||txt(el),href:el.getAttribute('href')})),
 details:[...document.querySelectorAll('details')].filter(visible).map(el=>({summary:txt(el.querySelector('summary')),open:el.open})),
 images:[...document.images].filter(visible).map(el=>({alt:el.alt,src:el.getAttribute('src'),loaded:el.complete&&el.naturalWidth>0})),
 width:innerWidth,height:innerHeight,documentWidth:document.documentElement.scrollWidth,documentHeight:document.documentElement.scrollHeight,
 fontFaces:[...document.fonts].map(font=>({family:font.family,status:font.status,weight:font.weight,style:font.style})),bodyFont:getComputedStyle(document.body).fontFamily,
 workspace:{heading:document.querySelector('.hc-care-workspace h1')?.innerText||'',status:document.querySelector('.hc-care-heading .hc-care-status')?.innerText||'',headerText:document.querySelector('.hc-care-workspace header')?.innerText||'',primaryTitle:document.querySelector('#care-next-title')?.innerText||'',selectedTabs:[...document.querySelectorAll('.hc-care-tabs [aria-current="page"]')].map(el=>el.innerText)},
 };});}

async function capture(spec){
 if(requestedIds.size&&!requestedIds.has(spec.id))return;
 if(!forceCapture&&results.some(r=>r.id===spec.id && r.status==='captured'&&r.captureVersion===5&&r.buildHash===buildHash))return;
 const existing=results.find(r=>r.id===spec.id);
 const record={...spec,captureVersion:5,capturedAt:new Date().toISOString(),buildHash,prepared:Boolean(spec.prepare)};delete record.prepare;
 try{
  const pair=await Promise.allSettled(Object.entries(contexts).map(async ([device,{page,viewport}])=>{
   const response=await page.goto(base+spec.url);await settle(page);
   if(!response?.ok())throw new Error('HTTP '+response?.status());
   if(spec.prepare){await spec.prepare(page);await settle(page);}
   if(spec.disclosure==='expanded'){await openDetails(page);await settle(page);}
   const data=await inventory(page);
   if(/Internal Server Error|Server Error|ErrorException|SQLSTATE\[/.test(data.text))throw new Error('Application error in page');
   if(!page.url().startsWith(base+'/family/')&&!page.url().includes('/messages/')&&!page.url().includes('/caregivers/'))throw new Error('Unexpected redirect '+page.url());
   const prefix=spec.id+'-'+device;
   const p={viewport:'screenshots/'+prefix+'-viewport.png',full:'screenshots/'+prefix+'-full.png',focus:'screenshots/'+prefix+'-focus.png',text:'evidence/'+prefix+'.txt',inventory:'evidence/'+prefix+'.json'};
   const oldText=fs.existsSync(path.join(output,p.text))?fs.readFileSync(path.join(output,p.text),'utf8'):null;
   const needsShots=!reconcile||!existing||existing.status!=='captured'||spec.prepare||recaptureIds.has(spec.id)||oldText!==data.text||!fs.existsSync(path.join(output,p.focus));
   if(needsShots){
    await page.evaluate(()=>scrollTo(0,0));
    await page.screenshot({path:path.join(output,p.viewport),fullPage:false,animations:'disabled'});
    await page.screenshot({path:path.join(output,p.full),fullPage:true,animations:'disabled'});
    if(!(await page.getByRole('dialog').isVisible().catch(()=>false))){
     const anchor=spec.focusSelector?page.locator(spec.focusSelector).first():page.locator('h1').first();
     if(await anchor.isVisible()){await anchor.evaluate(el=>window.scrollTo(0,Math.max(0,el.getBoundingClientRect().top+window.scrollY-105)));}
    }
    await page.screenshot({path:path.join(output,p.focus),fullPage:false,animations:'disabled'});
   }
   fs.writeFileSync(path.join(output,p.text),data.text);
   fs.writeFileSync(path.join(output,p.inventory),JSON.stringify(data,null,2));
   record[device]={...p,metadata:{...viewport,documentHeight:data.documentHeight,horizontalOverflow:Math.max(0,data.documentWidth-viewport.width),finalUrl:page.url()}};
  }));
  for(const deviceResult of pair)if(deviceResult.status==='rejected')throw deviceResult.reason;
  record.status='captured';
  console.log('Captured '+record.id+' '+record.title);
 }catch(e){record.status='failed';record.error=e.message;console.log('FAILED '+record.id+': '+e.message);}
 const old=results.findIndex(r=>r.id===record.id);if(old>=0)results.splice(old,1,record);else results.push(record);
 fs.writeFileSync(capturesPath,JSON.stringify(results,null,2));
 fs.writeFileSync(path.join(output,'browser-errors.json'),JSON.stringify(errors,null,2));
}
const only=process.env.AUDIT_CAPTURE_GROUP||'all';
if(['all','core'].includes(only)){
 const states={waiting:['Waiting / inviting',['applicants','overview']],invitations:['Waiting / inviting',['applicants','overview']],applicants:['Waiting / inviting',['applicants','overview']],scheduled:['Hired / before shift',['shift','applicants','support','overview']],in_progress:['During shift',['shift','support','applicants','overview']],after_shift:['After shift',['shift','support','applicants','overview']],timesheet_review:['Timesheet / review',['shift','support','applicants','overview']],leave_review:['Timesheet / review',['shift','applicants','support','overview']],closed:['Closed',['shift','applicants','support','overview']],cancelled:['Closed',['shift','applicants','support','overview']]};
 states.cancelled=['Closed',['overview','applicants']];
 states.cancelled_booking=['Closed',['shift','applicants','support','overview']];
 for(const [variation,requests] of Object.entries(manifest.requests)){
  for(const [state,[stage,tabs]] of Object.entries(states)){
   if(!requests[state])continue;
   for(const tab of tabs)await capture({id:`${variation==='one_time'?'OT':'RC'}-${state}-${tab}`,variation,stage,title:state.replaceAll('_',' ')+' — '+({shift:'Visit',applicants:'Caregivers',support:'Help',overview:'Care details'}[tab]),tab,disclosure:'default',url:`/family/requests/${requests[state]}?tab=${tab}`});
  }
 }
}
if(['all','expanded'].includes(only)){
 for(const [variation,requests] of Object.entries(manifest.requests)){
  for(const [state,tab,stage] of [['invitations','applicants','Waiting / inviting'],['applicants','applicants','Waiting / inviting'],['scheduled','shift','Hired / before shift'],['scheduled','support','Hired / before shift'],['in_progress','shift','During shift'],['timesheet_review','shift','Timesheet / review'],['timesheet_review','support','Timesheet / review'],['closed','shift','Closed'],['waiting','overview','Waiting / inviting']]){
   await capture({id:`${variation==='one_time'?'OT':'RC'}-${state}-${tab}-expanded`,variation,stage,title:state.replaceAll('_',' ')+' — '+tab+' / all details open',tab,disclosure:'expanded',url:`/family/requests/${requests[state]}?tab=${tab}`});
  }
  await capture({id:`${variation==='one_time'?'OT':'RC'}-invite-drawer`,variation,stage:'Waiting / inviting',title:'Invite caregivers drawer',tab:'applicants',disclosure:'invite drawer',url:`/family/requests/${requests.waiting}?tab=applicants`,prepare:async page=>{await page.locator('[wire\\:click="openCaregiverInvitePanel"]').first().click();await page.getByRole('dialog').waitFor({state:'visible'});}});
 }
}
if(['all','plans'].includes(only)){
 for(const [state,id] of Object.entries(manifest.plans)){
  if(typeof id!=='number')continue;
  await capture({id:'RC-plan-'+state,variation:'recurring',stage:state==='ended'?'Closed':state==='pending'?'Waiting / inviting':'Hired / before shift',title:'Recurring plan — '+state,tab:'overview',disclosure:'default',url:'/family/care/'+id});
 }
 for(const [panel,button] of [['extra','Add an extra visit'],['schedule','Change future schedule'],['pause','Pause recurring care'],['end','End recurring care']]){
  const heading=({extra:'Ask for one extra visit',schedule:'Change future schedule',pause:'Pause recurring care',end:'End recurring care?'}[panel]);
  await capture({id:'RC-plan-panel-'+panel,variation:'recurring',stage:'Hired / before shift',title:'Recurring plan — '+button,tab:'visits',disclosure:panel,focusSelector:'h3:has-text("'+heading+'")',url:'/family/care/'+manifest.plans.active+'?tab=visits',prepare:async page=>{await page.getByRole('button',{name:button,exact:true}).first().click();await settle(page);await page.getByRole('heading',{name:heading,exact:true}).waitFor({state:'visible'});}});
 }
 await capture({id:'RC-plan-details-expanded',variation:'recurring',stage:'Hired / before shift',title:'Recurring plan — care details and instructions',tab:'details',disclosure:'expanded',url:'/family/care/'+manifest.plans.active+'?tab=details'});
 for(const [id,title,url,stage] of [['overview','Care overview','/family/requests','Waiting / inviting'],['arrangements','Arrangements','/family/care','Hired / before shift'],['schedule','Schedule','/family/care/schedule','Hired / before shift'],['history','History','/family/care/history','Closed']]){
  await capture({id:'SH-'+id,variation:'shared',stage,title,tab:title,disclosure:'default',url});
 }
}
if(['all','create'].includes(only)){
 for(const variation of ['one_time','recurring']){
  const prefix=variation==='one_time'?'OT':'RC';
  const choose=async page=>{if(variation==='recurring'){await page.locator('input[value="recurring"]').check({force:true});await page.getByText('Which days each week?',{exact:true}).waitFor();await settle(page);}};
  await capture({id:prefix+'-create-empty',variation,stage:'Create',title:'Create care request — initial form',tab:'Single form',disclosure:'default',url:'/family/requests/create',prepare:choose});
  await capture({id:prefix+'-create-details',variation,stage:'Create',title:'Create care request — details, contact and schedule options',tab:'Single form',disclosure:'expanded',url:'/family/requests/create',prepare:async page=>{
   await choose(page);
   await page.locator('input[value="other"]').check({force:true});await settle(page);
   await openDetails(page);
   const contact=page.locator('input[wire\\:model\\.live="includeThirdPartyContact"]');if(await contact.isVisible()){await contact.check();await page.getByLabel('Contact name',{exact:true}).waitFor();await settle(page);}
   if(variation==='recurring'){await page.locator('input[wire\\:model\\.live="recurring_days"]').first().check({force:true});await page.locator('#recurring-start-0').waitFor();await settle(page);await page.locator('input[value="date"]').check();await page.getByLabel('Last day',{exact:true}).waitFor();}
  }});
 }
}
if(['all','additional'].includes(only)){
 const {additionalSpecs}=await import('./additional-specs.mjs');
 for(const spec of await additionalSpecs(manifest))await capture(spec);
}
if(['all','new'].includes(only)){
 const {newStateSpecs}=await import('./new-state-specs.mjs');
 for(const spec of await newStateSpecs(manifest))await capture(spec);
}
if(['all','profiles'].includes(only)&&fs.existsSync(path.join(output,'capture/quick-profile-specs.mjs'))){
 const {quickProfileSpecs}=await import('./quick-profile-specs.mjs');
 const latestManifest=JSON.parse(fs.readFileSync(path.join(output,'fixture-manifest.json'),'utf8'));
 if(latestManifest.emptyProfileFamily)for(const spec of await quickProfileSpecs(latestManifest))await capture(spec);
}
await browser.close();
console.log(JSON.stringify({total:results.length,captured:results.filter(r=>r.status==='captured').length,failed:results.filter(r=>r.status==='failed').map(r=>({id:r.id,error:r.error}))}));
