// Run these at the end: each prepare switches its context to the separate
// no-profile audit family. All entered content stays in the unsaved create form.
async function settled(page){await page.waitForTimeout(400);await page.waitForLoadState('networkidle');}

export async function quickProfileSpecs(manifest){
  if(!manifest.emptyProfileFamily)throw new Error('Run seed-empty-family.mjs before quick-profile captures.');
  const actor=manifest.emptyProfileFamily;
  return ['one_time','recurring'].map(variation=>({
    id:(variation==='one_time'?'OT':'RC')+'-create-quick-profile',variation,stage:'Create',
    title:'Create — simple care profile draft for a new family',tab:'Single form',
    disclosure:'simple care profile expanded',focusSelector:'textarea[wire\\:model="quick_profile_about"]',
    url:'/family/requests/create?type='+variation,
    prepare:async page=>{
      const base=new URL(page.url()).origin;
      await page.context().clearCookies();
      await page.goto(base+'/login');await settled(page);
      await page.getByLabel('Email',{exact:true}).fill(actor.email);
      await page.getByLabel('Password',{exact:true}).fill(actor.password);
      await page.getByRole('button',{name:'Log in',exact:true}).click();
      await page.waitForURL(url=>!url.pathname.endsWith('/login'));
      await page.goto(base+'/family/requests/create?type='+variation);await settled(page);
      const pattern=page.locator(`input[wire\\:model\\.live="request_type"][value="${variation}"]`);
      if(!await pattern.isChecked()) {await pattern.check({force:true});await settled(page);}
      await page.getByRole('heading',{name:variation==='recurring'?'Recurring care schedule':'Visit schedule',exact:true}).waitFor();
      await page.getByRole('button',{name:'Add a simple care profile',exact:true}).click();
      await settled(page);
      await page.getByLabel('What helps care go well?',{exact:true}).waitFor({state:'visible'});
      await page.getByLabel('Family member receiving care',{exact:true}).fill('Harold Brooks');
      await page.getByLabel('Relationship to you',{exact:true}).fill('Father');
      await page.getByLabel('What should a caregiver know?',{exact:true}).fill('Harold is a retired librarian who enjoys jazz, garden walks, and talking about books. He appreciates a friendly introduction and time to get to know someone.');
      await page.getByLabel('What helps care go well?',{exact:true}).fill('A relaxed pace, clear choices, and a cup of tea help Harold feel comfortable. Please ask about his usual routine and give him time to answer.');
      await page.locator('input[wire\\:model="quick_profile_sharing_acknowledged"]').check();
      // A reversible live task selection flushes deferred profile text into
      // Livewire's draft only. It does not call publish or a profile-save method.
      await page.locator('input[wire\\:model\\.live="selectedTasks"]').first().check({force:true});
      await settled(page);
      await page.getByRole('button',{name:'Skip',exact:true}).waitFor();
      if(!await page.locator('input[wire\\:model="quick_profile_sharing_acknowledged"]').isChecked())throw new Error('Sharing acknowledgment draft not preserved');
      if((await page.getByLabel('What should a caregiver know?',{exact:true}).inputValue()).length<80)throw new Error('Quick-profile draft text not preserved');
    },
  }));
}
