import fs from 'node:fs';
import path from 'node:path';
import {output} from './environment.mjs';

const rows = JSON.parse(fs.readFileSync(path.join(output, 'captures.json'), 'utf8'));
const fixtures = JSON.parse(fs.readFileSync(path.join(output, 'fixture-semantics.json'), 'utf8'));
const checks = [];
const primaryTasks = {
  waiting: 'Your request is live.', invitations: 'Your request is live.',
  applicants: 'Caregivers are ready for review.', single_applicant: 'Caregivers are ready for review.', many_applicants: 'Caregivers are ready for review.',
  private_waiting: 'Finding a caregiver by invitation', scheduled: 'Your visit is scheduled.',
  in_progress: 'Care is happening now.', paused: 'The visit is paused.',
  after_shift: 'Caregiver hours need your review.', timesheet_review: 'Caregiver hours need your review.',
  leave_review: 'This visit is complete.', closed: 'This visit is complete.',
  cancelled: 'This request was withdrawn.', cancelled_booking: 'This visit was cancelled.',
  payment_attention: 'Your visit is scheduled.', time_correction: 'Review the updated visit hours',
};
const planLabels = {pending_caregiver: 'Waiting for Sofia Martinez', countered: 'New schedule proposed', active: 'Recurring care is active', payment_attention: 'Payment needs attention', paused: 'Care is paused', ended: 'Recurring care ended', cancelled: 'Recurring care cancelled', declined: 'Offer declined', expired: 'Offer expired', draft: 'Draft recurring care'};
for (const row of rows) for (const device of ['desktop', 'mobile']) {
  const check = {id: row.id, device, route: row.url, kind: 'auxiliary route', assertions: [], failures: []};
  const expect = (condition, description) => { check.assertions.push(description); if (!condition) check.failures.push(description); };
  const item = row[device];
  if (!item || row.status !== 'captured') { check.failures.push('Successful capture is required'); checks.push(check); continue; }
  const data = JSON.parse(fs.readFileSync(path.join(output, item.inventory), 'utf8'));
  expect(new URL(item.metadata.finalUrl).pathname === new URL(row.url, 'http://localhost').pathname, 'Rendered the intended route');
  const requestId = Number(row.url.match(/^\/family\/requests\/(\d+)(?:\?|$)/)?.[1]);
  const planId = Number(row.url.match(/^\/family\/care\/(\d+)(?:\?|$)/)?.[1]);
  if (requestId && fixtures.requests[requestId]) {
    check.kind = 'request or dated visit';
    const expected = fixtures.requests[requestId];
    check.fixtureState = expected.fixtureState;
    check.bookingId = expected.bookingId;
    check.databaseStatus = expected.bookingStatus || expected.status;
    expect(data.workspace?.status === expected.expectedHeaderStatus, 'Header status matches stored fixture state');
    const reference = expected.bookingId ? 'Visit #' + expected.bookingId : 'Request #' + requestId;
    expect((data.workspace?.headerText || '').includes(reference), 'Header identifies the intended ' + reference);
    if (['waiting', 'invitations', 'applicants', 'single_applicant', 'many_applicants', 'private_waiting'].includes(expected.fixtureState)) expect(!expected.oneTimeDatePassed, 'Recruitment fixture has not expired into a different lifecycle stage');
    if (['home', 'shift'].includes(row.tab) && primaryTasks[expected.fixtureState]) expect(data.workspace?.primaryTitle === primaryTasks[expected.fixtureState], 'Primary task matches intended ' + expected.fixtureState + ' stage');
    if (['after_shift', 'timesheet_review'].includes(expected.fixtureState)) expect(expected.submitted && !expected.familyConfirmed, 'Hours are submitted and still need family approval');
  } else if (planId) {
    check.kind = 'recurring parent';
    const fixture = Object.values(fixtures.plans).find(plan => plan.id === planId);
    expect(Boolean(fixture), 'Plan is a documented synthetic fixture');
    if (fixture) {
      check.databaseStatus = fixture.status;
      expect((data.workspace?.headerText || '').includes('Care #' + planId), 'Header identifies the intended parent plan');
      expect((data.workspace?.headerText || '').includes(planLabels[fixture.status]), 'Header status matches intended plan lifecycle');
      const tab = new URL(row.url, 'http://localhost').searchParams.get('tab') || 'overview';
      expect((data.workspace?.selectedTabs || []).includes({overview: 'Overview', caregivers: 'Caregivers', visits: 'Visits', details: 'Care details'}[tab]), 'Intended parent tab is selected');
      if (['countered', 'declined', 'cancelled'].includes(fixture.status)) expect(fixture.generated_booking_count === 0 && !fixture.next_booking_id, 'New offer contains no generated recurring booking');
    }
  } else if (row.url.startsWith('/family/requests/create') && ['one_time', 'recurring'].includes(row.variation)) {
    check.kind = 'creation variation';
    expect(data.controls.some(control => control.type === 'radio' && control.value === row.variation && control.checked), 'Requested creation variation is selected');
  }
  check.passed = check.failures.length === 0;
  checks.push(check);
}
const result = {checkedAt: new Date().toISOString(), deviceChecks: checks.length, assertions: checks.reduce((sum, check) => sum + check.assertions.length, 0), checks, failures: checks.filter(check => check.failures.length), passed: checks.every(check => !check.failures.length)};
fs.writeFileSync(path.join(output, 'screen-semantics.json'), JSON.stringify(result, null, 2));
console.log(JSON.stringify({deviceChecks: result.deviceChecks, assertions: result.assertions, failures: result.failures, passed: result.passed}, null, 2));
if (!result.passed) process.exitCode = 1;
