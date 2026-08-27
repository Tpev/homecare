# Family Intent Coverage Closure

Status: Source complete; deployment and production KB publication pending

Implemented: August 27, 2026

Scope: Family AI Support only; exact two-user pilot unchanged; Live for everyone remains off; Caregiver AI unchanged

## Outcome

The executable Family catalog still contains exactly 324 explicitly KB-mapped intents. The eleven rows that previously had an explanation but no operational path now have a concrete user outcome. No generic database, browser, URL, selector, model-defined action, or new domain mutation was added.

| Mutually exclusive outcome | Before | Source after closure |
| --- | ---: | ---: |
| Complete — Execute plus Verify | 74 | 74 |
| Assisted — Read, Navigate, Guide, or Prepare | 152 | 163 |
| Human terminal | 87 | 87 |
| No operational path | 11 | 0 |

The overlapping current-stage counts are 324 Understand, 319 Explain, 172 Read, 197 Navigate, 193 Guide, 77 Prepare, 74 Execute, 74 Verify, and 88 Human. Human is 88 as an overlapping stage because one Complete row also supports escalation; the exclusive Human terminal count remains 87.

## Closed intent contracts

| Intent | Implemented resolution |
| --- | --- |
| `FAM-START-001` | A specific governed explanation of what LoLo helps Families do, followed by a Support Center action. |
| `FAM-START-002` | A specific non-medical-care explanation, 911/medical boundary, and Support action. The existing deterministic medical guard still transfers medical requests. |
| `FAM-START-016` | A concise statement of the assistant’s read, guide, prepare, confirm, verify, privacy, and human boundaries, followed by Support. |
| `FAM-PROFILE-001` | Purpose and candidate-versus-assigned visibility explanation followed by Care receiver profiles. |
| `FAM-REQUEST-032` | States that publication itself never hires, then reads the latest authorized request, hired application, and booking state and opens the exact request. |
| `FAM-REQUEST-033` | States that publication does not authorize or charge a card and opens the request. |
| `FAM-REQUEST-044` | States that caregiver availability and response time cannot be guaranteed, with a current request/support next step and no timing promise. |
| `FAM-PAY-010` | States that publication does not charge and opens Care requests. |
| `FAM-PAY-028` | Uses the versioned pricing service for the current fee boundary and offers request creation. |
| `FAM-PAY-029` | Uses deterministic explicit-duration calculation and offers request creation. |
| `FAM-PAY-030` | States that coupons, promo codes, and manually applied account credits are unsupported. It offers Care history so an existing payment/refund claim can be identified before requesting a person. |

Informational resolution runs before broad domain-overview routing, so asking what a care-receiver profile is no longer receives only an account-state answer. These bounded information questions also do not start an unnecessary persistent operational goal. Pricing retains its deterministic provider-free fast path but now produces a registered guided next step and intent evidence.

## Governed knowledge

Five source definitions were added to the existing Family Operations package:

- `KB-FOP-ORI-001` — What LoLo helps Families do.
- `KB-FOP-ORI-002` — What non-medical care means.
- `KB-FOP-ORI-003` — What AI Support can and cannot do.
- `KB-FOP-PAY-010` — Coupons, credits, and promo codes are not supported.
- `KB-FOP-PAY-011` — Hourly pricing, explicit-duration calculation boundary, and the next care step.

The package now contains 55 new-entry definitions plus the existing `KB-FAM-004` corrective revision, with 280 linked evaluation cases. The five additions use new stable IDs, so production publication creates them without modifying the existing published entries. All 324 catalog rows remain explicitly mapped.

## Safety and rollout

- Every personalized read is scoped through the signed-in Family Account.
- Publication does not equal hiring, booking, payment authorization, or payment capture.
- Pricing answers come from `AiSupportPricingTruth`; estimates require an explicit duration.
- Promo codes and discounts are never invented or applied.
- Navigation uses registered, role-authorized destinations.
- No care request, caregiver hire, visit, payment, profile, message, or account record is mutated by these paths.
- Pilot grants and Availability controls are unchanged by deployment or KB import.

## Verification

Focused source verification passes:

- Family Operations governed catalog: 55 entries, one revision, 280 unique linked evaluations.
- Coverage partition: 74 Complete / 163 Assisted / 87 Human / 0 no-path.
- Eleven runtime cases: every former gap returns the intended answer and registered next step.
- `FAM-REQUEST-032`: authoritative no-request, no-hire, Filled, booking, and hired-caregiver boundaries use current Family-scoped state.
- Pricing and all eleven closure cases make zero provider calls.
- Combined focused regression: 35 tests and 1,965 assertions.
- Complete AI Support feature regression: 240 tests and 5,911 assertions.

The complete AI Support regression passed before deployment preparation.

## Production operation after deployment

First inspect the plan:

```bash
php artisan ai-support:import-family-operations-kb
```

Expected production plan: five creates, zero revisions, existing exact entries as no-ops, and zero conflicts. Then publish the five new entries:

```bash
php artisan ai-support:import-family-operations-kb \
  --publish \
  --actor-email=test@test.com \
  --reason="Publish the five governed Family intent coverage-closure entries for the exact pilot." \
  --confirm=PUBLISH-FAMILY-OPERATIONS-KB
```

This operation does not change pilot users or enable Live for everyone. After publication, run one authenticated pilot pass over the eleven phrases and confirm the destination button for each. Do not create or confirm a live request during that audit.
