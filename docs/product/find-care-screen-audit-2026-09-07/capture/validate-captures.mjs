// Read-only validation of prepared capture evidence. No browser/DB state changes.
import fs from 'node:fs';
import path from 'node:path';
const output = path.resolve('docs/product/find-care-screen-audit-2026-09-07');
const captures = JSON.parse(fs.readFileSync(path.join(output, 'captures.json'), 'utf8'));
const targets = JSON.parse(fs.readFileSync(path.join(output, 'recapture-ids.json'), 'utf8'));
const checks = [];
for (const id of targets) {
  const capture = captures.find(row => row.id === id);
  if (!capture || capture.captureVersion < 4 || capture.status !== 'captured') {
    checks.push({id, status:'pending', version:capture?.captureVersion, error:capture?.error});
    continue;
  }
  for (const device of ['desktop', 'mobile']) {
    const evidence = JSON.parse(fs.readFileSync(path.join(output, capture[device].inventory), 'utf8'));
    const {controls, text, headings} = evidence;
    const hasField = name => controls.some(c => c.label === name || c.label.startsWith(name+'\n'));
    const hasButton = name => controls.some(c => c.tag === 'BUTTON' && c.text === name);
    const failures = [];
    const require = (condition, description) => {if (!condition) failures.push(description);};
    if (/after_shift-/.test(id)) {
      require(text.includes('Caregiver hours need your review.'), 'Review-hours hero missing');
      require(text.includes('Approve hours and pay $62.00'), 'Approve-hours action missing');
    }
    if (/invite-drawer$/.test(id)) require(hasField('Search by caregiver name'), 'Invitation search field missing');
    if (id === 'RC-plan-panel-extra') {
      for (const field of ['Day','Start time','Optional note']) require(hasField(field), field+' field missing');
      require(controls.some(c => c.tag === 'SELECT'), 'Duration selector missing');
      require(text.includes('Ask for one extra visit'), 'Extra-visit panel heading missing');
    }
    if (id === 'RC-plan-panel-schedule') {
      require(controls.filter(c => c.label === 'Starts at').length === 3, 'Expected three weekday start-time fields');
      require(controls.filter(c => c.label === 'Ends at').length === 3, 'Expected three weekday end-time fields');
      require(hasField('Start new schedule on'), 'Effective date field missing');
      require(hasButton('Send schedule change'), 'Schedule-change action missing');
    }
    if (id === 'RC-plan-panel-pause') for (const field of ['Pause starting','Return date (optional)']) require(hasField(field), field+' missing');
    if (id === 'RC-plan-panel-end') {
      require(hasField('Also cancel the next confirmed visit'), 'Cancel-next-visit checkbox missing');
      require(hasButton('Keep recurring care'), 'Keep-care action missing');
    }
    if (id === 'RC-create-empty') {
      require(headings.some(h => h.text === 'Recurring care schedule'), 'Recurring schedule heading missing');
      require(controls.some(c => c.type === 'checkbox' && c.label === 'Sunday'), 'Weekday options missing');
      require(!hasField('Starting day'), 'Stale one-time date field remains');
    }
    if (/create-details$/.test(id)) {
      for (const field of ['Contact name','Relationship','Phone','Email (optional)']) require(hasField(field), field+' missing');
      if (id.startsWith('RC-')) {
        require(hasField('Last day'), 'Recurring end-date field missing');
        require(controls.some(c => c.type === 'time'), 'Selected weekday start-time field missing');
        require(controls.some(c => c.tag === 'SELECT' && c.label === 'Visit length' && c.options?.some(o => o.value === '60' && o.selected)), 'Selected weekday visit-length selector missing');
      }
    }
    if (/create-self-private$/.test(id)) {
      require(hasButton('Create private request'), 'Private-create action missing');
      require(text.includes('Only caregivers you invite can view and respond'), 'Private explanation missing');
      require(controls.some(c => c.checked && c.value === 'self'), 'Self-care option missing');
    }
    if (id === 'OT-create-saved-profile') {
      require(hasButton('Publish request'), 'Public publish action missing');
      require(!hasButton('Create private request'), 'Stale private action remains');
      require(text.includes('Eleanor Bennett'), 'Saved recipient missing');
    }
    if (/invite-search-certifications$/.test(id)) {
      require(text.includes('No caregivers match your search and CPR with LoLo verification.'), 'Verified-credential no-match result missing');
      require(controls.some(c => c.checked && c.value === 'verified_only'), 'Verified-only filter missing');
      require(!text.includes('Searching caregivers…'), 'Search still loading');
    }
    if (/review-form-draft$/.test(id)) {
      require(text.includes('Selected rating: 5/5'), 'Selected rating missing');
      require(controls.some(c => c.label === 'Review comment' && c.value === 'Sofia was punctual, thoughtful, and made Ellie feel comfortable.'), 'Review comment draft missing');
    }
    checks.push({id, device, status:failures.length?'failed':'passed', failures});
  }
}
// Default pages must exclude content nested inside closed disclosures.
for (const id of ['OT-waiting-applicants', 'OT-create-empty', 'RC-plan-active']) {
  const capture = captures.find(row => row.id === id);
  if (capture?.captureVersion !== 4 || capture.status !== 'captured') continue;
  for (const device of ['desktop', 'mobile']) {
    const j = JSON.parse(fs.readFileSync(path.join(output, capture[device].inventory), 'utf8'));
    let failures = [];
    if (id === 'OT-waiting-applicants' && j.controls.some(c => c.label === 'CPR')) failures.push('Closed certification options included');
    if (id === 'OT-create-empty' && j.controls.some(c => ['Care notes','Home access','Timing note'].includes(c.label))) failures.push('Closed optional fields included');
    if (id === 'RC-plan-active' && j.headings.some(c => ['Care location','Care notes','Tasks'].includes(c.text))) failures.push('Closed care-detail headings included');
    checks.push({id, device, status:failures.length?'failed':'passed', failures});
  }
}
console.log(JSON.stringify({passed:checks.filter(c=>c.status==='passed').length, pending:checks.filter(c=>c.status==='pending'), failed:checks.filter(c=>c.status==='failed')},null,2));
