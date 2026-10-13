import {manifest} from './environment.mjs';
export async function settle(page){await page.waitForTimeout(400);await page.waitForLoadState('networkidle');await page.evaluate(()=>document.fonts.ready);}
async function change(page,field,value){await field.fill(value);await field.press('Tab');await settle(page);}
async function fillCreate(page,variation){
 await page.locator('input[wire\\:model\\.live="care_for"][value="other"]').check({force:true});await settle(page);
 await change(page,page.getByLabel('Family member receiving care',{exact:true}),'Eleanor Bennett');
 await change(page,page.getByLabel('Relationship to you',{exact:true}),'Mother');
 await page.locator('input[wire\\:model\\.live="selectedTasks"]').first().check({force:true});await settle(page);
 await change(page,page.getByLabel('Street address',{exact:true}),'210 Oakwood Avenue');
 await change(page,page.getByLabel('Apartment, unit, or gate (optional)',{exact:true}),'Side entrance');
 await change(page,page.getByLabel('City',{exact:true}),'Raleigh');
 await page.getByLabel('State',{exact:true}).selectOption('NC');await settle(page);
 await change(page,page.getByLabel('ZIP',{exact:true}),'27601');
 const firstDate='2026-09-16',lastDate='2026-11-09';
 if(variation==='one_time'){
  await change(page,page.getByLabel('Starting day',{exact:true}),firstDate);
  await change(page,page.getByLabel('Starting time',{exact:true}),'10:00');
  await page.getByLabel('Duration (HH:MM)',{exact:true}).selectOption('120');await settle(page);
 }else{
  for(const day of ['1','3']){
   const checkbox=page.locator(`input[wire\\:model\\.live="recurring_days"][value="${day}"]`);
   if(!await checkbox.isChecked())await checkbox.locator('..').click();await settle(page);
   if(!await checkbox.isChecked())throw new Error('Recurring weekday did not become selected: '+day);
   await change(page,page.locator('#recurring-start-'+day),day==='1'?'10:00':'13:00');
   await page.locator('#recurring-duration-'+day).selectOption(day==='1'?'120':'180');await settle(page);
  }
  await change(page,page.getByLabel('When should this schedule begin?',{exact:true}),firstDate);
  await page.locator('input[value="date"]').check({force:true});await settle(page);
  await change(page,page.getByLabel('Last day',{exact:true}),lastDate);
 }
 await page.locator('#private-care-request').check({force:true});await settle(page);
 await page.locator('#private-care-request').uncheck({force:true});await settle(page);
}
async function expand(page){
 for(let turn=0;turn<3;turn++){
  const summaries=page.locator('main details:not([open]) > summary');let opened=0;
  for(let index=(await summaries.count())-1;index>=0;index--){const summary=summaries.nth(index);if(await summary.isVisible()){await summary.click();opened++;}}
  if(!opened)break;
 }
 await settle(page);
}
export function specs(){
 const list=[];
 for(const variation of ['one_time','recurring']){
  const prefix=variation==='one_time'?'OT':'RC',requests=manifest.requests[variation];
  const add=(key,title,url,extra={})=>list.push({id:prefix+'-'+key,variation,title,url,...extra});
  add('create-empty','Create care — initial form','/family/requests/create?type='+variation,{stage:'Create'});
  add('create-review','Create care — filled review before publication','/family/requests/create?type='+variation,{stage:'Create',focusSelector:'#care-review',prepare:page=>fillCreate(page,variation)});
  add('request','Care request — public overview',`/family/requests/${requests.waiting}?tab=home`,{stage:'Care request',request:requests.waiting});
  add('request-private','Care request — invitation-only overview',`/family/requests/${requests.private_waiting}?tab=home`,{stage:'Care request',request:requests.private_waiting});
  add('request-details','Care request — all care details expanded',`/family/requests/${requests.waiting}?tab=overview`,{stage:'Care request',request:requests.waiting,prepare:expand});
  add('find-search','Find caregivers — recommendations',`/family/requests/${requests.waiting}?tab=invite&view=search`,{stage:'Find caregivers',candidate:'discovery-row',request:requests.waiting});
  add('find-name','Find caregivers — name search',`/family/requests/${requests.waiting}?tab=invite&view=search`,{stage:'Find caregivers',candidate:'discovery-row',request:requests.waiting,prepare:async page=>{await page.locator('input[wire\\:model\\.live\\.debounce\\.350ms="caregiverSearch"]').fill('Sofia');await settle(page);}});
  add('find-none','Find caregivers — no search results',`/family/requests/${requests.waiting}?tab=invite&view=search`,{stage:'Find caregivers',request:requests.waiting,prepare:async page=>{await page.locator('input[wire\\:model\\.live\\.debounce\\.350ms="caregiverSearch"]').fill('NoMatchingCaregiver');await settle(page);}});
  add('find-invited','Find caregivers — invitations already sent',`/family/requests/${requests.invitations}?tab=invite&view=invited`,{stage:'Find caregivers',request:requests.invitations});
  add('find-saved','Find caregivers — saved caregivers',`/family/requests/${requests.applicants}?tab=invite&view=saved`,{stage:'Find caregivers',request:requests.applicants});
  add('legacy-invite-search','Private request — preserved invitation search entry',`/family/requests/${requests.private_waiting}?tab=applicants&invite=1`,{stage:'Find caregivers',request:requests.private_waiting,verifyLegacyEscape:true,prepare:async page=>{await page.getByRole('dialog').waitFor({state:'visible'});}});
  add('invite-review','Invite caregiver — message and request review',`/family/requests/${requests.waiting}?tab=invite&view=search`,{stage:'Find caregivers',request:requests.waiting,prepare:async page=>{await page.locator('button[wire\\:click^="beginCaregiverInvitation("]').first().click();await page.getByRole('dialog').waitFor({state:'visible'});await page.getByLabel('Invitation message (optional)',{exact:true}).fill('Hello Sofia, Ellie would enjoy a calm visit with conversation and lunch. Please review the schedule when convenient.');}});
  for(const [key,state,title] of [['zero','waiting','no applicants yet'],['one','single_applicant','one applicant'],['many','many_applicants','four applicants']]){
   add('applicants-'+key,'Review applicants — '+title,`/family/requests/${requests[state]}?tab=applicants`,{stage:'Review applicants',request:requests[state],candidate:key==='zero'?null:'applicant-row',expectedApplicants:key==='zero'?0:key==='one'?1:4});
  }
  add('applicants-saved','Review applicants — shortlisted applicants',`/family/requests/${requests.many_applicants}?tab=applicants`,{stage:'Review applicants',request:requests.many_applicants,candidate:'applicant-row',expectedApplicants:1,prepare:async page=>{await page.locator('[wire\\:click="setApplicantView(\'shortlisted\')"]').click();await settle(page);}});
  add('applicants-past','Review applicants — past applicants',`/family/requests/${requests.many_applicants}?tab=applicants`,{stage:'Review applicants',request:requests.many_applicants,expectedApplicants:0,prepare:async page=>{await page.locator('[wire\\:click="setApplicantView(\'past\')"]').click();await settle(page);}});
  add('applicants-details','Review applicants — application and qualifications expanded',`/family/requests/${requests.many_applicants}?tab=applicants`,{stage:'Review applicants',request:requests.many_applicants,expectedApplicants:4,prepare:expand});
  add('hire-review','Get started — hire terms review before confirmation',`/family/requests/${requests.applicants}?tab=applicants`,{stage:'Get started',request:requests.applicants,focusSelector:'#care-decision-review',prepare:async page=>{await page.locator('button[wire\\:click^="reviewHire("]').first().click();await page.locator('#care-decision-review').waitFor({state:'visible'});await page.getByRole('button',{name:'Confirm hire',exact:true}).waitFor({state:'visible'});}});
  add('scheduled','Get started — booked visit',`/family/requests/${requests.scheduled}?tab=shift`,{stage:'Get started',request:requests.scheduled});
 }
 list.push({id:'RC-request-visits',variation:'recurring',stage:'Care request',title:'Requested recurring schedule — no booked visits yet',url:`/family/requests/${manifest.requests.recurring.waiting}?tab=visits`,request:manifest.requests.recurring.waiting});
 list.push({id:'RC-plan-overview',variation:'recurring',stage:'Get started',title:'Recurring arrangement — stable home after hiring',url:'/family/care/1?tab=overview'});
 list.push({id:'RC-plan-visits',variation:'recurring',stage:'Get started',title:'Recurring arrangement — individual dated visits',url:'/family/care/1?tab=visits'});
 return list;
}
