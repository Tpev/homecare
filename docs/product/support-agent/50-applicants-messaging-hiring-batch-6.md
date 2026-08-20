# Applicants, Messaging, and Hiring — Batch 6

Status: Source implementation complete; automated verification complete; production deployment and authenticated pilot audit pending; **Live for everyone remains off**

Implemented: August 20, 2026

Scope: `FAM-MATCH-001` through `FAM-MATCH-025`

## Outcome

Batch 6 lets an eligible Family pilot user use the existing support conversation to understand and operate the applicant journey without learning the app structure. The assistant can read only Family-visible caregiver facts, open the exact request/applicant/conversation, prepare and send invitations and messages after confirmation, shortlist or decline an applicant, and hire through the existing booking and payment workflow.

The model does not receive a general database tool. Intent resolution selects one declared contract; the application selects and authorizes the Family-owned resource, builds the recap, checks fresh state again at confirmation, calls a narrow domain service, and creates a verified receipt.

## Implemented coverage

| Family goal | Assistant behavior |
| --- | --- |
| Browse or filter caregivers | Explain visible filters and open the authorized caregiver/request matching page |
| Understand a caregiver profile | Summarize only visible profile, rating, experience, reliability, badge, and credential facts |
| Invite or reinvite a caregiver | Select one open future request and one caregiver, show the exact optional message, recap, confirm, send, and verify |
| Check or cancel an invitation | Read authoritative invitation state; cancel only a pending invitation after confirmation |
| See and compare applicants | Read active applicants for the exact Family request and compare only visible facts |
| Save or decline an applicant | Recap the exact caregiver/request/state, confirm, mutate only the application, and verify |
| Start or open a conversation | Open the existing authorized conversation or create it from one eligible applicant after confirmation |
| Send a caregiver message | Show the exact recipient and message, require confirmation, send, and verify; never infer that it was read |
| Hire a caregiver | Show caregiver, request, schedule, `$30/hour` Family rate, payment consequence, and affected applications; confirm; use the existing booking/payment service; verify the booking or plan |
| Ask the assistant to choose “the best” caregiver | Organize facts but leave the subjective decision to the Family |
| Report a misleading profile, credential concern, or blocking need | Transfer the same support conversation to a human without making a finding |

## Narrow confirmed tools

All Batch 6 writes use capability `family_care_operations_v1`, tool version `v1`, a 30-minute recap, exact-user authorization, current-state fingerprints, an idempotency key, and a post-action receipt.

- `caregiver.invite`
- `caregiver.invitation.cancel`
- `applicant.shortlist`
- `applicant.reject`
- `applicant.conversation`
- `caregiver.message`
- `caregiver.hire`

The hiring operation is implemented in `CareRequestHiringService` so chat and future product surfaces can call one authoritative operation. One-time hiring creates the booking and begins the existing on-session payment-authorization flow. Recurring hiring delegates to the existing `CarePlanService`. The support work does not change Stripe pricing or payment-policy code.

## Authorization and truth rules

- Every request, application, invitation, conversation, booking, and plan is selected through the signed-in Family Account.
- A numeric ID from another Family Account never becomes a candidate.
- An invitation requires an open request; a one-time request must still be in the future and must not already have a hired caregiver.
- Hiring requires an open request and an applied or shortlisted caregiver at both recap and confirmation time.
- A message can be sent only when the existing conversation state allows Family messages.
- “Sent” is verified from the stored message or invitation. “Read” is never claimed without an authoritative read record.
- The assistant never promises caregiver availability, acceptance, response time, safety, suitability, or a hiring outcome.
- Family price support truth remains `$30/hour`; caregiver earnings remain `$27/hour`; LoLo remains `$3/hour`.

## Knowledge package

The combined Batches 6 and 7 package is `marketplace-care-kb-v1`. Its nine `KB-B67-MATCH-*` entries cover all 25 Match intents and are backed by five cases each: positive, boundary, stale/unavailable, wrong-account, and handoff.

Publication command:

```bash
php artisan ai-support:import-marketplace-care-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish approved Batches 6 and 7 marketplace-care knowledge for the two-user pilot." \
  --confirm=PUBLISH-MARKETPLACE-CARE-KB
```

The importer is exact and idempotent. It refuses a stable ID whose existing governed content differs, and it does not change assistant availability or marketplace records.

## Verification completed in source

- all 25 Match catalog rows are mapped to the combined governed package;
- all declared write contracts resolve to registered `family_care_operations_v1` tools;
- all 86 Batch 6/7 intents and all 344 registered ordinary-language phrases resolve deterministically;
- Family-account isolation is tested with a second Family and caregiver;
- invitation, shortlist, stale-state denial, exact-message sending, hiring, idempotency, and receipts are tested against actual domain records;
- the older Batch 3 and Batch 5 suites remain green; and
- configuration compiles successfully through `artisan config:cache`.

Production deployment is not recorded as complete in this document. After deployment, publish the combined KB package, activate the combined pilot capability, and perform the authenticated two-user journeys described in the Batch 7 record.
