# Batch 1-2 Production Pilot Audit and Corrective Release

Status: Corrective implementation verified in source; normal `deploy.sh` deployment and final production recheck pending

Audit date: August 18, 2026

Owner: Product and Engineering

Scope: Family AI Support Batches 1 and 2 for exact pilot users `19` and `282`

## Outcome

The production pilot is still bounded to the same two Family users. The audit did not enable **Live for everyone** and did not create, edit, approve, charge, retry, or delete any care, request, profile, message, timesheet, payment, or pilot-control record.

The production journeys proved the core Read/Guide architecture, and they found four correctable integration defects that the isolated suite alone did not reveal:

1. natural “status of my care request” wording missed the deterministic request reader;
2. explicit regular-care status/plan questions could fall through to a generic visit or model navigation result;
3. the messaging page's Livewire poll could replace the highlighted element while leaving focus and the guide strip behind; and
4. Billing & Payments returned HTTP 500 when the existing Stripe saved-card lookup was unavailable.

All four now have source corrections and regression coverage. Deployment remains a normal `deploy.sh` operation. Pilot/Everyone controls are unchanged.

## Production boundary confirmed

Before the intent journeys, the authenticated Administrator view confirmed:

- availability was **Pilot only**;
- exact pilot Family users were IDs `19` and `282`;
- automation was running;
- **Live for everyone** was off; and
- the production ticket used for this audit was deliberately returned to automation after an earlier safe human transfer.

The browser was then authenticated as pilot user `19`. No attempt was made to test the second user's private account state in the same session.

## Production journeys

| Journey | Production result before corrective deploy | Source correction or disposition |
| --- | --- | --- |
| Start a new conversation after resolution | Passed after deployed polling correction; composer stayed usable beyond refresh interval | Existing regression retained |
| Two consecutive messages without reload | Passed after deployed stable-Alpine correction; zero console errors | Existing regression retained |
| “What is the status of my care request?” | Fell through to generic unsupported copy | Router and frozen corpus now recognize the natural phrase deterministically |
| “What is the status of my request?” | Correct authorized request state and exact request/timesheet target | Copy now prevents doubled sentence punctuation |
| “When is my next visit?” | Truthfully reported no current/upcoming visit and guided to Care | Passed |
| “Do I have a timesheet to review?” | Returned current authoritative attention and exact timesheet target | Passed; no approval performed |
| “Is my care receiver profile ready?” | Truthfully reported no active profile and guided to the create editor | Passed; no profile data entered |
| “Do I have unread caregiver messages?” | Truthfully reported none unread and opened exact recent conversation | Target focus passed; polling removed highlight, now corrected with exact-target reacquisition |
| “Show my care history.” | Reported three completed visits and guided to Care history | Passed |
| “When is my next regular care visit?” | Truthful no-upcoming result but used generic Care target | Dedicated regular-care reader now distinguishes no plan, plan without next visit, and upcoming visit |
| “Show me my regular care plan.” | Model copy claimed Care but linked to generic requests | Same dedicated deterministic reader now guides to Regular care or the exact plan |
| “Why did my care payment fail?” | Truthfully found no payment requiring action and guided to payment history | Passed; no payment action performed |
| “Do I have a card on file?” | Truthfully said the card could not be verified and offered Billing | Billing destination returned HTTP 500; controller now renders a safe unavailable state and keeps the secure add/update button usable |

## Responsive and accessibility observations

Representative Care, request/timesheet, profile, message, and history targets were inspected at `390 x 844`:

- no horizontal document overflow occurred;
- the guide strip stayed within the viewport;
- **Show me**, **Stop**, and **Talk to a person** controls were at least 44 pixels high;
- keyboard focus moved to the exact semantic target;
- Tab continued to a visible page action;
- refresh preserved active guidance where tested; and
- stopping a guide removed the strip and highlight.

The messaging thread exposed the re-render defect: the exact target retained focus, but its runtime highlight class disappeared after polling. The coordinator now watches only for replacement/removal of the one server-issued semantic target and reapplies its focus/highlight. It does not inspect domain outcomes, select a substitute, or click anything.

A real screen-reader session is still required. Automated accessible-name, focus, reflow, and target tests do not replace it.

## Data observation outside AI mutation

Two existing request detail pages displayed submitted hours of `0h 00m` and estimated payment of `$0.00` even though surrounding request cards showed scheduled time ranges. The AI did not create or modify those values. This is recorded as a separate domain-data consistency follow-up; it is not silently treated as a support-answer success or changed in this corrective batch.

## Corrective implementation

The corrective source batch adds:

- a `PaymentException` fallback on Billing & Payments that reports the internal failure, renders **Check unavailable**, avoids claiming that no card exists, and preserves the secure **Add or update card securely** control;
- a dedicated `family_regular_care` deterministic intent and DB-backed reader;
- a registered `family.regular_care` destination and stable semantic target on the regular-care index;
- exact request-status coverage for “status of my care request”;
- sentence-safe request/timesheet copy;
- exact-target highlight reacquisition after Livewire DOM replacement or attribute reconciliation; and
- focused PHP, corpus, and Chromium regression cases.

The Stripe secure form, card data, payment calculation, authorization, capture, fees, payouts, and existing domain actions are unchanged.

## Verification completed in source

| Verification | Result |
| --- | ---: |
| Batch 1/2 registry intents | 40 / 40 |
| Frozen routing phrases | 121 / 121 |
| Near-neighbor collision cases | 10 / 10 |
| Shared application tests | 30 passed |
| Shared application assertions | 367 |
| Complete AI Support feature regression | 134 passed / 1,186 assertions |
| Billing fallback tests | 2 passed / 8 assertions |
| Production asset build | Passed |
| Chromium DOM-replacement/highlight journey | Passed |
| Provider calls in mass evaluation | 0 |
| Production database use in mass evaluation | 0 |

## Deploy and recheck

Run the normal deployment only:

```bash
./deploy.sh
```

Do not switch to Everyone. After deployment, use pilot user `19` and verify:

1. **Do I have a card on file?** -> **Add payment method** opens `/family/billing` with HTTP 200, an unavailable or current-card state, the exact focused/highlighted secure button, and no Stripe action until the user clicks it.
2. **Show me my regular care plan.** -> the deterministic no-plan answer opens `/family/care` and highlights `family.regular_care`.
3. **When is my next regular care visit?** -> the same reader returns the truthful current state, not the generic request link.
4. **What is the status of my care request?** -> the deterministic request reader returns current account state without a model call and without doubled punctuation.
5. **Do I have unread caregiver messages?** -> the exact thread remains highlighted after at least one messaging poll interval.
6. Admin remains **Pilot only**, users remain `19` and `282`, and **Live for everyone** remains off.

If those checks pass, close this corrective record as deployed and move to Batch 3 safe prefill. If Billing still fails, inspect the server exception for the existing Stripe customer reference; do not paste provider secrets or card data into chat.

## Next product phase

After the post-deploy recheck, the next implementation phase is Batch 3 safe prefill:

1. care-receiver profile draft preparation;
2. request editing/reuse where the existing product supports it;
3. caregiver-message draft preparation; and
4. support-intake prefill.

Each remains reviewable and reversible. No domain write is considered complete until the normal application service returns an authoritative receipt.
