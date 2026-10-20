# Approved caregiver CRM

Open **CRM → Caregivers**, or `/admin/crm?pipeline=caregivers`. This tab is available to administrators. Sales retains its existing family and referral CRM access.

## Who appears

The board follows `CaregiverWorkEligibility`: a caregiver user with an active profile, no screening suspension, and either an initial screening approval or explicitly retained legacy work access. It does not require public-directory visibility, saved availability, or a new-client availability toggle. Unapproved and suspended caregivers are excluded from every read and write. Their existing CRM history is retained if they become ineligible.

## Stages

- **New:** approved, with no application or hire evidence.
- **Applied:** has applied for a real care request or continuous-coverage engagement, including an application later withdrawn or rejected.
- **Hired:** has confirmed hire evidence; this takes precedence over Applied and remains historical after care ends or is cancelled.

Hire evidence includes genuine request applications marked hired, retained bookings, activated regular-care plans, and confirmed continuous-coverage shifts or their durable replacement history. Draft and system-generated request applications alone do not count. Invitations, roster membership, and unaccepted proposals are not hires.

Counts represent distinct engagements: a care request, an accepted direct regular-care plan, or a coverage plan for this caregiver. Repeated visits do not multiply a single engagement. Columns are computed from operational data, not manually movable. Each column has independent bounded pagination so every approved caregiver remains accessible.

## Relationship workspace

Cards show application/hire counts, owner, priority, next action, follow-up date, and a **Spoke on phone** tag. Search covers name, email, phone, and ZIP. Filters cover phone conversations, due/upcoming follow-ups, and ownership.

The panel provides:

- Editable pinned notes, administrator owner, priority, next action, and follow-up.
- A dated activity log for notes, phone calls, emails, texts, and meetings.
- Explicit phone outcomes: spoke on phone, no answer, or left voicemail. Only a connected conversation creates the phone tag; previously unrecorded conversations can be logged with their actual date.
- An activity timeline with actor attribution and older/newer pagination.
- Next-action completion, recorded in the timeline before clearing the reminder.

Logging an interaction records work already performed; it sends no messages or calls. Follow-ups stay in the caregiver CRM. Reading the board does not create Lead or CRM records. Record edits use version checks so another staff member's newer changes cannot be overwritten. Closing a panel with unsaved drafts asks before discarding them.

## Rollout and verification

Run the normal application migrations, including `2026_10_20_000200_create_caregiver_crm_tables`, and build frontend assets with `npm ci` / `npm run build`. No backfill job is needed: existing approved caregivers and their milestones are discovered automatically.

Tests:

- `tests/Feature/Admin/CaregiverCrmDirectoryTest.php`: eligibility, historical evidence, filters, pagination, and query boundaries.
- `tests/Feature/Admin/CaregiverCrmServiceTest.php`: permissions, validation, optimistic concurrency, and audit history.
- `tests/Feature/Admin/CaregiverCrmPageTest.php`: Livewire integration, panel actions, and existing-tab isolation.
- `playwright.caregiver-crm.config.ts`: desktop/mobile workflows in a guarded SQLite fixture with outbound messages and HTTP blocked.
