# Production Audit Corrections — Batch 4.1

Status: Implemented, regression-tested, and ready for the normal `deploy.sh`; not an availability change

Date: August 18, 2026

## Purpose

The authenticated production review of the deployed Batch 3/4 two-user pilot found several places where the underlying capability existed but ordinary Family wording or continuation behavior did not reach it reliably. This correction batch turns those observed conversations into permanent deterministic regression cases.

The correction does not add a domain write, change Stripe or payment calculations, approve submitted hours, retry a payment, expand the pilot, or enable **Live for everyone**.

## Findings and corrections

| Authenticated finding | Correction | Expected user experience |
| --- | --- | --- |
| “Why did my latest payment fail, and what should I do next?” widened to the whole-account overview. | Specific payment, hours, message, profile, history, visit, and request rules now run before broad overview wording. | The assistant explains the latest authorized payment failure and opens the exact recovery control. |
| “What hours did my caregiver submit?” sometimes fell through to generic knowledge. | The deterministic submitted-hours matcher now recognizes `hours → caregiver → submitted` and `caregiver → submitted → hours` wording. | The assistant reads the latest authorized hours, differences, tasks, notes, and review state. |
| “Question submitted hours because the end time should be 11 AM” opened a read instead of preparing a correction. | `question` and `dispute` are explicit reversible-preparation verbs, and the useful reason after “because” is preserved. | A submitted-hours correction card contains the exact request and editable reason; nothing is submitted automatically. |
| Profile creation stored the whole sentence as the preferred name. | Common “care-receiver profile for NAME” and preferred-name forms extract only the name. Ambiguous name wording asks for the name instead of guessing. | “Create a care-receiver profile for Maria” prepares `Maria`, not the full request sentence. |
| Discarding prepared details changed backend state but left a stale card and weak feedback. | Cancellation locks and cancels the preparation, invalidates every matching active preparation action, clears its session reference, and writes a public confirmation. | The card disappears and the assistant says “Prepared details discarded. Nothing was saved or sent.” |
| “I did it” on a failed-payment guide used the unavailable generic verifier. | Payment-attention guides carry `family_payment_attention_v1`, which re-authorizes and re-reads the exact request or care plan. | If attention remains, the assistant explains the safe reason and does not complete. It completes only after that exact payment attention clears. |
| Admin showed zero transfers after an actual human transfer. | Admin includes `transferred_to_human` and counts one transfer per distinct support ticket across the related transfer event types. | The compact outcome summary reflects actual transferred conversations without double-counting. |
| One earlier browser session logged a transient optimistic-message null-state error. | The pending-message template snapshots the non-null object created by Alpine's `x-if` before rendering its fields. | Sending and retry UI no longer dereferences a pending object that was cleared during the same render turn. |

The first production session also showed transient browser errors around a dialog and Billing response. They did not reproduce in the fresh authenticated pass, current source uses a non-native dialog section, and the isolated browser suite is green. No speculative unrelated Billing or dialog change was made; recurrence should be captured with its exact response and stack before altering those domains.

## Authoritative payment re-check contract

The new payment-attention verifier accepts only an exact `care_request` or `care_plan` reference. It verifies that the resource belongs to the signed-in active Family Account, then asks the normalized payment/time reader for an actionable state on that exact resource.

- If the payment still needs attention, the task remains open and the response contains only the allowlisted reason and recovery instruction.
- If the exact payment no longer has an actionable state, the task may complete with “This care payment no longer needs attention.”
- If the reference is absent, belongs to another account, or cannot be authorized, no completion is recorded.
- Raw provider errors, provider IDs, client secrets, and payment credentials remain outside the response.

## Verification

Completed locally on August 18, 2026:

- exact production-regression cases: **5 tests / 49 assertions**;
- focused Family guidance, operating-layer, and state matrix: **28 tests / 1,687 assertions**;
- complete AI Support suite: **159 tests / 2,943 assertions**;
- production frontend build: passed;
- isolated Chromium browser suite: **7 / 7** scenarios passed, covering interactive request confirmation, exact payment navigation and verification, same-account conversation isolation, Admin audit, support conversation/human reply, launcher visibility, keyboard operation, and draft preservation;
- PHP formatting and `git diff --check`: passed.

The test environments use isolated databases and fake/bypassed providers. They do not write production users, payments, visits, timesheets, KB entries, Availability, or pilot grants.

## Deployment and pilot check

Use the existing normal deployment only:

```bash
./deploy.sh
```

No new migration, KB import, or publication command is required for Batch 4.1. After deployment, test these exact messages in either pilot account:

1. `Why did my latest payment fail, and what should I do next?`
2. `What hours did my caregiver submit?`
3. `I need help to question submitted hours because the end time should be 11 AM.`
4. `Can you help me create a care-receiver profile for Maria?`
5. discard the prepared profile and confirm the card disappears;
6. on a failed-payment guide, send `I did it` before and after the actionable state is resolved; and
7. transfer one conversation to a person and confirm the Admin transfer count increases once.

Keep Availability on **Pilot only**. Batch 5 was subsequently implemented and verified in source; continue with its [deployment and two-user pilot procedure](49-care-profiles-request-lifecycle-batch-5.md).
