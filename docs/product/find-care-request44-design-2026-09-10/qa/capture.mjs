import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {chromium} from 'playwright';

const root=process.cwd(),out=path.join(root,'docs/product/find-care-request44-design-2026-09-10'),base='http://127.0.0.1:8028';
for(const folder of ['screenshots','evidence'])fs.mkdirSync(path.join(out,folder),{recursive:true});
const snapshot=()=>JSON.parse(execFileSync('php',['docs/product/find-care-recruitment-polish-2026-09-09/qa/fixtures.php'],{encoding:'utf8',windowsHide:true})).tables;
const before=snapshot();fs.writeFileSync(path.join(out,'data-before.json'),JSON.stringify(before,null,2));
const report={at:new Date().toISOString(),buildHash:crypto.createHash('sha256').update(fs.readFileSync('public/build/manifest.json')).digest('hex'),checks:[],errors:[],blockedRequests:[],captures:[],states:[],interactions:[]};
const check=(name,passed,details={})=>{report.checks.push({name,passed,...details});if(!passed)console.log('FAIL '+name+' '+JSON.stringify(details));};
const save=()=>fs.writeFileSync(path.join(out,'validation.json'),JSON.stringify(report,null,2));
const browser=await chromium.launch({channel:'msedge',headless:true});
const stages=[['home','Care request'],['invite','Find caregivers'],['applicants','Review applicants'],['start','Get started']];
let auth;
async function inspect(page){return page.evaluate(()=>{
 const rect=e=>{const r=e.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom};};
 const text=e=>(e.innerText||e.textContent||'').replace(/\s+/g,' ').trim();
 const steps=[...document.querySelectorAll('.hc-recruit-stage')].map(e=>({text:text(e),current:e.getAttribute('aria-current'),disabled:e.disabled,rect:rect(e),label:rect(e.querySelector('.hc-recruit-step-label')),count:e.querySelector('.hc-recruit-count')?{text:text(e.querySelector('.hc-recruit-count')),rect:rect(e.querySelector('.hc-recruit-count'))}:null,background:getComputedStyle(e).backgroundColor,color:getComputedStyle(e).color,before:{clip:getComputedStyle(e,'::before').clipPath,content:getComputedStyle(e,'::before').content},after:{clip:getComputedStyle(e,'::after').clipPath,content:getComputedStyle(e,'::after').content}}));
 return{url:location.href,width:innerWidth,documentWidth:document.documentElement.scrollWidth,text:document.body.innerText,steps,stylesheets:[...document.querySelectorAll('link[rel=stylesheet]')].map(e=>e.href)};
});}
try{
 for(const [device,viewport] of Object.entries({desktop:{width:1440,height:1000},mobile:{width:390,height:844},narrow:{width:320,height:844}})){
  const context=await browser.newContext({viewport,deviceScaleFactor:1,reducedMotion:'reduce',...(auth?{storageState:auth}:{})});
  await context.route('**/*',route=>{
   const request=route.request(),url=new URL(request.url());
   if(!['127.0.0.1','localhost'].includes(url.hostname)&&!['data:','blob:'].includes(url.protocol))return route.abort();
   if(url.pathname==='/livewire/update'){
    const methods=(request.postDataJSON()?.components||[]).flatMap(c=>(c.calls||[]).map(call=>call.method));
    if(methods.some(method=>!['login','$refresh','$set','setActiveTab','setCaregiverView','setApplicantView','reviewHire','closeDecisionReview'].includes(method))){report.blockedRequests.push({methods,url:url.href});return route.abort();}
   }else if(!['GET','HEAD','OPTIONS'].includes(request.method())){report.blockedRequests.push({method:request.method(),url:url.href});return route.abort();}
   return route.continue();
  });
  const page=await context.newPage();page.setDefaultTimeout(30000);page.setDefaultNavigationTimeout(90000);
  page.on('pageerror',error=>report.errors.push({device,url:page.url(),message:error.message}));
  if(!auth){await page.goto(base+'/login');await page.getByLabel('Email',{exact:true}).fill('maria.find-care-audit@example.com');await page.getByLabel('Password',{exact:true}).fill('password');await page.getByRole('button',{name:'Log in',exact:true}).click();await page.waitForURL(url=>!url.pathname.endsWith('/login'));auth=await context.storageState();}
  const response=await page.goto(base+'/family/requests/44?tab=home');check(device+' request44 HTTP',response.ok(),{status:response.status()});
  await page.waitForLoadState('networkidle');await page.evaluate(()=>document.fonts.ready);
  const nav=page.getByRole('navigation',{name:'Care recruitment',exact:true});
  for(let index=0;index<stages.length;index++){
   const [tab,label]=stages[index],id='request44-'+device+'-'+tab;
   await nav.getByRole('button',{name:new RegExp(label)}).click();
   await page.waitForURL(url=>url.searchParams.get('tab')===tab);
   await page.waitForFunction(index=>document.querySelectorAll('.hc-recruit-stage')[index]?.getAttribute('aria-current')==='step',index);
   await page.waitForLoadState('networkidle');await page.mouse.move(0,0);await page.evaluate(()=>scrollTo(0,0));
   const data=await inspect(page);report.states.push({id,...data});
   check(id+' no page error',!/Internal Server Error|ErrorException|SQLSTATE\[/.test(data.text));
   check(id+' active arrow',data.steps.filter(s=>s.current==='step').length===1&&data.steps[index].current==='step');
   check(id+' no overflow',data.documentWidth<=viewport.width+1,{documentWidth:data.documentWidth,width:viewport.width});
   check(id+' connected chevrons',data.steps.length===4&&data.steps.slice(0,3).every((s,i)=>s.before.clip!=='none'&&s.after.clip!=='none'&&Math.abs(s.rect.right-data.steps[i+1].rect.x)<=1));
   check(id+' labels and badges fit',data.steps.every(s=>s.label.x>=s.rect.x-1&&s.label.right<=s.rect.right+1&&s.label.bottom<=s.rect.bottom+1&&(!s.count||(s.count.rect.x>=s.rect.x-1&&s.count.rect.right<=s.rect.right+1&&s.count.rect.bottom<=s.label.y+1||s.count.rect.x>=s.label.right))),{steps:data.steps});
   if(tab==='start'){
    check(id+' preparation heading',await page.getByRole('heading',{name:'Choose a caregiver to get started',exact:true}).count()===1);
    check(id+' no hire confirmation',await page.locator('[wire\\:click="confirmReviewedHire"]').count()===0&&await page.locator('[role="dialog"]:visible').count()===0);
   }
   const file='screenshots/'+id+(device==='narrow'?'-navigation.png':'-top.png');
   if(device==='narrow')await nav.screenshot({path:path.join(out,file),animations:'disabled'});else await page.screenshot({path:path.join(out,file),animations:'disabled'});
   report.captures.push({id,file});save();console.log('Captured '+id+' '+file);
  }
  if(device==='desktop'||device==='narrow'){
   for(let index=0;index<3;index++){
    const button=nav.getByRole('button').nth(index),id='request44-'+device+'-'+(device==='desktop'?'hover':'focus')+'-'+(index+1);
    if(device==='desktop')await button.hover();
    else if(index===0){await page.getByRole('link',{name:'Care story',exact:true}).focus();await page.keyboard.press('Tab');}
    else await page.keyboard.press('Tab');
    const state=await button.evaluate(e=>{const ring=e.querySelector('.hc-recruit-focus-ring');return{focusVisible:e.matches(':focus-visible'),hover:e.matches(':hover'),outline:getComputedStyle(e).outline,outlineWidth:getComputedStyle(e).outlineWidth,outlineOffset:getComputedStyle(e).outlineOffset,shadow:getComputedStyle(e).boxShadow,background:getComputedStyle(e).backgroundColor,zIndex:getComputedStyle(e).zIndex,text:e.innerText,ring:ring?{visibility:getComputedStyle(ring).visibility,borderWidth:getComputedStyle(ring).borderWidth,zIndex:getComputedStyle(ring).zIndex}:null};});
    report.interactions.push({id,...state});check(id+' interaction visible',device==='desktop'?state.hover:state.focusVisible&&(parseFloat(state.outlineWidth)>0||state.ring?.visibility==='visible'&&parseFloat(state.ring.borderWidth)>=2),{state});
    const file='screenshots/'+id+'.png';await nav.screenshot({path:path.join(out,file),animations:'disabled'});report.captures.push({id,file});
   }
  }
  if(device==='narrow')for(const requestId of [28,13]){
   await page.goto(base+'/family/requests/'+requestId+'?tab=home');await page.waitForLoadState('networkidle');const data=await inspect(page);
   report.states.push({id:'compat-'+requestId,...data});check('compat '+requestId+' arrows and page fit',data.steps.length===4&&data.documentWidth<=321,{steps:data.steps});
   const file='screenshots/compat-'+requestId+'-navigation.png';await nav.screenshot({path:path.join(out,file),animations:'disabled'});report.captures.push({id:'compat-'+requestId,file});
  }
  await context.close();
 }
}catch(error){report.errors.push({message:error.stack});}finally{await browser.close();}
const after=snapshot(),changed=Object.keys(before).filter(t=>before[t].rows!==after[t].rows||before[t].sha256!==after[t].sha256);
report.dataPreservation={tables:Object.keys(before).length,changed,passed:changed.length===0};check('Original care data unchanged',changed.length===0,{changed});
report.passed=report.checks.every(c=>c.passed)&&!report.errors.length&&!report.blockedRequests.length&&report.states.length===14;save();
console.log(JSON.stringify({passed:report.passed,captures:report.captures.length,checks:report.checks.length,failures:report.checks.filter(c=>!c.passed),errors:report.errors,blockedRequests:report.blockedRequests}));
if(!report.passed)process.exitCode=1;
