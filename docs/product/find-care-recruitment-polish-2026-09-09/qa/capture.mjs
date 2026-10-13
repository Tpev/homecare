import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {chromium} from 'playwright';
import {base,output,manifest,viewports} from './environment.mjs';
import {specs,settle} from './specs.mjs';

const buildHash=crypto.createHash('sha256').update(fs.readFileSync('public/build/manifest.json')).digest('hex');
const selected=new Set((process.env.POLISH_CAPTURE_IDS||'').split(',').filter(Boolean));
const resume=process.env.POLISH_RESUME==='true';
const forceIds=new Set((process.env.POLISH_FORCE_IDS||'').split(',').filter(Boolean));
const pathFor=name=>path.join(output,name);
const compatibleBuilds=new Set(fs.existsSync(pathFor('asset-provenance.json'))?JSON.parse(fs.readFileSync(pathFor('asset-provenance.json'),'utf8')).acceptedPriorBuildHashes:[]);
const records=fs.existsSync(pathFor('captures.json'))?JSON.parse(fs.readFileSync(pathFor('captures.json'),'utf8')):[];
const report={at:new Date().toISOString(),buildHash,checks:[],errors:[],blockedMutations:[]};
const check=(name,pass,details={})=>{report.checks.push({name,pass,...details});if(!pass)console.log('CHECK FAILED '+name+' '+JSON.stringify(details));};
const browser=await chromium.launch({channel:'msedge',headless:true});
const contexts={};
let authenticationState;
const forbidden=new Set(['hire','confirmReviewedHire','sendCaregiverInvitation','inviteSuggestedCaregiver','shortlist','toggleFavorite','completeBooking','confirmReviewedCompletion','create','submit','publish','submitReview','postReview']);
const matrix=specs();
fs.writeFileSync(pathFor('matrix.json'),JSON.stringify(matrix.map(({prepare,...spec})=>spec),null,2));

async function inventory(page){return page.evaluate(()=>{
 const visible=el=>el.checkVisibility?el.checkVisibility({checkVisibilityCSS:true}):Boolean(el.getClientRects().length);
 const text=el=>(el.innerText||el.textContent||'').trim();
 const label=el=>el.getAttribute('aria-label')||[...(el.labels||[])].map(text).join(' / ')||el.getAttribute('placeholder')||el.getAttribute('title')||'';
 const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height};};
 return{
  text:document.body.innerText,
  headings:[...document.querySelectorAll('h1,h2,h3,h4')].filter(visible).map(el=>({level:el.tagName,text:text(el),rect:rect(el)})),
  controls:[...document.querySelectorAll('input:not([type=hidden]),textarea,select,button')].filter(visible).map(el=>({tag:el.tagName,type:el.type,label:label(el),text:el.tagName==='BUTTON'?text(el):'',value:el.value,checked:el.checked,disabled:el.disabled,required:el.required,current:el.getAttribute('aria-current'),expanded:el.getAttribute('aria-expanded'),pressed:el.getAttribute('aria-pressed'),action:el.getAttribute('wire:click'),placeholder:el.getAttribute('placeholder'),options:el.tagName==='SELECT'?[...el.options].map(option=>({label:option.text,value:option.value,selected:option.selected})):undefined})),
  links:[...document.querySelectorAll('a')].filter(visible).map(el=>({text:label(el)||text(el),href:el.getAttribute('href')})),
  details:[...document.querySelectorAll('details')].filter(visible).map(el=>({summary:text(el.querySelector('summary')),open:el.open})),
  images:[...document.images].filter(visible).map(el=>({alt:el.alt,src:el.getAttribute('src'),loaded:el.complete&&el.naturalWidth>0})),
  rows:[...document.querySelectorAll('[data-testid="applicant-row"],[data-testid="discovery-row"]')].filter(visible).map(el=>({kind:el.dataset.testid,text:text(el),rect:rect(el)})),
  width:innerWidth,height:innerHeight,documentWidth:document.documentElement.scrollWidth,documentHeight:document.documentElement.scrollHeight,
  fontFaces:[...document.fonts].map(font=>({family:font.family,status:font.status})),
  stylesheets:[...document.querySelectorAll('link[rel="stylesheet"]')].map(link=>link.href),
  selectedNavigation:[...document.querySelectorAll('main [aria-current],main [aria-pressed="true"]')].filter(visible).map(el=>({text:text(el),current:el.getAttribute('aria-current'),pressed:el.getAttribute('aria-pressed')})),
 };});}

async function inspectPrimary(page,id,device){
 const dialog=page.getByRole('dialog').filter({visible:true}).first();
 if(!await dialog.count()){
  const popovers=page.locator('details.hc-recruit-filter[open] > summary,details.hc-recruit-filters[open] > summary');
  while(await popovers.count())await popovers.first().click();
 }
 const primary=await dialog.count()?dialog.locator('.hc-primary-button:visible,.hc-action-primary:visible,button[wire\\:click="sendCaregiverInvitation"]:visible').first():page.locator('.hc-care-workspace .hc-primary-button:visible,.hc-care-workspace .hc-action-primary:visible').first();
 if(!await primary.count()||await primary.isDisabled())return;
 for(const state of ['normal','hover','focus']){
  if(state==='hover')await primary.hover();
  if(state==='focus'){await page.keyboard.press('Tab');await primary.focus();}
  const color=await primary.evaluate(el=>{
   const parse=value=>{const n=value.match(/[\d.]+/g)?.map(Number)||[0,0,0,0];return[n[0],n[1],n[2],n.length>3?n[3]:1];};
   const composite=(a,b)=>[a[0]*a[3]+b[0]*(1-a[3]),a[1]*a[3]+b[1]*(1-a[3]),a[2]*a[3]+b[2]*(1-a[3]),1];
   let chain=[];for(let node=el;node;node=node.parentElement)chain.unshift(node);
   let bg=[255,255,255,1];for(const node of chain)bg=composite(parse(getComputedStyle(node).backgroundColor),bg);
   const style=getComputedStyle(el),fg=composite(parse(style.color),bg);
   const lum=c=>c.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
   const a=lum(fg),b=lum(bg);
   return{text:el.innerText,color:style.color,background:style.backgroundColor,outline:style.outlineStyle,outlineWidth:style.outlineWidth,boxShadow:style.boxShadow,contrast:(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
  });
  check(`${id}/${device} primary ${state} contrast`,color.contrast>=4.5,color);
  if(state==='focus')check(`${id}/${device} focus visible`,color.outline!=='none'&&color.outlineWidth!=='0px'||color.boxShadow!=='none',color);
 }
}

async function expandedInventory(page){
 const activeDialog=page.getByRole('dialog').filter({visible:true}).first();
 const scope=await activeDialog.count()?activeDialog:page.locator('main');
 for(let turn=0;turn<4;turn++){
  const summaries=scope.locator('details:not([open]) > summary');let opened=0;
  for(let index=(await summaries.count())-1;index>=0;index--){const summary=summaries.nth(index);if(await summary.isVisible()){await summary.click();opened++;}}
  if(!opened)break;
 }
 return inventory(page);
}

try{
 for(const [device,viewport] of Object.entries(viewports)){
  const context=await browser.newContext({viewport,deviceScaleFactor:1,isMobile:device!=='desktop',hasTouch:device!=='desktop',reducedMotion:'reduce',locale:'en-US',...(authenticationState?{storageState:authenticationState}:{})});
  await context.route('**/*',route=>{
   const request=route.request(),url=new URL(request.url());
   if(!['127.0.0.1','localhost'].includes(url.hostname)&&!['data:','blob:'].includes(url.protocol))return route.abort();
   if(url.pathname==='/livewire/update'){
    const body=request.postDataJSON();
    const methods=(body?.components||[]).flatMap(component=>(component.calls||[]).map(call=>call.method));
    if(methods.some(method=>forbidden.has(method))){report.blockedMutations.push({url:url.href,methods});return route.abort();}
   }
   return route.continue();
  });
  const page=await context.newPage();page.setDefaultTimeout(30000);page.setDefaultNavigationTimeout(90000);
  page.on('pageerror',error=>report.errors.push({device,url:page.url(),message:error.message}));
  if(!authenticationState){
   await page.goto(base+'/login');
   await page.getByLabel('Email',{exact:true}).fill(manifest.familyEmail);await page.getByLabel('Password',{exact:true}).fill(manifest.password);
   await page.getByRole('button',{name:'Log in',exact:true}).click();await page.waitForURL(url=>!url.pathname.endsWith('/login'));
   authenticationState=await context.storageState();
  }
  contexts[device]={page,context,viewport};
 }
 for(const spec of matrix){
  if(selected.size&&!selected.has(spec.id))continue;
  const existing=records.find(record=>record.id===spec.id);
  if(resume&&!forceIds.has(spec.id)&&existing?.status==='captured'&&(existing.buildHash===buildHash||compatibleBuilds.has(existing.buildHash))&&Object.keys(viewports).every(device=>existing.devices[device]?.expandedInventory))continue;
  const {prepare,...metadata}=spec;
  const record={...metadata,buildHash,at:new Date().toISOString(),devices:{}};
  const checkStart=report.checks.length;
  for(const [device,{page,viewport}] of Object.entries(contexts)){
   try{
    const response=await page.goto(base+spec.url);await settle(page);
    check(`${spec.id}/${device} HTTP`,response.ok(),{status:response.status()});
    if(!response.ok())throw new Error(`HTTP ${response.status()}: ${(await page.locator('body').innerText()).slice(0,850)}`);
    if(prepare){await prepare(page);await settle(page);}
    await page.evaluate(()=>scrollTo(0,0));await page.waitForTimeout(100);
    const data=await inventory(page);
    check(`${spec.id}/${device} no app error`,!/Internal Server Error|Server Error|ErrorException|SQLSTATE\[/.test(data.text));
    check(`${spec.id}/${device} no horizontal overflow`,data.documentWidth<=viewport.width+1,{width:viewport.width,documentWidth:data.documentWidth});
    const fonts=data.fontFaces.filter(font=>font.status==='loaded').map(font=>font.family);
    check(`${spec.id}/${device} local brand fonts`,fonts.includes('Inter')&&fonts.includes('Source Serif 4'),{fonts});
    if(spec.expectedApplicants!==undefined)check(`${spec.id}/${device} applicant count`,data.rows.filter(row=>row.kind==='applicant-row').length===spec.expectedApplicants,{expected:spec.expectedApplicants,actual:data.rows.filter(row=>row.kind==='applicant-row').length});
    if(spec.candidate){const row=data.rows.find(row=>row.kind===spec.candidate);check(`${spec.id}/${device} first candidate visible in initial viewport`,Boolean(row&&row.rect.y<viewport.height-100),{firstRow:row?.rect,viewport});}
    if(spec.request)check(`${spec.id}/${device} request identity`,data.text.includes('Eleanor')||data.text.includes('Ellie'),{requestId:spec.request});
    const files={viewport:`screenshots/${spec.id}-${device}-viewport.png`,full:`screenshots/${spec.id}-${device}-full.png`,inventory:`evidence/${spec.id}-${device}.json`,text:`evidence/${spec.id}-${device}.txt`};
    await page.screenshot({path:pathFor(files.viewport),fullPage:false,animations:'disabled'});
    await page.screenshot({path:pathFor(files.full),fullPage:true,animations:'disabled'});
    if(spec.focusSelector){
     await page.locator(spec.focusSelector).first().evaluate(el=>scrollTo(0,Math.max(0,el.getBoundingClientRect().top+scrollY-105)));
     files.focus=`screenshots/${spec.id}-${device}-focus.png`;
     await page.screenshot({path:pathFor(files.focus),fullPage:false,animations:'disabled'});
    }
    fs.writeFileSync(pathFor(files.inventory),JSON.stringify(data,null,2));fs.writeFileSync(pathFor(files.text),data.text);
    await inspectPrimary(page,spec.id,device);
    const expanded=await expandedInventory(page);
    files.expandedInventory=`evidence/${spec.id}-${device}-expanded.json`;
    files.expandedText=`evidence/${spec.id}-${device}-expanded.txt`;
    fs.writeFileSync(pathFor(files.expandedInventory),JSON.stringify(expanded,null,2));fs.writeFileSync(pathFor(files.expandedText),expanded.text);
    if(spec.verifyLegacyEscape){
     await page.keyboard.press('Escape');await page.getByRole('dialog').waitFor({state:'hidden'});
     await page.waitForFunction(()=>document.activeElement?.matches('.hc-recruit-stage[aria-current="step"]'));
     check(`${spec.id}/${device} legacy Escape focuses current phase`,await page.evaluate(()=>document.activeElement?.matches('.hc-recruit-stage[aria-current="step"]')));
    }
    record.devices[device]={...files,dimensions:viewport,stylesheets:data.stylesheets,buildHash,finalUrl:page.url(),documentHeight:data.documentHeight,horizontalOverflow:Math.max(0,data.documentWidth-viewport.width),status:'captured'};
   }catch(error){record.devices[device]={status:'failed',error:error.stack};report.errors.push({id:spec.id,device,message:error.stack});console.log('FAILED '+spec.id+' '+device+' '+error.message);}
  }
  record.checks=report.checks.slice(checkStart);
  record.status=Object.values(record.devices).every(device=>device.status==='captured')&&record.checks.every(check=>check.pass)?'captured':'failed';
  const old=records.findIndex(row=>row.id===record.id);if(old>=0)records.splice(old,1,record);else records.push(record);
  fs.writeFileSync(pathFor('captures.json'),JSON.stringify(records,null,2));
  fs.writeFileSync(pathFor('validation-latest.json'),JSON.stringify(report,null,2));
  console.log('Captured '+spec.id+' '+record.status);
 }
}finally{await browser.close();}
report.passed=report.checks.every(check=>check.pass)&&report.errors.length===0&&report.blockedMutations.length===0;
fs.writeFileSync(pathFor('validation-latest.json'),JSON.stringify(report,null,2));
const aggregate={at:report.at,buildHash,states:records.length,expectedStates:matrix.length,checks:records.flatMap(record=>record.checks||[]),failedStates:records.filter(record=>record.status!=='captured').map(record=>record.id),staleStates:records.filter(record=>record.buildHash!==buildHash&&!compatibleBuilds.has(record.buildHash)).map(record=>record.id),compatiblePriorBuildStates:records.filter(record=>record.buildHash!==buildHash&&compatibleBuilds.has(record.buildHash)).map(record=>record.id),provenance:'asset-provenance.json',browserErrors:report.errors,blockedMutations:report.blockedMutations};
aggregate.passed=aggregate.states===aggregate.expectedStates&&aggregate.checks.every(check=>check.pass)&&!aggregate.failedStates.length&&!aggregate.staleStates.length&&!report.errors.length&&!report.blockedMutations.length;
fs.writeFileSync(pathFor('validation.json'),JSON.stringify(aggregate,null,2));
console.log(JSON.stringify({passed:report.passed,checks:report.checks.length,failed:report.checks.filter(check=>!check.pass),errors:report.errors,blockedMutations:report.blockedMutations}));
if(!report.passed)process.exitCode=1;
