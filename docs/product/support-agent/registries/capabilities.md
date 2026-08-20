# Capability Registry

Status: Current implementation registry; availability controlled by Pilot/Everyone/Emergency stop

Last updated: August 20, 2026

Owner: Product

## How to use this registry

This is the portfolio view, not a substitute for full capability specifications. Every capability promoted beyond **Candidate** needs a document created from the [capability template](../templates/capability-spec-template.md).

The exhaustive Family-user demand side is tracked separately in the [Family intent and AI action coverage registry](../38-family-intent-action-coverage-registry.md). That registry distinguishes product support, AI explanation, navigation/read/draft assistance, and complete AI execution.

No status in this registry proves production deployment. The human support chat is the only existing platform baseline for this program. Legacy AI copilot behavior does not confer implementation or release status on any new capability.

## Platform capabilities

| ID | Outcome | Users | Class | State | Target phase | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `SUP-HUMAN-001` | Start or continue human support in the persistent chat | Family, caregiver | Existing human workflow | Existing baseline | Phase 0 | Canonical support-ticket conversation; verify production state |
| [`SUP-HANDOFF-001`](../capabilities/SUP-HANDOFF-001.md) | Transfer an automated conversation to a human without repetition | Role follows released support cohort | A/platform control | Foundation implemented; release disabled | Phase 1-2 | Deterministic atomic transfer, preview invalidation, human-only suppression, deliberate admin return, compact evidence; no automated queue-status replies |
| [`ADM-AI-PILOT-001`](../11-admin-control-plane-and-pilot.md) | Enable or revoke AI pilot access for one exact user | Authorized administrators | Platform control | Implemented; release disabled | Phase 1 | Deny-by-default, server-enforced, audited, no inherited or percentage grants during pilot |
| [`ADM-KB-001`](../11-admin-control-plane-and-pilot.md) | List, create, draft, edit, review, publish, pause, supersede, and safely delete KB entries | Authorized administrators | Platform control | Implemented; production lifecycle verified | Phase 1 | Single-Administrator lifecycle; domain packages publish independently of Availability |
| `SUP-ANSWER-001` | Answer approved product questions from the governed KB | Family, caregiver | A | Implemented; release disabled | Phase 3 | No general-knowledge product answers; role-scoped retrieval only |
| `SUP-CONTEXT-001` | Explain the current page or status using authorized context | Family, caregiver | A | Implemented; release disabled | Phase 3 | Requires exact state source and KB applicability |
| `NAV-SEMANTIC-001` | Navigate to an approved route and semantic target | Family, caregiver | B | Implemented; release disabled | Phase 4 | Registered routes only; no raw DOM selectors or coordinate clicking |
| `FAM-STATE-001` | Read a normalized Family overview and targeted current states from authorized domain readers | Family | B/read | Batches 2, 4, and 5 implemented; mass regression passing | Guided assistance | Safe payment, request/applicant, visit/hours, profile lifecycle/readiness, message, history, and regular-care state; no raw database/model access |
| `NAV-GUIDE-001` | Navigate to, focus, and accessibly highlight one registered UI control | Family first; caregiver later | B/guide | Batch 2 implemented; deterministic target regression passing | Guided assistance Batch 1-2 | Exact account-authorized request, plan, profile, conversation, billing, Care, Messages, and History targets; no arbitrary selectors, coordinates, or autonomous clicking |
| `TASK-VERIFY-001` | Verify guided-task completion against authoritative server state and continue the chat | Family first; caregiver later | B/read | Payment, request, and profile verification implemented | Guided assistance | Domain receipt or fresh authorized read required; abandoned, failed, stale, and unverifiable results remain recoverable and never claim success |
| `FORM-PREFILL-001` | Prefill allowlisted non-secret reversible form fields through a server draft | Family first; caregiver later | C | Batch 3 implemented; Batch 5 profile/request promotion implemented | Guided assistance | All values visible/editable; profile and request lifecycle may use a separately approved confirmed tool; other preparation families do not save or send |
| [`FAM-GOAL-JOURNEY-001`](../54-family-goal-guided-journeys.md) | Retain and truthfully complete one authorized Family goal across chat, refresh, navigation, secure UI detours, and human transfer | Exact-pilot authenticated Family users | B/C/orchestration | Batch 10 source complete; not deployed or production-audited | Guided assistance Batch 10 | Ten versioned goals compose existing readers/actions; encrypted seven-day continuation, explicit care choice, one active goal, no generic tool, no Caregiver scope; [implementation record](../56-family-goal-guided-journeys-batch-10-implementation-record.md) |
| [`FAM-PROFILE-LIFECYCLE-001`](../49-care-profiles-request-lifecycle-batch-5.md) | Create, update, make ready/default, archive, or restore an authorized care profile through recap, confirmation, and verification | Active Family member | D | Production-verified for the exact two-user pilot | Guided assistance Batch 5 | Full create/edit/readiness/default/archive/restore journey and cleanup passed; permanent deletion and current-care impact review transfer to a person; exact revision and account are revalidated |
| [`FAM-REQUEST-LIFECYCLE-001`](../49-care-profiles-request-lifecycle-batch-5.md) | Read, reuse, copy, replace, recover, or withdraw an authorized care request | Active Family member | B/C/D by operation | Production-verified for the exact two-user pilot | Guided assistance Batch 5 | Full read/publish/withdraw/copy/republish/source-preservation journey passed; never reopen expired/withdrawn in place; live changes use a replacement; booked care stays in visit workflow |

## Initial family navigation candidates

| ID | Outcome | Class | State | Target phase | Approved destination scope |
| --- | --- | --- | --- | --- | --- |
| `NAV-FAMILY-001` | Open the family dashboard | B | Candidate | Phase 4 | `dashboard` after role-aware resolution |
| `NAV-REQUEST-001` | Open the user's care-request list or an authorized request | B | Implemented in source | Guided assistance Batch 2 | Exact list, overview, applicants, visit, visit-issue, timesheet, and payment-attention targets |
| `NAV-CARE-001` | Open regular care or care history | B | Implemented in source | Guided assistance Batch 2 | Authorized regular-care attention and Care-history targets |
| `NAV-MESSAGE-001` | Open messages or an authorized conversation | B | Implemented in source | Guided assistance Batch 2 | Account-scoped inbox and exact conversation target |
| `NAV-SUPPORT-001` | Open the Support Center or current support ticket | B | Candidate | Phase 4 | `support.index` / authorized ticket |
| `NAV-BILLING-001` | Open Family billing and guide an active Family member to the secure payment-method control without changing payment details | B/guide | Payment-method slice implemented | Guided assistance Batch 1 | Active-member exact target; safe card summary may appear in chat but no full card data, token, customer ID, or credential |

The implementation contract for these platform capabilities is [App-aware guided assistance](../39-app-aware-guided-assistance.md).

## Care-request capabilities

| ID | Outcome | Users | Class | State | Target phase | Legacy relationship |
| --- | --- | --- | --- | --- | --- | --- |
| [`CARE-REQUEST-001`](../capabilities/CARE-REQUEST-001.md) | Collect and prepare a one-time non-medical care-request draft | Active family owner/member | C | Superseded by `CARE-REQUEST-005` | Historical | Retained only for traceability; never reuse legacy copilot |
| `CARE-REQUEST-002` | Validate and show a complete one-time request preview | Active family owner/member | C | Superseded by `CARE-REQUEST-006` | Historical | Original candidate replaced by expanded deterministic recap |
| [`CARE-REQUEST-003`](../capabilities/CARE-REQUEST-003.md) | Publish a confirmed one-time care request | Active family owner/member | D | Superseded by `CARE-REQUEST-007` | Historical | Retained only for traceability; never reuse legacy copilot |
| `CARE-REQUEST-004` | Explain an authorized care request's status and next action | Active family owner/member | A/B read-guide | Expanded in Batch 5 source | Guided assistance Batch 2 and 5 | Exact lifecycle, blockers, applicant count, visit, hours, and care-payment target selection |
| `CARE-REBOOK-001` | Prepare a one-time booking-again draft using prior care context | Active family owner/member | C | Candidate | Phase 7 | Reuse caregiver, recipient, address, tasks; ask only schedule |
| `CARE-REGULAR-001` | Prepare a regular-care offer without committing financial effects | Active family owner/member | C | Superseded by `CARE-REQUEST-005` | Historical | Recurring request drafting is now in the shared approved flow |
| [`CARE-INTAKE-001`](../capabilities/CARE-INTAKE-001.md) | Recommend one-time, recurring, or human-assisted 24/7 care and obtain the user's explicit path choice | Active family owner/member | A/B | Implemented and evaluated; release disabled | Expanded Family request phase | No clinical recommendation; emergency override remains separate |
| [`CARE-CONTEXT-001`](../capabilities/CARE-CONTEXT-001.md) | Retrieve approved Family profiles, addresses, request/visit history, preferences, and account context for the current task | Active family owner/member | B/read | Implemented and evaluated; release disabled | Expanded Family request phase | Server-authorized, purpose-limited, and audited; no secrets or cross-role/account data |
| [`CARE-REQUEST-005`](../capabilities/CARE-REQUEST-005.md) | Prepare a progressive one-time or recurring request draft from conversation and authorized context | Active family owner/member | C | Implemented and evaluated; release disabled | Expanded Family request phase | One internal flow; minimum fields under `DEC-051`; private seven-day autosave/resume; no publication before confirmation |
| [`CARE-REQUEST-006`](../capabilities/CARE-REQUEST-006.md) | Validate and show the deterministic recap for a one-time or recurring request | Active family owner/member | C | Implemented and evaluated; release disabled | Expanded Family request phase | Material fields, price only after reconciliation, outcome, and modification controls |
| [`CARE-REQUEST-007`](../capabilities/CARE-REQUEST-007.md) | Publish a confirmed one-time or recurring request directly to the marketplace | Active family owner/member | D | Implemented and evaluated; release disabled | Expanded Family request phase | Action-specific confirmation, revalidation, idempotency, and authoritative receipt |
| [`CARE-24H-001`](../capabilities/CARE-24H-001.md) | Recognize 24/7 coverage needs and transfer an intake summary to human support | Active family owner/member | A/platform control | Implemented and evaluated; release disabled | Expanded Family request phase | No AI-created request; immediate danger uses emergency behavior first |

## Caregiver capability candidates

These are required program areas but remain candidates until individually specified and approved. Under `DEC-009`, caregiver approved answers and semantic navigation follow the initial family/care-receiver release. Caregiver operational capabilities follow individually; approval of one does not approve another.

| ID | Outcome | Class | State | Initial boundary |
| --- | --- | --- | --- | --- |
| `CG-SUP-ANSWER-001` | Answer approved caregiver product and work questions | A | Candidate | Caregiver-only KB and state applicability |
| `CG-NAV-WORK-001` | Open caregiver work inbox, invitations, shifts, messages, or earnings | B | Candidate | Registered caregiver routes and authorized records only |
| `CG-NAV-PROFILE-001` | Open the correct onboarding, profile, task, insurance, video, verification, or payout setup step | B | Candidate | Explain current status from authoritative services |
| `CG-APPLICATION-001` | Prepare an application to an eligible care request | C | Candidate | No submission until a separate Class D capability is approved |
| `CG-INVITATION-001` | Explain and navigate an invitation response | A/B | Candidate | Accept/decline remains a separately confirmed action |
| `CG-SHIFT-001` | Explain the caregiver's next scheduled shift and open it | A/B | Candidate | No check-in/out execution initially |
| `CG-HOURS-001` | Explain submitted-hours state and open the normal workflow | A/B | Candidate | No hours submission or correction execution initially |
| `CG-EARNINGS-001` | Explain authoritative earnings/payout status and open payout setup | A/B | Candidate | No bank/payment credential collection in chat |

## Initially restricted capabilities

These remain explanation/navigation/handoff only until an approved capability changes the class.

| ID | Outcome | Class | State | Initial behavior |
| --- | --- | --- | --- | --- |
| `PAYMENT-METHOD-001` | Add, replace, or remove a payment method | E | Add/replace guide implemented; AI execution remains restricted | Owner uses the existing secure Stripe flow while the assistant guides and verifies; remove remains unsupported; no card collection or AI mutation |
| `PAYMENT-DISPUTE-001` | Resolve a billing dispute, refund, or charge correction | E | Restricted | Human escalation or approved structured Support Center form |
| `VISIT-APPROVE-001` | Approve hours and trigger payment capture | E | Restricted | Explain/open normal flow; no agent execution |
| `CARE-HIRE-001` | Hire a caregiver and authorize expected payment | E | Restricted | Explain/open normal flow; no agent execution |
| `CARE-CANCEL-001` | Cancel booked or regular care | E | Restricted | Explain policy/open structured flow or human support |
| `FAMILY-ACCESS-001` | Invite, remove, or transfer family access | E | Restricted | Owner-only structured flow; ownership transfer remains support-assisted |
| `MEDICAL-ADVICE-001` | Provide medical advice or arrange medical procedures | E | Prohibited | State non-medical scope and escalate appropriately |
| `EMERGENCY-001` | Handle immediate-danger language | E | Mandatory escalation | Show emergency limitation/instruction and priority human escalation |

## Capability dependencies

`CARE-REQUEST-007` cannot progress to exact-user publication until all of the following are implemented and passing:

- `SUP-HANDOFF-001`
- `CARE-INTAKE-001`, `CARE-CONTEXT-001`, `CARE-REQUEST-005`, and `CARE-REQUEST-006`
- Agent event model
- One-time request-type commit control; recurring commit remains off until the one-time gate passes
- Confirmation binding
- Publication idempotency
- Critical safety and authorization corpus

## Promotion notes

- Begin user-visible release with a small set of Class A family/care-receiver topics.
- Introduce Class B only after semantic targets and route contracts exist.
- Do not reuse or integrate the legacy AI request copilot. `CARE-REQUEST-001` through `CARE-REQUEST-003` are historical; current implementation authority is `CARE-INTAKE-001`, `CARE-CONTEXT-001`, `CARE-REQUEST-005` through `CARE-REQUEST-007`, and `CARE-24H-001`.
- Release caregiver approved answers and navigation after the family/care-receiver track passes its gates.
- Add caregiver operational capabilities individually and only with caregiver-specific sources, states, permissions, and evaluations.
