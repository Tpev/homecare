# Capability Specification: `CARE-REQUEST-005` — Prepare a One-Time or Recurring Request Draft

Status: Implemented and evaluated; release disabled

Version: 1.0

Owner: Family care product

Required release reviewers: Product, engineering, design/accessibility, security/privacy, support operations

Last reviewed: August 14, 2026

Implementation evidence: [Interactive assistant implementation and release evidence](../24-interactive-assistant-implementation-and-release-evidence.md)

## 1. User outcome

After explicitly selecting a path, an authorized Family user can describe a one-time or regular/recurring non-medical need and receive a private, resumable, validated structured draft without completing the ordinary form alone.

Saving a draft never publishes, notifies a Caregiver, authorizes payment, or implies availability.

## 2. Preconditions

- `CARE-INTAKE-001` recorded explicit `one_time` or `recurring` selection
- Active authorized Family actor and exact pilot grant
- Automated conversation ownership
- Draft capability and relevant request-type sub-control enabled
- Approved CareTask taxonomy and domain validation available

## 3. Required information

Common:

- Care recipient: requester or authorized profile
- At least one approved non-medical CareTask
- Service address
- Explicit request type

One-time:

- Future date
- Start time
- Duration from one through twelve hours in 30-minute increments

Recurring:

- One or more weekdays
- Start time and duration for every selected weekday
- First service date
- Ongoing or explicit end date

Use the Family Account's saved Caregiver response preference; otherwise default to 12 hours. Generate title and scope deterministically from confirmed content. Do not expose Fast Track versus Complete Setup.

Optional recipient, mobility, task notes, access, additional instructions, and third-party contact fields are requested only when relevant or volunteered.

## 4. Conversation behavior

- Capture all usable details already provided.
- Ask one missing material question at a time.
- Do not repeat answered questions.
- Resolve dates explicitly and show Eastern Time.
- Clarify vague time, duration, recipient, task, address, or schedule.
- Let the user change fields in ordinary English.
- After two misunderstandings of the same field, offer a person.
- Keep **Use the regular form** and **Talk to a person** available.

## 5. Draft record

Bind the draft to actor, Family Account, support conversation, request type, capability/schema version, and retention policy. For every normalized field store:

- Value
- Provenance: user, authorized profile, previous request, deterministic default, or model extraction
- Confirmation/review state
- Updated timestamp
- Draft version

Use optimistic concurrency. A stale tab/device cannot overwrite newer data. Detect another apparent request and present **Resume saved request** or **Discard and start over**; never merge silently.

## 6. Autosave, resume, and deletion

- Autosave after each field passes field-level validation.
- Remain private and marketplace-invisible.
- Resume for seven calendar days after the last valid update.
- **Discard this draft** deletes immediately and invalidates related recaps/confirmations.
- Automatic deletion runs after seven inactive days, subject only to a narrow approved hold.
- Logout preserves the draft but invalidates confirmation; current authorization is required on resume.
- Membership/access loss blocks access immediately.

## 7. Deterministic validation

Server validation owns actor/membership, record scope, task IDs, address/geography, date/time/timezone, duration, recurring schedule, text lengths, request type, and current supported-domain rules. The model cannot mark a draft ready.

Invalid or ambiguous text may remain as uncommitted conversational context but never as a publishable normalized value.

## 8. State and outputs

States:

- `collecting`
- `needs_clarification`
- `validating`
- `ready_for_recap`
- `discarded`
- `expired`
- `transferred`

When ready, state only that the private draft is ready to review and invoke `CARE-REQUEST-006`. Do not claim the request exists.

## 9. Events and metrics

Record minimized draft started/updated/resumed/discarded/expired/conflict, field provenance category, validation reason codes, ready state, fallback, and transfer. Metrics include completion, turns, correction, abandonment, resume, concurrency conflict, validation failure, latency, tokens, and cost.

## 10. Evaluation and release

Cover all-details-at-once, incremental detail, one/multiple saved recipients and addresses, same-as-last-time, one-time and different-per-day recurring schedules, DST/near-midnight, vague time, changed type, concurrent tabs/members, refresh/logout/resume/expiry/discard, medical/emergency/24/7, unauthorized IDs, and outages.

Gates:

- At least 98% first-pass material-field extraction
- 100% required-field completeness before recap
- 100% authorization/isolation and seven-day lifecycle cases
- Zero publication or Caregiver notification
- Older-adult and accessibility gates under `DEC-064`

Pilot one-time drafting before recurring commit authority, even though both draft structures may be built together.
