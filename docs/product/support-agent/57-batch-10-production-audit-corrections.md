# Batch 10 Authenticated Production Audit Corrections

Status: Initial correction deployed; post-deployment production-capability routing follow-up is source complete and awaiting the normal deployment/recheck; exact two-user pilot unchanged; Live for everyone remains off

Audited: August 21, 2026

Owner: Product and Engineering

## Audit outcome

The authenticated pilot audit used one Administrator session and the exact Family pilot account. It verified that one-time, weekly regular, and irregular-date care needs classify correctly; Enter submits; the composer clears immediately and retains focus; the chat stays anchored at the latest message; mobile uses the intended full-height sheet; payment guidance opens and highlights the secure payment area; 24/7 language transfers to a person; the Admin transcript and evidence are visible; and no synthetic September 2 care request became live.

Six defects were reproduced and corrected:

| Defect | Corrected contract |
| --- | --- |
| “Someone else” and “My mother needs care” repeated the same recipient question | The server now records self/other and common relationship answers deterministically. Once “other” is known, the next single question asks for the person's full name instead of restarting the choice. |
| A next-visit question selected a reviewed March visit | Current-visit reads consider only in-progress, paused, or scheduled current/upcoming bookings. A past completed/reviewed visit cannot answer “next.” |
| A waiting-hours question selected an already reviewed/transferred visit | Unreviewed submitted hours are prioritized over confirmed history. When several items are waiting, all authorized items are listed with exact guided destinations. Confirmed items are excluded from the waiting answer. |
| The 24/7 handoff note carried a stopped one-time draft and ordinary request goal | Stopping a care-request task discards its encrypted private draft. Switching to 24/7 cancels and discards the ordinary request draft before transfer, starts a human-help journey, and records only the 24/7 handoff context. |
| A caregiver-waiting question returned generic request guidance | Applicant-waiting language has its own deterministic Family state intent. It answers from the authorized action inbox with an exact count/list, or clearly states that nobody is waiting. |
| The Admin queue retained the conversation's original title/context | Human transfer refreshes the ticket subject. Continuous coverage uses `Chat: Help with 24/7 continuous care`, a reason-specific public transfer message, and a clean internal summary. |

## Safety and data behavior

- No generic database, URL, selector, browser-control, or model-defined action was added.
- Every state answer remains scoped through the signed-in Family account.
- The correction does not create, publish, modify, hire, charge, approve, or transfer any care-domain record while answering status questions.
- Stopping an unfinished chat request clears only its encrypted AI private draft; it does not alter an existing live care request.
- Ordinary human handoff still preserves a useful active goal and resumable safe draft. The discard behavior is limited to explicit Stop/cancel and an ordinary request superseded by 24/7 coverage.
- Continuous coverage stays human-owned and makes no queue, response-time, caregiver-availability, or outcome promise.
- Pilot eligibility and the separate Everyone switch are unchanged.

## Regression coverage

Focused verification passes 58 tests and 1,135 assertions across Batch 10 journeys, Family guided state, Batch 6/7 care operations, and the interactive runtime. The complete AI Support feature suite passes 238 tests and 5,847 assertions.

The new exact cases prove:

- `Someone else.` advances to the full-name question;
- `My mother needs care.` records `Mother` without repeating the self/other question;
- Stop discards the private AI draft and creates no care request;
- a months-old reviewed visit cannot answer `When is my next scheduled visit and who is the caregiver?`;
- two unconfirmed records both answer `Which submitted hours are waiting for me to review right now?`, while confirmed history is omitted;
- `Do I have any caregivers waiting for me to review or hire?` returns an authoritative empty or populated state without a provider call;
- the direct Batch 6/7 reader selects a future booking over a past reviewed booking and selects an unconfirmed submitted-hours record over a newer confirmed one; and
- 24/7 transfer discards the superseded ordinary draft, creates a human-help terminal journey, refreshes the Admin title, and omits stale draft content from the internal note.

## Post-deployment production-capability follow-up

The authenticated recheck confirmed that recipient continuation and upcoming-visit selection were corrected. It also exposed a test-configuration gap: with the production `family_care_operations_v1` capability enabled, its deep intent handler could answer three read-only state phrases before the authoritative Family action-inbox reader. That selected one historical visit for submitted hours and treated a hired caregiver as a waiting response. It also started a persistent operational goal for a status question, which could interfere with the next question.

The runtime now routes the three stable read-only intents `FAM-VISIT-001`, `FAM-VISIT-018`, and `FAM-MATCH-013` through the authoritative Family state reader before operational handlers. These answers do not start a goal, do not mutate a domain record, and cannot preempt actual action intents such as `FAM-VISIT-020` approval or `FAM-MATCH-020` hiring. The regression runs with the production care-operations capability enabled and asserts that no goal journey is created.

## Deployment and recheck

Deploy with the normal `deploy.sh` workflow. There is no migration, KB publication, pilot-grant change, Availability change, or special Artisan command in this correction.

After deployment, repeat the six exact audit phrases in a fresh automated pilot conversation. The 24/7 case intentionally transfers that conversation to human-only; return or resolve the synthetic support conversation through the normal Admin control after reviewing the reason-specific title and internal summary. Do not publish a care-request recap during this recheck.
