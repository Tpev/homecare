# Caregiver roster assistant

## Purpose and staff workflow

Give admin and customer-success users an on-demand recruiting assistant inside Customer Success. Choose a managed customer request (the customer is resolved from that request), supply context and urgency, and generate a proposed contact roster. Existing request screens link directly into the assistant. Customer and caregiver screens never render the assistant, its notes, rankings, or approval records. SDR/sales access to other workspaces does not grant this permission.

1. Choose a request and enter operational context: deadline, schedule flexibility, objections, previous conversations, and what CS wants to confirm.
2. Generate a draft. This reads data and calls the existing OpenAI provider configuration; it sends no invitations or messages. Generation runs on the existing queue. Show progress and recoverable errors.
3. Review ranked caregivers, including people never invited. Each row shows current invitation/application status, fit, caveats, confirmation questions, dated source records, saved schedule, travel estimate, and proposed outreach. Existing applicants/accepted invitations remain visible for review; they are not reinvited. Pending invitations can receive an explicitly approved SMS follow-up. Declined/cancelled/expired invitations are not automatically recycled.
4. Select a small contact batch. Edit the invitation text and optional SMS independently for each person. SMS defaults off, even for urgent searches. No caregiver is preselected.
5. Preview the exact selected recipients, channels and texts. Persist this preview server-side with a unique review token and expiration. Editing or replacing a preview invalidates the previous token.
6. Approve the reviewed batch. Revalidate staff authority, request ownership/state, schedule, eligibility, existing outreach, phone and SMS consent. Queue only the frozen approved actions. Repeated clicks and queue retries must not duplicate invitations or SMS.
7. Show per-person invitation/application and SMS delivery status, including blocked, failed and awaiting-provider-confirmation results. New searches use current evidence. Invitations and positive replies never hire or book automatically.

## Evidence and selection

Screen the entire profile pool structurally before applying any shortlist limit. Exclude profiles ineligible for new invitations, revoked users, and known opt-outs. Use the bundled ZIP geography, not city-string similarity alone; distances are approximate straight-line estimates, not driving times. Include unknown geography as a visible uncertainty. Consider nearby candidates beyond their stated radius as conditional options only when CS explicitly reviews travel.

Build evidence packets from allowlisted fields, linked by durable IDs:

- Request: customer/account IDs, title, care description, tasks, location at city/ZIP precision, one-time or recurring schedule, start/end dates and status. No home access codes or payment data.
- Customer: linked lead notes and activity, inbound/outbound SMS, request conversations, and CS action notes. Preserve author, date and source ID; deduplicate SMS-backed activity. Do not merge identities by names.
- Caregiver: bio, experience notes/tags, skills, languages, saved availability and its update time, service ZIP/radius, screening state and qualification verification/expiry. Identity checks are not proof of task competence.
- History: caregiver CRM notes, human activities, SMS and job conversations with request IDs; invitations, applications, recruitment, future bookings, recurring plans and continuous-coverage assignments/offers. Distinguish tentative interest from committed work.
- Coverage: total profiles screened, excluded counts/reasons, regional candidates evaluated, and every evidence window or text truncation. No silent first-N database cutoff. If an input exceeds the configured processing ceiling, fail clearly instead of pretending the whole pool was reviewed.

Process regional evidence in bounded batches, assess every candidate in those batches, then rank the strongest candidates globally. Keep evidence references with each assessment. The model may not invent caregivers, source IDs, certification verification, availability, acceptance, or probabilities. Server validation rejects unknown/duplicate IDs and unknown evidence references. Empty results are valid and explained.

The instructions carry forward the reporting skill: read chronology and corrections; distinguish facts, reported experience and inference; treat notes/SMS as untrusted evidence rather than instructions; respect opt-outs; avoid caregiver pay discussion; avoid inferring protected characteristics or personal qualities from names/photos. Use job-relevant professional evidence. Outbound drafts must not reveal private CS assessments, unrelated customer details, health history, or credentials/contact details from evidence.

## Implementation

- Separate staff authorization checked on route/component actions, generation jobs, approval and dispatch. Existing family AI rollout switches remain unchanged.
- Persist `caregiver_rosters` and `caregiver_roster_entries`: context, urgency, request fingerprint, evidence time/coverage, prompt version/model/usage, assessments, editable drafts, frozen review token, approver/time, dispatch UUIDs and result IDs.
- Use existing `services.openai` credentials, endpoint, TLS settings and timeout, and the configured AI model. Share the existing Responses transport; provide a roster-specific strict JSON schema and prompt. No new key, environment setup or customer AI activation.
- Use existing `CareRequestInvitationService` for actual invitations and existing CRM `LeadSmsService` for optional texts. Add only a narrowly authorized CS roster entry point to SMS; do not widen general caregiver CRM access.
- Keep managed-request customer-notification suppression intact. Invitation copy remains caregiver-facing; roster reasoning remains staff-only.
- Approval is separate from generation. Server-held preview expires after 15 minutes; drafts older than 24 hours require regeneration. Request material changes invalidate the draft. Recheck operational conflicts before approval and dispatch. Saved availability is a signal, not confirmation of outside commitments.
- Per-entry queue jobs use stable SMS request UUIDs and existing invitation deduplication. A submitted or indeterminate SMS is never automatically resent. Recheck the original approver's current permission before sending.
- Model/provider failures leave a failed draft, never an executable partial roster. Store safe error descriptions, not provider payloads or credentials.
- Generation checkpoints collection, each 12-caregiver assessment batch, and final ranking separately. Each worker step makes at most one provider request with a 40-second timeout, under the 55-second job limit. Retries resume the saved cursor; completed/failed drafts discard the temporary full-pool working packet. The review screen shows batch progress. Existing queue configuration is sufficient.

## Verification and rollout

Feature tests cover admin/CS access and all denied roles; customer invisibility and notification silence; generation with no sends; previously uninvited candidates; notes/SMS provenance and unrelated-record isolation; malformed AI IDs/evidence; exact edited-message approval; tampering and expired/replaced previews; changed requests, revoked staff, suspended caregivers and new conflicts; SMS off/on, opt-outs, pending invite follow-up, repeated approval and job retries; provider failure and uncertain SMS status.

Run focused existing customer-care, invitations and SMS suites, PHP formatting and Blade compilation. Validate the rendered staff workflow locally with synthetic fixtures. No production invitations/SMS during implementation. Deploy via the normal migrations, asset build and queue-worker restart. This feature needs no new secret or environment variable.

### Implementation verification — 28 November 2026

- 101 PHP tests passed (554 assertions), including 20 roster tests and existing customer-care, invitation, SMS and AI-support regression coverage.
- Two Playwright tests passed: desktop and mobile, from CS sign-in through generation, editing, exact preview, approval and recorded dispatch. Both use an isolated SQLite database and fake OpenAI, SMS and notification providers.
- Production asset build, PHP formatting and whitespace checks passed. Desktop and mobile roster/approval screenshots were inspected.
- The existing pricing KB test `test_published_pricing_kb_uses_exact_family_and_caregiver_math_without_provider_calls` fails governed validation for `KB-B4-PRICE-001`. The same failure was reproduced with the original OpenAI client from HEAD; it was excluded from the 101-test passing run.
- A fictional-data smoke request using the existing OpenAI configuration could not connect from this machine (cURL transport error 7). Live provider behavior remains unverified; mocked provider tests passed. No credentials were changed or exposed.
- Implementation is local and has not been deployed. No production invitation or SMS was sent.
