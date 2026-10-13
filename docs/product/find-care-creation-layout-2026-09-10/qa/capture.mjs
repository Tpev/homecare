import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {chromium} from 'playwright';

const root=process.cwd(),output=path.join(root,'docs/product/find-care-creation-layout-2026-09-10');
const base='http://127.0.0.1:8028';
for(const directory of ['screenshots','evidence'])fs.mkdirSync(path.join(output,directory),{recursive:true});
const snapshot=()=>JSON.parse(execFileSync('php',['docs/product/find-care-recruitment-polish-2026-09-09/qa/fixtures.php'],{cwd:root,encoding:'utf8',windowsHide:true}));
const before=snapshot();fs.writeFileSync(path.join(output,'data-before.json'),JSON.stringify(before,null,2));
const buildHash=crypto.createHash('sha256').update(fs.readFileSync('public/build/manifest.json')).digest('hex');
const report={at:new Date().toISOString(),buildHash,checks:[],errors:[],blockedRequests:[],captures:[]};
const check=(name,passed,details={})=>{report.checks.push({name,passed,...details});if(!passed)console.log('FAIL '+name+' '+JSON.stringify(details));};
const browser=await chromium.launch({channel:'msedge',headless:true});
const viewports={desktop:{width:1440,height:1000},mobile:{width:390,height:844},narrow:{width:320,height:844}};
let auth;
try{
 for(const [device,viewport] of Object.entries(viewports)){
  const context=await browser.newContext({viewport,deviceScaleFactor:1,isMobile:device!=='desktop',hasTouch:device!=='desktop',reducedMotion:'reduce',...(auth?{storageState:auth}:{})});
  await context.route('**/*',route=>{
   const request=route.request(),url=new URL(request.url());
   if(!['127.0.0.1','localhost'].includes(url.hostname)&&!['data:','blob:'].includes(url.protocol))return route.abort();
   if(url.pathname==='/livewire/update'){
    const methods=(request.postDataJSON()?.components||[]).flatMap(component=>(component.calls||[]).map(call=>call.method));
    if(methods.some(method=>!['$refresh','$set','login'].includes(method))){report.blockedRequests.push({url:url.href,methods});return route.abort();}
   }
   return route.continue();
  });
  const page=await context.newPage();page.setDefaultTimeout(30000);page.setDefaultNavigationTimeout(90000);
  page.on('pageerror',error=>report.errors.push({device,url:page.url(),message:error.message}));
  if(!auth){await page.goto(base+'/login');await page.getByLabel('Email',{exact:true}).fill('maria.find-care-audit@example.com');await page.getByLabel('Password',{exact:true}).fill('password');await page.getByRole('button',{name:'Log in',exact:true}).click();await page.waitForURL(url=>!url.pathname.endsWith('/login'));auth=await context.storageState();}
  for(const variation of ['one_time','recurring']){
   const id=(variation==='one_time'?'OT':'RC')+'-'+device;
   const response=await page.goto(base+'/family/requests/create?type='+variation);
   await page.waitForLoadState('networkidle');await page.evaluate(()=>document.fonts.ready);await page.locator('.hc-create-form').waitFor();
   check(id+' HTTP',response.ok(),{status:response.status()});
   const data=await page.evaluate(()=>{
    const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,bottom:r.bottom,right:r.right};};
    const text=el=>(el.innerText||el.textContent||'').replace(/\s+/g,' ').trim();
    const form=document.querySelector('.hc-create-form'),fields=form.querySelector('.hc-create-fields'),review=form.querySelector('#care-review');
    const controls=[...form.querySelectorAll('input:not([type=hidden]),textarea,select')].map(el=>({tag:el.tagName,type:el.type,model:[...el.attributes].find(attribute=>attribute.name.startsWith('wire:model'))?.value,label:el.getAttribute('aria-label')||[...(el.labels||[])].map(text).join(' / ')||el.getAttribute('placeholder')||'',value:el.value,checked:el.checked,visible:el.checkVisibility(),options:el.tagName==='SELECT'?[...el.options].map(option=>({value:option.value,label:option.text})):undefined}));
    const steps=[...document.querySelectorAll('.hc-create-journey>li')].map(el=>({text:text(el),current:el.getAttribute('aria-current'),rect:rect(el),before:{content:getComputedStyle(el,'::before').content,clip:getComputedStyle(el,'::before').clipPath,width:getComputedStyle(el,'::before').width,borderLeft:getComputedStyle(el,'::before').borderLeftWidth},after:{content:getComputedStyle(el,'::after').content,clip:getComputedStyle(el,'::after').clipPath,width:getComputedStyle(el,'::after').width,borderLeft:getComputedStyle(el,'::after').borderLeftWidth}}));
    return{text:document.body.innerText,form:rect(form),fields:rect(fields),review:rect(review),asideCount:form.querySelectorAll('aside').length,sectionSubnavCount:document.querySelectorAll('nav[aria-label="Request sections"],.hc-create-subnav,.hc-create-section-nav').length,steps,controls,width:innerWidth,documentWidth:document.documentElement.scrollWidth,stylesheets:[...document.querySelectorAll('link[rel="stylesheet"]')].map(link=>link.href),fonts:[...document.fonts].filter(font=>font.status==='loaded').map(font=>font.family)};
   });
   check(id+' no application error',!/Internal Server Error|ErrorException|SQLSTATE\[/.test(data.text));
   check(id+' no horizontal overflow',data.documentWidth<=viewport.width+1,{width:viewport.width,documentWidth:data.documentWidth});
   check(id+' no form sidebar',data.asideCount===0);
   check(id+' no Request sections subnav',data.sectionSubnavCount===0&&await page.getByRole('navigation',{name:'Request sections',exact:true}).count()===0);
   check(id+' review after all fields',data.review.y>=data.fields.bottom-1,{fields:data.fields,review:data.review});
   check(id+' review and fields same column width',Math.abs(data.fields.width-data.review.width)<=1&&Math.abs(data.fields.x-data.review.x)<=1,{fields:data.fields,review:data.review});
   check(id+' four lifecycle stages',data.steps.length===4&&['Care request','Find caregivers','Review applicants','Get started'].every((label,index)=>data.steps[index].text.includes(label)));
   check(id+' first lifecycle stage current',data.steps[0].current==='step');
   check(id+' stages connected without gaps',data.steps.slice(1).every((step,index)=>Math.abs(step.rect.x-data.steps[index].rect.right)<=1.5&&Math.abs(step.rect.y-data.steps[0].rect.y)<=1),{rects:data.steps.map(step=>step.rect)});
   check(id+' first three stages have chevrons',data.steps.slice(0,3).every(step=>[step.before,step.after].some(pseudo=>pseudo.content!=='none'&&pseudo.content!=='normal'&&(pseudo.clip!=='none'||parseFloat(pseudo.borderLeft)>0))),{steps:data.steps});
   check(id+' local brand fonts',data.fonts.includes('Inter')&&data.fonts.includes('Source Serif 4'),{fonts:data.fonts});
   const required=['request_type','care_for','selectedTasks','address_line1','address_line2','city','state','zip','additional_info','time_expectations','home_access_notes',...(variation==='one_time'?['requested_start_date','requested_start_time','requested_duration_minutes']:['recurring_days','recurring_starts_on','recurring_end_choice'])];
   const models=new Set(data.controls.map(control=>control.model));
   check(id+' expected existing input models retained',required.every(model=>models.has(model)),{missing:required.filter(model=>!models.has(model)),models:[...models]});
   const files={top:'screenshots/'+id+'-top.png',full:'screenshots/'+id+'-full.png',inventory:'evidence/'+id+'.json'};
   await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(output,files.top),fullPage:false,animations:'disabled'});await page.screenshot({path:path.join(output,files.full),fullPage:true,animations:'disabled'});
   fs.writeFileSync(path.join(output,files.inventory),JSON.stringify(data,null,2));report.captures.push({id,variation,device,viewport,...files});
   fs.writeFileSync(path.join(output,'validation.json'),JSON.stringify(report,null,2));console.log('Captured '+id);
  }
  await context.close();
 }
}catch(error){report.errors.push({message:error.stack});}finally{await browser.close();}
const after=snapshot();const changed=Object.keys(before.tables).filter(table=>before.tables[table].rows!==after.tables[table].rows||before.tables[table].sha256!==after.tables[table].sha256);
report.dataPreservation={tables:Object.keys(before.tables).length,changed,passed:changed.length===0};
check('Original care records unchanged',changed.length===0,{changed});
report.passed=report.checks.every(check=>check.passed)&&report.errors.length===0&&report.blockedRequests.length===0&&report.captures.length===6;
fs.writeFileSync(path.join(output,'validation.json'),JSON.stringify(report,null,2));
console.log(JSON.stringify({passed:report.passed,captures:report.captures.length,checks:report.checks.length,failures:report.checks.filter(check=>!check.passed),errors:report.errors,blockedRequests:report.blockedRequests}));if(!report.passed)process.exitCode=1;
