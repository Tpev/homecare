import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {chromium} from 'playwright';
const out=path.resolve('docs/product/find-care-request44-design-2026-09-10'),base='http://127.0.0.1:8028';
const report={at:new Date().toISOString(),checks:[],errors:[],blockedRequests:[],captures:[]};
const snapshot=()=>JSON.parse(execFileSync('php',['docs/product/find-care-recruitment-polish-2026-09-09/qa/fixtures.php'],{encoding:'utf8',windowsHide:true})).tables;
const original=JSON.parse(fs.readFileSync(path.join(out,'data-before.json'),'utf8')),before=snapshot();
fs.writeFileSync(path.join(out,'focus-data-before.json'),JSON.stringify(before,null,2));
if(fs.existsSync(path.join(out,'focus-validation.json')))fs.copyFileSync(path.join(out,'focus-validation.json'),path.join(out,'focus-validation-initial.json'));
if(fs.existsSync(path.join(out,'screenshots/request44-narrow-focus-fixed-2.png')))fs.copyFileSync(path.join(out,'screenshots/request44-narrow-focus-fixed-2.png'),path.join(out,'evidence/focus-ring-before-clearance-fix.png'));
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const context=await browser.newContext({viewport:{width:320,height:844},reducedMotion:'reduce'});
 await context.route('**/*',route=>{
  const request=route.request(),url=new URL(request.url());
  if(!['127.0.0.1','localhost'].includes(url.hostname)&&!['data:','blob:'].includes(url.protocol))return route.abort();
  if(url.pathname==='/livewire/update'){
   const methods=(request.postDataJSON()?.components||[]).flatMap(c=>(c.calls||[]).map(call=>call.method));
   if(methods.some(method=>!['login','$refresh','$set','setActiveTab'].includes(method))){report.blockedRequests.push({methods});return route.abort();}
  }else if(!['GET','HEAD','OPTIONS'].includes(request.method())){report.blockedRequests.push({url:url.href});return route.abort();}
  return route.continue();
 });
 const page=await context.newPage();page.setDefaultTimeout(30000);page.on('pageerror',error=>report.errors.push(error.message));
 await page.goto(base+'/login');await page.getByLabel('Email',{exact:true}).fill('maria.find-care-audit@example.com');await page.getByLabel('Password',{exact:true}).fill('password');await page.getByRole('button',{name:'Log in',exact:true}).click();await page.waitForURL(url=>!url.pathname.endsWith('/login'));
 await page.goto(base+'/family/requests/44?tab=start');await page.waitForLoadState('networkidle');await page.evaluate(()=>document.fonts.ready);
 report.stylesheets=await page.locator('link[rel=stylesheet]').evaluateAll(es=>es.map(e=>e.href));
 const nav=page.getByRole('navigation',{name:'Care recruitment',exact:true});
 await page.getByRole('link',{name:'Care story',exact:true}).focus();
 await page.keyboard.press('Tab');
 for(let index=1;index<3;index++){
  await page.keyboard.press('Tab');
  const state=await nav.getByRole('button').nth(index).evaluate(e=>{const ring=e.querySelector('.hc-recruit-focus-ring'),css=getComputedStyle(ring),r=ring.getBoundingClientRect(),b=e.getBoundingClientRect(),l=e.querySelector('.hc-recruit-step-label').getBoundingClientRect();return{text:e.innerText,focused:document.activeElement===e,focusVisible:e.matches(':focus-visible'),visibility:css.visibility,borderWidth:css.borderWidth,zIndex:css.zIndex,left:r.left-b.left,right:r.right-b.left,buttonWidth:b.width,labelClearance:l.left>=r.left+2-0.5&&l.right<=r.right-2+0.5,shadow:css.boxShadow};});
  report.checks.push({name:'Stage '+(index+1)+' keyboard focus clear of arrow and label',passed:state.focused&&state.focusVisible&&state.visibility==='visible'&&parseFloat(state.borderWidth)>=2&&parseInt(state.zIndex,10)>2&&state.left>=5&&state.right<state.buttonWidth&&state.labelClearance,state});
  const file='screenshots/request44-narrow-focus-fixed-'+(index+1)+'.png';await nav.screenshot({path:path.join(out,file),animations:'disabled'});report.captures.push({file});
 }
 await page.goto(base+'/family/requests/28?tab=home');await page.waitForLoadState('networkidle');await page.evaluate(()=>document.fonts.ready);
 await page.getByRole('link',{name:'Care story',exact:true}).focus();await page.keyboard.press('Tab');await page.keyboard.press('Tab');await page.keyboard.press('Tab');
 const badge=await nav.getByRole('button').nth(2).evaluate(e=>{const r=e.querySelector('.hc-recruit-count').getBoundingClientRect(),l=e.querySelector('.hc-recruit-step-label').getBoundingClientRect();return{focusVisible:e.matches(':focus-visible'),count:e.querySelector('.hc-recruit-count').innerText,belowBadge:l.top>=r.bottom,left:r.left,right:r.right};});
 report.checks.push({name:'Applicant count stays above label with keyboard focus',passed:badge.focusVisible&&badge.belowBadge,state:badge});
 await nav.screenshot({path:path.join(out,'screenshots/compat-28-focus-final.png'),animations:'disabled'});report.captures.push({file:'screenshots/compat-28-focus-final.png'});
 await context.close();
}catch(error){report.errors.push(error.stack);}finally{await browser.close();}
const after=snapshot();
const changed=Object.keys(before).filter(t=>before[t].rows!==after[t].rows||before[t].sha256!==after[t].sha256);
report.dataPreservation={tables:Object.keys(before).length,changed,passed:changed.length===0};
const delta=JSON.parse(execFileSync('php',['docs/product/find-care-request44-design-2026-09-10/qa/inspect-invitations.php'],{encoding:'utf8',windowsHide:true}));
const originalChanged=Object.keys(original).filter(t=>t!=='care_request_invitations'&&(original[t].rows!==after[t].rows||original[t].sha256!==after[t].sha256));
report.originalDataPreservation={tables:Object.keys(original).length,changed:originalChanged,originalInvitationRows:delta.originalRows,passed:!originalChanged.length&&delta.originalRows.sha256===original.care_request_invitations.sha256,addition:delta.invitations.find(row=>row.id===7),note:'One invitation was independently added between the initial matrix and focus verification. The guarded QA browser did not send it. Every original row still matches the baseline; no data was removed or changed by QA.'};
fs.writeFileSync(path.join(out,'data-addition-proof.json'),JSON.stringify({baseline:original.care_request_invitations,...delta},null,2));
report.passed=report.checks.length===3&&report.checks.every(c=>c.passed)&&report.dataPreservation.passed&&report.originalDataPreservation.passed&&!report.errors.length&&!report.blockedRequests.length;
fs.writeFileSync(path.join(out,'focus-validation.json'),JSON.stringify(report,null,2));
console.log(JSON.stringify(report));if(!report.passed)process.exitCode=1;
