import fs from 'node:fs';
import path from 'node:path';
import {chromium} from 'playwright';
import {output,base} from './environment.mjs';
const report={at:new Date().toISOString(),checks:[],errors:[]};
const check=(name,pass,detail={})=>{report.checks.push({name,pass,...detail});if(!pass) console.log('FAIL '+name+' '+JSON.stringify(detail));};
const browser=await chromium.launch({channel:'msedge',headless:true});
const rgb=v=>v.match(/[\d.]+/g).slice(0,3).map(Number);
const luminance=v=>rgb(v).map(x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4;}).reduce((a,x,i)=>a+x*[.2126,.7152,.0722][i],0);
const contrast=(a,b)=>{const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);};
const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
await context.route('**/*',r=>{const u=new URL(r.request().url());return ['127.0.0.1','localhost','fonts.googleapis.com','fonts.gstatic.com'].includes(u.hostname)||['data:','blob:'].includes(u.protocol)?r.continue():r.abort();});
const page=await context.newPage();page.setDefaultTimeout(30000);
page.on('pageerror',e=>report.errors.push({url:page.url(),message:e.message}));
async function settle(){await page.waitForTimeout(450);await page.waitForLoadState('networkidle');}
try{
 await page.goto(base+'/login');await page.getByLabel('Email',{exact:true}).fill('maria.find-care-audit@example.com');await page.getByLabel('Password',{exact:true}).fill('password');await page.getByRole('button',{name:'Log in',exact:true}).click();await page.waitForURL(u=>!u.pathname.endsWith('/login'));
 for(const width of [1440,390,320]){
  await page.setViewportSize({width,height:width===1440?1000:844});
  for(const route of ['/family/requests/13?tab=home','/family/requests/13?tab=visits','/family/requests/28?tab=applicants','/family/requests/8?tab=shift','/family/requests/38?tab=shift','/family/care/1?tab=overview','/family/care/1?tab=visits']){
   const response=await page.goto(base+route);await settle();
   const dim=await page.evaluate(()=>({width:innerWidth,scrollWidth:document.documentElement.scrollWidth}));
   check('render '+width+' '+route,response.ok()&&!/Server Error|ErrorException|SQLSTATE\[/.test(await page.locator('body').innerText()));
   check('no overflow '+width+' '+route,dim.scrollWidth<=dim.width,dim);
   const primary=page.locator('.hc-care-workspace .hc-primary-button:visible,.hc-care-workspace .hc-action-primary:visible').first();
   if(await primary.count())for(const state of ['normal','hover','focus']){
    if(state==='hover')await primary.hover();if(state==='focus')await primary.focus();
    const colors=await primary.evaluate(el=>{const s=getComputedStyle(el);return {color:s.color,background:s.backgroundColor,outline:s.outlineStyle};});
    const ratio=contrast(colors.color,colors.background);check('primary '+state+' contrast '+width+' '+route,ratio>=4.5,{ratio,...colors});
    if(state==='focus')check('focus visible '+width+' '+route,colors.outline!=='none');
   }
  }
 }
 await page.setViewportSize({width:390,height:844});
 await page.goto(base+'/family/requests/8?tab=shift');await settle();
 await page.locator('#care-request-primary').getByRole('button',{name:'Review hours & payment',exact:true}).click();await settle();
 check('review visible',await page.locator('#care-decision-review').isVisible());
 const note=page.locator('#care-confirmation-note');await note.fill('Local QA draft — do not submit');
 await page.getByRole('button',{name:'Back to visit',exact:true}).click();await settle();
 await page.locator('#care-request-primary').getByRole('button',{name:'Review hours & payment',exact:true}).click();await settle();
 check('approval draft retained',await note.inputValue()==='Local QA draft — do not submit');
 const confirm=page.getByRole('button',{name:/Approve hours and pay/});await confirm.scrollIntoViewIfNeeded();
 const exposed=await confirm.evaluate(el=>{const b=el.getBoundingClientRect();return [.2,.5,.8].every(x=>el.contains(document.elementFromPoint(b.x+b.width*x,b.y+b.height/2)));});
 check('confirmation button unobscured',exposed);
 check('floating launcher replaced',!(await page.locator('.support-chat-launcher').isVisible()));
 await page.getByRole('button',{name:'Back to visit',exact:true}).click();await settle();
 await page.locator('.hc-care-heading').getByRole('button',{name:'Get help',exact:true}).click();await settle();
 await page.getByRole('button',{name:'Chat with support',exact:true}).click();await page.waitForTimeout(800);
 check('support still opens',await page.locator('.support-chat-panel').isVisible());
 report.fonts=await page.evaluate(()=>[...document.fonts].filter(f=>f.status==='loaded').map(f=>f.family));
 check('brand fonts loaded locally',report.fonts.includes('Inter')&&report.fonts.includes('Source Serif 4'),{fonts:report.fonts});
}catch(e){report.errors.push({url:page.url(),message:e.stack});}finally{await browser.close();}
report.passed=report.checks.every(c=>c.pass)&&report.errors.length===0;
fs.writeFileSync(path.join(output,'workspace-ui-validation.json'),JSON.stringify(report,null,2));
console.log(JSON.stringify({passed:report.passed,checks:report.checks.length,failures:report.checks.filter(c=>!c.pass),errors:report.errors}));
if(!report.passed)process.exitCode=1;
