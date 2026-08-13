# Capability Registry

Status: Draft registry

Last updated: August 13, 2026

Owner: Product

## How to use this registry

This is the portfolio view, not a substitute for full capability specifications. Every capability promoted beyond **Candidate** needs a document created from the [capability template](../templates/capability-spec-template.md).

No status in this registry proves production deployment. The human support chat is the only existing platform baseline for this program. Legacy AI copilot behavior does not confer implementation or release status on any new capability.

## Platform capabilities

| ID | Outcome | Users | Class | State | Target phase | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `SUP-HUMAN-001` | Start or continue human support in the persistent chat | Family, caregiver | Existing human workflow | Existing baseline | Phase 0 | Canonical support-ticket conversation; verify production state |
| [`SUP-HANDOFF-001`](../capabilities/SUP-HANDOFF-001.md) | Transfer an automated conversation to a human without repetition | Role follows released support cohort | A/platform control | Foundation implemented; release disabled | Phase 1-2 | Deterministic atomic transfer, preview invalidation, human-only suppression, deliberate admin return, compact evidence; no automated queue-status replies |
| [`ADM-AI-PILOT-001`](../11-admin-control-plane-and-pilot.md) | Enable or revoke AI pilot access for one exact user | Authorized administrators | Platform control | Accepted requirements; [UX draft](../12-admin-ux-specification.md) | Phase 1 | Deny-by-default, server-enforced, audited, no inherited or percentage grants during pilot |
| [`ADM-KB-001`](../11-admin-control-plane-and-pilot.md) | List, create, draft, edit, review, publish, pause, supersede, and safely delete KB entries | Authorized administrators | Platform control | Accepted requirements; [UX draft](../12-admin-ux-specification.md) | Phase 1 | Drafts excluded from retrieval; released deletions withdraw and tombstone |
| `SUP-ANSWER-001` | Answer approved product questions from the governed KB | Family initially | A | Candidate | Phase 3 | No general-knowledge product answers |
| `SUP-CONTEXT-001` | Explain the current page or status using authorized context | Family initially | A | Candidate | Phase 3 | Requires exact state source and KB applicability |
| `NAV-SEMANTIC-001` | Navigate to an approved route and semantic target | Family initially | B | Candidate | Phase 4 | No raw DOM selectors or coordinate clicking |

## Initial family navigation candidates

| ID | Outcome | Class | State | Target phase | Approved destination scope |
| --- | --- | --- | --- | --- | --- |
| `NAV-FAMILY-001` | Open the family dashboard | B | Candidate | Phase 4 | `dashboard` after role-aware resolution |
| `NAV-REQUEST-001` | Open the user's care-request list or an authorized request | B | Candidate | Phase 4 | `family.requests.index` / authorized `family.requests.show` |
| `NAV-CARE-001` | Open regular care or care history | B | Candidate | Phase 4 | `family.care.index` / `family.care.history` |
| `NAV-MESSAGE-001` | Open messages or an authorized conversation | B | Candidate | Phase 4 | `messages.index` / authorized `messages.show` |
| `NAV-SUPPORT-001` | Open the Support Center or current support ticket | B | Candidate | Phase 4 | `support.index` / authorized ticket |
| `NAV-BILLING-001` | Open family billing without changing payment details | B | Candidate | Later review | Owner/member presentation differs; no payment data in chat |

## Care-request capabilities

| ID | Outcome | Users | Class | State | Target phase | Legacy relationship |
| --- | --- | --- | --- | --- | --- | --- |
| [`CARE-REQUEST-001`](../capabilities/CARE-REQUEST-001.md) | Collect and prepare a one-time non-medical care-request draft | Active family owner/member | C | Draft specification | Phase 5 | New implementation; legacy copilot will be removed |
| `CARE-REQUEST-002` | Validate and show a complete one-time request preview | Active family owner/member | C | Candidate | Phase 5 | Existing UI shows editable draft but lacks target preview contract |
| [`CARE-REQUEST-003`](../capabilities/CARE-REQUEST-003.md) | Publish a confirmed one-time care request | Active family owner/member | D | Draft specification | Phase 6 | New implementation; legacy copilot publication path will be removed |
| `CARE-REQUEST-004` | Explain an authorized care request's status and next action | Active family owner/member | A | Candidate | Phase 3 or 7 | Use authoritative request/application state |
| `CARE-REBOOK-001` | Prepare a one-time booking-again draft using prior care context | Active family owner/member | C | Candidate | Phase 7 | Reuse caregiver, recipient, address, tasks; ask only schedule |
| `CARE-REGULAR-001` | Prepare a regular-care offer without committing financial effects | Active family owner/member | C | Candidate | Phase 7 | Separate capability and domain spec required |

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
| `PAYMENT-METHOD-001` | Add, replace, or remove a payment method | E | Restricted | Owner-only navigation to structured billing flow; no card collection in chat |
| `PAYMENT-DISPUTE-001` | Resolve a billing dispute, refund, or charge correction | E | Restricted | Human escalation or approved structured Support Center form |
| `VISIT-APPROVE-001` | Approve hours and trigger payment capture | E | Restricted | Explain/open normal flow; no agent execution |
| `CARE-HIRE-001` | Hire a caregiver and authorize expected payment | E | Restricted | Explain/open normal flow; no agent execution |
| `CARE-CANCEL-001` | Cancel booked or regular care | E | Restricted | Explain policy/open structured flow or human support |
| `FAMILY-ACCESS-001` | Invite, remove, or transfer family access | E | Restricted | Owner-only structured flow; ownership transfer remains support-assisted |
| `MEDICAL-ADVICE-001` | Provide medical advice or arrange medical procedures | E | Prohibited | State non-medical scope and escalate appropriately |
| `EMERGENCY-001` | Handle immediate-danger language | E | Mandatory escalation | Show emergency limitation/instruction and priority human escalation |

## Capability dependencies

`CARE-REQUEST-003` cannot progress beyond offline evaluation until all of the following are approved and passing:

- `SUP-HANDOFF-001`
- Authorized context contract
- Agent event model
- One-time request draft and preview contracts
- Confirmation binding
- Publication idempotency
- Critical safety and authorization corpus

## Promotion notes

- Begin user-visible release with a small set of Class A family/care-receiver topics.
- Introduce Class B only after semantic targets and route contracts exist.
- Do not reuse or integrate the legacy AI request copilot. Implement `CARE-REQUEST-001` and `CARE-REQUEST-003` only from their approved new specifications.
- Release caregiver approved answers and navigation after the family/care-receiver track passes its gates.
- Add caregiver operational capabilities individually and only with caregiver-specific sources, states, permissions, and evaluations.
