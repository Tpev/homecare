import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

const auditRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const screens = JSON.parse(fs.readFileSync(path.join(auditRoot, 'captures.json'), 'utf8'));
function note(screen) {
  const {id, tab, stage, url = ''} = screen;
  if (id.includes('inline-support')) return ['Help must remain reachable without covering the mobile decision button.', 'The inline entry opens the existing support dialog. Context and support behavior remain unchanged.'];
  if (id.includes('hire-review')) return ['Hiring needs a clear final opportunity to check the selected caregiver, scope and price.', 'The inline review shows the existing terms before confirmation; final confirmation calls the original hire action.'];
  if (/-(in_progress|timesheet_review)-confirmation$/.test(id)) return ['Ending care and approving submitted hours are different decisions.', 'This explicit preview describes the relevant decision, retains draft notes and delegates confirmation to the existing completion method.'];
  if (/\/family\/care\/\d+(?:\?|$)/.test(url)) {
    if (tab === 'caregivers') return ['The ongoing caregiver relationship needs a stable destination, separate from a dated visit.', 'Caregiver identity, offer context and original recruitment/conversation access stay with the recurring care.'];
    if (tab === 'details') return ['Care instructions should be findable without repeating the full profile above every action.', 'The complete attached profile, plan notes, tasks, address and plan record stay here. Full event payloads are retained in the database, not presented as a new timeline.'];
    if (tab === 'visits') return ['Families need to distinguish confirmed dates from a requested schedule and find older outstanding work.', 'Filters use real bookings, retain exact-visit links and preserve the tab, filter and pagination context on return. Schedule management keeps existing business rules.'];
    return ['The recurring arrangement needs one continuing home across individual visits.', 'A current visit and outstanding decisions are visible with their own status. Optional reviews follow operational decisions; plan status does not replace visit or payment status.'];
  }
  if (stage === 'Create') return ['Creation needs a readable sequence and a complete check before publishing.', 'Person/help, schedule, location/notes and review use the existing form fields and publish action. Recurring schedule previews remain requests until booked.'];
  if (tab === 'applicants') return ['A family comparing replies should reach candidates without navigating through a large invitation area.', 'Replies lead when present; invitations lead when there are no replies. Filters, profiles, messages, invitation history and original eligibility rules remain available.'];
  if (tab === 'support') return ['Changes, hours questions and disputes need the context of the affected visit.', 'Existing contextual forms and support actions remain on this record. They do not silently apply a change to the whole recurring arrangement.'];
  if (tab === 'visits') return ['Before hire, a recurring weekly pattern is not a set of confirmed visits.', 'This page labels the requested pattern clearly and keeps the family in the same recruiting workspace.'];
  if (tab === 'overview' && /\/family\/requests\/\d+/.test(url)) return ['The full care information needs a stable destination while the main task remains concise.', 'The legacy overview route still opens care details/instructions, including the attached care-profile version, tasks, address and contact information.'];
  if (tab === 'home') return ['The next step should remain clear for zero, one or many replies, including overdue requests.', 'Overview gives the request status and current action; Caregivers, Visits where relevant, Care details and Help remain predictable destinations.'];
  if (tab === 'shift') return ['The dated visit is the correct scope for care, hours, payment and feedback.', 'The compact header retains exact identity and status. Relevant actions lead, with the existing detailed record, corrections, payment information and review fields still accessible.'];
  return ['Cross-care lists and history must retain their existing record identity and filtering.', 'Shared styling connects these pages to the new workspace. Care cards open the continuing arrangement; booked-date actions open the exact visit.'];
}
const notes = Object.fromEntries(screens.map(screen => {
  const [findings, redesign] = note(screen);
  return [screen.id, {review: 'Implementation rationale; validation recorded separately', findings, redesign, owner: 'Local care workspace implementation', priority: ''}];
}));
fs.writeFileSync(path.join(auditRoot, 'review-notes.json'), JSON.stringify(notes, null, 2) + '\n');
console.log('Wrote implementation rationale for ' + screens.length + ' screen states; this is not an individual visual-review certification.');
