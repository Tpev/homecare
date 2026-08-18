# Family Intent and AI Action Coverage Registry

Status: Active coverage registry; executable Batch 3 catalog generated and validated from all 324 rows

Established: August 17, 2026

Owner: Product

Scope: Signed-in Family users, including Family Account owners and Family members

## Purpose

This registry answers two separate questions for every likely Family-user intent:

1. **Can the AI explain it accurately now?**
2. **Can the AI do it for the user now?**

It is deliberately broader than the current AI feature. It inventories the actual Family-facing application, then adds probable user needs and failure cases that may not yet have a complete product flow. A row marked as a product gap must not be mistaken for an AI implementation task until Product defines the underlying app behavior.

Baseline source: production-oriented repository state audited August 18, 2026, the governed KB inventory including Family Operations Wave 1 and the Batch 4 payment/time package, and the Batch 3 operating layer. Payment-method rows include Batch 1; request, visit, hours, profile, message, history, care-payment, and Family-overview rows include Batch 2; every row has an executable disposition; five reversible preparation families are implemented in Batch 3; and pricing/payment/submitted-hours knowledge plus normalized reads are implemented in Batch 4 source. Production availability remains controlled separately. Re-audit this registry whenever a Family workflow, AI tool, governed KB entry, or authorization rule changes.

The phased implementation and testing sequence for moving this portfolio from explanation/navigation to verified task completion is defined in the [Family chat operator master coverage and delivery plan](44-family-chat-operator-master-plan.md).

## Automated coverage of implemented rows

The registry is the human-readable source for the generated 324-intent executable catalog. The [Family Batch 1–4 evaluation harness](40-family-batch-1-2-evaluation-harness.md) validates **324 / 324** catalog records, **197 / 197** explicit KB mappings, and **1,296** phrase definitions. Its deep runtime regression covers the exact **40** read/guide rows implemented in Batches 1 and 2, the reusable Batch 3 task, verifier, preparation, start-card, security, and Admin contracts, and the Batch 4 payment/time reader, knowledge, pricing, and recovery paths.

The August 18, 2026 Batch 4 source baseline validates 324 of 324 catalog records, 197 of 197 KB mappings, 45 of 45 deep runtime intents, 137 of 137 representative phrases, and 10 of 10 collision cases. The isolated mass harness passes 64 application tests with 2,107 assertions; the complete AI Support suite passes 156 tests with 2,916 assertions; and 100 wider payment/time/regular-care tests pass with 623 assertions. The Batch 4 package adds 18 governed entries, 90 linked KB evaluations, exact pricing calculations for both roles, safe normalized payment reasons, and submitted-hours differences with zero provider calls on deterministic paths. Catalog validation is not a claim that all 324 intents can execute a domain action: each record's current stages and rollout state remain authoritative. Guide and preparation coverage remain distinct from completion.

## Status legend

### Product today

- **UI** — the app has a Family-facing workflow for this action.
- **Partial UI** — some of the need is supported, but the exact action or recovery path is incomplete.
- **Human** — LoLo Support or an administrator must handle it.
- **Gap** — no clear supported product workflow was found.
- **Restricted** — the requested information or operation must not be exposed through the Family product.

Qualifiers such as `owner only`, `flagged`, or `held truth` narrow the base status; they do not create AI authority.

### AI can explain now

- **Yes** — a published governed KB entry and/or deterministic rule directly covers the intent.
- **Partial** — the AI can explain a narrow general rule, but not the full workflow or the user's current live state.
- **No** — no sufficient governed knowledge or authorized live-state source exists.
- **Human** — the safe current response is transfer, not an automated answer.

### AI can do now

- **Yes** — the AI completes the action through a registered deterministic tool after required confirmation.
- **Draft** — the AI can collect, save, modify, recap, and prepare the action, but commit still follows the declared confirmation rule.
- **Navigate** — the AI can open a registered destination but cannot perform the action.
- **Guide** — the AI can open the exact destination, focus/highlight the registered control, and verify the resulting application state; the user still performs the domain action in the normal UI.
- **Read** — the AI can use narrowly authorized live data, without mutation.
- **Transfer** — the AI can move the same conversation to human support.
- **No** — the AI has no registered capability or tool for the action.

`Navigate`, `Guide`, `Read`, `Draft`, and `Transfer` are intentionally not counted as `Yes` for a complete action.

## Baseline coverage snapshot

This registry contains **324 unique Family intents**.

| Coverage dimension | Yes | Partial / assisted | No | Transfer |
| --- | ---: | ---: | ---: | ---: |
| AI can explain now | 86 | 62 | 176 | 0 |
| AI can do now | 6 complete | 100 draft/navigation/guide/partial-read | 176 | 42 |

The counts are intent-level inventory indicators, not quality scores. One implementation may cover several rows, and one row may require several KB entries, read contracts, tools, and failure tests. Update the counts when row statuses change.

Batch 3 adds an executable operational view without rewriting these baseline inventory counts. The generated catalog records the current stage, target stage, rollout state, explicit KB mapping, narrow contract references, unsupported behavior, and evaluation IDs for every row. The application rejects a stale generated catalog by comparing this file's SHA-256 hash.

## Recommended target behavior vocabulary

- **Explain** — add governed, role-applicable knowledge.
- **Navigate** — add a registered, authorization-aware destination.
- **Guide** — navigate to an exact semantic control, highlight it accessibly, observe the product event, and verify the result against authoritative state.
- **Read state** — retrieve the current user's authoritative state and explain it without guessing.
- **Prepare** — collect or prefill data, show a recap, and let the user edit it.
- **Confirm & execute** — perform a deterministic mutation only after an explicit recap and confirmation.
- **Human only** — retain human handling because judgment, external coordination, or exceptional authority is required.
- **Never in chat** — do not collect or expose secrets such as full card data or passwords; use the secure structured UI.

## 1. Orientation, getting started, and care-path selection

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-START-001 | Understand what LoLo does for Families | UI | Partial | No | Explain the bounded marketplace and support model |
| FAM-START-002 | Understand what non-medical care means | UI | Yes | No | Keep governed explanation; transfer medical questions |
| FAM-START-003 | Understand what the Family dashboard shows | UI | Yes | Navigate | Keep covered |
| FAM-START-004 | Open the Family dashboard | UI | Yes | Navigate | Keep covered |
| FAM-START-005 | Understand the difference between one-time, regular, and 24/7 care | UI | Yes | Draft / Transfer | Keep one-time/regular drafting and 24/7 transfer |
| FAM-START-006 | Decide whether the need is one visit or recurring weekly care | UI | Yes | Draft | Keep AI recommendation plus explicit path selection |
| FAM-START-007 | Explain an ambiguous need and ask the minimum clarifying question | UI | Yes | Draft | Keep covered |
| FAM-START-008 | Start one-time care | UI | Yes | Draft | Keep draft, recap, and confirmed publication |
| FAM-START-009 | Start regular/recurring care | UI | Yes | Draft | Keep draft, recap, and confirmed publication |
| FAM-START-010 | Ask for continuous day-and-night or 24/7 coverage | UI / Human | Yes | Transfer | Keep immediate human transfer without queue promise |
| FAM-START-011 | Understand whether 24/7 care is automatically an emergency | Human | Yes | Transfer | Keep governed distinction and transfer |
| FAM-START-012 | Ask for medical, clinical, medication, or treatment help | Human | Yes | Transfer | Keep non-medical boundary and human transfer |
| FAM-START-013 | Report immediate danger or urgent medical need | Human | Yes | Transfer | Keep 911-first deterministic response, then transfer |
| FAM-START-014 | Ask to use the assistant in another language | Human | Yes | Transfer | English-only explanation; no translation claim |
| FAM-START-015 | Ask to speak to a person | Human | Yes | Transfer | Keep covered in the same conversation |
| FAM-START-016 | Understand what the AI can and cannot do | Partial UI | Partial | No | Publish a concise capability/help entry generated from this registry |
| FAM-START-017 | Ask what currently needs attention across the Family Account | UI | Yes | Read / Guide | Deterministically check supported payment-method, care-payment, request, applicant, visit-change, submitted-hours, profile, and unread-message states; show up to six exact actions |

## 2. Login, identity, personal account, and security

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-ACCOUNT-001 | Create a Family login | UI | No | No | Explain and navigate to registration when signed out |
| FAM-ACCOUNT-002 | Sign in | UI | No | No | Explain and navigate; never request password in chat |
| FAM-ACCOUNT-003 | Sign out | UI | No | No | Navigate or provide deterministic sign-out action |
| FAM-ACCOUNT-004 | Recover a forgotten password | UI | No | No | Explain and navigate to secure reset flow |
| FAM-ACCOUNT-005 | Understand why a reset link failed or expired | Partial UI | No | No | Explain common states and offer a new secure reset link |
| FAM-ACCOUNT-006 | Change password while signed in | UI | Yes | Navigate | Keep navigation; never collect passwords in chat |
| FAM-ACCOUNT-007 | Change personal name | UI | Yes | Navigate | Add confirmed profile-edit tool only if needed |
| FAM-ACCOUNT-008 | Change email address | UI | Yes | Navigate | Prefer secure structured UI; explain re-verification |
| FAM-ACCOUNT-009 | Understand why email verification is required | UI | No | No | Add governed explanation |
| FAM-ACCOUNT-010 | Resend verification email | UI | No | No | Navigate or deterministic resend after confirmation |
| FAM-ACCOUNT-011 | Fix an email-verification link problem | Partial UI | No | No | Explain recovery and transfer persistent failures |
| FAM-ACCOUNT-012 | Open Account Settings | UI | Yes | Navigate | Keep covered |
| FAM-ACCOUNT-013 | Understand the difference between personal settings and Family Account settings | UI | Partial | Navigate | Expand KB with owner/member distinction |
| FAM-ACCOUNT-014 | Delete personal account | UI | No | No | Explain consequences; secure UI confirmation; human for owner conflicts |
| FAM-ACCOUNT-015 | Understand what happens to care, bookings, payments, and Family access after account deletion | Partial UI | No | No | Define product behavior before AI coverage |
| FAM-ACCOUNT-016 | Report suspected account takeover or unauthorized access | Human | No | Transfer | Human only; give immediate account-security steps |
| FAM-ACCOUNT-017 | Change phone number | Gap | No | No | Define whether phone is an account field before AI work |
| FAM-ACCOUNT-018 | Enable or manage multi-factor authentication | Gap | No | No | Product gap; never claim availability |
| FAM-ACCOUNT-019 | Download or export personal data | Gap / Human | No | No | Define privacy-request workflow; likely prepare then human |
| FAM-ACCOUNT-020 | Correct personal data the user cannot edit | Human | No | Transfer | Human only with identity verification as needed |

## 3. Family Account access, ownership, and collaboration

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-ACCESS-001 | Understand Account owner versus Family member permissions | UI | Yes | Navigate | Keep covered |
| FAM-ACCESS-002 | Open Family access | UI | Yes | Navigate | Keep covered |
| FAM-ACCESS-003 | See current Family members | UI | Partial | Navigate | Add authorized membership read-state |
| FAM-ACCESS-004 | Invite a Family member by email | UI, owner only | Partial | Navigate | Prepare email + recap; confirm & execute for owner |
| FAM-ACCESS-005 | Understand what an invited member will be able to see and do | UI | Yes | Navigate | Keep covered; include payment-use disclosure |
| FAM-ACCESS-006 | Resend an invitation | UI, owner only | Partial | Navigate | Read state then confirm & execute |
| FAM-ACCESS-007 | Replace an expired invitation | UI, owner only | Partial | Navigate | Read expiry then confirm & execute |
| FAM-ACCESS-008 | Cancel a pending invitation | UI, owner only | Partial | Navigate | Read state then confirm & execute |
| FAM-ACCESS-009 | Accept a Family invitation | UI | Partial | No | Explain and navigate to the token-bound review flow |
| FAM-ACCESS-010 | Sign in with the correct account to accept an invitation | UI | Partial | No | Explain without exposing invitation details |
| FAM-ACCESS-011 | Decline or defer joining a Family Account | UI | No | No | Explain and navigate |
| FAM-ACCESS-012 | Remove a Family member | UI, owner only | Partial | Navigate | Show impact recap; confirm & execute |
| FAM-ACCESS-013 | Leave a Family Account as a non-owner member | UI | Partial | Navigate | Show loss-of-access recap; confirm & execute |
| FAM-ACCESS-014 | Understand whether a Family member may change the shared saved card | UI | Yes | Read / Guide | Explain that active Family Account members share billing access; read safe card state and guide to the secure control |
| FAM-ACCESS-015 | Understand whose name is recorded for shared-account actions | UI | Yes | No | Keep covered |
| FAM-ACCESS-016 | Transfer Family Account ownership | Gap / Human | Partial | Transfer | Define product workflow; human only until implemented |
| FAM-ACCESS-017 | Close the entire Family Account | Gap / Human | No | Transfer | Human only with impact review |
| FAM-ACCESS-018 | Resolve duplicate Family Accounts or wrong-account membership | Human | No | Transfer | Human only |
| FAM-ACCESS-019 | Understand why Family access ended | UI / Human | Partial | No | Add reason-safe explanation and support route |
| FAM-ACCESS-020 | Restrict one member from specific recipients, payments, or conversations | Gap | No | No | Product authorization model gap |

## 4. Care-receiver profiles and household care context

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-PROFILE-001 | Understand what a care-receiver profile is and who sees it | UI | Partial | No | Add governed visibility and purpose explanation |
| FAM-PROFILE-002 | View care-receiver profiles | UI | Yes | Read / Guide | Read active profile readiness and open the exact profile area |
| FAM-PROFILE-003 | Create a care-receiver profile | UI | Partial | Guide | Open and highlight the profile editor; preparation and saving remain Batch 3-4 work |
| FAM-PROFILE-004 | Save a profile and finish later | UI | Yes | Read / Guide | Explain authoritative draft state and open the first missing required step |
| FAM-PROFILE-005 | Mark a profile ready for care requests | UI | No | No | Validate, recap sharing, confirm & execute |
| FAM-PROFILE-006 | Select who receives care and whether it is the Family user | UI | Partial | Draft | Keep selection inside request draft; add profile-edit support |
| FAM-PROFILE-007 | Edit preferred name, full name, relationship, DOB, or pronouns | UI | No | No | Prepare sensitive changes; explicit recap and confirmation |
| FAM-PROFILE-008 | Edit description, interests, comforts, or good-visit notes | UI | No | No | Prepare and confirm changes |
| FAM-PROFILE-009 | Edit communication preferences and communication notes | UI | No | No | Prepare and confirm changes |
| FAM-PROFILE-010 | Edit everyday health or memory context for non-medical care | UI | No | No | Prepare carefully; medical boundary; confirm changes |
| FAM-PROFILE-011 | Edit mobility information | UI | No | No | Prepare and confirm changes |
| FAM-PROFILE-012 | Edit routine, food, allergies, personal-care, or overnight preferences | UI | No | No | Prepare and confirm changes |
| FAM-PROFILE-013 | Edit safety notes and caregiver-quality preferences | UI | No | No | Prepare and confirm changes; transfer urgent risk |
| FAM-PROFILE-014 | Add or change an additional contact | UI | No | No | Prepare and confirm; prevent cross-account disclosure |
| FAM-PROFILE-015 | Preview what candidate caregivers see before care is confirmed | UI | No | No | Read authoritative sharing preview |
| FAM-PROFILE-016 | Preview what the hired caregiver sees after confirmation | UI | No | No | Read authoritative sharing preview |
| FAM-PROFILE-017 | Apply profile changes to current care | UI | No | No | Show affected care/booking recap; confirm & execute |
| FAM-PROFILE-018 | Save profile changes without changing current care | UI | No | No | Confirm profile-only scope and execute |
| FAM-PROFILE-019 | Choose or change the default care-receiver profile | UI | No | No | Read current default then confirm & execute |
| FAM-PROFILE-020 | Archive a care-receiver profile | UI | No | No | Show use/dependency impact; confirm & execute |
| FAM-PROFILE-021 | Restore an archived care-receiver profile | UI | No | No | Read state then confirm & execute |
| FAM-PROFILE-022 | Use a saved care profile in a new request | UI | Yes | Draft | Keep authorized proposal and visible recap |
| FAM-PROFILE-023 | Change the saved profile proposed by the AI | UI | Yes | Draft | Keep user-controlled modification |
| FAM-PROFILE-024 | Use or change the saved household address and home-access notes | UI | Yes | Draft | Keep authorized proposal and visible recap |
| FAM-PROFILE-025 | Correct a care profile the user no longer has permission to edit | Human | No | Transfer | Human only |
| FAM-PROFILE-026 | Delete a care profile permanently | Gap | No | No | Define retention/dependency behavior before AI work |

## 5. Care-request creation, editing, and publication

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-REQUEST-001 | Open the new care-request flow | UI | Yes | Navigate | Keep covered |
| FAM-REQUEST-002 | Understand what information is required for one-time care | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-003 | Understand what information is required for regular care | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-004 | Choose care for self versus someone else | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-005 | Select an existing care-receiver profile | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-006 | Create a simple care profile while creating a request | UI | Partial | No | Prepare profile plus request; separate confirmations if persisted |
| FAM-REQUEST-007 | Choose one or more non-medical care tasks | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-008 | Add task-specific notes | UI | Partial | Draft | Add clearer governed examples and limits |
| FAM-REQUEST-009 | Describe additional instructions | UI | Partial | Draft | Keep bounded extraction and recap |
| FAM-REQUEST-010 | Choose a one-time date | UI | Yes | Draft | Keep strict future-date validation |
| FAM-REQUEST-011 | Choose a one-time start time | UI | Yes | Draft | Keep Eastern Time disclosure and no guessing |
| FAM-REQUEST-012 | Choose a one-time duration | UI | Yes | Draft | Keep 1–12 hours in 30-minute increments |
| FAM-REQUEST-013 | Choose recurring weekdays | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-014 | Use different times and durations on different weekdays | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-015 | Choose a recurring start date | UI | Yes | Draft | Keep deterministic weekday alignment and disclosure |
| FAM-REQUEST-016 | Choose ongoing care or a recurring end date | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-017 | Enter or reuse the care address | UI | Yes | Draft | Keep authorized household proposal and validation |
| FAM-REQUEST-018 | Change reused address or home-access notes | UI | Yes | Draft | Keep visible modification and recap |
| FAM-REQUEST-019 | Choose how quickly caregivers should respond | UI | Partial | Draft | Add governed explanation of preference versus promise |
| FAM-REQUEST-020 | Reuse the last request | UI | Yes | Partial read / Draft | Require explicit “same as last time”; expand context if needed |
| FAM-REQUEST-021 | Start from saved profiles instead of the last request | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-022 | Understand whether the chat draft is live | UI | Yes | Draft | Keep clear private-draft explanation |
| FAM-REQUEST-023 | Resume a saved chat draft | UI | Yes | Draft | Keep seven-day resume behavior |
| FAM-REQUEST-024 | Discard a saved chat draft | UI | Yes | Yes | Keep explicit discard action |
| FAM-REQUEST-025 | Change one section of the draft | UI | Yes | Draft | Keep “Modify something” and refreshed recap |
| FAM-REQUEST-026 | Review the full request recap | UI | Yes | Draft | Keep deterministic server-rendered recap |
| FAM-REQUEST-027 | Understand why the recap must be reviewed again after a change | UI | Yes | Draft | Keep covered |
| FAM-REQUEST-028 | Confirm and publish a one-time request | UI | Yes | Yes | Keep explicit confirmation and idempotent publication |
| FAM-REQUEST-029 | Confirm and publish a recurring request | UI | Yes | Yes | Keep explicit confirmation and idempotent publication |
| FAM-REQUEST-030 | Renew an expired 30-minute confirmation without retyping | UI | Yes | Yes | Keep one-step fresh recap |
| FAM-REQUEST-031 | Understand what happens after publication | UI | Yes | Navigate | Keep authoritative receipt and View request action |
| FAM-REQUEST-032 | Understand whether publication hired a caregiver | UI | Yes | No | Keep “live is not hired” explanation |
| FAM-REQUEST-033 | Understand whether publication charged or authorized the card | UI | Yes | No | Keep governed no-charge-at-publication explanation |
| FAM-REQUEST-034 | See current request status | UI | Yes | Read / Guide | Read the current lifecycle and open the exact request tab |
| FAM-REQUEST-035 | See whether caregivers viewed or applied | UI | Partial | Read / Guide | Read authoritative applicant counts and guide to replies; never claim caregiver views |
| FAM-REQUEST-036 | Edit a live request's recipient, tasks, date, time, duration, address, or notes | Gap / Partial UI | No | No | Define edit/version/notification behavior; then prepare + confirm |
| FAM-REQUEST-037 | Change a live one-time request into regular care or vice versa | Gap | No | No | Define replace-versus-edit behavior before AI work |
| FAM-REQUEST-038 | Withdraw an open request | UI | Partial | No | Read impact; confirm & execute |
| FAM-REQUEST-039 | Restore or reopen a withdrawn or expired request | Gap | No | No | Define product behavior; otherwise offer create-from-copy |
| FAM-REQUEST-040 | Duplicate a request | Partial UI | Partial | Draft | Use explicit previous-request copy with new schedule recap |
| FAM-REQUEST-041 | Understand why request publication failed validation | UI | Partial | Draft | Explain exact field error and reopen affected section |
| FAM-REQUEST-042 | Understand why an AI confirmation failed or became stale | UI | Yes | Yes | Explain and reload fresh recap |
| FAM-REQUEST-043 | Avoid creating a duplicate request after retrying | UI | Yes | Yes | Keep idempotent reconciliation and authoritative receipt |
| FAM-REQUEST-044 | Ask for a guaranteed caregiver response or response time | Gap | Yes | No | Explain preference is not a guarantee; no availability promise |
| FAM-REQUEST-045 | Ask for a medical task inside a request | Human | Yes | Transfer | Keep medical boundary and transfer |

## 6. Finding, comparing, inviting, messaging, and hiring caregivers

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-MATCH-001 | Browse caregivers | UI | No | No | Explain and navigate to authorized search |
| FAM-MATCH-002 | Search caregivers by name, location, availability, or fit | UI / Partial UI | No | No | Add navigation and structured filters; never promise availability |
| FAM-MATCH-003 | Filter caregivers by certifications or verification status | UI | No | No | Explain verified versus self-reported credentials and navigate |
| FAM-MATCH-004 | Understand profile ratings, reviews, experience, reliability, or completed visits | UI | No | No | Add governed field definitions and authoritative profile read |
| FAM-MATCH-005 | View a caregiver profile | UI | No | No | Register authorization-aware navigation target |
| FAM-MATCH-006 | Understand why a caregiver is recommended | Partial UI | No | No | Define recommendation explanation before AI use |
| FAM-MATCH-007 | Find matching caregivers for a request | UI | No | No | Read request needs and open structured matching UI |
| FAM-MATCH-008 | Invite a caregiver to an open request | UI | No | No | Prepare invitation preferences/message; recap; confirm & send |
| FAM-MATCH-009 | Invite someone the Family already knows | UI | No | No | Prepare and confirm invitation; explain recipient requirements |
| FAM-MATCH-010 | Reinvite a caregiver | UI | No | No | Read prior invitation state then confirm & execute |
| FAM-MATCH-011 | See invitation status | UI | No | No | Add authoritative invitation-state read |
| FAM-MATCH-012 | Cancel a caregiver invitation | Partial UI | No | No | Define supported cancellation state and confirm & execute |
| FAM-MATCH-013 | See caregivers who applied or replied | UI | Yes | Read / Guide | Read pending applied/shortlisted counts and open the exact applicant area |
| FAM-MATCH-014 | Compare applicants | UI | No | No | Summarize only authorized comparable fields; user decides |
| FAM-MATCH-015 | Shortlist or save an applicant for later | UI | No | No | Confirm & execute low-risk state change |
| FAM-MATCH-016 | Reject or mark “not this caregiver” | UI | No | No | Explain impact then confirm & execute |
| FAM-MATCH-017 | Start or open a conversation with an applicant | UI | Partial | Read / Guide | Open the newest/unread authorized existing conversation; creating a new conversation remains normal UI work |
| FAM-MATCH-018 | Send a message to an applicant or hired caregiver | UI | No | No | Prepare message; user reviews and explicitly sends |
| FAM-MATCH-019 | Understand whether a message was delivered or read | Partial UI | No | No | Read only authoritative delivery state; never infer reading |
| FAM-MATCH-020 | Hire a caregiver who applied | UI | No | No | Read eligibility/payment prerequisites; recap; confirm & execute |
| FAM-MATCH-021 | Understand what hiring does to the request and booking | UI | Partial | No | Add governed lifecycle explanation |
| FAM-MATCH-022 | Understand why hiring is blocked | UI | No | No | Read exact authorization/payment/request state and explain |
| FAM-MATCH-023 | Choose between multiple caregivers | UI | No | No | Explain comparison factors; never make the final decision autonomously |
| FAM-MATCH-024 | Report misleading profile information or credential concerns | Human | No | Transfer | Prepare report and transfer to human review |
| FAM-MATCH-025 | Block a caregiver from future contact or matching | Gap / Human | No | No | Define product/safety workflow; human until implemented |

## 7. Billing, saved cards, authorizations, charges, refunds, and failures

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-PAY-001 | Open Billing & Payments | UI | Yes | Guide | Open the secure Billing & Payments target and preserve chat through the return flow |
| FAM-PAY-002 | Understand who may manage the saved card | UI | Yes | Guide / Read | Active Family Account members get the secure guided flow and only safe shared-card facts |
| FAM-PAY-003 | See whether a card is on file | UI, owner only for details | Yes | Read / Guide | Deterministically read only brand, last4, expiry, readiness, and attention; offer the correct secure action without a model call |
| FAM-PAY-004 | Add a first payment card | UI, owner only | Yes | Guide | Open Billing & Payments, focus/highlight Add card securely, preserve through Stripe, and verify current state before success |
| FAM-PAY-005 | Replace or change the card on file | UI, owner only | Yes | Guide | Open Billing & Payments, focus/highlight Update card, preserve through Stripe, and verify current state before success |
| FAM-PAY-006 | Update an expiring or expired card | UI, owner only | Yes | Guide | Read safe expiry attention, guide to secure setup, and never collect card data in chat |
| FAM-PAY-007 | Remove the card on file | Gap | Yes | Guide | Explain that chat cannot remove the card; open Billing & Payments or transfer when the current UI has no valid removal control |
| FAM-PAY-008 | Understand whether a Family member may change the shared card | UI | Yes | Read / Guide | Deterministically explain shared Family billing access, return only safe card facts, and guide to the secure control |
| FAM-PAY-009 | Understand when payment is authorized and captured | UI | Yes | Read / Guide | Explain the approved lifecycle, then use the current authorized visit state for a specific case |
| FAM-PAY-010 | Understand whether publishing a request charges the card | UI | Yes | No | Keep covered |
| FAM-PAY-011 | Understand an authorization hold versus a captured charge | UI | Yes | Read / Guide | Explain the governed distinction and label the current Family-visible payment state |
| FAM-PAY-012 | Understand a pending payment | UI | Partial | Read / Guide | Read whether Family action is required and open the exact care-payment recovery area |
| FAM-PAY-013 | Understand why card authorization failed | UI | Yes | Read / Guide | State a safe normalized reason when possible, never raw provider text, and open the exact retry control |
| FAM-PAY-014 | Retry a failed card authorization | UI | Partial | Guide | Highlight the existing secure recovery action; the user completes it in the normal UI |
| FAM-PAY-015 | Complete bank/card authentication or action-required flow | UI | Yes | Guide / Verify | Navigate to the secure application/provider flow and verify the resulting LoLo payment state |
| FAM-PAY-016 | Understand why payment capture failed after approving care | Partial UI | Yes | Read / Guide | Read the normalized safe reason and open the exact visit recovery control |
| FAM-PAY-017 | Retry payment for a time correction | UI | Partial | Guide | Open and highlight the correction payment-recovery step |
| FAM-PAY-018 | Retry payment for a completed extra visit | UI | Partial | Guide | Open and highlight the regular-care attention area |
| FAM-PAY-019 | View payment history | UI | Yes | Read / Guide | Read whether history exists and open highlighted Care history |
| FAM-PAY-020 | View the amount authorized, captured, refunded, and net paid | UI | Yes | Read / Guide | Read authoritative Family-visible amounts and explain each label |
| FAM-PAY-021 | Understand a payment failure shown in Care history | UI | Yes | Read / Guide | Read the current normalized failure reason and recommend the exact recovery page |
| FAM-PAY-022 | Request a refund | Human | No | Transfer | Prepare reason and booking reference; human only |
| FAM-PAY-023 | Understand refund status or amount | UI / Human | Yes | Read / Guide | Read the authoritative refund state and amount; transfer exceptions |
| FAM-PAY-024 | Dispute a charge or submitted hours | UI / Human | No | No | Prepare dispute; explicit submission; human review |
| FAM-PAY-025 | Understand a duplicate or unfamiliar charge | Human | No | Transfer | Read matching authorized records if safe, then human |
| FAM-PAY-026 | Get a receipt | UI | Yes | Guide | Navigate to the exact Family-visible care and payment record; never invent a document action |
| FAM-PAY-027 | Get an invoice or tax document | Gap / Human | Yes | Guide / Transfer | Explain current availability and transfer rather than promise a document |
| FAM-PAY-028 | Understand taxes, fees, tips, mileage, holiday charges, or surcharges | Approved pricing truth | Yes | Explain | State $30/hour Family, $27/hour caregiver, and $3/hour LoLo; do not add other charges without later governed policy |
| FAM-PAY-029 | Ask the hourly price or estimated total | Approved pricing truth | Yes | Explain / Calculate | State $30/hour Family, $27/hour caregiver, and $3/hour LoLo; calculate only from an explicit duration |
| FAM-PAY-030 | Apply a coupon, credit, or promo code | Gap | No | No | Product gap; never invent discounts |
| FAM-PAY-031 | Change billing owner or use a different person's card | Gap / Human | No | No | Define authorization and account model; human until then |
| FAM-PAY-032 | Understand whether caregiver payout has happened | Human / restricted | No | No | Do not expose caregiver payout details; explain Family-visible state only |

## 8. Scheduled visits, check-in, submitted hours, completion, and problems

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-VISIT-001 | View the next scheduled visit | UI | Yes | Read / Guide | Read the next/current authorized booking and open its exact visit area |
| FAM-VISIT-002 | View visit date, time, duration, location, tasks, and instructions | UI | No | No | Add authorized booking read with field-level privacy |
| FAM-VISIT-003 | Understand the current visit status | UI | Yes | Read / Guide | Explain the normalized live/scheduled status and open the visit |
| FAM-VISIT-004 | Message the hired caregiver before or during a visit | UI | No | No | Navigate or prepare message; explicit send |
| FAM-VISIT-005 | Request a reschedule | UI | No | No | Prepare change request; recap; explicit send |
| FAM-VISIT-006 | Request a visit cancellation | UI | No | No | Explain policy/impact; recap; confirm & execute or send request |
| FAM-VISIT-007 | Cancel a scheduled visit directly when allowed | UI | No | No | Read eligibility/impact; confirm & execute |
| FAM-VISIT-008 | Understand late-cancellation consequences | Partial UI | No | No | Add authoritative policy explanation before confirmation |
| FAM-VISIT-009 | Review a caregiver's schedule-change request | UI | Partial | Read / Guide | Detect the pending caregiver request and highlight the exact decision card; full current/proposed recap remains |
| FAM-VISIT-010 | Accept a caregiver's change request | UI | Partial | Guide | Highlight the normal Accept/Reject decision card; the user performs the decision in the app |
| FAM-VISIT-011 | Reject a caregiver's change request | UI | Partial | Guide | Highlight the normal Accept/Reject decision card; the user performs the decision in the app |
| FAM-VISIT-012 | Understand caregiver check-in or check-out state | UI | No | No | Add authorized live-state read |
| FAM-VISIT-013 | Report that a caregiver is late | Human / Partial UI | No | No | Prepare support contact; distinguish late from no-show |
| FAM-VISIT-014 | Mark a caregiver as no-show | UI | No | No | Read eligibility and consequences; explicit confirmation |
| FAM-VISIT-015 | Report a safety incident during or after care | UI / Human | No | No | Prepare structured incident; urgent safety rule; human review |
| FAM-VISIT-016 | Create a support ticket about the visit | UI | Partial | Transfer | Prefer same support conversation with booking context |
| FAM-VISIT-017 | Tell the app that the visit ended | UI | No | No | Read booking state; confirm & execute |
| FAM-VISIT-018 | Understand what a timesheet or submitted-hours review is | UI | Yes | Read / Guide | Read whether submitted hours need attention and open the exact hours area |
| FAM-VISIT-019 | Review caregiver-submitted start, end, duration, tasks, and notes | UI | Yes | Read / Guide | Read the authoritative submission and present a concise recap |
| FAM-VISIT-020 | Approve submitted hours and payment | UI | Partial | Guide | Read the submitted duration and highlight the normal review/approval area; AI execution remains restricted |
| FAM-VISIT-021 | Question submitted hours | UI | No | No | Prepare dispute/change request; explicit submission |
| FAM-VISIT-022 | Ask caregiver to correct submitted time | UI | No | No | Prepare reason and proposed correction; explicit send |
| FAM-VISIT-023 | Review a caregiver-submitted time correction | UI | Yes | Read / Guide | Recap original versus proposed hours and open the exact review section |
| FAM-VISIT-024 | Approve a time correction and payment | UI | No | No | Two-step recap and explicit confirm & execute |
| FAM-VISIT-025 | Request changes to a time correction | UI | No | No | Prepare request; explicit send |
| FAM-VISIT-026 | Continue payment after a correction requires card action | UI | Partial | Guide | Read the payment-action-required state and highlight the secure continuation control |
| FAM-VISIT-027 | Escalate a time correction to LoLo | UI / Human | No | Transfer | Transfer with booking/correction context |
| FAM-VISIT-028 | Open a dispute after care | UI / Human | No | No | Prepare dispute, show effect, explicit submit; human review |
| FAM-VISIT-029 | Understand dispute status | UI / Human | Yes | Read / Guide | Read the authoritative case status and next action without promising an outcome or time |
| FAM-VISIT-030 | Leave a star rating and review | UI | No | No | Prepare text, user selects rating and explicitly submits |
| FAM-VISIT-031 | Edit or remove a submitted review | Gap / Human | No | No | Define moderation/edit policy before AI work |
| FAM-VISIT-032 | Book the same caregiver again | UI | No | No | Prepare one-time invite with new schedule; confirm & send |
| FAM-VISIT-033 | Turn a successful visit into regular care | UI | Partial | No | Prepare recurring offer; recap; confirm & send |
| FAM-VISIT-034 | Understand whether approving hours triggers payment | UI | Yes | Read / Guide | Explain the approved lifecycle and use current authorized visit/payment state for a specific case |
| FAM-VISIT-035 | Correct a completed visit record | Human | No | Transfer | Human only with audit preservation |

## 9. Regular care plans and extra visits

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-REGULAR-001 | View active, paused, pending, or ended regular-care plans | UI | Partial | Read / Guide | Batch 2 guides exact regular-care attention; complete plan-list status coverage remains |
| FAM-REGULAR-002 | Set up regular care with a known caregiver | UI | Partial | No | Prepare offer, schedule, notes; recap; confirm & send |
| FAM-REGULAR-003 | Choose weekly days, per-day times, durations, start, and end | UI | Yes | Draft | Reuse request drafting rules where product semantics match |
| FAM-REGULAR-004 | Reuse care details and tasks from a prior request | UI | Yes | Partial read | Require explicit reuse and visible recap |
| FAM-REGULAR-005 | Add a message to the caregiver with a regular-care offer | UI | No | No | Prepare text; explicit send |
| FAM-REGULAR-006 | Understand why a payment method is required | UI | Partial | No | Publish reconciled billing explanation and navigate |
| FAM-REGULAR-007 | Understand a caregiver counteroffer | UI | No | No | Read original and countered schedule/terms |
| FAM-REGULAR-008 | Accept a caregiver counteroffer | UI | No | No | Difference recap; explicit confirmation |
| FAM-REGULAR-009 | View the next regular-care visit | UI | Yes | Read / Guide | Read the next plan booking and open the highlighted regular-care page |
| FAM-REGULAR-010 | View later generated visits | UI | No | No | Add authorized read and navigation |
| FAM-REGULAR-011 | Skip one upcoming regular-care visit | UI | No | No | Explain late window; explicit confirm & execute |
| FAM-REGULAR-012 | Add one extra future visit | UI | No | No | Prepare date/time/duration; recap; confirm & send |
| FAM-REGULAR-013 | Review a caregiver-reported completed extra visit | UI | Partial | Read / Guide | Detect the pending report and open the exact regular-care attention area; complete field recap remains |
| FAM-REGULAR-014 | Approve and pay a completed extra visit | UI | No | No | Recap; explicit confirm & execute |
| FAM-REGULAR-015 | Request changes to a completed extra visit | UI | No | No | Prepare and explicitly send correction request |
| FAM-REGULAR-016 | Dispute a completed extra visit | UI / Human | No | No | Prepare dispute; explicit submit; human review |
| FAM-REGULAR-017 | Retry payment for a completed extra visit | UI | Partial | Guide | Highlight the existing secure retry area; normal UI performs payment |
| FAM-REGULAR-018 | Escalate a completed extra visit to LoLo | UI / Human | No | Transfer | Transfer with exact visit context |
| FAM-REGULAR-019 | Request a future schedule change | UI | No | No | Prepare old/new schedule comparison; explicit send |
| FAM-REGULAR-020 | Pause regular care with or without a return date | UI | No | No | Show cancelled-visit impact; confirm & execute |
| FAM-REGULAR-021 | Resume paused regular care | UI | No | No | Read next generated visit; confirm & execute |
| FAM-REGULAR-022 | End regular care | UI | No | No | Show next-visit choice and future impact; confirm & execute |
| FAM-REGULAR-023 | End regular care and cancel the next visit | UI | No | No | Strong impact recap; confirm & execute |
| FAM-REGULAR-024 | View regular-care history and payments | UI | Partial | Read / Guide | Open Care history and read the available completed-visit summary; plan filter preselection remains |
| FAM-REGULAR-025 | Message the regular caregiver | UI | No | No | Navigate or prepare message; explicit send |
| FAM-REGULAR-026 | Replace the caregiver on a regular-care plan | Gap / Human | No | Transfer | Define replacement workflow; human until implemented |

## 10. Continuous Coverage / 24/7 plan management

Continuous Coverage exists behind a product middleware flag. The current AI policy transfers any new 24/7 need to human support instead of performing plan intake. Management intents below remain valuable coverage targets if and when this product area is released to a Family cohort.

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-COVERAGE-001 | Understand what Continuous Coverage is | Partial UI / Human | Yes | Transfer | Explain high-level boundary and transfer |
| FAM-COVERAGE-002 | Create a Continuous Coverage plan | Flagged UI / Human | Partial | Transfer | Human only until separately authorized |
| FAM-COVERAGE-003 | Choose recipient, address, pattern, timezone, dates, and coverage windows | Flagged UI | No | Transfer | Human-assisted structured intake if released |
| FAM-COVERAGE-004 | Understand whether coverage is fully staffed | Flagged UI | No | No | Read authoritative coverage gaps; never promise availability |
| FAM-COVERAGE-005 | Open the coverage calendar | Flagged UI | No | No | Add authorized navigation |
| FAM-COVERAGE-006 | View covered, offered, open, replacement, cancelled, or completed shifts | Flagged UI | No | No | Add governed statuses plus live state |
| FAM-COVERAGE-007 | Filter calendar or coverage history | Flagged UI | No | No | Navigate with safe filter parameters |
| FAM-COVERAGE-008 | View shift details and payment status | Flagged UI | No | No | Read exact authorized shift state |
| FAM-COVERAGE-009 | Find and add a caregiver to the care team | Flagged UI | No | No | Prepare search/invitation preferences; confirm & send |
| FAM-COVERAGE-010 | Review and approve a caregiver application | Flagged UI | No | No | Read profile/application; recap; confirm & invite |
| FAM-COVERAGE-011 | Decline a caregiver application | Flagged UI | No | No | Confirm & execute |
| FAM-COVERAGE-012 | Edit future-offer preferences for a caregiver | Flagged UI | No | No | Prepare changes; recap; confirm & execute |
| FAM-COVERAGE-013 | Pause or resume a care-team member | Flagged UI | No | No | Explain future-offer impact; confirm & execute |
| FAM-COVERAGE-014 | Remove a caregiver from future offers | Flagged UI | No | No | Explain existing-shift/history impact; confirm & execute |
| FAM-COVERAGE-015 | Offer a recurring coverage lane | Flagged UI | No | No | Read eligible team; recap lane; confirm & offer |
| FAM-COVERAGE-016 | Approve one or all requested recurring lanes | Flagged UI | No | No | Read availability; recap; confirm & execute |
| FAM-COVERAGE-017 | Decline a requested lane | Flagged UI | No | No | Confirm & execute |
| FAM-COVERAGE-018 | Confirm an accepted replacement caregiver | Flagged UI | No | No | Compare replacement and gap; explicit confirmation |
| FAM-COVERAGE-019 | Decline a replacement and continue searching | Flagged UI | No | No | Explain effect; explicit confirmation |
| FAM-COVERAGE-020 | Retry replacement search | Flagged UI | No | No | Read case state and execute deterministic retry |
| FAM-COVERAGE-021 | Change the future coverage schedule | Flagged UI | No | No | Show current/proposed calendar diff; confirm & execute |
| FAM-COVERAGE-022 | End a coverage plan | Flagged UI | No | No | Strong impact recap; confirm & execute or human review |
| FAM-COVERAGE-023 | Delete an unused coverage plan | Flagged UI | No | No | Verify eligibility/dependencies; explicit confirmation |
| FAM-COVERAGE-024 | View coverage receipts, holds, captured amounts, refunds, and net billed | Flagged UI | No | No | Read authoritative payment values and explain labels |
| FAM-COVERAGE-025 | Resolve payment attention on a coverage shift | Flagged UI | No | No | Read safe failure; navigate to secure recovery |
| FAM-COVERAGE-026 | Report a missed, disputed, unsafe, or incorrect coverage shift | Flagged UI / Human | No | Transfer | Prepare exact context and transfer to human |

## 11. Messages, notifications, and communication preferences

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-COMMS-001 | Open the Family message inbox | UI | Yes | Guide | Open and highlight the authorized message inbox |
| FAM-COMMS-002 | Find the conversation for a request, applicant, or hired caregiver | UI | Partial | Read / Guide | Read unread/recent authorized conversations and open the exact thread; named-request resolution remains later work |
| FAM-COMMS-003 | Send a message | UI | No | No | Prepare message; user reviews and explicitly sends |
| FAM-COMMS-004 | Understand why a message cannot be sent | Partial UI | No | No | Read conversation/request state and explain |
| FAM-COMMS-005 | Report harassment, abuse, or unsafe messaging | Human | No | Transfer | Safety-first transfer with preserved conversation |
| FAM-COMMS-006 | Open Family notifications | UI | No | No | Add registered navigation target |
| FAM-COMMS-007 | Understand a notification | UI | No | No | Use event-specific governed definitions plus safe live payload |
| FAM-COMMS-008 | Open the object referenced by a notification | UI | No | No | Navigate using the notification's authorized destination |
| FAM-COMMS-009 | Mark one notification read | UI | No | No | Confirm-free low-risk execute |
| FAM-COMMS-010 | Mark all notifications read | UI | No | No | Short recap; confirm & execute |
| FAM-COMMS-011 | Filter unread, read, or all notifications | UI | No | No | Navigate with filter |
| FAM-COMMS-012 | Filter notifications by event type | UI | No | No | Navigate with filter and explain categories |
| FAM-COMMS-013 | Change email and in-app notification preferences | UI | No | No | Prepare preference changes; recap; confirm & save |
| FAM-COMMS-014 | Understand why an expected notification or email did not arrive | Partial UI / Human | No | No | Read delivery/preferences state; troubleshoot; human if failed |
| FAM-COMMS-015 | Unsubscribe from non-essential email | Partial UI | No | No | Explain preference effect and navigate/confirm |
| FAM-COMMS-016 | Change notification destination email | UI | Partial | Navigate | Personal email change with re-verification |
| FAM-COMMS-017 | Ask the AI to remind the Family about a visit | Gap | No | No | Product/automation gap; define consent and delivery first |

## 12. Care history, receipts, rebooking, and records

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-HISTORY-001 | Open Care history | UI | Yes | Read / Guide | Read whether completed history exists and open highlighted Care history |
| FAM-HISTORY-002 | Search history by booking, caregiver, recipient, or title | UI | No | No | Navigate with filters; later authorized search read |
| FAM-HISTORY-003 | Filter by date, status, payment state, recipient, caregiver, or regular-care plan | UI | No | No | Navigate with safe filters |
| FAM-HISTORY-004 | Understand history summary totals | UI | Partial | Read / Guide | Read completed-visit count and latest record; full money/hour aggregation explanation remains |
| FAM-HISTORY-005 | View a past visit record | UI | No | No | Navigate to exact authorized record |
| FAM-HISTORY-006 | View tasks, event timeline, incidents, change requests, and reviews | UI | No | No | Read exact record with privacy minimization |
| FAM-HISTORY-007 | View captured, refunded, and net paid amounts | UI | No | No | Read authoritative values and explain |
| FAM-HISTORY-008 | Open the caregiver profile from history | UI | No | No | Navigate to authorized public profile |
| FAM-HISTORY-009 | Open the related regular-care plan | UI | No | No | Navigate to exact plan |
| FAM-HISTORY-010 | Get help with a past visit | UI / Human | Partial | Transfer | Transfer with exact booking context |
| FAM-HISTORY-011 | Book the same caregiver again for one visit | UI | No | No | Prepare new schedule/message; recap; confirm & invite |
| FAM-HISTORY-012 | Book the same caregiver again for regular care | UI | Partial | No | Prepare recurring offer; recap; confirm & send |
| FAM-HISTORY-013 | Reuse a prior request with a different schedule | UI | Yes | Draft | Keep explicit reuse and new recap |
| FAM-HISTORY-014 | Download or print visit/payment history | Gap | No | No | Define export scope, format, and privacy controls |
| FAM-HISTORY-015 | Correct an inaccurate historical record | Human | No | Transfer | Human only; preserve audit history |

## 13. General support, complaints, privacy, and exceptional cases

| ID | Family intent | Product today | AI explain now | AI do now | Recommended target behavior |
| --- | --- | --- | --- | --- | --- |
| FAM-SUPPORT-001 | Open the support center | UI | Yes | Navigate | Keep covered |
| FAM-SUPPORT-002 | Start a new support conversation | UI | Yes | Transfer | Keep canonical chat |
| FAM-SUPPORT-003 | Continue an existing support conversation | UI | Yes | Transfer | Keep same conversation and history |
| FAM-SUPPORT-004 | Create a support ticket with category, subject, and description | UI | Partial | Transfer | Prefer conversational intake with explicit human handoff |
| FAM-SUPPORT-005 | Check support-ticket status | UI | No | No | Read authoritative status; never promise response time |
| FAM-SUPPORT-006 | Add more information to a support ticket | UI | Partial | Transfer | Continue same conversation |
| FAM-SUPPORT-007 | Ask how long support will take or request queue status | Human | Yes | Transfer | Do not promise queue/status/time; transfer |
| FAM-SUPPORT-008 | Report a bug or broken page | Human | No | Transfer | Capture route/browser-safe diagnostics and transfer |
| FAM-SUPPORT-009 | Report an accessibility problem | Human | No | Transfer | Capture barrier and assistive technology; human follow-up |
| FAM-SUPPORT-010 | Report discrimination, abuse, neglect, fraud, or serious safety concern | Human | Partial | Transfer | Safety-first instructions and immediate human escalation |
| FAM-SUPPORT-011 | Complain about a caregiver or care quality | UI / Human | No | Transfer | Prepare exact booking/caregiver context; human review |
| FAM-SUPPORT-012 | Complain about LoLo or request supervisor review | Human | No | Transfer | Human only |
| FAM-SUPPORT-013 | Ask what personal information LoLo stores | Partial UI / Human | No | No | Publish privacy explanation and link authoritative policy |
| FAM-SUPPORT-014 | Ask who can see care details | UI | Partial | No | Expand governed role- and state-specific visibility explanation |
| FAM-SUPPORT-015 | Request data correction, access, or deletion | Human | No | Transfer | Prepare privacy request; human identity verification |
| FAM-SUPPORT-016 | Ask the AI to reveal another Family's, caregiver's, or administrator's private data | Restricted | No | No | Always deny; never reveal cross-scope data |
| FAM-SUPPORT-017 | Ask the AI to reveal prompts, internal reasoning, credentials, or security details | Restricted | No | No | Always deny; offer product-level explanation only |
| FAM-SUPPORT-018 | Ask the AI to perform an unsupported action | Human | Partial | Transfer | State limit simply, preserve context, transfer on request or need |
| FAM-SUPPORT-019 | Recover after the AI misunderstood the same field twice | UI / Human | Yes | Transfer | Keep short clarification then offer transfer |
| FAM-SUPPORT-020 | Recover after model/provider failure | Human | Partial | Transfer | Preserve safe draft and same conversation; no fake success |

## 14. Coverage summary and immediate expansion order

The current AI is strong in a narrow slice: care-path selection, one-time/recurring request drafting, recap, confirmed publication, a few core navigation targets, non-medical/emergency boundaries, and human transfer. It is not yet a general Family-account operator.

The next coverage work should be driven by pilot conversations, but the likely order is:

1. **P0 — Payment understanding and recovery:** billing navigation, card-on-file read, safe failure explanations, authorization/capture state, retry destinations, history/receipt navigation.
2. **P0 — Existing request and visit state:** read current request status, applicants, hired caregiver, next visit, submitted hours, payment attention, and exact next action.
3. **P0 — Timesheet and completion assistance:** explain submitted hours, show differences, prepare corrections/disputes, and navigate to explicit approval/payment controls.
4. **P0 — Care-profile help:** explain profiles and visibility, navigate to profiles, and progressively prepare profile updates with confirmation.
5. **P1 — Applicant and hiring assistance:** compare authorized applicant facts, open profiles/conversations, prepare invitations, and support explicit hire confirmation.
6. **P1 — Live request management:** withdraw, duplicate, and—after Product defines versioning—edit live request details safely.
7. **P1 — Regular-care management:** read plan state, skip/add/change/pause/resume/end with clear impact recaps.
8. **P1 — Messages, notifications, and history:** exact navigation, safe state explanations, message preparation, and preference changes.
9. **P2 — Family access and account administration:** invite/resend/cancel/remove/leave with owner checks and consequence recaps.
10. **Human-only or separately authorized:** refunds, ownership transfer, account closure, serious complaints, privacy requests, record corrections, medical issues, emergencies, and new 24/7 coordination.

## 15. Rules for converting a row into AI coverage

A row may move to **AI explain: Yes** only when:

- the product truth and owner are clear;
- a governed English KB entry covers normal behavior, boundaries, failures, and escalation;
- personalized claims use authorized live state rather than KB memory;
- at least one positive, boundary, wrong-role/account, and failure evaluation exists.

A row may move to **AI do: Yes** only when:

- an existing domain service remains authoritative;
- authorization is checked again at execution time;
- the tool is narrow, versioned, idempotent where needed, and auditable;
- the user sees the material values and consequences before confirmation;
- stale, duplicate, conflicting, or uncertain execution fails safely;
- the receipt comes from the authoritative domain result;
- human support remains available.

Secure secrets are never chat inputs. Card number, CVC, passwords, reset tokens, verification secrets, and provider authentication stay in their dedicated secure flows.

## 16. Maintenance procedure

For every Family-facing product or AI change:

1. add or update the intent row;
2. link the relevant route, UI component, domain service, KB entry, capability, and tests in the implementation work item;
3. update **Product today**, **AI explain now**, and **AI do now** only after verification;
4. record newly discovered pilot wording as a retrieval/evaluation example, never as unreviewed product truth;
5. split a row when its authorization, confirmation, or failure behavior differs materially.

This registry is the portfolio tracker. Individual capability specifications remain the implementation authority for any new read or write tool.

The reusable contract for live state, exact navigation, accessible highlighting, prefill, and completion verification is [App-aware guided assistance](39-app-aware-guided-assistance.md).

After editing any row, regenerate and validate the executable catalog before committing:

```bash
php tools/generate_family_intent_catalog.php
php artisan ai-support:test-family-intents --plan
```

The Batch 3 implementation details and preserved boundaries are recorded in [Family Operating Layer — Batch 3A and 3B](46-family-operating-layer-batch-3.md).

## 17. Repository audit basis

The baseline inventory was derived from:

- Family routes in `routes/web.php`;
- `app/Livewire/Family` and `resources/views/livewire/family`, including request, care-profile, Family-access, regular-care, care-history, notification, and Continuous Coverage workflows;
- the role-aware Family dashboard;
- shared messaging and support-ticket/chat components;
- `FamilyBillingController`, the Family billing view, and payment recovery actions embedded in request/visit screens;
- personal profile, email-verification, password-reset, password-change, and account-deletion screens;
- current Family domain actions exposed by `ManageCareRequest`, `RegularCareShow`, `ContinuousCoverageShow`, and their services;
- `config/ai_support.php`, the current Family AI bundle, registered navigation targets, and registered write tools;
- the production governed KB entries plus the source-defined Batch 4 payment/time package in `resources/ai-support/knowledge-base`;
- current AI context, drafting, recap, confirmation, publication, safety-transfer, and human-takeover services.

“Probable” rows that have no complete current UI were added from the failure and recovery paths implied by those workflows: expired links, card failures, unfamiliar charges, refunds, record corrections, ownership changes, privacy requests, exports, security incidents, and unsupported mutations.
