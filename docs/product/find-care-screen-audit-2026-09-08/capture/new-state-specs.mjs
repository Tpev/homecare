// New visible destinations and review-only interactions introduced by the redesign.
// These preparations never invoke the final hire, completion or payment methods.
export async function newStateSpecs(manifest) {
  const specs = [];
  const requireTab = label => async page => {
    const tab = page.getByRole('button', {name: label, exact: true});
    await tab.waitFor({state: 'visible'});
    if (await tab.getAttribute('aria-current') !== 'page') throw new Error('Plan tab did not become active: ' + label);
  };
  const beforeHire = ['waiting', 'invitations', 'applicants', 'single_applicant', 'many_applicants', 'private_waiting', 'cancelled'];
  for (const [variation, requests] of Object.entries(manifest.requests)) {
    const prefix = variation === 'one_time' ? 'OT' : 'RC';
    for (const state of beforeHire) {
      if (!requests[state]) continue;
      const stage = state === 'cancelled' ? 'Closed' : 'Waiting / inviting';
      for (const [tab, label] of [['home', 'Overview'], ['support', 'Help'], ...(variation === 'recurring' ? [['visits', 'Visits']] : [])]) {
        specs.push({id: `${prefix}-${state}-${tab}`, variation, stage, title: state.replaceAll('_', ' ') + ' — ' + label, tab, disclosure: 'default', url: `/family/requests/${requests[state]}?tab=${tab}`, addedByRedesign: true});
      }
    }
    specs.push({id: `${prefix}-hire-review`, variation, stage: 'Waiting / inviting', title: 'Hire caregiver — terms and payment review before confirmation', tab: 'applicants', disclosure: 'hire review', url: `/family/requests/${requests.applicants}?tab=applicants`, focusSelector: '#care-decision-review', addedByRedesign: true, prepare: async page => {
      await page.locator('button[wire\\:click^="reviewHire("]').first().click();
      await page.locator('#care-decision-review').waitFor({state: 'visible'});
      await page.getByRole('button', {name: 'Confirm hire', exact: true}).waitFor({state: 'visible'});
    }});
    for (const [state, title, finalLabel, stage] of [
      ['in_progress', 'End visit — confirmation before recording completion', 'Confirm visit has ended', 'During shift'],
      ['timesheet_review', 'Review submitted hours and payment — confirmation', 'Approve hours and pay $62.00', 'Timesheet / review'],
    ]) {
      specs.push({id: `${prefix}-${state}-confirmation`, variation, stage, title, tab: 'shift', disclosure: 'completion review', url: `/family/requests/${requests[state]}?tab=shift`, focusSelector: '#care-decision-review', addedByRedesign: true, prepare: async page => {
        await page.locator('button[wire\\:click="reviewCompletion"]').first().click();
        await page.locator('#care-decision-review').waitFor({state: 'visible'});
        await page.getByRole('button', {name: finalLabel, exact: true}).waitFor({state: 'visible'});
      }});
    }
  }
  const plans = {...manifest.plans, ...manifest.newPlans, ...Object.fromEntries(Object.entries(manifest.extraPlans ?? {}).map(([key, id]) => ['visit_' + key, id]))};
  for (const [state, id] of Object.entries(plans)) {
    if (typeof id !== 'number') continue;
    const stage = ['ended', 'cancelled', 'declined'].includes(state) ? 'Closed' : ['pending', 'countered'].includes(state) ? 'Waiting / inviting' : 'Hired / before shift';
    for (const [tab, label] of [['overview', 'Overview'], ['caregivers', 'Caregivers'], ['visits', 'Visits'], ['details', 'Care details']]) {
      specs.push({id: `RC-plan-${state}-${tab}`, variation: 'recurring', stage, title: `Recurring care ${state} — ${label}`, tab, disclosure: 'default', url: `/family/care/${id}?tab=${tab}`, addedByRedesign: true, prepare: requireTab(label)});
    }
    for (const filter of ['attention', 'past', 'cancelled', 'all']) {
      specs.push({id: `RC-plan-${state}-visits-${filter}`, variation: 'recurring', stage, title: `Recurring care ${state} — Visits / ${filter}`, tab: 'visits', disclosure: filter + ' filter', url: `/family/care/${id}?tab=visits&visit_filter=${filter}`, addedByRedesign: true, prepare: async page => {
        await requireTab('Visits')(page);
        const label = {attention: 'Needs follow-up', past: 'Past', cancelled: 'Cancelled', all: 'All visits'}[filter];
        if (await page.getByRole('button', {name: label, exact: true}).getAttribute('aria-pressed') !== 'true') throw new Error('Visit filter did not become active: ' + filter);
      }});
    }
    specs.push({id: `RC-plan-${state}-help`, variation: 'recurring', stage, title: `Recurring care ${state} — Help`, tab: 'overview', disclosure: 'help expanded', url: `/family/care/${id}?tab=overview`, focusSelector: '#plan-help', addedByRedesign: true, prepare: async page => {
      await page.getByRole('button', {name: 'Get help', exact: true}).click();
      await page.locator('#plan-help').waitFor({state: 'visible'});
    }});
    specs.push({id: `RC-plan-${state}-details-expanded`, variation: 'recurring', stage, title: `Recurring care ${state} — Complete care profile and plan record`, tab: 'details', disclosure: 'expanded', url: `/family/care/${id}?tab=details`, addedByRedesign: true, prepare: requireTab('Care details')});
  }
  for (const [id, variation, stage, url] of [
    ['SH-inline-support-create', 'shared', 'Create', '/family/requests/create'],
    ['OT-inline-support-visit', 'one_time', 'Timesheet / review', `/family/requests/${manifest.requests.one_time.timesheet_review}?tab=support`],
    ['RC-inline-support-plan', 'recurring', 'Hired / before shift', `/family/care/${manifest.plans.active}?tab=overview`],
  ]) {
    specs.push({id, variation, stage, title: 'Inline Help — support chat open', tab: id.endsWith('plan') ? 'overview' : id.endsWith('visit') ? 'support' : 'Single form', disclosure: 'support chat', url, addedByRedesign: true, prepare: async page => {
      // The existing widget remembers its open state across pages. Close it
      // through its control, then prove this page's own entry can reopen it.
      const supportPanel = page.getByTestId('support-chat-panel');
      if (await supportPanel.isVisible()) {
        await page.getByRole('button', {name: 'Minimize support chat', exact: true}).click();
        await supportPanel.waitFor({state: 'hidden'});
      }
      if (id.endsWith('plan')) {
        await page.getByRole('button', {name: 'Get help', exact: true}).click();
        await page.locator('#plan-help').waitFor({state: 'visible'});
      }
      await page.getByRole('button', {name: 'Chat with support', exact: true}).click();
      await page.getByRole('dialog').waitFor({state: 'visible'});
    }});
  }
  return specs;
}
