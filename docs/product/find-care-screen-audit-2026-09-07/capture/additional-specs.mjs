// Additive capture cases. Every prepare action changes only the browser's draft
// UI state: no invitation, hire, payment, message, review, or change is submitted.
async function settled(page) {
  await page.waitForTimeout(400);
  await page.waitForLoadState('networkidle');
}

async function fillChange(page, locator, value) {
  await locator.fill(value);
  await locator.press('Tab');
  await settled(page);
}

async function openDisclosure(scope, text) {
  const summary = scope.locator('summary').filter({hasText: text}).first();
  if (!await summary.count()) throw new Error('Missing disclosure: ' + text);
  if (!await summary.locator('..').getAttribute('open').then(value => value !== null)) {
    await summary.click();
  }
}

async function openInvites(page) {
  await page.locator('[wire\\:click="openCaregiverInvitePanel"]').first().click();
  await settled(page);
  const dialog = page.getByRole('dialog');
  await dialog.waitFor({state:'visible'});
  return dialog;
}

function dateAfter(seed, days) {
  const value = new Date(seed);
  value.setUTCDate(value.getUTCDate() + days);
  return value.toISOString().slice(0,10);
}

async function fillReadyCreate(page, variation, manifest, {self=false, saved=false, privateRequest=false}={}) {
  // The query parameter selects the pattern before the first render.
  if (saved) {
    await page.locator(`[wire\\:click="selectCareRecipientProfile(${manifest.recipientProfileId})"]`).click();
    await settled(page);
    await page.locator('[wire\\:click="clearCareRecipientProfile"]').waitFor({state:'visible'});
  } else if (self) {
    await page.locator('input[wire\\:model\\.live="care_for"][value="self"]').check({force:true});
    await settled(page);
    await page.getByText('This request will be marked as care for the person posting it.',{exact:true}).waitFor();
  } else {
    await page.locator('input[wire\\:model\\.live="care_for"][value="other"]').check({force:true});
    await settled(page);
    await page.getByLabel('Family member receiving care',{exact:true}).fill('Eleanor Bennett');
    await page.getByLabel('Relationship to you',{exact:true}).fill('Mother');
  }
  const firstTask=page.locator('input[wire\\:model\\.live="selectedTasks"]').first();
  await firstTask.check({force:true});
  await settled(page);
  await page.getByLabel('Street address',{exact:true}).fill('210 Oakwood Avenue');
  await page.getByLabel('Apartment, unit, or gate (optional)',{exact:true}).fill('Side entrance');
  await page.getByLabel('City',{exact:true}).fill('Raleigh');
  await page.getByLabel('State',{exact:true}).selectOption('NC');
  await page.getByLabel('ZIP',{exact:true}).fill('27601');
  if (variation==='one_time') {
    await fillChange(page,page.getByLabel('Starting day',{exact:true}),dateAfter(manifest.seededAt,7));
    await fillChange(page,page.getByLabel('Starting time',{exact:true}),'10:00');
    await page.getByLabel('Duration (HH:MM)',{exact:true}).selectOption('120');
    await settled(page);
  } else {
    for (const day of ['1','3']) {
      await page.locator(`input[wire\\:model\\.live="recurring_days"][value="${day}"]`).check({force:true});
      await settled(page);
      await fillChange(page,page.locator(`#recurring-start-${day}`),day==='1'?'10:00':'13:00');
      await page.locator(`#recurring-duration-${day}`).selectOption(day==='1'?'120':'180');
      await settled(page);
    }
    await fillChange(page,page.getByLabel('When should this schedule begin?',{exact:true}),dateAfter(manifest.seededAt,7));
    await page.locator('input[value="date"]').check();
    await settled(page);
    await page.getByLabel('Last day',{exact:true}).fill(dateAfter(manifest.seededAt,60));
  }
  // A real live checkbox interaction flushes deferred text fields into the
  // form's review panel without invoking its publish handler.
  const privacy=page.locator('#private-care-request');
  await privacy.check();
  await settled(page);
  await page.getByRole('button',{name:'Create private request',exact:true}).waitFor();
  await page.getByText('This request won’t appear in the caregiver marketplace. Only caregivers you invite can view and respond to it. You’ll choose invitees after creating the request.',{exact:true}).waitFor();
  if (!privateRequest) {
    await privacy.uncheck();await settled(page);
    await page.getByRole('button',{name:'Publish request',exact:true}).waitFor();
    await page.getByText('Leave this unchecked to let eligible caregivers discover and apply to the request. You can still invite specific caregivers.',{exact:true}).waitFor();
  }
  if (await privacy.isChecked()!==privateRequest) throw new Error('Privacy checkbox and requested draft variant disagree');
  await page.getByText('All essentials are ready. Review once, then publish.',{exact:true}).waitFor();
}

export async function additionalSpecs(manifest) {
  const specs=[];
  const caregiver=manifest.caregivers.sofia||Object.values(manifest.caregivers)[0];
  for(const [variation,requests] of Object.entries(manifest.requests)) {
    const prefix=variation==='one_time'?'OT':'RC';
    const add=(key,stage,title,tab,disclosure,url,prepare)=>specs.push({id:prefix+'-'+key,variation,stage,title,tab,disclosure,url,prepare});
    const create='/family/requests/create?type='+variation;
    add('create-ready','Create','Create — filled family request ready to publish','Single form','filled',create,
      page=>fillReadyCreate(page,variation,manifest));
    add('create-self-private','Create','Create — care for me / private request ready to publish','Single form','filled / self / private',create,
      page=>fillReadyCreate(page,variation,manifest,{self:true,privateRequest:true}));
    add('create-saved-profile','Create','Create — saved care profile selected / ready to publish','Single form','saved profile selected',create,
      page=>fillReadyCreate(page,variation,manifest,{saved:true}));
    add('invite-confirmation','Waiting / inviting','Invite caregiver — message preview before sending','applicants','invitation confirmation',`/family/requests/${requests.waiting}?tab=applicants`,async page=>{
      const dialog=await openInvites(page);
      await dialog.locator(`[wire\\:click="beginCaregiverInvitation(${caregiver.id})"]`).first().click();
      await settled(page);
      await dialog.getByRole('button',{name:'Back to caregiver results',exact:true}).waitFor();
      await dialog.getByLabel('Invitation message (optional)',{exact:true}).fill('Hello Sofia, Ellie would enjoy a calm visit with conversation and lunch. Please review the schedule when convenient.');
    });
    add('invite-search-certifications','Waiting / inviting','Invite caregiver — name search and selected certification filters','applicants','search / certification filter expanded',`/family/requests/${requests.waiting}?tab=applicants`,async page=>{
      const dialog=await openInvites(page);
      await dialog.getByLabel('Search by caregiver name',{exact:true}).fill(caregiver.name.split(' ')[0]);
      await settled(page);
      await dialog.getByRole('heading',{name:'Search results',exact:true}).waitFor();
      await openDisclosure(dialog,'Certifications & training');
      const credential=dialog.locator('input[type="checkbox"]').first();
      if(await credential.count()) {
        const credentialLabel=(await credential.locator('..').innerText()).trim();
        await credential.check();await settled(page);
        await dialog.getByRole('button',{name:'Remove '+credentialLabel+' filter',exact:true}).waitFor();
        await dialog.getByText('No caregivers match your search and '+credentialLabel+'.',{exact:true}).waitFor();
        await dialog.locator('input[value="verified_only"]').check();await settled(page);
        await dialog.getByRole('button',{name:'Remove LoLo verified only filter',exact:true}).waitFor();
        await dialog.getByText('No caregivers match your search and '+credentialLabel+' with LoLo verification.',{exact:true}).waitFor();
      }
    });
    add('invite-drawer-bottom','Waiting / inviting','Invite caregiver — lower results in the drawer','applicants','invite drawer scrolled to end',`/family/requests/${requests.waiting}?tab=applicants`,async page=>{
      const dialog=await openInvites(page);
      await dialog.locator('[x-ref="panelScroll"]').evaluate(el=>{el.scrollTop=el.scrollHeight;});
    });
    add('scheduled-reschedule-draft','Hired / before shift','Help — reschedule fields filled before sending','support','reschedule draft',`/family/requests/${requests.scheduled}?tab=support`,async page=>{
      await page.getByLabel('Change type',{exact:true}).selectOption('reschedule');
      // Same-tab navigation is a normal existing Livewire control, and flushes
      // changeType (which uses deferred wire:model) without sending a request.
      await page.locator('[wire\\:click="setActiveTab(\'support\')"]').first().click();
      await settled(page);
      await page.getByLabel('Proposed start',{exact:true}).waitFor({state:'visible'});
      await page.getByLabel('Proposed start',{exact:true}).fill(dateAfter(manifest.seededAt,8)+'T10:00');
      await page.getByLabel('Proposed end',{exact:true}).fill(dateAfter(manifest.seededAt,8)+'T12:00');
      await page.getByLabel('Reason',{exact:true}).fill('A family appointment changed. Would the next morning work?');
    });
    if(manifest.conversations[variation]) add('existing-chat','Waiting / inviting','Existing caregiver conversation — care request context','Conversation','default',`/messages/${manifest.conversations[variation]}`);
    add('care-story','Closed','Care story — completed request and connected visit','Care sections','default',`/family/care/journey/${variation==='recurring'?'regular':'request'}/${variation==='recurring'?manifest.plans.ended:requests.closed}`);

    // Added fixtures are optional until the separate seed-extension run returns
    // them. Every eligible tab gets its own desktop/mobile pair via the harness.
    const extraStates={
      single_applicant:['Waiting / inviting',['applicants','overview']],
      many_applicants:['Waiting / inviting',['applicants','overview']],
      private_waiting:['Waiting / inviting',['applicants','overview']],
      paused:['During shift',['shift','support','applicants','overview']],
      payment_attention:['Hired / before shift',['shift','applicants','support','overview']],
      time_correction:['Timesheet / review',['shift','support','applicants','overview']],
    };
    for(const [state,[stage,tabs]] of Object.entries(extraStates)) {
      if(!requests[state])continue;
      for(const tab of tabs) add(state+'-'+tab,stage,state.replaceAll('_',' ')+' — '+({shift:'Visit',applicants:'Caregivers',overview:'Care details',support:'Help'}[tab]),tab,'default',`/family/requests/${requests[state]}?tab=${tab}`);
    }
    if(requests.many_applicants) add('many_applicants-applicants-expanded','Waiting / inviting','Many applicants — status, sorting and profile details expanded','applicants','expanded',`/family/requests/${requests.many_applicants}?tab=applicants`);
    if(requests.time_correction) add('time-correction-approval-preview','Timesheet / review','Time correction — approval and payment confirmation preview','shift','confirmation preview',`/family/requests/${requests.time_correction}?tab=shift`,async page=>{
      await page.locator('button[wire\\:click^="beginTimeCorrectionApproval("]').click();
      await settled(page);
      await page.getByText('Confirm approval',{exact:true}).waitFor();
    });
  }
  specs.push({id:'OT-book-again',variation:'one_time',stage:'Closed',title:'Book caregiver again — one visit or recurring handoff',tab:'Single form',disclosure:'default',url:`/family/requests/${manifest.requests.one_time.closed}/book-again`});
  for(const variation of ['one_time','recurring']){
    const prefix=variation==='one_time'?'OT':'RC';
    for(const filled of [false,true])specs.push({id:prefix+'-review-form-'+(filled?'draft':'empty'),variation,stage:'Timesheet / review',title:'Leave a caregiver review — '+(filled?'rating and comment draft':'empty form'),tab:'shift',disclosure:'review form',focusSelector:'h2:has-text("Leave a caregiver review")',url:`/family/requests/${manifest.requests[variation].leave_review}?tab=shift`,prepare:async page=>{
      await page.getByRole('heading',{name:'Leave a caregiver review',exact:true}).waitFor();
      if(filled){await page.getByRole('button',{name:'Rate 5 out of 5',exact:true}).click();await settled(page);await page.getByText('Selected rating: 5/5',{exact:true}).waitFor();await page.getByLabel('Review comment',{exact:true}).fill('Sofia was punctual, thoughtful, and made Ellie feel comfortable.');}
    }});
  }
  specs.push({id:'SH-navigation-menu',variation:'shared',stage:'Create',title:'Family navigation — desktop bar / mobile menu open',tab:'Global navigation',disclosure:'mobile menu',url:'/family/requests/create',prepare:async page=>{const toggle=page.getByRole('button',{name:'Toggle navigation menu',exact:true});if(await toggle.isVisible())await toggle.click();}});
  return specs;
}
